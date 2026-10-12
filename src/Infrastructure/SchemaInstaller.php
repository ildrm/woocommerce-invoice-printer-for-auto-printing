<?php

namespace WCInvoicePrinter\Infrastructure;

/** Verify plugin tables through the same access used at runtime and retain failures. */
final class SchemaInstaller {
	public const BUILD = 'queue-repair-20261012';
	private static string $error = '';

	public static function error(): string { return self::$error; }

	public static function install( string $table, string $sql ): bool {
		global $wpdb;
		self::$error = '';
		try {
			$wpdb->last_error = '';
			dbDelta( $sql );
			// Capture DDL errors before a successful verification query clears them.
			if ( '' !== (string) $wpdb->last_error ) { return self::fail( $table ); }
			if ( ! self::table_exists( $table ) ) { return false; }
			return true;
		} catch ( \Throwable $error ) {
			return self::fail( $table, $error->getMessage() );
		}
	}

	public static function table_exists( string $table ): bool {
		global $wpdb;
		try {
			// SHOW TABLES can normalize names or be filtered by database adapters.
			// A zero-row SELECT verifies the exact table without fetching job data.
			$name = str_replace( '`', '``', $table );
			$wpdb->last_error = '';
			if ( false === $wpdb->query( "SELECT 1 FROM `{$name}` LIMIT 0" ) ) { return self::fail( $table, 'The table could not be created or read.' ); }
			return true;
		} catch ( \Throwable $error ) { return self::fail( $table, $error->getMessage() ); }
	}

	public static function fail( string $table, string $reason = '' ): bool {
		global $wpdb;
		$database_error = trim( (string) $wpdb->last_error );
		$detail = '' !== $database_error ? $database_error : ( '' !== $reason ? $reason : 'Schema verification failed.' );
		$detail = ( function_exists( 'mb_substr' ) ? mb_substr( $detail, 0, 300 ) : substr( $detail, 0, 300 ) ) . ' [installer ' . self::BUILD . ']';
		$query = (string) ( $wpdb->last_query ?? '' );
		// Only our table's DDL is safe to include; never expose unrelated value/credential queries.
		if ( preg_match( '/^(?:CREATE|ALTER) TABLE\s+`?' . preg_quote( $table, '/' ) . '`?(?:\s|\()/i', $query ) ) {
			$detail .= '; SQL: ' . preg_replace( '/\s+/', ' ', $query );
		}
		$message = $table . ': ' . preg_replace( '/[\r\n]+/', ' ', $detail );
		self::$error = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 500 ) : substr( $message, 0, 500 );
		return false;
	}
}
