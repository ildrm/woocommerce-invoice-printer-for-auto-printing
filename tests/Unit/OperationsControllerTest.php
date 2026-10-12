<?php

namespace WCInvoicePrinter\Tests\Unit;

use WCInvoicePrinter\Rest\OperationsController;
use WCInvoicePrinter\Tests\Support\JobTestCase;

final class OperationsControllerTest extends JobTestCase {
	/** @dataProvider destinations */
	public function test_diagnostics_reports_the_selected_automatic_destination( array $settings, bool $ready, ?bool $agent_capability = null ): void {
		$this->settings->update( $settings );
		$GLOBALS['wcip_test_users'] = array();
		if ( null !== $agent_capability ) {
			$GLOBALS['wcip_test_users'][ $settings['agent_user_id'] ] = new class( $agent_capability ) {
				private bool $capability;
				public function __construct( bool $capability ) { $this->capability = $capability; }
				public function has_cap( string $capability ): bool { return 'wcip_run_print_agent' === $capability && $this->capability; }
			};
		}
		$GLOBALS['wcip_test_capabilities'] = array( 'wcip_manage_settings' => true, 'wcip_print_invoices' => true );
		try {
			$response = ( new OperationsController( $this->settings, $this->service, $this->jobs ) )->diagnostics();
			self::assertInstanceOf( \WP_REST_Response::class, $response );
			self::assertSame( $ready, $response->get_data()['printer_configured'] );
		} finally { $GLOBALS['wcip_test_capabilities'] = array(); unset( $GLOBALS['wcip_test_users'] ); }
	}

	public static function destinations(): array {
		return array(
			'paired agent without CUPS' => array( array( 'automatic_provider' => 'agent', 'agent_user_id' => 42, 'cups_endpoint' => '' ), true, true ),
			'paired user was removed' => array( array( 'automatic_provider' => 'agent', 'agent_user_id' => 43, 'cups_endpoint' => '' ), false ),
			'paired user lacks capability' => array( array( 'automatic_provider' => 'agent', 'agent_user_id' => 44, 'cups_endpoint' => '' ), false, false ),
			'unpaired agent with CUPS' => array( array( 'automatic_provider' => 'agent', 'agent_user_id' => 0 ), false ),
			'configured CUPS' => array( array( 'automatic_provider' => 'cups' ), true ),
			'CUPS without queue' => array( array( 'automatic_provider' => 'cups', 'cups_printer_id' => '' ), false ),
		);
	}
}
