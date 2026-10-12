<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Exercise the real Activator with a controllable dbDelta/WordPress boundary. */
final class SchemaInstallationTest extends TestCase {
	private string $site_path;

	protected function setUp(): void {
		$this->site_path = sys_get_temp_dir() . '/wcip-schema-test-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->site_path . '/wp-admin/includes', 0700, true );
		file_put_contents( $this->site_path . '/wp-admin/includes/upgrade.php', '<?php function dbDelta( string $sql ): array { global $wpdb; $wpdb->query( $sql ); return array(); }' );
	}

	protected function tearDown(): void {
		unlink( $this->site_path . '/wp-admin/includes/upgrade.php' );
		rmdir( $this->site_path . '/wp-admin/includes' );
		rmdir( $this->site_path . '/wp-admin' );
		rmdir( $this->site_path );
	}

	private function run_case( string $scenario ): array {
		$script = <<<'PHP'
define( 'ABSPATH', $argv[1] . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
require $argv[2];
require dirname( $argv[2] ) . '/PrintingMigration.php';
$scenario = $argv[3];
$options = array();
function get_option( string $key, $default = false ) { global $options; return $options[$key] ?? $default; }
function update_option( string $key, $value, $autoload = null ): bool { global $options; $options[$key] = $value; return true; }
function esc_html__( string $message, string $domain ): string { return $message; }
function esc_html( string $message ): string { return htmlspecialchars( $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function wp_die( string $message, string $title, array $args ) { throw new RuntimeException( $message, $args['response'] ); }
class TestRole {
    public array $caps = array();
    public int $writes = 0;
    public function has_cap( string $cap ): bool { return $this->caps[$cap] ?? false; }
    public function add_cap( string $cap ): void { $this->caps[$cap] = true; ++$this->writes; }
}
$roles = array( 'administrator' => new TestRole(), 'shop_manager' => new TestRole() );
function get_role( string $name ) { global $roles; return $roles[$name] ?? null; }
class TestDatabase {
    public string $options = 'wp_options';
    public string $prefix = 'wp_';
    public string $last_error = '';
    public string $last_query = '';
    public bool $exists = false;
    public string $mode = '';
    public array $ddl = array();
    public array $tables = array();
    public function get_row( string $sql, $format = null ): array { return array( 'Collation' => 'ascii_bin' ); }
    public function get_charset_collate(): string { return ''; }
    public function get_results( string $sql, $format = null ): array {
        $this->last_query = $sql; $this->last_error = '';
        $old = 'index_repair_failure' === $this->mode;
        if ( 0 === strpos( $sql, 'SHOW FULL COLUMNS' ) ) { return array( array( 'Field' => 'provider_id', 'Type' => 'varchar(64)' ), array( 'Field' => 'printer_id', 'Type' => $old ? 'varchar(96)' : 'varchar(191)' ), array( 'Field' => 'status', 'Type' => 'varchar(20)' ) ); }
        if ( 'missing_index' === $this->mode ) { return array(); }
        $result = array();
        foreach ( array( 'provider_id', 'printer_id', 'status', 'id' ) as $offset => $column ) { $result[] = array( 'Column_name' => $column, 'Seq_in_index' => $offset + 1, 'Sub_part' => 'printer_id' === $column && ! $old ? 96 : null, 'Non_unique' => 'unique_queue' === $this->mode ? 0 : 1, 'Index_type' => 'BTREE' ); }
        return $result;
    }
    public function esc_like( string $value ): string { return addcslashes( $value, '_%\\' ); }
    public function prepare( string $sql, ...$values ): string { foreach ($values as $value) { $sql = preg_replace('/%s/', "'" . $value . "'", $sql, 1); } return $sql; }
    public function get_var( string $sql ): ?string { $this->last_error = ''; if ( false !== strpos( $sql, 'SHOW COLUMNS' ) ) { preg_match("/LIKE '([a-z_]+)'/", stripslashes( $sql ), $column); if ( 'missing_column' === $this->mode && 'document_type' === ( $column[1] ?? '' ) ) { return null; } return $this->exists ? ($column[1] ?? null) : null; } foreach ( $this->tables as $table ) { if ( false !== strpos( stripslashes( $sql ), "'" . $table . "'" ) ) { return 'normalized_table_names' === $this->mode ? strtolower( $table ) : $table; } } return $this->exists ? 'wp_wc_invoice_print_jobs' : null; }
    public function query( string $sql ): bool {
        $this->last_query = $sql;
        if ( 0 === strpos( $sql, 'SELECT 1 FROM ' ) ) {
            if ( 'select_exception' === $this->mode ) { throw new RuntimeException( 'Verification query denied' ); }
            preg_match( '/FROM `([^`]+)`/', $sql, $match );
            foreach ( $this->tables as $table ) { if ( strtolower( $table ) === strtolower( $match[1] ?? '' ) ) { $this->last_error = ''; return true; } }
            $this->last_error = 'Table does not exist'; return false;
        }
        if ( 0 !== strpos( $sql, 'CREATE TABLE' ) && 0 !== strpos( $sql, 'ALTER TABLE' ) ) { return true; }
        $this->ddl[] = $sql;
        if ( 'index_repair_failure' === $this->mode ) { $this->last_error = 'Index ALTER denied'; return false; }
        if ( 'thrown_failure' === $this->mode ) { throw new RuntimeException( 'CREATE failed' ); }
        if ( in_array( $this->mode, array( 'create_failure', 'activation_failure', 'alter_failure' ), true ) ) { $this->last_error = 'CREATE/ALTER denied'; return false; }
        if ( in_array( $this->mode, array( 'operational_failure', 'repair_partial_install' ), true ) && false !== strpos( $sql, 'wcip_runtime_leases' ) ) { $this->last_error = 'CREATE denied for coordinator table'; return false; }
        if ( 'silent_failure' === $this->mode ) { return false; }
        preg_match( '/CREATE TABLE ([A-Za-z_]+)/', $sql, $match ); $this->tables[] = $match[1];
        $this->exists = true;
        $this->last_error = '';
        return true;
    }
}
$wpdb = new TestDatabase();
$wpdb->mode = $scenario;
if ( 'normalized_table_names' === $scenario ) { $wpdb->prefix = 'Wp_'; }
if ( 'alter_failure' === $scenario ) { $wpdb->exists = true; $options['wcip_db_version'] = '0.9.0'; }
if ( 'lost_table' === $scenario ) { $options['wcip_db_version'] = '1.0.0'; }
if ( 'previous_schema' === $scenario ) { $wpdb->exists = true; $wpdb->tables[] = 'wp_wc_invoice_print_jobs'; $options['wcip_db_version'] = '1.4.0'; }
if ( in_array( $scenario, array( 'index_repair_failure', 'unique_queue' ), true ) ) { $wpdb->exists = true; $wpdb->tables[] = 'wp_wc_invoice_print_jobs'; }
if ( 'missing_role' === $scenario ) { unset( $roles['shop_manager'] ); }
if ( 'already_provisioned' === $scenario ) {
    $roles['administrator']->caps = array( 'wcip_print_invoices' => true, 'wcip_view_print_jobs' => true, 'wcip_manage_settings' => true, 'wcip_export_orders' => true, 'wcip_scan_orders' => true, 'wcip_manage_fulfillment' => true );
    $roles['shop_manager']->caps = array( 'wcip_print_invoices' => true, 'wcip_view_print_jobs' => true, 'wcip_scan_orders' => true, 'wcip_manage_fulfillment' => true );
}
$exception = null;
try {
    if ( 'activation_failure' === $scenario ) { \WCInvoicePrinter\Infrastructure\Activator::activate(); $ready = true; }
    else { $ready = \WCInvoicePrinter\Infrastructure\Activator::maybe_upgrade(); }
} catch ( Throwable $error ) { $ready = false; $exception = array( 'code' => $error->getCode(), 'message' => $error->getMessage() ); }
$first_options = $options;
$first_error = \WCInvoicePrinter\Infrastructure\Activator::schema_error();
if ( 'repair_partial_install' === $scenario ) { $wpdb->mode = 'success'; $ready = \WCInvoicePrinter\Infrastructure\Activator::maybe_upgrade(); }
if ( 'missing_role' === $scenario ) { $roles['shop_manager'] = new TestRole(); $ready = \WCInvoicePrinter\Infrastructure\Activator::maybe_upgrade(); }
if ( 'already_provisioned' === $scenario ) { $ready = \WCInvoicePrinter\Infrastructure\Activator::maybe_upgrade(); }
echo json_encode( array( 'ready' => $ready, 'options' => $options, 'first_options' => $first_options, 'exists' => $wpdb->exists, 'ddl' => $wpdb->ddl, 'roles' => $roles, 'exception' => $exception, 'error' => \WCInvoicePrinter\Infrastructure\Activator::schema_error(), 'first_error' => $first_error ) );
PHP;
		$process = proc_open( array( PHP_BINARY, '-r', $script, $this->site_path, WCIP_PATH . 'src/Infrastructure/Activator.php', $scenario ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		self::assertIsResource( $process );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		self::assertSame( 0, proc_close( $process ), $error );
		return json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
	}

	/**
	 * @dataProvider failed_schema_cases
	 */
	public function test_failed_schema_installation_does_not_mark_version_current( string $scenario ): void {
		$result = $this->run_case( $scenario );
		self::assertFalse( $result['ready'] );
		self::assertSame( 'alter_failure' === $scenario ? '0.9.0' : null, $result['options']['wcip_db_version'] ?? null );
		self::assertArrayNotHasKey( 'wcip_capabilities_version', $result['options'] );
		self::assertCount( 1, $result['ddl'] );
		self::assertNotEmpty( $result['error'] );
		if ( 'thrown_failure' === $scenario ) { self::assertStringContainsString( 'CREATE failed', $result['error'] ); }
		if ( 'select_exception' === $scenario ) { self::assertStringContainsString( 'Verification query denied', $result['error'] ); }
	}

	public static function failed_schema_cases(): array {
		return array( array( 'create_failure' ), array( 'silent_failure' ), array( 'alter_failure' ), array( 'thrown_failure' ), array( 'missing_column' ), array( 'select_exception' ), array( 'index_repair_failure' ), array( 'missing_index' ) );
	}

	public function test_activation_reports_clear_failure_and_does_not_add_capabilities(): void {
		$result = $this->run_case( 'activation_failure' );
		self::assertSame( 500, $result['exception']['code'] );
		self::assertStringContainsString( 'database permissions', $result['exception']['message'] );
		self::assertStringContainsString( 'wp_wc_invoice_print_jobs: CREATE/ALTER denied', $result['exception']['message'] );
		self::assertSame( array(), $result['options'] );
		self::assertSame( 0, $result['roles']['administrator']['writes'] );
	}

	public function test_database_table_name_normalization_does_not_report_false_install_failure(): void {
		$result = $this->run_case( 'normalized_table_names' );
		self::assertTrue( $result['ready'] );
		self::assertCount( 6, $result['ddl'] );
		self::assertSame( '', $result['error'] );
	}

	public function test_operational_failure_preserves_the_failing_table_and_database_error(): void {
		$result = $this->run_case( 'operational_failure' );
		self::assertFalse( $result['ready'] );
		self::assertStringContainsString( 'wp_wcip_runtime_leases: CREATE denied for coordinator table', $result['error'] );
		self::assertStringContainsString( '[installer queue-repair-20261012]', $result['error'] );
		self::assertArrayNotHasKey( 'wcip_db_version', $result['options'] );
		self::assertCount( 2, $result['ddl'] );
	}

	public function test_retry_repairs_partial_installation_and_clears_the_old_error(): void {
		$result = $this->run_case( 'repair_partial_install' );
		self::assertTrue( $result['ready'] );
		self::assertArrayNotHasKey( 'wcip_db_version', $result['first_options'] );
		self::assertNotEmpty( $result['first_error'] );
		self::assertSame( '', $result['error'] );
		self::assertSame( '1.4.3', $result['options']['wcip_db_version'] );
	}

	public function test_successful_installation_marks_schema_and_capabilities_current(): void {
		$result = $this->run_case( 'success' );
		self::assertTrue( $result['ready'] );
		self::assertTrue( $result['exists'] );
		self::assertSame( '1.4.3', $result['options']['wcip_db_version'] );
		self::assertSame( '1.4.0', $result['options']['wcip_capabilities_version'] );
	}

	public function test_missing_table_is_repaired_even_if_a_previous_install_stamped_version(): void {
		$result = $this->run_case( 'lost_table' );
		self::assertTrue( $result['ready'] );
		self::assertTrue( $result['exists'] );
		self::assertCount( 6, $result['ddl'] );
	}

	public function test_previous_schema_is_upgraded_when_its_table_already_exists(): void {
		$result = $this->run_case( 'previous_schema' );
		self::assertTrue( $result['ready'] );
		self::assertCount( 6, $result['ddl'] );
		self::assertSame( '1.4.3', $result['options']['wcip_db_version'] );
		self::assertSame( '', $result['error'] );
	}

	public function test_an_unexpected_unique_queue_index_is_preserved(): void {
		$result = $this->run_case( 'unique_queue' );
		self::assertFalse( $result['ready'] );
		self::assertSame( array(), $result['ddl'] );
		self::assertStringContainsString( 'unexpectedly unique', $result['error'] );
		self::assertArrayNotHasKey( 'wcip_db_version', $result['options'] );
	}

	public function test_missing_shop_manager_role_is_provisioned_on_a_later_request(): void {
		$result = $this->run_case( 'missing_role' );
		self::assertArrayNotHasKey( 'wcip_capabilities_version', $result['first_options'] );
		self::assertSame( '1.4.0', $result['options']['wcip_capabilities_version'] );
		self::assertTrue( $result['roles']['shop_manager']['caps']['wcip_print_invoices'] );
		self::assertTrue( $result['roles']['shop_manager']['caps']['wcip_view_print_jobs'] );
		self::assertCount( 6, $result['ddl'] );
		self::assertSame( 6, $result['roles']['administrator']['writes'] );
	}

	public function test_existing_capabilities_are_not_written_again(): void {
		$result = $this->run_case( 'already_provisioned' );
		self::assertTrue( $result['ready'] );
		self::assertSame( 0, $result['roles']['administrator']['writes'] );
		self::assertSame( 0, $result['roles']['shop_manager']['writes'] );
		self::assertCount( 6, $result['ddl'] );
	}
}
