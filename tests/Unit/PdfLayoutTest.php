<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Invoice\InvoiceData;
use WCInvoicePrinter\Invoice\InvoiceFactory;
use WCInvoicePrinter\Pdf\LayoutException;
use WCInvoicePrinter\Pdf\MpdfRenderer;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\HtmlRenderer;
use WCInvoicePrinter\Template\TemplateRegistry;

final class PdfLayoutTest extends TestCase {
	private TemplateRegistry $templates;

	protected function setUp(): void {
		$GLOBALS['wcip_test_options'] = array();
		$GLOBALS['wcip_test_filters'] = array();
		unset( $GLOBALS['wcip_temp_dir'], $GLOBALS['wcip_test_translations'], $GLOBALS['wcip_test_locale'], $GLOBALS['wcip_test_user_locale'] );
		$this->templates = new TemplateRegistry();
	}

	/** @dataProvider fixed_sheets */
	public function test_long_invoice_fits_one_fixed_sheet_and_retains_final_content( string $id, int $items, float $width, float $height ): void {
		$unfitted = $this->pdf( $this->invoice( $items ), $id );
		self::assertGreaterThan( 1, $this->pages( $unfitted ), 'The regression fixture must overflow without single-sheet output.' );
		$fitted = $this->pdf( $this->invoice( $items, array( 'single_page' => true ) ), $id );
		self::assertSame( 1, $this->pages( $fitted ) );
		$this->assert_dimensions( $fitted, $width, $height );
		$this->assert_retained_content( $fitted, $items );
	}

	public static function fixed_sheets(): array {
		return array( array( 'classic', 22, 210.0, 297.0 ), array( 'compact', 35, 210.0, 297.0 ), array( 'classic-a5', 12, 148.0, 210.0 ) );
	}

	/** @dataProvider thermal_rolls */
	public function test_thermal_auto_height_keeps_width_and_ends_after_short_or_long_content( string $id, float $width ): void {
		$short = $this->pdf( $this->invoice( 2, array( 'thermal_auto_height' => true ) ), $id );
		$long = $this->pdf( $this->invoice( 32, array( 'thermal_auto_height' => true ) ), $id );
		self::assertSame( 1, $this->pages( $short ) );
		self::assertSame( 1, $this->pages( $long ) );
		$short_size = $this->dimensions( $short ); $long_size = $this->dimensions( $long );
		self::assertEqualsWithDelta( $width, $short_size[0], 0.02 );
		self::assertEqualsWithDelta( $width, $long_size[0], 0.02 );
		self::assertLessThan( 297, $short_size[1], 'Short receipts should not feed an A4 length.' );
		self::assertGreaterThan( 297, $long_size[1], 'Long receipts must extend instead of clipping the bottom.' );
		self::assertLessThan( 2000, $long_size[1] );
		$this->assert_retained_content( $short, 2 );
		$this->assert_retained_content( $long, 32 );
	}

	public static function thermal_rolls(): array { return array( array( 'thermal', 80.0 ), array( 'thermal58', 58.0 ) ); }

	public function test_compact_spacing_reduces_receipt_length_and_keeps_all_content(): void {
		$normal = $this->pdf( $this->invoice( 18, array( 'thermal_auto_height' => true ) ), 'thermal' );
		$compact = $this->pdf( $this->invoice( 18, array( 'thermal_auto_height' => true, 'compact_layout' => true ) ), 'thermal' );
		self::assertSame( 1, $this->pages( $normal ) );
		self::assertSame( 1, $this->pages( $compact ) );
		self::assertLessThan( $this->dimensions( $normal )[1], $this->dimensions( $compact )[1] );
		$this->assert_retained_content( $compact, 18 );
	}

	public function test_excessive_fixed_sheet_content_fails_without_clipping(): void {
		$this->expectException( LayoutException::class );
		$this->pdf( $this->invoice( 80, array( 'single_page' => true ) ), 'classic' );
	}

	public function test_bundling_two_orders_and_two_copies_keeps_every_sheet_and_order(): void {
		$first = $this->invoice( 22, array( 'single_page' => true ), 'ORDER-A-1042' );
		$second = $this->invoice( 24, array( 'single_page' => true ), 'ORDER-B-1043' );
		$renderer = new HtmlRenderer( $this->templates );
		$first_html = $renderer->render( $first, 'classic' ); $second_html = $renderer->render( $second, 'classic' );
		$pdf = ( new MpdfRenderer() )->bundle( array( $first_html, $first_html, $second_html, $second_html ), $this->templates->get( 'classic' ) );
		self::assertSame( 4, $this->pages( $pdf ) );
		for ( $page = 1; $page <= 4; $page++ ) { $this->assert_dimensions( $pdf, 210, 297, $page ); }
		$text = $this->extract_text( $pdf );
		if ( null !== $text ) {
			self::assertSame( 2, substr_count( $text, 'ORDER-A-1042' ) );
			self::assertSame( 2, substr_count( $text, 'ORDER-B-1043' ) );
			self::assertSame( 4, substr_count( $text, 'INVOICE-END-NOTE' ) );
			self::assertSame( 2, substr_count( $text, 'FINAL-ITEM-22' ) );
			self::assertSame( 2, substr_count( $text, 'FINAL-ITEM-24' ) );
		}
	}

