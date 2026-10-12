<?php
/**
 * Run with WP-CLI in a disposable WordPress database named wcip_*_test:
 * WCIP_RUN_DB_TESTS=1 wp --skip-plugins=wc-invoice-printer eval-file tests/Integration/schema-key-length.php
 * This fixture resets the six plugin tables in that test database.
 */
if ( '1' !== getenv( 'WCIP_RUN_DB_TESTS' ) || ! defined( 'DB_NAME' ) || ! preg_match( '/^wcip_[a-z0-9_]+_test$/', DB_NAME ) ) {
	throw new RuntimeException( 'Use an explicitly enabled disposable wcip_*_test database.' );
}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WCInvoicePrinter\Infrastructure\Activator;
use WCInvoicePrinter\Printing\Agent\AgentQueue;

global $wpdb;
$check = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
$query = static function ( string $sql ) use ( $wpdb ): void {
	if ( false === $wpdb->query( $sql ) ) { throw new RuntimeException( $wpdb->last_error ); }
};
$table = $wpdb->prefix . 'wc_invoice_print_jobs';
$wpdb->suppress_errors( true );
$query( "SET SESSION default_storage_engine = 'MyISAM'" );
$check( 'utf8mb4' === $wpdb->charset, 'This test requires the four-byte WordPress charset.' );
foreach ( array( 'wc_invoice_print_jobs', 'wcip_runtime_leases', 'wcip_document_references', 'wcip_fulfillment', 'wcip_fulfillment_events', 'wcip_export_outbox' ) as $suffix ) {
	$query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . $suffix . '`' );
}
delete_option( 'wcip_db_version' );

// Restore the pre-1.4.3 CREATE to reproduce the real 1000-byte server failure.
$legacy = static function ( string $sql ) use ( $table ): string {
	if ( 0 !== strpos( $sql, 'CREATE TABLE ' . $table . ' (' ) ) { return $sql; }
	return preg_replace( '/printer_id\(\d+\)/', 'printer_id', str_replace( 'ENGINE=InnoDB ', '', $sql ) );
};
add_filter( 'query', $legacy );
$check( ! Activator::maybe_upgrade(), 'The oversized legacy index unexpectedly installed.' );
$check( false !== strpos( Activator::schema_error(), 'max key length is 1000 bytes' ), 'The reported database failure was not reproduced: ' . Activator::schema_error() );
$check( false === get_option( 'wcip_db_version' ), 'Failed installation stamped the schema version.' );
remove_filter( 'query', $legacy );

