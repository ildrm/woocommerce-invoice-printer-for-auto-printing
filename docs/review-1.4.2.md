# Version 1.4.2 activation maintenance release

Reviewed October 12, 2026, after a report that activation displayed the generic database installation failure on PHP 7.4.33, MariaDB 10.6.24 and WordPress 7.1.3. The exact failure on that site has not been reproduced. The original installer succeeds in the disposable environment described below; the changes address a fragile verification check and make remaining installation failures diagnosable.

| Issue | Change | Regression coverage |
| --- | --- | --- |
| Table verification compared metadata names literally, which can reject a readable table when the database normalizes its name | Probe the quoted table with a zero-row SELECT, using the same access needed at runtime | Case-normalized table names, missing tables and query exceptions in `SchemaInstallationTest` |
| Activation discarded the database or thrown error, leaving only a permissions suggestion | Retain the failing table and bounded error details; display escaped details during activation and in administrator-only notices | CREATE/ALTER failures, thrown errors, required-column failures and operational-table failures |
| An installation may stop after creating some tables | Keep the schema version unstamped on failure and allow the existing installer to complete the tables on retry; clear obsolete error details | Partial-install failure followed by a successful retry |

The PHP suite passes 336 tests with 1,243 assertions on PHP 8.5.8. The changed production PHP files parse with PHP 7.4 grammar. This is a syntax check, not a PHP 7.4 runtime execution.

A disposable WordPress 7.1.3 installation using real `wpdb` and `dbDelta`, local MariaDB 10.6.28 and PHP 8.5.8 verifies all six tables, repeated activation, and case-normalized table metadata. A deliberately rejected operational-table CREATE reports the correct table and native SQL error, does not stamp the schema version, and recovers successfully after the rejection is removed. The database runs on a local-only socket and contains no production data. The exact reported MariaDB 10.6.24/PHP 7.4.33 combination is not covered by this execution.

The packaged plugin also activates successfully with WooCommerce 11.1.2 in that disposable WordPress site. ZIP integrity, all nine locked runtime dependencies, source parity and the SHA-256 checksum pass verification. All 52 plugin symbols autoload from the extracted package; all seven templates generate valid PDFs in English and Persian (14 PDFs), QR/barcode rendering works, and PDF temporary files are cleaned up.

The release retains schema and capability version 1.4.0 and printing migration version 1.3.0. It changes no table definitions or indexes. Existing jobs, settings and printing history remain in place. The production ZIP includes locked runtime dependencies and an optimized autoloader.

Install the 1.4.2 ZIP by replacing the existing plugin, then activate it. If activation still fails, the additional line identifies the table and the database or verification error needed for the next diagnosis. See the [1.4.1 review](review-1.4.1.md) for the previous maintenance changes and their validation.
