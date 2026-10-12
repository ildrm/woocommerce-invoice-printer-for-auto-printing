<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Invoice\InvoiceFactory;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\HtmlRenderer;
use WCInvoicePrinter\Template\TemplateRegistry;

final class InvoiceFactoryTest extends TestCase {
	private InvoiceFactory $factory;

	protected function setUp(): void {
		$GLOBALS['wcip_test_options'] = array( 'wcip_settings' => array( 'show_customer_note' => true ) );
		$GLOBALS['wcip_test_transients'] = array();
		$GLOBALS['wcip_test_filters'] = array();
		$GLOBALS['wcip_test_users'] = array();
		$GLOBALS['wcip_test_role_names'] = array( 'customer' => 'Customer', 'wholesale_customer' => 'Wholesale customer' );
		$GLOBALS['wcip_test_role_translations'] = array();
		unset( $GLOBALS['wcip_http_callback'] );
		$this->factory = new InvoiceFactory( new SettingsRepository() );
	}

	protected function tearDown(): void { unset( $GLOBALS['wcip_http_callback'], $GLOBALS['wcip_test_users'], $GLOBALS['wcip_test_role_names'], $GLOBALS['wcip_test_role_translations'] ); }

	public function test_discounted_taxed_line_uses_the_charged_amount_and_order_currency(): void {
		$order = new InvoiceOrderFixture();
		$order->items = array( new InvoiceItemFixture() );
		$data = $this->factory->from_order( $order );
		self::assertSame( 'EUR 40.00', $data->items[0]['unit_price'] );
		self::assertSame( 'EUR 100.00', $data->items[0]['subtotal'] );
		self::assertSame( 'EUR 20.00', $data->items[0]['discount'] );
		self::assertSame( 'EUR 8.00', $data->items[0]['tax'] );
		self::assertSame( 'EUR 88.00', $data->items[0]['total'] );
		self::assertSame( 2.5, $data->items[0]['quantity'] );
		self::assertSame( '', $data->items[0]['sku'] ); // A deleted product still prints.
		self::assertSame( array( false, false ), $order->subtotal_options );
	}

	public function test_private_item_metadata_is_excluded_and_display_entities_are_decoded(): void {
		$order = new InvoiceOrderFixture();
		$order->items = array( new InvoiceItemFixture() );
		$data = $this->factory->from_order( $order );
		self::assertSame( 'Color: Blue & green', $data->items[0]['variation'] );
		self::assertStringNotContainsString( 'internal-secret', $data->items[0]['variation'] );
	}

	public function test_formatted_addresses_keep_line_breaks_and_do_not_double_escape_entities(): void {
		$GLOBALS['wcip_test_options']['wcip_settings']['show_billing_address'] = true;
		$data = $this->factory->from_order( new InvoiceOrderFixture() );
		self::assertSame( "Alex & Company\n12 Market Street\nPortland", $data->customer['billing_address'] );
		self::assertSame( "Warehouse\n24 Dispatch Street", $data->customer['shipping_address'] );
		foreach ( array( 'classic', 'compact', 'thermal' ) as $template ) {
			$html = ( new HtmlRenderer( new TemplateRegistry() ) )->render( $data, $template );
			self::assertStringContainsString( 'Alex &amp; Company<br', $html );
			self::assertStringNotContainsString( '&amp;amp;', $html );
		}
	}

	public function test_order_totals_retain_currency_direction_and_supported_price_markup(): void {
		$data = $this->factory->from_order( new InvoiceOrderFixture() );
		self::assertSame( 'Total:', $data->totals[0]['label'] );
		self::assertSame( 'order_total', $data->totals[0]['key'] );
		self::assertSame( '<strong><span class="woocommerce-Price-amount"><bdi>EUR 88.00</bdi></span></strong>', $data->totals[0]['value'] );
	}

	public function test_customer_note_respects_privacy_setting(): void {
		$order = new InvoiceOrderFixture();
		self::assertSame( 'Leave at reception.', $this->factory->from_order( $order )->fulfillment['note'] );
		$GLOBALS['wcip_test_options']['wcip_settings']['show_customer_note'] = false;
		self::assertSame( '', $this->factory->from_order( $order )->fulfillment['note'] );
	}

	public function test_customer_roles_use_the_orders_linked_account_and_localized_role_labels(): void {
		$order = new InvoiceOrderFixture();
		self::assertSame( 'Guest', $this->factory->from_order( $order )->customer['role'] );
		$order->customer_id = 7;
		$GLOBALS['wcip_test_users'][7] = (object) array( 'roles' => array( 'wholesale_customer', 'customer' ) );
		$GLOBALS['wcip_test_role_translations']['Wholesale customer'] = 'Wholesale translated';
		self::assertSame( 'Wholesale translated, Customer', $this->factory->from_order( $order )->customer['role'] );
	}

	public function test_preview_and_real_orders_share_presentation_and_note_privacy(): void {
		$GLOBALS['wcip_test_options']['wcip_settings'] = array( 'show_customer_note' => false, 'show_customer_email' => true, 'show_shipping_address' => false, 'single_page' => true, 'invoice_font' => 'dejavusansmono', 'invoice_font_size' => 8 );
		$real = $this->factory->from_order( new InvoiceOrderFixture() );
		$sample = $this->factory->sample();
		self::assertSame( $real->fulfillment['presentation'], $sample->fulfillment['presentation'] );
		self::assertSame( $real->store, $sample->store );
		self::assertSame( '', $sample->fulfillment['note'] );
		self::assertFalse( $sample->fulfillment['show_paid_date'] );
		self::assertFalse( $sample->fulfillment['show_shipping_phone'] );
		self::assertFalse( $sample->fulfillment['label_show_sender'] );
	}

