<?php

namespace WCInvoicePrinter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCInvoicePrinter\Admin\PreviewController;
use WCInvoicePrinter\Automation\Scheduler;
use WCInvoicePrinter\Invoice\InvoiceFactory;
use WCInvoicePrinter\PrintJob\PrintJobRepository;
use WCInvoicePrinter\PrintJob\PrintJobService;
use WCInvoicePrinter\Settings\SettingsRepository;
use WCInvoicePrinter\Template\HtmlRenderer;
use WCInvoicePrinter\Template\TemplateDefinition;
use WCInvoicePrinter\Template\TemplateRegistry;
use WCInvoicePrinter\Tests\Support\InMemoryWpdb;
use WCInvoicePrinter\Tests\Support\TranslationCatalog;

final class PreviewControllerTest extends TestCase {
	private InMemoryWpdb $database;
	private TemplateRegistry $templates;
	private PreviewController $controller;
	private string $temporary_template = '';

	protected function setUp(): void {
		$this->database = new InMemoryWpdb();
		$GLOBALS['wpdb'] = $this->database;
		$GLOBALS['wcip_test_options'] = array();
		$GLOBALS['wcip_test_filters'] = array();
		$GLOBALS['wcip_test_orders'] = array( 1 => new \WC_Order( 1 ) );
		$GLOBALS['wcip_test_capabilities'] = array( 'wcip_print_invoices' => true );
		$GLOBALS['wcip_test_nonce_valid'] = true;
		$GLOBALS['wcip_test_logs'] = array();
		unset( $GLOBALS['wcip_nocache_called'], $GLOBALS['wcip_nocache_exception'] );
		unset( $GLOBALS['wcip_test_locale'], $GLOBALS['wcip_test_translations'] );
		$_GET = array( 'order_ids' => '1', 'template' => 'classic' );
		$settings = new SettingsRepository();
		$this->templates = new TemplateRegistry();
		$jobs = new PrintJobRepository();
		$this->controller = new PreviewController( new InvoiceFactory( $settings ), new HtmlRenderer( $this->templates ), $this->templates, new PrintJobService( $jobs, new Scheduler( $jobs ), $settings, $this->templates ), $jobs );
	}

	protected function tearDown(): void {
		$_GET = array();
		$GLOBALS['wcip_test_nonce_valid'] = true;
		unset( $GLOBALS['wcip_nocache_called'], $GLOBALS['wcip_nocache_exception'] );
		unset( $GLOBALS['wcip_test_locale'], $GLOBALS['wcip_test_translations'] );
		$GLOBALS['wcip_test_filters'] = array();
		if ( $this->temporary_template ) { unlink( $this->temporary_template ); }
	}

	private function assert_failure( int $status, string $message ): void {
		try {
			$this->controller->output();
			self::fail( 'The preview unexpectedly succeeded.' );
		} catch ( \RuntimeException $error ) {
			self::assertSame( $status, $error->getCode() );
			self::assertStringContainsString( $message, $error->getMessage() );
		}
		self::assertSame( array(), $this->database->rows );
	}

	public function test_preview_requires_printing_capability(): void {
		$GLOBALS['wcip_test_capabilities'] = array();
		$this->assert_failure( 403, 'not allowed' );
	}

	public function test_invalid_nonce_prevents_preview_and_job_creation(): void {
		$GLOBALS['wcip_test_nonce_valid'] = false;
		$this->assert_failure( 0, 'Invalid nonce' );
	}

	/**
	 * @dataProvider invalid_selections
	 */
	public function test_invalid_selection_does_not_create_browser_jobs( $selection, int $status ): void {
		$_GET['order_ids'] = $selection;
		$this->assert_failure( $status, is_array( $selection ) ? 'Invalid preview input' : ( 400 === $status ? 'Invalid order selection' : 'selected orders' ) );
	}

	public static function invalid_selections(): array {
		return array( array( '-1', 400 ), array( '1,missing', 400 ), array( '1,999', 404 ), array( array( '1' ), 400 ) );
	}

	public function test_invalid_copy_count_does_not_prepare_jobs(): void {
		$_GET['copies'] = '1.5';
		$this->assert_failure( 400, 'between 1 and 20' );
	}

	public function test_over_limit_selection_is_rejected_instead_of_truncated(): void {
		$_GET['order_ids'] = implode( ',', range( 1, 51 ) );
		$this->assert_failure( 400, 'no more than 50' );
	}