	public function test_thermal_bundle_preserves_each_orders_individual_paper_length(): void {
		$renderer = new HtmlRenderer( $this->templates );
		$documents = array( $renderer->render( $this->invoice( 2, array( 'thermal_auto_height' => true ), 'SHORT-ORDER' ), 'thermal58' ), $renderer->render( $this->invoice( 32, array( 'thermal_auto_height' => true ), 'LONG-ORDER' ), 'thermal58' ) );
		$pdf = ( new MpdfRenderer() )->bundle( $documents, $this->templates->get( 'thermal58' ) );
		self::assertSame( 2, $this->pages( $pdf ) );
		$short = $this->dimensions( $pdf, 1 ); $long = $this->dimensions( $pdf, 2 );
		self::assertEqualsWithDelta( 58, $short[0], 0.02 );
		self::assertEqualsWithDelta( 58, $long[0], 0.02 );
		self::assertLessThan( $long[1], $short[1] );
	}

	private function pdf( InvoiceData $invoice, string $id ): string {
		return ( new MpdfRenderer() )->render( ( new HtmlRenderer( $this->templates ) )->render( $invoice, $id ), $this->templates->get( $id ) );
	}

	private function invoice( int $count, array $presentation = array(), string $number = '1042' ): InvoiceData {
		$sample = ( new InvoiceFactory( new SettingsRepository() ) )->sample();
		$order = $sample->order; $order['number'] = $number;
		$items = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$item = $sample->items[0];
			$item['name'] = 'Product ' . $i . ' with extended invoice detail';
			if ( 1 === $i ) { $item['name'] = 'FIRST-ITEM-1 with extended invoice detail'; }
			if ( $i === $count ) { $item['name'] = 'FINAL-ITEM-' . $count; }
			$items[] = $item;
		}
		$fields = $sample->fulfillment;
		$fields['note'] = 'INVOICE-END-NOTE';
		$fields['presentation'] = array_merge( $fields['presentation'], array( 'show_customer_note' => true ), $presentation );
		$totals = array( array( 'key' => 'order_total', 'label' => 'Invoice total:', 'value' => '$987.65' ) );
		return new InvoiceData( $order, $sample->store, $sample->customer, $items, $totals, $fields );
	}

	private function reader( string $pdf ): \setasign\Fpdi\PdfReader\PdfReader {
		return new \setasign\Fpdi\PdfReader\PdfReader( new \setasign\Fpdi\PdfParser\PdfParser( \setasign\Fpdi\PdfParser\StreamReader::createByString( $pdf ) ) );
	}

	private function pages( string $pdf ): int { return $this->reader( $pdf )->getPageCount(); }

	private function dimensions( string $pdf, int $page = 1 ): array {
		[$width, $height] = $this->reader( $pdf )->getPage( $page )->getWidthAndHeight( \setasign\Fpdi\PdfReader\PageBoundaries::MEDIA_BOX );
		return array( $width * 25.4 / 72, $height * 25.4 / 72 );
	}

	private function assert_dimensions( string $pdf, float $width, float $height, int $page = 1 ): void {
		$dimensions = $this->dimensions( $pdf, $page );
		self::assertEqualsWithDelta( $width, $dimensions[0], 0.02 );
		self::assertEqualsWithDelta( $height, $dimensions[1], 0.02 );
	}

	private function assert_retained_content( string $pdf, int $last_item ): void {
		$text = $this->extract_text( $pdf );
		if ( null === $text ) { self::assertGreaterThan( 1000, strlen( $pdf ) ); return; }
		self::assertStringContainsString( 'FIRST-ITEM-1', $text );
		self::assertStringContainsString( 'FINAL-ITEM-' . $last_item, $text );
		self::assertStringContainsString( '$987.65', $text );
		self::assertStringContainsString( 'INVOICE-END-NOTE', $text );
	}

	/** Optional Poppler check verifies that fitting retains actual PDF text, not only HTML. */
	private function extract_text( string $pdf ): ?string {
		$binary = null;
		foreach ( array_merge( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ), array( '/opt/homebrew/bin', '/usr/local/bin', '/usr/bin' ) ) as $directory ) {
			$path = rtrim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'pdftotext';
			if ( is_file( $path ) && is_executable( $path ) ) { $binary = $path; break; }
		}
		if ( null === $binary || ! function_exists( 'proc_open' ) ) { return null; }
		$file = tempnam( sys_get_temp_dir(), 'wcip-pdf-text-' );
		try {
			file_put_contents( $file, $pdf );
			$process = proc_open( array( $binary, '-layout', $file, '-' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
			self::assertIsResource( $process );
			fclose( $pipes[0] ); $text = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
			$error = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
			self::assertSame( 0, proc_close( $process ), $error );
			return $text;
		} finally { unlink( $file ); }
	}
}