	public function test_preview_prints_paid_date_only_when_enabled(): void {
		$GLOBALS['wcip_test_options']['wcip_settings']['show_paid_date'] = true;
		$sample = $this->factory->sample();
		self::assertTrue( $sample->fulfillment['payment_confirmed'] );
		$templates = new TemplateRegistry();
		$renderer = new HtmlRenderer( $templates );
		foreach ( $templates->all() as $template ) {
			if ( 'invoice' !== $template->document_type ) { continue; }
			$html = $renderer->render( $sample, $template->id );
			self::assertStringContainsString( 'Payment date', $html, $template->id );
			self::assertStringContainsString( $sample->order['paid_date'], $html, $template->id );
		}
		$GLOBALS['wcip_test_options']['wcip_settings']['show_paid_date'] = false;
		self::assertStringNotContainsString( 'Payment date', $renderer->render( $this->factory->sample(), 'classic' ) );
	}

	public function test_hidden_logo_does_not_make_an_http_request(): void {
		$GLOBALS['wcip_test_options']['wcip_settings']['logo_url'] = 'https://example.com/logo.png';
		$GLOBALS['wcip_http_callback'] = static function () { self::fail( 'Hidden logo was requested.' ); };
		self::assertSame( '', $this->factory->from_order( new InvoiceOrderFixture() )->store['logo_data_uri'] );
	}

	public function test_logo_is_cached_only_after_the_actual_image_type_is_verified(): void {
		$GLOBALS['wcip_test_options']['wcip_settings']['logo_url'] = 'https://example.com/logo.png';
		$GLOBALS['wcip_test_options']['wcip_settings']['show_store_logo'] = true;
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jJxkAAAAASUVORK5CYII=' );
		$GLOBALS['wcip_http_response'] = array( 'response' => array( 'code' => 200 ), 'body' => $png, 'headers' => array( 'content-type' => 'image/png; charset=binary' ) );
		$data = $this->factory->from_order( new InvoiceOrderFixture() );
		self::assertSame( 'data:image/png;base64,' . base64_encode( $png ), $data->store['logo_data_uri'] );
		self::assertSame( $data->store['logo_data_uri'], $this->factory->sample()->store['logo_data_uri'] );
		self::assertSame( 2 * MB_IN_BYTES, $GLOBALS['wcip_last_http'][1]['limit_response_size'] );
		$GLOBALS['wcip_http_response']['body'] = 'not an image';
		self::assertSame( $data->store['logo_data_uri'], $this->factory->from_order( new InvoiceOrderFixture() )->store['logo_data_uri'] );
	}

	public function test_mislabeled_or_invalid_logo_data_is_omitted(): void {
		$GLOBALS['wcip_test_options']['wcip_settings']['logo_url'] = 'https://example.com/logo.png';
		$GLOBALS['wcip_test_options']['wcip_settings']['show_store_logo'] = true;
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jJxkAAAAASUVORK5CYII=' );
		$GLOBALS['wcip_http_response'] = array( 'response' => array( 'code' => 200 ), 'body' => $png, 'headers' => array( 'content-type' => 'image/jpeg' ) );
		self::assertSame( '', $this->factory->from_order( new InvoiceOrderFixture() )->store['logo_data_uri'] );
		self::assertSame( array(), $GLOBALS['wcip_test_transients'] );
		$GLOBALS['wcip_http_response']['headers']['content-type'] = 'image/png';
		$GLOBALS['wcip_http_response']['body'] = 'invalid png';
		self::assertSame( '', $this->factory->from_order( new InvoiceOrderFixture() )->store['logo_data_uri'] );
	}
}

final class InvoiceOrderFixture extends \WC_Order {
	public array $items = array();
	public int $customer_id = 0;
	public array $subtotal_options = array();
	public function get_items(): array { return $this->items; }
	public function get_customer_id(): int { return $this->customer_id; }
	public function get_item_subtotal( $item, bool $inc_tax = false, bool $round = true ): float {
		$this->subtotal_options = array( $inc_tax, $round );
		return ( (float) $item->get_subtotal() + ( $inc_tax ? 10 : 0 ) ) / $item->get_quantity();
	}
	public function get_currency(): string { return 'EUR'; }
	public function get_formatted_billing_address(): string { return 'Alex &amp; Company<br/>12 Market Street<BR>Portland'; }
	public function get_formatted_shipping_address(): string { return 'Warehouse<br />24 Dispatch Street'; }
	public function get_customer_note(): string { return 'Leave at reception.'; }
	public function get_order_item_totals(): array { return array( 'order_total' => array( 'label' => '<b>Total:</b>', 'value' => '<strong><span class="woocommerce-Price-amount"><bdi>EUR 88.00</bdi></span></strong>' ) ); }
}

final class InvoiceItemFixture extends \WC_Order_Item_Product {
	public function get_product(): bool { return false; }
	public function get_name(): string { return 'Notebook'; }
	public function get_quantity(): float { return 2.5; }
	public function get_subtotal(): string { return '100'; }
	public function get_total(): string { return '80'; }
	public function get_total_tax(): string { return '8'; }
	public function get_formatted_meta_data( string $hideprefix = '_' ): array {
		$metadata = array(
			(object) array( 'key' => 'color', 'display_key' => 'Color', 'display_value' => '<p>Blue &amp; green</p>' ),
			(object) array( 'key' => '_private_token', 'display_key' => '_private_token', 'display_value' => 'internal-secret' ),
		);
		return array_filter( $metadata, static fn( $meta ) => '' === $hideprefix || 0 !== strpos( $meta->key, $hideprefix ) );
	}
}
