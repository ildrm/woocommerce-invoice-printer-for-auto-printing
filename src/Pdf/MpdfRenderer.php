<?php

namespace WCInvoicePrinter\Pdf;

use WCInvoicePrinter\Template\TemplateDefinition;

final class MpdfRenderer implements PdfRendererInterface {
	public function render( string $html, TemplateDefinition $template ): string {
		if ( strlen( $html ) > 4 * MB_IN_BYTES ) { throw new \RuntimeException( __( 'The document exceeds the safe rendering limit.', 'wc-invoice-printer' ) ); }
		if ( ! class_exists( \Mpdf\Mpdf::class ) ) {
			throw new \RuntimeException( __( 'PDF support is unavailable. Install the Composer dependencies.', 'wc-invoice-printer' ) );
		}
		$temp_dir = trailingslashit( get_temp_dir() ) . 'wcip-mpdf-' . bin2hex( random_bytes( 16 ) );
		// A private, per-render directory prevents other sites/users reading cached
		// images and avoids mPDF cleanup races between concurrent print workers.
		if ( ! @mkdir( $temp_dir, 0700 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A safe user-facing error is emitted below.
			throw new \RuntimeException( __( 'A secure PDF working directory could not be created.', 'wc-invoice-printer' ) );
		}
		try {
			$thermal = in_array( $template->paper_size, array( '80mm', '58mm' ), true );
			$format = $thermal ? array( '58mm' === $template->paper_size ? 58 : 80, 297 ) : $template->paper_size;
			$config = array(
				'mode' => 'utf-8', 'format' => $format,
				'orientation' => strtoupper( substr( $template->orientation, 0, 1 ) ),
				'tempDir' => $temp_dir, 'autoScriptToLang' => true, 'autoLangToFont' => true,
				// Other plugins can load mPDF's global configuration classes first.
				'fontDir' => array( WCIP_PATH . 'vendor/mpdf/mpdf/ttfonts' ),
				'default_font' => 'dejavusans', 'shrink_tables_to_fit' => 1,
				'margin_left' => $thermal ? 4 : 10, 'margin_right' => $thermal ? 4 : 10,
				'margin_top' => $thermal ? 4 : 10, 'margin_bottom' => $thermal ? 4 : 10,
			);
			$layout = $this->layout( $html );
			$mpdf = new \Mpdf\Mpdf( $config );
			$width = $mpdf->w; $height = $mpdf->h;
			$mpdf->WriteHTML( $html );
			if ( ( $thermal && $layout['thermal_auto_height'] ) || ( $layout['single_page'] && $mpdf->page > 1 ) ) {
				unset( $mpdf );
				gc_collect_cycles();
				// Measure a continuous page before fitting. Never clip a paginated source.
				$long_config = array_merge( $config, array( 'format' => array( $width, 2000 ), 'orientation' => 'P' ) );
				$continuous = new \Mpdf\Mpdf( $long_config );
				$continuous->WriteHTML( $html );
				if ( 1 !== $continuous->page ) { throw new LayoutException( __( 'The document is too long for one page. Hide optional fields or use a smaller font.', 'wc-invoice-printer' ) ); }
				$content_height = max( 25, ceil( $continuous->y + $continuous->bMargin + 2 ) );
				unset( $continuous );
				gc_collect_cycles();
				if ( $content_height > 2000 ) { throw new LayoutException( __( 'The document is too long for one page. Hide optional fields or use a smaller font.', 'wc-invoice-printer' ) ); }
				// mPDF's keep-together estimate can exceed its final cursor height.
				// Retry with a small bounded allowance instead of losing the closing block.
				for ( $attempt = 0; $attempt < 4; $attempt++ ) {
					unset( $trimmed );
					gc_collect_cycles();
					$trimmed = new \Mpdf\Mpdf( array_merge( $long_config, array( 'format' => array( $width, $content_height ) ) ) );
					$trimmed->WriteHTML( $html );
					if ( 1 === $trimmed->page || $content_height >= 2000 ) { break; }
					$content_height = min( 2000, $content_height + max( 10, ceil( $content_height * 0.05 ) ) );
				}
				if ( 1 !== $trimmed->page ) { throw new LayoutException( __( 'The document is too long for one page. Hide optional fields or use a smaller font.', 'wc-invoice-printer' ) ); }
				if ( $thermal && $layout['thermal_auto_height'] ) {
					$mpdf = $trimmed;
					unset( $trimmed );
				} else {
					$scale = min( 1, $height / $content_height );
					if ( $scale < 0.6 ) { throw new LayoutException( __( 'The document cannot fit legibly on one sheet. Enable compact layout, hide optional fields or use a smaller font.', 'wc-invoice-printer' ) ); }
					$source = $trimmed->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
					unset( $trimmed );
					gc_collect_cycles();
					if ( strlen( $source ) > 20 * MB_IN_BYTES ) { throw new \RuntimeException( __( 'The document exceeds the safe rendering limit.', 'wc-invoice-printer' ) ); }
					$mpdf = new \Mpdf\Mpdf( $config );
					$mpdf->setSourceFile( \setasign\Fpdi\PdfParser\StreamReader::createByString( $source ) );
					$mpdf->AddPage();
					$mpdf->useTemplate( $mpdf->importPage( 1 ), ( $width - $width * $scale ) / 2, 0, $width * $scale, $content_height * $scale );
				}
			}
			$pdf = $mpdf->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
			if ( strlen( $pdf ) > 20 * MB_IN_BYTES ) { throw new \RuntimeException( __( 'The document exceeds the safe rendering limit.', 'wc-invoice-printer' ) ); }
			return $pdf;
		} finally {
			// mPDF services reference the renderer; release cycles before the next order.
			unset( $mpdf, $continuous, $trimmed );
			gc_collect_cycles();
			$this->remove_temp_directory( $temp_dir );
		}
	}

	/** Only the renderer's bounded metadata controls PDF layout. */
	private function layout( string $html ): array {
		$value = array();
		if ( preg_match( '/<meta name="wcip-layout" content="([^"]*)">/', $html, $match ) ) {
			$value = json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
		}
		return array( 'single_page' => true === ( $value['single_page'] ?? false ), 'thermal_auto_height' => true === ( $value['thermal_auto_height'] ?? false ) );
	}

	/** Preserve each document's page size when a browser prints several orders/copies. */
	public function bundle( array $documents, TemplateDefinition $template ): string {
		if ( ! class_exists( \Mpdf\Mpdf::class ) ) { throw new \RuntimeException( __( 'PDF support is unavailable. Install the Composer dependencies.', 'wc-invoice-printer' ) ); }
		$directory = trailingslashit( get_temp_dir() ) . 'wcip-mpdf-' . bin2hex( random_bytes( 16 ) );
		if ( ! @mkdir( $directory, 0700 ) ) { throw new \RuntimeException( __( 'A secure PDF working directory could not be created.', 'wc-invoice-printer' ) ); }
		try {
			$bundle = new \Mpdf\Mpdf( array( 'tempDir' => $directory, 'fontDir' => array( WCIP_PATH . 'vendor/mpdf/mpdf/ttfonts' ) ) );
			$cache = array(); $bytes = 0;
			foreach ( $documents as $html ) {
				$key = hash( 'sha256', $html );
				if ( ! isset( $cache[ $key ] ) ) { $cache[ $key ] = $this->render( $html, $template ); }
				$bytes += strlen( $cache[ $key ] );
				if ( $bytes > 20 * MB_IN_BYTES ) { throw new \RuntimeException( __( 'The document exceeds the safe rendering limit.', 'wc-invoice-printer' ) ); }
				$pages = $bundle->setSourceFile( \setasign\Fpdi\PdfParser\StreamReader::createByString( $cache[ $key ] ) );
				for ( $page = 1; $page <= $pages; $page++ ) {
					$id = $bundle->importPage( $page ); $size = $bundle->getTemplateSize( $id );
					$bundle->AddPage( $size['orientation'] );
					$bundle->useTemplate( $id, 0, 0, $size['width'], $size['height'], true );
				}
			}
			$pdf = $bundle->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
			if ( strlen( $pdf ) > 20 * MB_IN_BYTES ) { throw new \RuntimeException( __( 'The document exceeds the safe rendering limit.', 'wc-invoice-printer' ) ); }
			return $pdf;
		} finally { unset( $bundle ); gc_collect_cycles(); $this->remove_temp_directory( $directory ); }
	}

	private function remove_temp_directory( string $directory ): void {
		$entries = @scandir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup must not replace a generation error.
		if ( false !== $entries ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				$path = $directory . DIRECTORY_SEPARATOR . $entry;
				if ( is_dir( $path ) && ! is_link( $path ) ) {
					$this->remove_temp_directory( $path );
				} else {
					@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup must not replace a generation error.
				}
			}
		}
		@rmdir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup.
	}

}
