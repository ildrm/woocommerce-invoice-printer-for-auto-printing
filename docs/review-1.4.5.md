# Version 1.4.5 review and verification

Reviewed 14 October 2026 against repository baseline `5901fa4` (1.4.4) and the supplied local package `/Users/shahinilderemi/Documents/tmp/wc-invoice-printer` (1.4.5). The supplied directory was read only and was not modified.

## Scope and comparison

Compared every package file outside third-party `vendor` dependencies with the repository. Imported 49 changed or new package files, including all ten changed runtime files, all fourteen PO/MO catalog pairs, the POT, release metadata and Markdown documentation. Retained repository-only tests, CI workflows, Composer manifests/lock, release tooling and the fourteen developer DOCX editions; their checksums still match the documentation manifest.

Read all 74 first-party runtime/customization source and configuration files: the plugin entry point and uninstaller; every class in `src`; all templates, JavaScript/CSS assets, the Python workstation agent and its example configuration; and the customization example. Reviewed manual browser/CUPS/agent creation, automatic payment events/reconciliation/queue recovery, authorization, job state transitions, physical confirmation, SQL/schema lifecycle, invoice amounts/addresses, layout/PDF cleanup, localization, warehouse transitions, signed exports and packaging. Third-party dependency source was inspected where needed for PDF interoperability; it was not independently audited in full.

The codebase-memory graph supplied architecture and call-path candidates. Its coverage report flagged embedded PHP templates and stale metadata; direct source reads covered these gaps and were authoritative. A clean graph signal is not proof of complete or correct runtime behavior.

## Incorporated behavior

- Business email defaults to an empty value. Store email and Customer email keep independent visibility switches. Existing saved email addresses and explicit display preferences are preserved.
- Invoice payment gateway display uses the existing `show_payment_method` setting and the order's saved title/ID. Missing titles fall back to the ID, absent values omit the label, and values are escaped. Hidden keyed payment totals cannot reappear through the legacy totals fallback. Packing lists and shipping labels retain their nonpayment presentation.
- mPDF explicitly uses bundled fonts for individual rendering and PDF bundles. Sample data construction uses smaller methods, retaining the supplied PHP 7.4 OPcache workaround.
- Manual order Print/Preview retains a direct recovery link. Browser batches render all documents first, then create jobs in a transaction and prepare them in one conditional write. Failed batches roll back or invalidate unconfirmed remnants on nontransactional tables without overwriting concurrent confirmation/cancellation.
- Optional payment-presentation failures omit confirmation and emit bounded diagnostics without exception messages, traces, SQL or credentials. Automatic payment eligibility remains strict.

## Additional repository fixes and regression coverage

The supplied billing-address guard did not cover malformed billing data used by `RecipientResolver`'s optional shipping fallback. Normalize the resolved address before processing it. Shipping eligibility keeps the existing policy. This is the only runtime source difference from the supplied package.

The repository's release builder previously skipped `composer check-platform-reqs --no-dev` when reusing a production vendor directory. Check both installation paths and resolve Composer launchers explicitly, including Windows `.bat`/`.cmd` launchers. Preserve the exact locked production package check and regenerate the autoloader for staged source.

Updated the WordPress/DOM/SQL test fixtures for gateway IDs, recovery links, batch ID predicates and injected transaction failures. Added regressions for independent gateway/email presentation across all five invoice formats; hidden gateway totals and legacy totals; malformed billing fallback; optional payment/logger failures; failed batch insert/update/commit; transaction rollback and nontransactional invalidation; unrelated jobs and concurrent cancellation/confirmation; foreign mPDF font configuration with Persian content and two-copy bundles; and reused-vendor platform rejection. Release builder tests run in the existing Windows/Linux/macOS Python CI matrix.

## Executed checks for this branch

- PHP 8.5.8: **418 tests, 2,005 assertions passed**. These include actual mPDF generation and existing manual/automatic/CUPS/agent eligibility, duplicate prevention, retry, confirmation, schema and lifecycle tests using controlled WordPress/database doubles.
- JavaScript: **25 tests passed**. Local-agent Python: **9 tests passed**. Release-builder Python: **4 tests passed**.
- PHP syntax checks across **117 files**, all four JavaScript syntax checks and strict Composer manifest validation passed.
- All **14 PO/MO catalogs** validated with **372 messages each**, including plural forms and format placeholders. A fresh WP-CLI source extraction matches the shipped catalogs. WP-CLI's own dependencies emitted PHP 8.5 deprecation notices; extraction and catalog comparison completed successfully.
- All fourteen retained DOCX checksums match the manifest. Their contents and prior rendering record retain the 1.4.4 baseline.
- Production ZIP packaging verifies platform requirements, regenerates production autoloading and requires exactly the locked no-dev package set. Archive integrity, runtime file parity, release metadata and SHA-256 are checked after packaging.

The supplied package contained an earlier report of PHP 7.4 and real WordPress/WooCommerce/database integration runs. Those are upstream claims, not tests executed for this branch. That report referenced a release-specific integration script absent from the package; this record does not claim that script was run here.

## Limits and release metadata

No physical printer, native Windows spooler, live payment gateway or production store was exercised. The local Docker daemon was unavailable, so no PHP 7.4 engine or disposable WordPress/MariaDB integration was run in this session. Existing CI retains the PHP 7.4–8.5 matrix and cross-platform agent coverage; results of future CI runs are not claimed here. Unit SQL doubles do not prove real database locking or engine behavior. Translation completeness does not prove independent native-speaker editorial accuracy. Review and tests cannot guarantee 100% correctness for every installation or third-party extension.

Plugin version and stable tag are **1.4.5**. Database schema remains **1.4.3**, capabilities remain **1.4.0**, and provider-retirement migration remains **1.3.0**. No new schema migration is required. Developer DOCX editions retain their **1.4.4** baseline, supplemented by [the release notes](release-notes-1.4.5.md).

## Reproduction

```sh
composer install --prefer-dist --no-interaction
composer validate --strict
composer lint
composer lint:js
composer test
composer test:js
python3 -m unittest discover -s print-agent -p test_agent.py
python3 -m unittest discover -s scripts -p test_build_release.py
composer i18n:check
wp i18n make-pot . /tmp/wcip-source.pot --domain=wc-invoice-printer --exclude=vendor,tests,build,languages,scripts,system-development --skip-js
python3 scripts/check-translations.py --pot /tmp/wcip-source.pot
python3 scripts/build-release.py --production-vendor /path/to/verified/production/vendor
```

Before production acceptance, activate the complete ZIP on a disposable WordPress/WooCommerce site, test both HPOS and legacy storage using the existing `tests/Integration` scripts, and perform manual and automatic test prints with the actual printer/driver.
