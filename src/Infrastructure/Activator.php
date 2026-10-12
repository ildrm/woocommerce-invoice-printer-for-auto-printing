<?php

namespace WCInvoicePrinter\Infrastructure;

final class Activator {
	public const DB_VERSION = '1.4.3';
	public const CAPABILITIES_VERSION = '1.4.0';

	public static function activate(): void {
		if ( ! self::install_schema() ) {
			$message = esc_html__( 'The invoice printer database could not be installed. Check database permissions and try activation again.', 'wc-invoice-printer' );
			if ( '' !== self::schema_error() ) { $message .= '<p><code>' . esc_html( self::schema_error() ) . '</code></p>'; }
			wp_die( $message, '', array( 'response' => 500 ) );
		}
		self::add_capabilities();
		if ( ! PrintingMigration::run() ) { wp_die( esc_html__( 'The printing configuration migration failed. Check database permissions.', 'wc-invoice-printer' ) ); }
	}

	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			// Null matches every job's arguments while keeping hook/group scoped.
			as_unschedule_all_actions( 'wcip_process_print_job', null, 'wc-invoice-printer' );
			as_unschedule_all_actions( 'wcip_reconcile_paid_orders', null, 'wc-invoice-printer' );
			as_unschedule_all_actions( 'wcip_process_export', null, 'wc-invoice-printer' );
			as_unschedule_all_actions( 'wcip_recover_exports', null, 'wc-invoice-printer' );
		}
		global $wpdb;
		// Pending jobs remain reviewable and can be recovered when the plugin is reactivated.
		$wpdb->update( $wpdb->prefix . 'wc_invoice_print_jobs', array( 'action_id' => null ), array( 'status' => 'queued' ), array( '%d' ), array( '%s' ) );
	}

	public static function maybe_upgrade(): bool {
		if ( self::DB_VERSION !== get_option( 'wcip_db_version' ) || ! self::table_exists() ) {
			if ( ! self::install_schema() ) { return false; }
		}
		if ( self::CAPABILITIES_VERSION !== get_option( 'wcip_capabilities_version' ) ) {
			self::add_capabilities();
		}
		return PrintingMigration::run();
	}

	private static function install_schema(): bool {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once __DIR__ . '/SchemaInstaller.php';
		$table   = $wpdb->prefix . 'wc_invoice_print_jobs';
		$charset = $wpdb->get_charset_collate();
		// Keep the non-unique queue index below legacy key limits without shortening stored printer IDs.
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			trigger_type varchar(20) NOT NULL,
			document_type varchar(32) NOT NULL DEFAULT 'invoice',
			idempotency_key char(64) NOT NULL,
			template_id varchar(64) NOT NULL,
			provider_id varchar(64) NOT NULL,
			printer_id varchar(191) NOT NULL,
			copies smallint(5) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL,
			attempt_count smallint(5) unsigned NOT NULL DEFAULT 0,
			action_id bigint(20) unsigned NULL,
			external_job_id varchar(191) NULL,
			error_code varchar(64) NULL,
			error_message varchar(500) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			started_at datetime NULL,
			completed_at datetime NULL,
			printed_at datetime NULL,
			printed_by bigint(20) unsigned NULL,
			printed_note_id bigint(20) unsigned NULL,
			agent_token char(64) NULL,
			agent_user bigint(20) unsigned NULL,
			agent_phase varchar(16) NULL,
			agent_claimed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY status_created (status, created_at),
			KEY order_created (order_id, created_at),
			KEY order_printed (order_id, printed_at),
			KEY document_printed (order_id, document_type, printed_at),
			KEY document_created (order_id, document_type, created_at),
			KEY trigger_created (trigger_type, created_at),
			KEY action_id (action_id),
			KEY agent_queue (provider_id(64), printer_id(96), status(20), id)
		) ENGINE=InnoDB {$charset};";
		try {
			// dbDelta resizes columns before comparing indexes and ignores prefix differences.
			// Repair the old full-width key first, so a printer-column expansion cannot exceed its limit.
			if ( ! self::repair_queue_index( $table, true ) ) { return false; }
			if ( ! SchemaInstaller::install( $table, $sql ) ) { return false; }
			if ( ! self::repair_queue_index( $table, false ) ) { return false; }
			foreach ( array( 'document_type', 'agent_token', 'agent_user', 'agent_phase', 'agent_claimed_at' ) as $column ) {
				if ( $column !== $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $wpdb->esc_like( $column ) ) ) ) {
					return SchemaInstaller::fail( $table, 'Missing required column: ' . $column );
				}
			}
			require_once __DIR__ . '/OperationalSchema.php';
			if ( ! OperationalSchema::install() ) { return false; }
		} catch ( \Throwable $error ) {
			return SchemaInstaller::fail( $table, $error->getMessage() );
		}
		update_option( 'wcip_db_version', self::DB_VERSION, false );
		if ( ! get_option( 'wcip_discovery_since' ) ) { update_option( 'wcip_discovery_since', time(), false ); }
		return true;
	}

	private static function repair_queue_index( string $table, bool $before_columns ): bool {
		global $wpdb;
		if ( ! SchemaInstaller::table_exists( $table ) ) { return $before_columns; }
		$name = '`' . str_replace( '`', '``', $table ) . '`';
		$index = $wpdb->get_results( "SHOW INDEX FROM {$name} WHERE Key_name = 'agent_queue'", ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) { return SchemaInstaller::fail( $table ); }
		if ( ! $index ) { return $before_columns ? true : SchemaInstaller::fail( $table, 'Missing required index: agent_queue' ); }
		foreach ( $index as $part ) {
			if ( 1 !== (int) $part['Non_unique'] ) { return SchemaInstaller::fail( $table, 'The agent_queue index is unexpectedly unique; it was preserved.' ); }
		}
		$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM {$name}", ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) { return SchemaInstaller::fail( $table ); }
		$lengths = array();
		foreach ( $columns ?: array() as $column ) {
			if ( preg_match( '/^(?:var)?char\((\d+)\)/i', $column['Type'], $match ) ) { $lengths[$column['Field']] = (int) $match[1]; }
		}
		$prefixes = array();
		foreach ( array( 'provider_id' => 64, 'printer_id' => 96, 'status' => 20 ) as $column => $bound ) {
			$length = $lengths[$column] ?? 0;
			if ( $length < 1 ) { return SchemaInstaller::fail( $table, 'Cannot inspect queue index column: ' . $column ); }
			$prefixes[$column] = min( $bound, $length );
			// A prefix equal to the old column width may be normalized to a full index.
			// Use a smaller temporary prefix until dbDelta has expanded that column.
			$target_length = 'printer_id' === $column ? 191 : $bound;
			if ( $before_columns && $length < $target_length ) { $prefixes[$column] = min( $bound, $length - 1 ); }
		}
		if ( in_array( 0, $prefixes, true ) ) {
			// One-character legacy columns have no smaller prefix; dbDelta rebuilds this nonunique key.
			return false !== $wpdb->query( "ALTER TABLE {$name} DROP INDEX agent_queue" ) || SchemaInstaller::fail( $table );
		}
		usort( $index, static function ( array $a, array $b ): int { return (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']; } );
		$expected = array( 'provider_id', 'printer_id', 'status', 'id' );
		$repair = count( $index ) !== count( $expected );
		foreach ( $index as $offset => $part ) {
			$column = $part['Column_name'];
			if ( $column !== ( $expected[$offset] ?? null ) || 'BTREE' !== strtoupper( $part['Index_type'] ) ) { $repair = true; continue; }
			if ( isset( $prefixes[$column] ) && (int) ( $part['Sub_part'] ?? $lengths[$column] ) !== $prefixes[$column] ) { $repair = true; }
		}
		if ( ! $repair ) { return true; }
		$sql = "ALTER TABLE {$name} DROP INDEX agent_queue, ADD KEY agent_queue (provider_id({$prefixes['provider_id']}), printer_id({$prefixes['printer_id']}), status({$prefixes['status']}), id)";
		return false !== $wpdb->query( $sql ) || SchemaInstaller::fail( $table );
	}

	private static function table_exists(): bool {
		global $wpdb;
		require_once __DIR__ . '/SchemaInstaller.php';
		return SchemaInstaller::table_exists( $wpdb->prefix . 'wc_invoice_print_jobs' );
	}

	public static function schema_error(): string {
		require_once __DIR__ . '/SchemaInstaller.php';
		return SchemaInstaller::error();
	}

	private static function add_capabilities(): void {
		if ( function_exists( 'add_role' ) ) { add_role( 'wcip_print_agent', __( 'Print agent', 'wc-invoice-printer' ), array( 'read' => true, 'wcip_run_print_agent' => true ) ); }
		$roles = array( 'administrator', 'shop_manager' );
		$complete = true;
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( array( 'wcip_print_invoices', 'wcip_view_print_jobs', 'wcip_scan_orders', 'wcip_manage_fulfillment' ) as $capability ) {
					if ( ! $role->has_cap( $capability ) ) { $role->add_cap( $capability ); }
				}
			} else {
				$complete = false;
			}
		}
		$administrator = get_role( 'administrator' );
		if ( $administrator && ! $administrator->has_cap( 'wcip_manage_settings' ) ) {
			$administrator->add_cap( 'wcip_manage_settings' );
		}
		if ( $administrator && ! $administrator->has_cap( 'wcip_export_orders' ) ) { $administrator->add_cap( 'wcip_export_orders' ); }
		if ( $complete ) {
			update_option( 'wcip_capabilities_version', self::CAPABILITIES_VERSION, false );
		}
	}
}
