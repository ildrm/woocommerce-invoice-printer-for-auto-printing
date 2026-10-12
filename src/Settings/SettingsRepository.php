<?php

namespace WCInvoicePrinter\Settings;

final class SettingsRepository {
	private const OPTION = 'wcip_settings';

	/** Safe PDF font families bundled with mPDF. */
	public const INVOICE_FONTS = array( 'dejavusans', 'dejavuserif', 'dejavusansmono' );

	/** Optional customer information is opt-in; only the shipping address appears by default. */
	public static function visibility_defaults(): array {
		return array(
			'show_store_name' => true,
			'show_store_logo' => false,
			'show_store_details' => false,
			'show_store_phone' => false,
			'show_store_email' => false,
			'show_store_address' => false,
			'show_customer_name' => false,
			'show_customer_company' => false,
			'show_billing_address' => false,
			'show_customer_phone' => false,
			'show_customer_email' => false,
			'show_shipping_address' => true,
			'show_customer_role' => false,
			'show_payment_method' => false,
			'show_shipping_method' => false,
			'show_order_date' => false,
			'show_order_status' => false,
			'show_paid_date' => false,
			'show_shipping_phone' => false,
			'show_item_sku' => false,
			'show_item_variation' => false,
			'show_item_unit_price' => false,
			'show_item_discount' => false,
			'show_item_tax' => false,
			'show_price_note' => false,
			'show_totals_breakdown' => false,
			'show_footer' => false,
			'show_recipient_extras' => false,
			'show_customer_note' => false,
		);
	}

	/** One presentation contract shared by previews, browser output and PDF output. */
	public function presentation(): array {
		$settings = $this->all();
		$keys = array_merge( array_keys( self::visibility_defaults() ), array( 'invoice_font', 'invoice_font_size', 'compact_layout', 'single_page', 'thermal_auto_height' ) );
		return array_intersect_key( $settings, array_flip( $keys ) );
	}

	public function all(): array {
		$defaults = array_merge( self::visibility_defaults(), array(
			'document_timezone' => 'site',
			'shipping_billing_fallback' => false,
			'shipping_field_mapping' => '{}',
			'label_show_sender' => false,
			'invoice_font' => 'dejavusans',
			'invoice_font_size' => 10,
			'compact_layout' => false,
			'single_page' => false,
			'thermal_auto_height' => false,
			'automatic_eligibility' => 'confirmed_payment',
			'reconciliation_interval' => 600,
			'fulfillment_on_confirmation' => false,
			'fulfillment_confirmation_stage' => 'preparing',
			'barcode_enabled' => false,
			'barcode_type' => 'Code128',
			'export_enabled' => false,
			'export_on_scan' => false,
			'export_on_packed' => false,
			'export_include_recipient' => false,
			'export_endpoint' => '',
			'export_allowed_host' => '',
			'export_signing_secret' => '',
			'business_name'          => get_bloginfo( 'name' ),
			'business_details'       => '',
			'business_phone'         => '',
			'business_email'         => get_option( 'admin_email', '' ),
			'logo_url'               => '',
			'default_template'       => 'classic',
			'automatic_enabled'      => false,
			'automatic_template'     => 'classic',
			'automatic_copies'       => 1,
			'automatic_provider'     => 'cups',
			'agent_queue'            => 'Office',
			'agent_user_id'          => 0,
			'cups_endpoint'      => '',
			'cups_username'      => '',
			'cups_password'      => '',
			'cups_printer_id'   => '',
			'cups_printer_name' => '',
		) );
		$value = get_option( self::OPTION, array() );
		return array_merge( $defaults, $this->sanitize( is_array( $value ) ? $value : array() ) );
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$settings = $this->all();
		return $settings[ $key ] ?? $default;
	}

	public function cups_endpoint(): string {
		return (string) $this->get( 'cups_endpoint', '' );
	}

	public function agent_ready(): bool {
		$id = (int) $this->get( 'agent_user_id' );
		if ( $id < 1 || ! \WCInvoicePrinter\Printing\Cups\CupsProvider::valid_printer( (string) $this->get( 'agent_queue' ) ) ) { return false; }
		if ( function_exists( 'get_userdata' ) ) { $user = get_userdata( $id ); return $user && $user->has_cap( 'wcip_run_print_agent' ); }
		return true;
	}
	public function automatic_printer(): string { return (string) $this->get( 'agent' === $this->get( 'automatic_provider' ) ? 'agent_queue' : 'cups_printer_id', '' ); }
	public function automatic_ready(): bool { return 'agent' === $this->get( 'automatic_provider' ) ? $this->agent_ready() : ( '' !== $this->cups_endpoint() && \WCInvoicePrinter\Printing\Cups\CupsProvider::valid_printer( $this->automatic_printer() ) ); }

	public function cups_password(): string {
		if ( $this->cups_password_is_external() ) {
			return WCIP_CUPS_PASSWORD;
		}
		return (string) $this->get( 'cups_password', '' );
	}

	public function cups_fingerprint(): string {
		return hash( 'sha256', wp_json_encode( array( $this->cups_endpoint(), $this->get( 'cups_username' ), $this->cups_password() ) ) );
	}

	public function cups_password_is_external(): bool {
		return defined( 'WCIP_CUPS_PASSWORD' ) && is_string( WCIP_CUPS_PASSWORD ) && '' !== trim( WCIP_CUPS_PASSWORD );
	}

