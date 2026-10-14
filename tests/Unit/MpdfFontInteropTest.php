<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Invoice\InvoiceFactory;
use WCInvoicePrinter\Pdf\MpdfRenderer;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\HtmlRenderer;
use WCInvoicePrinter\Template\TemplateRegistry;

final class MpdfFontInteropTest extends TestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_render_and_bundle_use_bundled_fonts_after_foreign_configuration_loads(): void {
		$GLOBALS['wcip_test_options'] = array();
		$GLOBALS['wcip_test_filters'] = array();
		$directory = sys_get_temp_dir() . '/wcip-foreign-config-' . bin2hex( random_bytes( 8 ) );
		mkdir( $directory, 0700 );
		$file = $directory . '/ConfigVariables.php';
		$config_path = WCIP_PATH . 'vendor/mpdf/mpdf/src/Config';
		$source = file_get_contents( $config_path . '/ConfigVariables.php' );
		$source = str_replace( "__DIR__ . '/../../ttfonts'", var_export( $directory . '/missing-fonts', true ), $source );
		$source = str_replace( '__DIR__', var_export( $config_path, true ), $source );
		file_put_contents( $file, $source );
		try {
			// Simulate configuration loaded by another plugin whose fonts are unavailable.
			require $file;
			$defaults = ( new \Mpdf\Config\ConfigVariables() )->getDefaults();
			self::assertNotSame( WCIP_PATH . 'vendor/mpdf/mpdf/ttfonts', $defaults['fontDir'][0] );
			self::assertFalse( is_dir( $defaults['fontDir'][0] ) );
			$settings = new SettingsRepository();
			$settings->update( array( 'compact_layout' => true, 'thermal_auto_height' => true ) );
			$templates = new TemplateRegistry();
			$invoice = ( new InvoiceFactory( $settings ) )->sample( true );
			foreach ( array( 'compact', 'thermal' ) as $id ) {
				$html = ( new HtmlRenderer( $templates ) )->render( $invoice, $id );
				$html = str_replace( '</body>', '<p>فاکتور فروش — سفارش آزمایشی</p></body>', $html );
				$renderer = new MpdfRenderer();
				$pdf = $renderer->render( $html, $templates->get( $id ) );
				self::assertStringStartsWith( '%PDF-', $pdf );
				self::assertStringContainsString( 'DejaVuSans', $pdf );
				$bundle = $renderer->bundle( array( $html, $html ), $templates->get( $id ) );
				self::assertSame( 2, preg_match_all( '/\/Type\s*\/Page\b/', $bundle ) );
				self::assertStringContainsString( 'DejaVuSans', $bundle );
			}
		} finally { unlink( $file ); rmdir( $directory ); }
	}
}
