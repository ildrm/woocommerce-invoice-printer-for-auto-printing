<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Settings\SettingsRepository;

final class SettingsRepositoryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcip_test_options'] = array();
		$GLOBALS['wcip_test_transients'] = array();
	}

	public function test_updates_normalize_values_and_reject_unknown_keys(): void {
		$settings = new SettingsRepository();
		$settings->update( array( 'business_name' => '<b>Store</b>', 'automatic_copies' => 99, 'automatic_enabled' => 'false', 'show_customer_note' => '1', 'cups_password' => 'secret', 'cups_printer_id' => 'Office_A4', 'unknown' => 'unexpected' ) );
		self::assertSame( 'Store', $settings->get( 'business_name' ) );
		self::assertSame( 20, $settings->get( 'automatic_copies' ) );
		self::assertFalse( $settings->get( 'automatic_enabled' ) );
		self::assertTrue( $settings->get( 'show_customer_note' ) );
		self::assertSame( 'secret', $settings->cups_password() );
		self::assertArrayNotHasKey( 'unknown', $settings->all() );
	}

	/**
	 * @dataProvider invalid_printer_ids
	 */
	public function test_invalid_printer_ids_are_not_rewritten_to_another_printer( $value ): void {
		$settings = new SettingsRepository();
		$settings->update( array( 'cups_printer_id' => $value ) );
		self::assertSame( '', $settings->get( 'cups_printer_id' ) );
	}

	public static function invalid_printer_ids(): array {
		return array( array( '-123' ), array( '../office' ), array( 'office/a' ), array( 'office?x=1' ), array( "123\n" ), array( array( '123' ) ) );
	}

	public function test_corrupt_storage_falls_back_to_typed_defaults(): void {
		$GLOBALS['wcip_test_options']['wcip_settings'] = array( 'business_name' => array( 'bad' ), 'automatic_copies' => -5, 'automatic_enabled' => 'false', 'cups_endpoint' => array( 'secret' ) );
		$settings = new SettingsRepository();
		self::assertSame( 'Test Store', $settings->get( 'business_name' ) );
		self::assertSame( 1, $settings->get( 'automatic_copies' ) );
		self::assertFalse( $settings->get( 'automatic_enabled' ) );
		self::assertSame( '', $settings->cups_endpoint() );
	}

	public function test_optional_invoice_fields_and_layout_options_are_disabled_by_default(): void {
		$settings = new SettingsRepository();
		$presentation = $settings->presentation();
		foreach ( array( 'show_customer_name', 'show_customer_role', 'show_store_email', 'show_customer_email', 'show_store_address', 'show_billing_address', 'show_store_logo', 'show_paid_date', 'show_shipping_phone', 'compact_layout', 'single_page', 'thermal_auto_height' ) as $key ) { self::assertFalse( $presentation[ $key ], $key ); }
		self::assertTrue( $presentation['show_shipping_address'] );
		self::assertFalse( $settings->get( 'shipping_billing_fallback' ) );
		self::assertFalse( $settings->get( 'label_show_sender' ) );
		self::assertSame( 'dejavusans', $presentation['invoice_font'] );
		self::assertSame( 10, $presentation['invoice_font_size'] );
		self::assertArrayNotHasKey( 'cups_password', $presentation );
	}

	public function test_presentation_rejects_css_input_and_normalizes_each_toggle(): void {
		$settings = new SettingsRepository();
		$settings->update( array( 'invoice_font' => 'serif; background: url(https://example.test)', 'invoice_font_size' => 'large', 'compact_layout' => 'true', 'single_page' => '1', 'show_customer_email' => '1', 'show_customer_role' => 'false' ) );
		self::assertSame( 'dejavusans', $settings->get( 'invoice_font' ) );
		self::assertSame( 10, $settings->get( 'invoice_font_size' ) );
		self::assertFalse( $settings->get( 'compact_layout' ) );
		self::assertTrue( $settings->get( 'single_page' ) );
		self::assertTrue( $settings->get( 'show_customer_email' ) );
		self::assertFalse( $settings->get( 'show_customer_role' ) );
		$settings->update( array( 'invoice_font' => 'dejavuserif', 'invoice_font_size' => 99 ) );
		self::assertSame( 'dejavuserif', $settings->get( 'invoice_font' ) );
		self::assertSame( 16, $settings->get( 'invoice_font_size' ) );
		$settings->update( array( 'invoice_font_size' => -1 ) );
		self::assertSame( 6, $settings->get( 'invoice_font_size' ) );
	}

	public function test_saved_opt_in_choices_are_preserved_when_other_settings_change(): void {
		$settings = new SettingsRepository();
		$settings->update( array( 'show_customer_email' => true, 'show_shipping_address' => false, 'shipping_billing_fallback' => true, 'label_show_sender' => true, 'invoice_font' => 'dejavusansmono' ) );
		$settings->update( array( 'business_name' => 'Updated Store' ) );
		self::assertTrue( $settings->get( 'show_customer_email' ) );
		self::assertFalse( $settings->get( 'show_shipping_address' ) );
		self::assertTrue( $settings->get( 'shipping_billing_fallback' ) );
		self::assertTrue( $settings->get( 'label_show_sender' ) );
		self::assertSame( 'dejavusansmono', $settings->get( 'invoice_font' ) );
	}

	public function test_business_email_has_no_admin_fallback_and_saved_gateway_choices_survive_updates(): void {
		$GLOBALS['wcip_test_options']['admin_email'] = 'administrator@example.test';
		$settings = new SettingsRepository();
		self::assertSame( '', $settings->get( 'business_email' ) );
		self::assertTrue( $settings->get( 'show_payment_method' ) );
		$settings->update( array( 'business_email' => 'store@example.test', 'show_store_email' => true, 'show_payment_method' => false ) );
		$settings->update( array( 'business_name' => 'Updated' ) );
		self::assertSame( 'store@example.test', $settings->get( 'business_email' ) );
		self::assertTrue( $settings->get( 'show_store_email' ) );
		self::assertFalse( $settings->get( 'show_payment_method' ) );
		self::assertFalse( $settings->get( 'show_customer_email' ) );
	}

	public function test_credential_change_invalidates_both_account_caches(): void {
		$settings = new SettingsRepository();
		$settings->update( array( 'cups_password' => 'old' ) ); $old = $settings->cups_fingerprint();
		$settings->update( array( 'cups_password' => 'new' ) ); $new = $settings->cups_fingerprint();
		$settings->update( array( 'cups_password' => 'old' ) );
		foreach ( array( 'wcip_cups_printers_' . $old, 'wcip_cups_printers_' . $new, 'unrelated' ) as $key ) { $GLOBALS['wcip_test_transients'][ $key ] = 'cached'; }
		$settings->update( array( 'cups_password' => 'new' ) );
		self::assertSame( array( 'unrelated' => 'cached' ), $GLOBALS['wcip_test_transients'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_external_key_has_precedence_and_cannot_be_replaced(): void {
		define( 'WCIP_CUPS_PASSWORD', 'external' );
		$GLOBALS['wcip_test_options']['wcip_settings'] = array( 'cups_password' => 'stored' );
		$settings = new SettingsRepository();
		$settings->update( array( 'cups_password' => 'replacement', 'business_name' => 'Updated' ) );
		self::assertTrue( $settings->cups_password_is_external() );
		self::assertSame( 'external', $settings->cups_password() );
		self::assertSame( 'stored', $settings->get( 'cups_password' ) );
		self::assertSame( 'Updated', $settings->get( 'business_name' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_empty_external_constant_does_not_hide_stored_key(): void {
		define( 'WCIP_CUPS_PASSWORD', ' ' );
		$settings = new SettingsRepository();
		$settings->update( array( 'cups_password' => 'stored' ) );
		self::assertFalse( $settings->cups_password_is_external() );
		self::assertSame( 'stored', $settings->cups_password() );
	}
}