	public function test_render_failure_does_not_mark_a_browser_job_submitted(): void {
		$this->temporary_template = tempnam( sys_get_temp_dir(), 'wcip-test-template-' );
		file_put_contents( $this->temporary_template, '<?php throw new \\RuntimeException("private template failure");' );
		$this->templates->register( new TemplateDefinition( 'broken', 'Broken', '', 'A4', 'portrait', true, $this->temporary_template ) );
		$_GET['template'] = 'broken';
		$this->assert_failure( 500, 'could not be prepared' );
	}

	public function test_later_render_failure_does_not_record_any_part_of_selection(): void {
		$this->temporary_template = tempnam( sys_get_temp_dir(), 'wcip-test-template-' );
		file_put_contents( $this->temporary_template, '<?php if (2 === $invoice->order["id"]) { throw new \\RuntimeException("failure"); } echo "<html><body>Invoice</body></html>";' );
		$this->templates->register( new TemplateDefinition( 'broken', 'Broken', '', 'A4', 'portrait', true, $this->temporary_template ) );
		$GLOBALS['wcip_test_orders'][2] = new \WC_Order( 2 );
		$_GET['order_ids'] = '1,2';
		$_GET['template'] = 'broken';
		$this->assert_failure( 500, 'could not be prepared' );
	}

	/** Stop at the HTTP boundary, after real template rendering, before headers/exit. */
	private function prepare_to_http_boundary(): void {
		$GLOBALS['wcip_nocache_exception'] = new \RuntimeException( 'HTTP boundary', 910 );
		try { $this->controller->output(); self::fail( 'Missing HTTP boundary.' ); }
		catch ( \RuntimeException $error ) { self::assertSame( 910, $error->getCode(), $error->getMessage() ); }
		self::assertTrue( $GLOBALS['wcip_nocache_called'] );
	}

	public function test_invoice_preview_does_not_create_a_print_job(): void {
		$_GET['preview'] = '1';
		$_GET['copies'] = '3';
		$this->prepare_to_http_boundary();
		self::assertSame( array(), $this->database->rows );
	}

	public function test_sample_preview_does_not_create_a_print_job(): void {
		$_GET = array( 'sample' => '1', 'template' => 'thermal' );
		$this->prepare_to_http_boundary();
		self::assertSame( array(), $this->database->rows );
	}

	/** @dataProvider rtl_locales */
	public function test_normal_sample_preview_follows_the_current_rtl_language( string $locale ): void {
		$GLOBALS['wcip_test_locale'] = $locale;
		$GLOBALS['wcip_test_translations'] = TranslationCatalog::load( $locale );
		$_GET = array( 'sample' => '1', 'template' => 'classic' );
		$rendered = '';
		$GLOBALS['wcip_test_filters']['wcip_rendered_invoice_html'][] = static function ( string $html ) use ( &$rendered ): string {
			$rendered = $html;
			return $html;
		};
		$this->prepare_to_http_boundary();
		self::assertStringContainsString( 'dir="rtl"', $rendered );
		self::assertStringContainsString( 'direction:rtl', $rendered );
		self::assertStringContainsString( __( 'INVOICE', 'wc-invoice-printer' ), $rendered );
		self::assertSame( array(), $this->database->rows );
	}

	public static function rtl_locales(): array {
		return array( array( 'fa_IR' ), array( 'ar' ) );
	}

	public function test_browser_print_records_prepared_job_with_correct_copies(): void {
		$_GET['print'] = '1';
		$_GET['copies'] = '3';
		$this->prepare_to_http_boundary();
		self::assertCount( 1, $this->database->rows );
		self::assertSame( 'submitted', $this->database->rows[1]['status'] );
		self::assertSame( 'browser', $this->database->rows[1]['provider_id'] );
		self::assertSame( 3, $this->database->rows[1]['copies'] );
	}

	public function test_browser_batch_prepares_only_its_own_jobs(): void {
		$jobs = new PrintJobRepository();
		$previous = $jobs->create( array( 'order_id' => 1, 'trigger_type' => 'manual', 'idempotency_key' => 'previous', 'template_id' => 'classic', 'provider_id' => 'browser', 'printer_id' => '', 'copies' => 1 ) );
		$GLOBALS['wcip_test_orders'][2] = new \WC_Order( 2 );
		$_GET['order_ids'] = '1,2';
		$this->prepare_to_http_boundary();
		self::assertCount( 3, $this->database->rows );
		self::assertSame( 'queued', $jobs->find( $previous['id'] )['status'] );
		self::assertSame( 'submitted', $jobs->find( 2 )['status'] );
		self::assertSame( 'submitted', $jobs->find( 3 )['status'] );
		self::assertContains( 'COMMIT', $this->database->queries );
	}

