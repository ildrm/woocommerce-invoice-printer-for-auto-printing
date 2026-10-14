<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Invoice\InvoiceData;
use WCInvoicePrinter\Invoice\InvoiceFactory;
use WCInvoicePrinter\Pdf\MpdfRenderer;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\HtmlRenderer;
use WCInvoicePrinter\Template\TemplateRegistry;

final class InvoicePresentationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcip_test_options'] = array();
		$GLOBALS['wcip_test_filters'] = array();
		$GLOBALS['wcip_test_transients'] = array();
		unset( $GLOBALS['wcip_test_translations'], $GLOBALS['wcip_test_locale'], $GLOBALS['wcip_test_user_locale'], $GLOBALS['wcip_http_callback'] );
	}

	protected function tearDown(): void { unset( $GLOBALS['wcip_http_callback'] ); }

	/** @dataProvider built_in_templates */
	public function test_defaults_hide_optional_customer_information_and_show_gateway_on_invoices( string $id ): void {
		$html = $this->render( $this->invoice(), $id );
		foreach ( array( 'CUSTOMER-NAME-PRIVATE', 'Wholesale role visible', 'store-hidden@example.test', 'customer-hidden@example.test', 'BILLING-ADDRESS-PRIVATE', 'STORE-ADDRESS-PRIVATE', 'RECIPIENT-EXTRA-PRIVATE' ) as $hidden ) { self::assertStringNotContainsString( $hidden, $html ); }
		if ( in_array( $id, array( 'shipping-label', 'packing-list' ), true ) ) { self::assertStringNotContainsString( 'PAYMENT-METHOD-PRIVATE', $html ); }
		else { self::assertStringContainsString( 'Payment gateway', $html ); self::assertStringContainsString( 'PAYMENT-METHOD-PRIVATE', $html ); self::assertStringContainsString( 'stripe', $html ); }
		self::assertStringContainsString( 'SHIPPING-ADDRESS-VISIBLE', $html );
		self::assertStringContainsString( '#1042', $html );
	}

	public static function built_in_templates(): array {
		return array_map( static fn( string $id ): array => array( $id ), array( 'classic', 'compact', 'classic-a5', 'thermal', 'thermal58', 'shipping-label', 'packing-list' ) );
	}

	/** @dataProvider built_in_templates */
	public function test_shipping_address_and_customer_role_can_be_toggled( string $id ): void {
		$enabled = $this->render( $this->invoice( array( 'show_shipping_address' => true, 'show_customer_role' => true ) ), $id );
		self::assertStringContainsString( 'SHIPPING-ADDRESS-VISIBLE', $enabled );
		self::assertStringContainsString( 'Wholesale role visible', $enabled );
		$html = $this->render( $this->invoice( array( 'show_shipping_address' => false, 'show_customer_role' => false ) ), $id );
		self::assertStringNotContainsString( 'SHIPPING-ADDRESS-VISIBLE', $html );
		self::assertStringNotContainsString( 'Wholesale role visible', $html );
		self::assertStringContainsString( '#1042', $html );
	}

	/** @dataProvider invoice_templates */
	public function test_optional_fields_and_item_prices_can_be_enabled_then_hidden( string $id ): void {
		$fields = array( 'show_customer_name', 'show_customer_role', 'show_store_email', 'show_customer_email', 'show_store_address', 'show_billing_address', 'show_customer_company', 'show_store_details', 'show_item_sku', 'show_item_variation', 'show_item_unit_price', 'show_item_discount', 'show_item_tax', 'show_customer_note', 'show_recipient_extras', 'show_footer' );
		$visible = $this->render( $this->invoice( array_fill_keys( $fields, true ) ), $id );
		$hidden = $this->render( $this->invoice( array_fill_keys( $fields, false ) ), $id );
		foreach ( array( 'CUSTOMER-NAME-PRIVATE', 'Wholesale role visible', 'store-hidden@example.test', 'customer-hidden@example.test', 'BILLING-ADDRESS-PRIVATE', 'STORE-ADDRESS-PRIVATE', 'COMPANY-PRIVATE', 'STORE-DETAILS-PRIVATE', 'SKU-PRIVATE', 'VARIATION-PRIVATE', 'UNIT-PRICE-PRIVATE', 'DISCOUNT-PRIVATE', 'TAX-PRIVATE', 'NOTE-PRIVATE', 'RECIPIENT-EXTRA-PRIVATE', 'Thank you for your order.' ) as $optional ) {
			self::assertStringContainsString( $optional, $visible, $id . ': ' . $optional );
			self::assertStringNotContainsString( $optional, $hidden, $id . ': ' . $optional );
		}
		foreach ( array( 'Product always visible', 'LINE-TOTAL-REQUIRED', 'GRAND-TOTAL-REQUIRED' ) as $essential ) { self::assertStringContainsString( $essential, $hidden ); }
	}

	public static function invoice_templates(): array {
		return array_map( static fn( string $id ): array => array( $id ), array( 'classic', 'compact', 'classic-a5', 'thermal', 'thermal58' ) );
	}

	/** @dataProvider invoice_templates */
	public function test_totals_use_source_keys_and_payment_visibility_even_when_reordered( string $id ): void {
		$invoice = $this->invoice( array( 'show_payment_method' => false ) );
		$totals = array(
			array( 'key' => 'order_total', 'label' => 'Grand total:', 'value' => 'GRAND-TOTAL-REQUIRED' ),
			array( 'key' => 'payment_method', 'label' => 'Payment method:', 'value' => 'PAYMENT-METHOD-PRIVATE' ),
			array( 'key' => 'cart_subtotal', 'label' => 'Subtotal:', 'value' => 'SUBTOTAL-OPTIONAL' ),
		);
		$invoice = new InvoiceData( $invoice->order, $invoice->store, $invoice->customer, $invoice->items, $totals, $invoice->fulfillment );
		$plain = $this->render( $invoice, $id );
		self::assertStringContainsString( 'GRAND-TOTAL-REQUIRED', $plain );
		self::assertStringNotContainsString( 'SUBTOTAL-OPTIONAL', $plain );
		self::assertStringNotContainsString( 'PAYMENT-METHOD-PRIVATE', $plain );
		$fulfillment = $invoice->fulfillment; $fulfillment['presentation']['show_totals_breakdown'] = true;
		$breakdown = $this->render( new InvoiceData( $invoice->order, $invoice->store, $invoice->customer, $invoice->items, $totals, $fulfillment ), $id );
		self::assertStringContainsString( 'SUBTOTAL-OPTIONAL', $breakdown );
		self::assertStringNotContainsString( 'PAYMENT-METHOD-PRIVATE', $breakdown );
		$fulfillment['presentation']['show_payment_method'] = true;
		self::assertStringContainsString( 'PAYMENT-METHOD-PRIVATE', $this->render( new InvoiceData( $invoice->order, $invoice->store, $invoice->customer, $invoice->items, $totals, $fulfillment ), $id ) );
	}

	public function test_table_columns_follow_optional_prices_without_removing_quantity_or_total(): void {
		$all = array_fill_keys( array( 'show_item_sku', 'show_item_unit_price', 'show_item_discount', 'show_item_tax' ), true );
		foreach ( array( 'classic', 'compact', 'classic-a5' ) as $id ) {
			foreach ( array( false, true ) as $enabled ) {
				$html = $this->render( $this->invoice( array_map( static fn( $value ) => $enabled, $all ) ), $id );
				self::assertSame( 1, preg_match( '/<thead>(.*?)<\/thead>/s', $html, $head ) );
				self::assertSame( $enabled ? 7 : 3, preg_match_all( '/<th\b/', $head[1] ) );
				self::assertStringContainsString( 'Qty', $head[1] );
				self::assertStringContainsString( 'Total', $head[1] );
			}
		}
	}

	/** @dataProvider supported_fonts */
	public function test_selected_safe_font_is_embedded_in_pdf( string $font, string $pdf_name ): void {
		$settings = new SettingsRepository(); $settings->update( array( 'invoice_font' => $font ) );
		$templates = new TemplateRegistry();
		$html = $this->render( ( new InvoiceFactory( $settings ) )->sample(), 'classic' );
		$pdf = ( new MpdfRenderer() )->render( $html, $templates->get( 'classic' ) );
		self::assertStringContainsString( $pdf_name, $pdf );
	}

	public static function supported_fonts(): array {
		return array( array( 'dejavusans', 'DejaVuSans' ), array( 'dejavuserif', 'DejaVuSerif' ), array( 'dejavusansmono', 'DejaVuSansMono' ) );
	}

	public function test_hidden_remote_logo_is_not_requested_in_preview(): void {
		$GLOBALS['wcip_test_options']['wcip_settings'] = array( 'logo_url' => 'https://example.test/private-logo.png' );
		$GLOBALS['wcip_http_callback'] = static function () { self::fail( 'Hidden logo triggered a network request.' ); };
		$sample = ( new InvoiceFactory( new SettingsRepository() ) )->sample();
		foreach ( self::built_in_templates() as [ $id ] ) { self::assertStringNotContainsString( '<img', $this->render( $sample, $id ) ); }
	}

	private function render( InvoiceData $invoice, string $id ): string { return ( new HtmlRenderer( new TemplateRegistry() ) )->render( $invoice, $id ); }

	private function invoice( array $presentation = array() ): InvoiceData {
		$sample = ( new InvoiceFactory( new SettingsRepository() ) )->sample();
		$store = array_merge( $sample->store, array( 'email' => 'store-hidden@example.test', 'address' => 'STORE-ADDRESS-PRIVATE', 'details' => 'STORE-DETAILS-PRIVATE' ) );
		$customer = array_merge( $sample->customer, array( 'name' => 'CUSTOMER-NAME-PRIVATE', 'role' => 'Wholesale role visible', 'company' => 'COMPANY-PRIVATE', 'email' => 'customer-hidden@example.test', 'billing_address' => 'BILLING-ADDRESS-PRIVATE', 'shipping_address' => 'SHIPPING-ADDRESS-VISIBLE', 'recipient' => array( 'formatted_address' => 'SHIPPING-ADDRESS-VISIBLE', 'extra' => array( 'entry_code' => 'RECIPIENT-EXTRA-PRIVATE' ) ) ) );
		$item = array_merge( $sample->items[0], array( 'name' => 'Product always visible', 'sku' => 'SKU-PRIVATE', 'variation' => 'VARIATION-PRIVATE', 'unit_price' => 'UNIT-PRICE-PRIVATE', 'discount' => 'DISCOUNT-PRIVATE', 'tax' => 'TAX-PRIVATE', 'total' => 'LINE-TOTAL-REQUIRED' ) );
		$fulfillment = $sample->fulfillment;
		$fulfillment['presentation'] = array_merge( $fulfillment['presentation'], $presentation );
		$fulfillment['note'] = 'NOTE-PRIVATE'; $fulfillment['payment_method'] = 'PAYMENT-METHOD-PRIVATE';
		return new InvoiceData( $sample->order, $store, $customer, array( $item ), array( array( 'key' => 'order_total', 'label' => 'Grand total:', 'value' => 'GRAND-TOTAL-REQUIRED' ) ), $fulfillment );
	}
}