	public function export_secret_is_external(): bool {
		return defined( 'WCIP_EXPORT_SIGNING_SECRET' ) && is_string( WCIP_EXPORT_SIGNING_SECRET ) && '' !== trim( WCIP_EXPORT_SIGNING_SECRET );
	}

	public function update( array $changes ): void {
		$old_key = $this->cups_fingerprint();
		$was_automatic = (bool) $this->get( 'automatic_enabled' );
		$changes = $this->sanitize( $changes );
		if ( $this->cups_password_is_external() ) {
			unset( $changes['cups_password'] );
		}
		if ( $this->export_secret_is_external() ) { unset( $changes['export_signing_secret'] ); }
		update_option( self::OPTION, array_merge( $this->all(), $changes ), false );
		if ( ! $was_automatic && $this->get( 'automatic_enabled' ) ) { update_option( 'wcip_discovery_since', time(), false ); delete_option( 'wcip_reconciliation_state' ); }
		if ( $old_key !== $this->cups_fingerprint() ) {
			delete_transient( 'wcip_cups_printers_' . $old_key );
			delete_transient( 'wcip_cups_printers_' . $this->cups_fingerprint() );
		}
	}

	/** Normalize persisted settings and reject unknown keys and non-scalar input. */
	private function sanitize( array $settings ): array {
		$clean = array();
		foreach ( $settings as $key => $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			if ( array_key_exists( $key, self::visibility_defaults() ) ) {
				$clean[ $key ] = in_array( $value, array( true, 1, '1' ), true );
				continue;
			}
				switch ( $key ) {
				case 'invoice_font':
					$clean[ $key ] = in_array( $value, self::INVOICE_FONTS, true ) ? $value : 'dejavusans'; break;
				case 'invoice_font_size':
					$clean[ $key ] = is_numeric( $value ) ? max( 6, min( 16, (int) $value ) ) : 10; break;
				case 'automatic_provider':
					$clean[ $key ] = 'agent' === $value ? 'agent' : 'cups'; break;
				case 'agent_user_id':
					$clean[ $key ] = absint( $value ); break;
				case 'agent_queue':
					$clean[ $key ] = \WCInvoicePrinter\Printing\Cups\CupsProvider::valid_printer( (string) $value ) ? (string) $value : ''; break;
				case 'fulfillment_confirmation_stage':
					$clean[ $key ] = in_array( $value, array( 'preparing', 'packed', 'ready_to_ship' ), true ) ? $value : 'preparing'; break;
				case 'business_name':
				case 'business_phone':
				case 'cups_printer_name':
					$clean[ $key ] = sanitize_text_field( (string) $value );
					break;
				case 'cups_endpoint':
					$clean[ $key ] = esc_url_raw( trim( (string) $value ), array( 'https' ) ); break;
				case 'cups_username':
					$clean[ $key ] = preg_match( '/^[^:\r\n\x00]{0,127}$/D', (string) $value ) ? trim( (string) $value ) : ''; break;
				case 'cups_password':
					$clean[ $key ] = strlen( (string) $value ) <= 512 && false === strpos( (string) $value, "\0" ) ? (string) $value : ''; break;
				case 'document_timezone':
					try { if ( 'site' !== $value ) { new \DateTimeZone( (string) $value ); } $clean[ $key ] = (string) $value; } catch ( \Exception $error ) { $clean[ $key ] = 'site'; } break;
				case 'export_allowed_host':
				case 'export_signing_secret':
					$clean[ $key ] = sanitize_text_field( (string) $value ); break;
				case 'export_endpoint':
					$clean[ $key ] = esc_url_raw( (string) $value, array( 'https' ) ); break;
				case 'automatic_eligibility':
					$clean[ $key ] = 'fulfillment' === $value ? 'fulfillment' : 'confirmed_payment'; break;
				case 'barcode_type':
					$clean[ $key ] = 'QR' === $value ? 'QR' : 'Code128'; break;
				case 'reconciliation_interval':
					$clean[ $key ] = max( 300, min( 3600, (int) $value ) ); break;
				case 'shipping_field_mapping':
					$map = json_decode( (string) $value, true );
					$clean[ $key ] = is_array( $map ) && count( $map ) <= 20 ? wp_json_encode( $map ) : '{}'; break;
				case 'business_details':
					$clean[ $key ] = sanitize_textarea_field( (string) $value );
					break;
				case 'business_email':
					$clean[ $key ] = sanitize_email( (string) $value );
					break;
				case 'logo_url':
					$clean[ $key ] = esc_url_raw( (string) $value, array( 'http', 'https' ) );
					break;
				case 'default_template':
				case 'automatic_template':
					$clean[ $key ] = sanitize_key( (string) $value ) ?: 'classic';
					break;
				case 'shipping_billing_fallback':
				case 'compact_layout':
				case 'single_page':
				case 'thermal_auto_height':
				case 'label_show_sender':
				case 'fulfillment_on_confirmation':
				case 'barcode_enabled':
				case 'export_enabled':
				case 'export_on_scan':
				case 'export_on_packed':
				case 'export_include_recipient':
				case 'automatic_enabled':
					$clean[ $key ] = in_array( $value, array( true, 1, '1' ), true );
					break;
				case 'automatic_copies':
					$clean[ $key ] = max( 1, min( 20, (int) $value ) );
					break;
				case 'cups_printer_id':
					$clean[ $key ] = \WCInvoicePrinter\Printing\Cups\CupsProvider::valid_printer( (string) $value ) ? (string) $value : '';
					break;
			}
		}
		return $clean;
	}
}
