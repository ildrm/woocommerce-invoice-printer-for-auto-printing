# Version 1.4.1 review and verification

Reviewed October 12, 2026. This maintenance release fixes six issues found in the shipped PHP, templates, admin JavaScript, local agent, lifecycle and packaging workflows. The review used the codebase graph with direct source inspection, including templates the graph could not parse. Schema and capability versions remain 1.4.0; this release requires no new database migration.

| Issue | Fix | Regression coverage |
| --- | --- | --- |
| Queued CUPS automatic jobs ignored the automation toggle, including when disabled during PDF generation | Check the current setting before rendering and again before dispatch; intentional manual prints remain available | `PrintWorkerTest` |
| Manual CUPS and agent jobs could still print orders moved to trash or checkout draft after enqueue | Recheck order availability before CUPS rendering/dispatch and agent preparation/start authorization | `PrintWorkerTest`, `AgentDeliveryTest` |
| Deactivation and uninstall passed empty arguments to Action Scheduler, leaving print/export actions containing job IDs scheduled | Use a null argument filter while retaining the plugin's hook and group restrictions | `LifecycleTest`; the scheduler double models argument matching from [the upstream Action Scheduler API](https://github.com/woocommerce/action-scheduler/blob/trunk/functions.php) |
| Opted-in uninstall left the retired-provider migration marker behind | Delete `wcip_printing_migration_version` with the other plugin options | `LifecycleTest` |
| A mapped phone became an extra address field when a shipping phone already existed; it could print despite recipient phone visibility being disabled | Keep the phone mapping in the dedicated phone branch and preserve the existing shipping value | `RecipientResolverTest`, including rendered output for all seven formats |
| Diagnostics always checked CUPS, giving incorrect readiness for a paired or unpaired agent | Check readiness for the selected automatic destination | `OperationsControllerTest` |

The original PHP baseline passed 318 tests with 1,160 assertions. Regression cases reproduced the issues before the fixes. The updated suite passes 331 tests with 1,205 assertions on PHP 8.5.8, including actual mPDF generation and schema-boundary subprocess tests. All 23 JavaScript tests and nine Python agent tests pass. PHP and JavaScript syntax checks, strict Composer validation, installed-platform requirements, all fourteen translation catalogs and `git diff --check` pass.

The release ZIP is built from a clean temporary staging directory using the committed production dependency lock, with optimized autoloading and no development dependencies. ZIP integrity, dependency inventory, source parity and packaged PDF generation are checked before delivery.

The WordPress/WooCommerce permission, database and scheduler unit tests use controlled doubles. This review does not establish a new live-site integration run, native Windows printing, physical printer output, multisite coverage or every gateway/runtime combination. A state can still change after the final eligibility check; uncertain delivery remains held for operator review. Existing historical integration evidence remains in the source repository's remediation report.
