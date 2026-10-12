<?php

namespace WCInvoicePrinter\Template;

use WCInvoicePrinter\Invoice\InvoiceData;

final class HtmlRenderer {
	private TemplateRegistry $templates;

	public function __construct( TemplateRegistry $templates ) {
		$this->templates = $templates;
	}

	public function render( InvoiceData $invoice, string $template_id ): string {
		$template = $this->templates->get( $template_id );
		$presentation = array_merge( ( new \WCInvoicePrinter\Settings\SettingsRepository() )->presentation(), $invoice->fulfillment['presentation'] ?? array() );
		foreach ( array( 'show_paid_date', 'show_shipping_phone' ) as $key ) { if ( isset( $invoice->fulfillment[ $key ] ) ) { $presentation[ $key ] = (bool) $invoice->fulfillment[ $key ]; } }
		$show = static function ( string $key ) use ( $presentation ): bool { return true === ( $presentation[ $key ] ?? false ); };
		$totals = array_values( array_filter( $invoice->totals, static function ( array $total ) use ( $show ): bool { return 'payment_method' !== ( $total['key'] ?? '' ) || $show( 'show_payment_method' ); } ) );
		if ( ! $show( 'show_totals_breakdown' ) ) {
			$totals = array_values( array_filter( $totals, static function ( array $total ): bool { return 'order_total' === ( $total['key'] ?? '' ); } ) );
			// Older custom InvoiceData objects may have no total keys.
			if ( ! $totals && $invoice->totals ) { $all_totals = $invoice->totals; $totals = array( end( $all_totals ) ); }
		}
		do_action( 'wcip_before_invoice_render', $invoice, $template );
		$buffer_level = ob_get_level();
		ob_start();
		try {
			include $template->path;
			$html = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
		}
		$html = (string) apply_filters( 'wcip_rendered_invoice_html', $html, $invoice, $template );
		do_action( 'wcip_after_invoice_render', $html, $invoice, $template );
		return $html;
	}
}