Activator::activate();
$check( Activator::maybe_upgrade(), 'Fresh activation did not recover: ' . Activator::schema_error() );
$engine = static function () use ( $wpdb, $table ): string {
	return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.tables WHERE table_schema = %s AND table_name = %s', DB_NAME, $table ) );
};
$check( 'InnoDB' === $engine(), 'Fresh table inherited the MyISAM default.' );
$index_prefix = static function ( string $column = 'printer_id' ) use ( $wpdb, $table ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUB_PART FROM information_schema.statistics WHERE table_schema = %s AND table_name = %s AND index_name = %s AND column_name = %s', DB_NAME, $table, 'agent_queue', $column ) );
};
$check( 96 === $index_prefix(), 'The agent queue index does not use the bounded prefix.' );
$column_length = static function ( string $column ) use ( $wpdb, $table ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = %s AND table_name = %s AND column_name = %s', DB_NAME, $table, $column ) );
};
$bounded_index = static function () use ( $check, $index_prefix, $column_length, $wpdb, $table ): void {
	$check( 3 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = %s AND table_name = %s AND index_name = %s AND column_name IN (%s,%s,%s)', DB_NAME, $table, 'agent_queue', 'provider_id', 'printer_id', 'status' ) ), 'The agent queue index does not contain all three string columns.' );
	foreach ( array( 'provider_id' => 64, 'printer_id' => 96, 'status' => 20 ) as $column => $length ) {
		$prefix = $index_prefix( $column );
		// MariaDB can normalize an explicit full-length prefix to SUB_PART=NULL.
		$bounded_full_column = 'printer_id' !== $column && 0 === $prefix && $column_length( $column ) > 0 && $column_length( $column ) <= $length;
		$check( $length === $prefix || $bounded_full_column, 'The agent queue index has an incorrect prefix for ' . $column . '.' );
	}
};
$bounded_index();

// Routes with identical indexed prefixes must retain full, exact identity.
$routes = array( str_repeat( 'چ', 96 ) . str_repeat( 'a', 95 ), str_repeat( 'چ', 96 ) . str_repeat( 'b', 95 ) );
$ids = array();
foreach ( $routes as $route ) {
	$check( false !== $wpdb->insert( $table, array(
		'order_id' => 1, 'trigger_type' => 'manual', 'document_type' => 'invoice',
		'idempotency_key' => hash( 'sha256', $route ), 'template_id' => 'classic',
		'provider_id' => 'agent', 'printer_id' => $route, 'status' => 'queued',
		'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
	) ), 'Could not store a complete printer route: ' . $wpdb->last_error );
	$ids[] = (int) $wpdb->insert_id;
}
$queue = new AgentQueue();
$check( array( $ids[0] ) === array_map( 'intval', array_column( $queue->candidates( $routes[0] ), 'id' ) ), 'Prefix collision exposed another printer route.' );
$check( ! $queue->claim( $ids[1], $routes[0], 1, 'test-token' ), 'Prefix collision claimed another printer route.' );

// Upgrade a legacy MyISAM table with stored jobs and without the agent fields/index.
$query( "ALTER TABLE {$table} DROP INDEX agent_queue, DROP COLUMN agent_token, DROP COLUMN agent_user, DROP COLUMN agent_phase, DROP COLUMN agent_claimed_at, ENGINE=MyISAM" );
update_option( 'wcip_db_version', '1.2.0', false );
$check( Activator::maybe_upgrade(), 'MyISAM upgrade failed: ' . Activator::schema_error() );
$check( 'MyISAM' === $engine(), 'The existing table engine was unexpectedly converted.' );
$check( 96 === $index_prefix(), 'Legacy MyISAM table did not gain the compatible index.' );
foreach ( $ids as $offset => $id ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT printer_id, status FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	$check( $row && $routes[$offset] === $row['printer_id'] && 'queued' === $row['status'], 'Upgrade changed stored jobs or truncated printer identity.' );
}
$check( array( $ids[1] ) === array_map( 'intval', array_column( $queue->candidates( $routes[1] ), 'id' ) ), 'Upgraded queue lost exact route filtering.' );
$check( $queue->claim( $ids[1], $routes[1], 1, 'test-token' ), 'Upgraded queue could not claim the correct printer route.' );
Activator::activate();
$check( Activator::DB_VERSION === get_option( 'wcip_db_version' ), 'Schema upgrade was not stamped.' );

// Existing full-column indexes must be narrowed before dbDelta expands printer_id.
foreach ( array( 96, 64 ) as $legacy_width ) {
	$stored_ids = $wpdb->get_col( "SELECT id FROM {$table} ORDER BY id" );
	foreach ( $stored_ids as $offset => $id ) {
		$short_route = str_repeat( 'چ', $legacy_width - 1 ) . chr( 97 + $offset );
		$check( false !== $wpdb->update( $table, array( 'printer_id' => $short_route ), array( 'id' => $id ) ), 'Could not prepare the legacy short printer route.' );
	}
	$query( "ALTER TABLE {$table} DROP INDEX agent_queue, MODIFY printer_id varchar({$legacy_width}) NULL, ADD KEY agent_queue (provider_id,printer_id,status,id)" );
	$check( $legacy_width === $column_length( 'printer_id' ) && 0 === $index_prefix(), 'The legacy full-column index fixture was not established.' );
	$rows_before = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A );
	update_option( 'wcip_db_version', '1.2.0', false );
	Activator::activate();
	$check( Activator::maybe_upgrade(), 'MyISAM printer-column expansion from ' . $legacy_width . ' characters failed: ' . Activator::schema_error() );
	$check( 'MyISAM' === $engine(), 'Expanding printer routes changed the existing table engine.' );
	$check( 191 === $column_length( 'printer_id' ), 'The legacy printer column was not expanded to its full supported length.' );
	$bounded_index();
	$check( $rows_before === $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A ), 'Printer-column expansion changed existing job data.' );
	$long_route = str_repeat( 'چ', 96 ) . str_repeat( 96 === $legacy_width ? 'c' : 'd', 95 );
	$check( false !== $wpdb->insert( $table, array(
		'order_id' => 2, 'trigger_type' => 'manual', 'document_type' => 'invoice',
		'idempotency_key' => hash( 'sha256', $long_route ), 'template_id' => 'classic',
		'provider_id' => 'agent', 'printer_id' => $long_route, 'status' => 'queued',
		'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
	) ), 'Expanded printer column rejected a full route: ' . $wpdb->last_error );
	$long_route_id = (int) $wpdb->insert_id;
	$check( $long_route === $wpdb->get_var( $wpdb->prepare( "SELECT printer_id FROM {$table} WHERE id = %d", $long_route_id ) ), 'The expanded printer column truncated the new route.' );
	$check( array( $long_route_id ) === array_map( 'intval', array_column( $queue->candidates( $long_route ), 'id' ) ), 'The expanded printer column lost exact route selection.' );
}

// Wider existing columns also need a bounded index while dbDelta applies the schema.
$query( "ALTER TABLE {$table} DROP INDEX agent_queue, MODIFY provider_id varchar(255) NOT NULL DEFAULT 'cups'" );
$check( 255 === $column_length( 'provider_id' ), 'The wider legacy provider fixture was not established.' );
$rows_before = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A );
update_option( 'wcip_db_version', '1.2.0', false );
$check( Activator::maybe_upgrade(), 'MyISAM upgrade with a wider provider column failed: ' . Activator::schema_error() );
$check( 'MyISAM' === $engine(), 'The wider provider upgrade changed the existing table engine.' );
$bounded_index();
$check( $rows_before === $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A ), 'The wider provider upgrade changed or truncated existing job data.' );
$check( array( $long_route_id ) === array_map( 'intval', array_column( $queue->candidates( $long_route ), 'id' ) ), 'The wider provider upgrade changed full-route selection.' );
$check( Activator::DB_VERSION === get_option( 'wcip_db_version' ), 'The wider provider upgrade was not stamped.' );
echo json_encode( array(
	'legacy_1000_byte_failure_reproduced' => true, 'fresh_activation' => true,
	'myisam_upgrade_preserves_jobs' => true, 'full_length_route_collision_checks' => true,
	'myisam_printer_96_expansion' => true, 'myisam_printer_64_expansion' => true, 'myisam_wide_provider_upgrade' => true,
	'repeated_activation' => true, 'schema' => get_option( 'wcip_db_version' ),
	'wordpress' => $GLOBALS['wp_version'], 'mariadb' => $wpdb->db_server_info(), 'php' => PHP_VERSION,
) ) . "\n";