	/** @dataProvider failed_batch_writes */
	public function test_failed_browser_batch_rolls_back_or_invalidates_every_new_job( string $failure, bool $transactional ): void {
		$this->database->transactional = $transactional;
		$GLOBALS['wcip_test_orders'][2] = new \WC_Order( 2 );
		$_GET['order_ids'] = '1,2';
		$this->database->before_query = static function ( InMemoryWpdb $db, string $sql ) use ( $failure ) {
			return ( 'commit' === $failure && 'COMMIT' === $sql ) || ( 'update' === $failure && false !== strpos( $sql, 'WHERE id IN (' ) ) ? false : null;
		};
		try { $this->controller->output(); self::fail( 'Expected failed preparation.' ); }
		catch ( \RuntimeException $error ) { self::assertSame( 500, $error->getCode() ); }
		self::assertContains( 'ROLLBACK', $this->database->queries );
		if ( $transactional ) { self::assertSame( array(), $this->database->rows ); }
		else {
			self::assertCount( 2, $this->database->rows );
			foreach ( $this->database->rows as $row ) {
				self::assertSame( 'failed', $row['status'] );
				self::assertSame( 'browser_preparation_failed', $row['error_code'] );
			}
		}
		self::assertSame( 'browser_jobs', $GLOBALS['wcip_test_logs'][0]['context']['stage'] );
	}

	public static function failed_batch_writes(): array {
		return array( array( 'update', true ), array( 'commit', true ), array( 'update', false ), array( 'commit', false ) );
	}

	public function test_later_insert_failure_rolls_back_earlier_browser_job(): void {
		$GLOBALS['wcip_test_orders'][2] = new \WC_Order( 2 );
		$_GET['order_ids'] = '1,2';
		$this->database->before_insert = static function ( InMemoryWpdb $db ): void {
			$db->before_insert = static function ( InMemoryWpdb $db ): void { $db->fail_insert = true; };
		};
		$this->assert_failure( 500, 'could not be prepared' );
		self::assertContains( 'ROLLBACK', $this->database->queries );
	}

	public function test_failure_log_omits_private_exception_details(): void {
		$this->temporary_template = tempnam( sys_get_temp_dir(), 'wcip-test-template-' );
		file_put_contents( $this->temporary_template, '<?php throw new \\RuntimeException("customer@example.test Authorization: secret SELECT private");' );
		$this->templates->register( new TemplateDefinition( 'broken', 'Broken', '', 'A4', 'portrait', true, $this->temporary_template ) );
		$_GET['template'] = 'broken';
		$this->assert_failure( 500, 'could not be prepared' );
		$logs = json_encode( $GLOBALS['wcip_test_logs'] );
		foreach ( array( 'customer@example.test', 'secret', 'SELECT private' ) as $private ) { self::assertStringNotContainsString( $private, $logs ); }
		self::assertSame( 'html_render', $GLOBALS['wcip_test_logs'][0]['context']['stage'] );
	}

	public function test_single_sheet_browser_print_prepares_valid_pdf_before_recording_job(): void {
		( new SettingsRepository() )->update( array( 'single_page' => true ) );
		$_GET['copies'] = '2';
		$this->prepare_to_http_boundary();
		self::assertCount( 1, $this->database->rows );
		self::assertSame( 2, $this->database->rows[1]['copies'] );
	}

	public function test_authenticated_pdf_view_does_not_create_another_job(): void {
		$_GET['pdf'] = '1';
		$_GET['copies'] = '2';
		$this->prepare_to_http_boundary();
		self::assertSame( array(), $this->database->rows );
	}

	public function test_pdf_view_requires_the_same_nonce_and_capability(): void {
		$_GET['pdf'] = '1';
		$GLOBALS['wcip_test_nonce_valid'] = false;
		$this->assert_failure( 0, 'Invalid nonce' );
		$GLOBALS['wcip_test_nonce_valid'] = true;
		$GLOBALS['wcip_test_capabilities'] = array();
		$this->assert_failure( 403, 'not allowed' );
	}

	public function test_single_sheet_layout_failure_does_not_prepare_a_job(): void {
		( new SettingsRepository() )->update( array( 'single_page' => true ) );
		$GLOBALS['wcip_test_filters']['wcip_rendered_invoice_html'][] = static function ( string $html ): string {
			return str_replace( '</body>', str_repeat( '<p>Additional document information</p>', 500 ) . '</body>', $html );
		};
		$this->assert_failure( 400, 'one page' );
	}
}
