# Version 1.4.3 database key-length fix

Released October 12, 2026, after activation reported `wp_wc_invoice_print_jobs: Specified key was too long; max key length is 1000 bytes`.

The failure was reproduced with real WordPress 7.1.3 `wpdb`/`dbDelta` and MariaDB 10.6.28 configured with MyISAM as its default engine. The job table previously inherited the server default and its `agent_queue` index could require 1,108 bytes with `utf8mb4`: `(64 + 191 + 20) × 4 + 8`. MyISAM permits a maximum 1,000-byte index. See [MariaDB's MyISAM documentation](https://mariadb.com/docs/server/server-usage/storage-engines/myisam-storage-engine/myisam-overview).

Version 1.4.3 bounds indexed provider/printer/status characters at 64/96/20, reducing the maximum key data to 728 bytes, and explicitly selects InnoDB for newly created job tables. The printer column still holds all 191 characters. The non-unique index only helps locate candidates; full printer identifiers remain in the exact SQL filters and claim conditions. Existing table engines and other indexes are retained.

After another report of the same error, the installer was corrected again without changing version 1.4.3. The additional failure was reproduced on a MyISAM table with a shorter printer column and a full queue index: WordPress widens the column before handling indexes, and [dbDelta ignores index-prefix differences](https://developer.wordpress.org/reference/functions/dbdelta/). The old key therefore blocks the resize. The installer now inspects and repairs that nonunique queue index before dbDelta, uses a smaller temporary prefix for short columns, and verifies the final bounded index afterward. Unexpected unique queue constraints are preserved and reported. The failure message identifies installer build `queue-repair-20261012` and includes bounded CREATE/ALTER details for the affected plugin table.

Schema version advances to 1.4.3 so existing installations run the updated installer. Capability version remains 1.4.0 and the printing migration stamp remains 1.3.0. Missing indexes are added with the bounded definition. Existing jobs, settings and history are preserved.

The PHP suite passes 340 tests on PHP 8.5.8. Changed PHP parses with PHP 7.4 grammar. The native [key-length regression](../tests/Integration/schema-key-length.php) verifies:

- The original CREATE fails with the same 1,000-byte error and leaves the schema unstamped.
- Corrected fresh activation succeeds despite the MyISAM server default and creates an InnoDB job table.
- An existing populated MyISAM table gains missing agent fields and the bounded index without losing jobs or converting its engine.
- Populated MyISAM tables with existing full queue indexes and 64- or 96-character printer columns expand safely to 191 characters while preserving their rows.
- A table with an older 255-character provider column upgrades successfully.
- Two 191-character printer routes sharing the first 96 characters remain distinct for candidate retrieval and claims.
- Repeated activation succeeds and stamps schema version 1.4.3.

The native test uses WordPress 7.1.3, MariaDB 10.6.28 and PHP 8.5.8 with a local-only database socket and disposable data. The reported PHP 7.4.33/MariaDB 10.6.24 combination has not been executed locally. The earlier test used an InnoDB default, which explains why version 1.4.2 did not reproduce the failure. This release addresses the confirmed index-size cause.

Install the corrected 1.4.3 ZIP by replacing the existing plugin, then activate it. The release version remains 1.4.3. If activation still fails, check for installer identifier `queue-repair-20261012` and the failing SQL statement; their presence confirms the repaired installer is running. The reported site's exact table definition and installed files have not been inspected directly.
