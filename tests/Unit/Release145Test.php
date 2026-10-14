<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Automation\PaymentEligibilityPolicy;
use WCInvoicePrinter\Invoice\InvoiceData;
use WCInvoicePrinter\Invoice\InvoiceFactory;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\HtmlRenderer;
use WCInvoicePrinter\Template\TemplateRegistry;

final class Release145Test extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcip_test_options'] = array();
		$GLOBALS['wcip_test_filters'] = array();
		$GLOBALS['wcip_test_logs'] = array();
		unset( $GLOBALS['wcip_test_log_failure'], $GLOBALS['wcip_test_translations'], $GLOBALS['wcip_test_locale'], $GLOBALS['wcip_test_user_locale'] );
	}

	protected function tearDown(): void {
		$GLOBALS['wcip_test_filters'] = array();
		unset( $GLOBALS['wcip_test_log_failure'] );
	}

	private function invoice( array $presentation = array(), array $payment = array(), ?array $totals = null ): InvoiceData {
		$settings = new SettingsRepository();
		$settings->update( array_merge( array( 'business_email' => 'store@example.test' ), $presentation ) );
		$sample = ( new InvoiceFactory( $settings ) )->sample();
		$fulfillment = array_merge( $sample->fulfillment, $payment );
		return new InvoiceData( $sample->order, $sample->store, $sample->customer, $sample->items, $totals ?? $sample->totals, $fulfillment );
	}

	/** @dataProvider invoice_templates */
	public function test_gateway_label_and_email_visibility_are_independent( string $id ): void {
		$renderer = new HtmlRenderer( new TemplateRegistry() );
		$html = $renderer->render( $this->invoice(), $id );
		self::assertStringContainsString( 'Payment gateway', $html );
		self::assertStringContainsString( 'Credit card', $html );
		self::assertStringContainsString( 'stripe', $html );
		self::assertStringNotContainsString( 'store@example.test', $html );
		self::assertStringNotContainsString( 'alex@example.com', $html );
		$html = $renderer->render( $this->invoice( array( 'show_payment_method' => false, 'show_store_email' => true ) ), $id );
		self::assertStringNotContainsString( 'Payment gateway', $html );
		self::assertStringNotContainsString( 'stripe', $html );
		self::assertStringContainsString( 'store@example.test', $html );
		self::assertStringNotContainsString( 'alex@example.com', $html );
		$html = $renderer->render( $this->invoice( array( 'show_store_email' => false, 'show_customer_email' => true ) ), $id );
		self::assertStringNotContainsString( 'store@example.test', $html );
		self::assertStringContainsString( 'alex@example.com', $html );
	}

	public static function invoice_templates(): array {
		return array_map( static fn( string $id ): array => array( $id ), array( 'classic', 'compact', 'classic-a5', 'thermal', 'thermal58' ) );
	}

	public function test_missing_gateway_values_and_untrusted_titles_render_safely(): void {
		$renderer = new HtmlRenderer( new TemplateRegistry() );
		$html = $renderer->render( $this->invoice( array(), array( 'payment_method' => '', 'payment_method_id' => 'custom_gateway' ) ), 'classic' );
		self::assertStringContainsString( 'custom_gateway', $html );
		self::assertStringContainsString( 'Payment gateway', $html );
		$html = $renderer->render( $this->invoice( array(), array( 'payment_method' => '', 'payment_method_id' => '' ) ), 'classic' );
		self::assertStringNotContainsString( 'Payment gateway', $html );
		$html = $renderer->render( $this->invoice( array(), array( 'payment_method' => '<script>alert(1)</script>', 'payment_method_id' => '<img src=x>' ) ), 'classic' );
		self::assertStringNotContainsString( '<script>alert', $html );
		self::assertStringNotContainsString( '<img src=x>', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
	}

	/** @dataProvider invoice_templates */
	public function test_legacy_totals_fallback_cannot_restore_a_keyed_hidden_gateway( string $id ): void {
		$renderer = new HtmlRenderer( new TemplateRegistry() );
		$payment_total = array( 'key' => 'payment_method', 'label' => 'Payment:', 'value' => 'HIDDEN-GATEWAY' );
		$html = $renderer->render( $this->invoice( array( 'show_payment_method' => false ), array(), array( $payment_total ) ), $id );
		self::assertStringNotContainsString( 'HIDDEN-GATEWAY', $html );
		$html = $renderer->render( $this->invoice( array( 'show_payment_method' => false ), array(), array( array( 'label' => 'Total:', 'value' => 'LEGACY-TOTAL' ), $payment_total ) ), $id );
		self::assertStringContainsString( 'LEGACY-TOTAL', $html );
		self::assertStringNotContainsString( 'HIDDEN-GATEWAY', $html );
	}

	public function test_malformed_billing_address_does_not_stop_manual_invoice_generation(): void {
		$order = new class extends \WC_Order {
			public function get_formatted_billing_address() { return array( 'unexpected' ); }
		};
		$settings = new SettingsRepository();
		$settings->update( array( 'shipping_billing_fallback' => true ) );
		$data = ( new InvoiceFactory( $settings ) )->from_order( $order );
		self::assertSame( '', $data->customer['billing_address'] );
		self::assertSame( '', $data->customer['shipping_address'] );
		self::assertFalse( $data->customer['recipient']['requires_shipping'] );
		self::assertSame( 'stripe', $data->fulfillment['payment_method_id'] );
	}

	public function test_optional_payment_failure_omits_confirmation_but_automatic_policy_stays_strict(): void {
		$GLOBALS['wcip_test_filters']['wcip_payment_confirmed'][] = static function () { throw new \RuntimeException( 'customer@example.test private gateway secret' ); };
		$settings = new SettingsRepository();
		$order = new \WC_Order();
		$factory = new InvoiceFactory( $settings );
		self::assertFalse( $factory->from_order( $order )->fulfillment['payment_confirmed'] );
		self::assertStringNotContainsString( 'private gateway secret', json_encode( $GLOBALS['wcip_test_logs'] ) );
		self::assertSame( 'payment_presentation', $GLOBALS['wcip_test_logs'][0]['context']['stage'] );
		$GLOBALS['wcip_test_log_failure'] = true;
		self::assertFalse( $factory->from_order( $order )->fulfillment['payment_confirmed'] );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'private gateway secret' );
		( new PaymentEligibilityPolicy( $settings ) )->eligible( $order );
	}
}
