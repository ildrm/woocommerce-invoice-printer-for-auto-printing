<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Admin\AdminPage;
use WCInvoicePrinter\PrintJob\PrintJobRepository;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\TemplateRegistry;
use WCInvoicePrinter\Tests\Support\InMemoryWpdb;

final class AdminPageTest extends TestCase {
	private AdminPage $page;

	protected function setUp(): void {
		$GLOBALS['wpdb'] = new InMemoryWpdb();
		$GLOBALS['wcip_test_options'] = array( 'wcip_settings' => array( 'business_name' => 'Existing' ) );
		$GLOBALS['wcip_test_capabilities'] = array();
		$GLOBALS['wcip_test_nonce_valid'] = true;
		$_POST = array( 'section' => 'general', 'business_name' => 'New' );
		$this->page = new AdminPage( new SettingsRepository(), new TemplateRegistry(), new PrintJobRepository() );
	}

	protected function tearDown(): void {
		$_POST = array();
		$_GET = array();
		$GLOBALS['wcip_test_nonce_valid'] = true;
		unset( $GLOBALS['wcip_redirect_exception'], $GLOBALS['wcip_test_redirect'] );
	}

	private function assert_save_fails( int $status ): void {
		try { $this->page->save(); self::fail( 'Settings unexpectedly saved.' ); }
		catch ( \RuntimeException $error ) { self::assertSame( $status, $error->getCode() ); }
		self::assertSame( 'Existing', $GLOBALS['wcip_test_options']['wcip_settings']['business_name'] );
	}

	public function test_settings_save_requires_management_capability(): void { $this->assert_save_fails( 403 ); }

	public function test_settings_save_rejects_invalid_nonce(): void {
		$GLOBALS['wcip_test_capabilities']['wcip_manage_settings'] = true;
		$GLOBALS['wcip_test_nonce_valid'] = false;
		$this->assert_save_fails( 0 );
	}

	public function test_unknown_settings_section_is_rejected(): void {
		$GLOBALS['wcip_test_capabilities']['wcip_manage_settings'] = true;
		$_POST['section'] = 'unknown';
		$this->assert_save_fails( 400 );
	}

	public function test_array_settings_input_is_rejected_instead_of_resetting_values(): void {
		$GLOBALS['wcip_test_capabilities']['wcip_manage_settings'] = true;
		$_POST['business_name'] = array( 'New' );
		$this->assert_save_fails( 400 );
	}

	public function test_template_form_exposes_every_visibility_and_layout_control(): void {
		$GLOBALS['wcip_test_capabilities']['wcip_manage_settings'] = true;
		$_GET = array( 'tab' => 'templates' );
		ob_start();
		$this->page->render();
		$html = ob_get_clean();
		foreach ( array_merge( array_keys( SettingsRepository::visibility_defaults() ), array( 'compact_layout', 'single_page', 'thermal_auto_height' ) ) as $key ) {
			self::assertStringContainsString( 'name="' . $key . '"', $html, $key );
		}
		self::assertStringContainsString( 'name="invoice_font"', $html );
		self::assertStringContainsString( 'name="invoice_font_size" type="number" value="10" min="6" max="16"', $html );
		self::assertMatchesRegularExpression( '/name="show_customer_email"[^>]*value="1"\s*>/', $html );
		self::assertMatchesRegularExpression( '/name="show_customer_name"[^>]*value="1"\s*>/', $html );
		self::assertMatchesRegularExpression( '/name="show_customer_role"[^>]*value="1"\s*>/', $html );
		self::assertMatchesRegularExpression( '/name="show_shipping_address"[^>]*checked="checked"/', $html );
	}

	public function test_template_form_saves_checked_fields_and_clears_unchecked_fields(): void {
		$GLOBALS['wcip_test_capabilities']['wcip_manage_settings'] = true;
		$GLOBALS['wcip_test_options']['wcip_settings']['show_customer_email'] = true;
		$_POST = array( 'section' => 'templates', 'default_template' => 'compact', 'show_shipping_address' => '1', 'show_customer_role' => '1', 'show_item_sku' => '1', 'compact_layout' => '1', 'invoice_font' => 'dejavuserif', 'invoice_font_size' => '8' );
		$GLOBALS['wcip_redirect_exception'] = new \RuntimeException( 'Test redirect' );
		try { $this->page->save(); self::fail( 'Expected redirect.' ); }
		catch ( \RuntimeException $error ) { self::assertSame( 'Test redirect', $error->getMessage() ); }
		$settings = new SettingsRepository();
		self::assertSame( 'compact', $settings->get( 'default_template' ) );
		self::assertTrue( $settings->get( 'show_item_sku' ) );
		self::assertTrue( $settings->get( 'show_shipping_address' ) );
		self::assertFalse( $settings->get( 'show_customer_email' ) );
		self::assertFalse( $settings->get( 'single_page' ) );
		self::assertTrue( $settings->get( 'compact_layout' ) );
		self::assertSame( 'dejavuserif', $settings->get( 'invoice_font' ) );
		self::assertSame( 8, $settings->get( 'invoice_font_size' ) );
		self::assertSame( 'Existing', $settings->get( 'business_name' ) );
		self::assertStringContainsString( 'tab=templates', $GLOBALS['wcip_test_redirect'] );
	}
}
