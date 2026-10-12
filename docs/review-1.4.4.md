# Version 1.4.4 review and invoice layout fixes

Reviewed 12 October 2026 against the current working tree. Earlier 1.4.3 database installation fixes and uncommitted work are preserved. This release addresses reported invoice disclosure, excessive spacing, split totals and missing typography controls, plus issues found in the PDF/browser/CUPS paths.

## Findings and changes

| Issue | Change |
| --- | --- |
| Emails and multiple addresses printed unconditionally | Bundled templates share independent visibility switches. Absent preferences hide email, billing/store addresses and other optional fields; only the shipping address is visible in the customer block, with name and role hidden. Explicit saved choices remain effective. Billing fallback/sender defaults are off when absent. |
| Customer role unavailable | Invoice data includes localized current roles from the linked WordPress account, with Guest for unlinked orders. |
| Dense or long orders split closing content | Optional compact spacing and bounded typography; totals/closing information are kept together where they fit. One-page PDF fitting preserves all enabled content with uniform scaling down to 60%, failing clearly beyond that bound. |
| Thermal pages left excess roll length | Optional content-height PDF pages retain 58/80 mm width and font, with a 2,000 mm maximum. Oversized content fails rather than clipping. |
| Browser HTML could defeat PDF page settings | Enabled page modes use the authenticated generated PDF viewer, with physical confirmation outside it. PDF generation is validated before creating tracked browser jobs; viewer reads do not create duplicate jobs. |
| Preview identity/optional note diverged from configuration | Sample previews use configured business identity and display preferences; customer/items remain fictional. Hidden logos are not fetched and disabled notes remain absent. |
| Repeated PDF renders retained mPDF cycles | Render cleanup releases mPDF references/cycles between invoices, preserving bulk-render memory bounds. |
| CUPS banner sheets could add pages | Print-Job requests `job-sheets=none`; server-enforced banners remain an administrator/driver policy. |

Field changes are presentation only. Saved amounts, payment/status, confirmations and queue identity retain their existing semantics. The final order total remains present even when its breakdown is hidden. Custom templates receive the full authorized model and must adopt `$presentation`, `$show` and shared fragments to honor display controls; visibility is not an authorization boundary.

## Defaults and compatibility

Store name and shipping address default visible; customer name and role default hidden. Most optional fields default hidden; all new compact/page modes default off. Font defaults to DejaVu Sans at 10 pt, with Sans/Serif/Sans Mono choices and 6–16 pt bounds. Script-specific PDF fallback remains enabled.

Plugin version is **1.4.4**. No new database migration is required: schema remains **1.4.3**, capabilities **1.4.0** and the retired-provider migration stamp **1.3.0**. Existing explicitly saved display settings remain effective. Companion templates are not overwritten by upgrade. Review saved settings and a real order after installing.

## Verification

The review covers settings normalization, rendering/escaping, invoice/sample data, authenticated preview/browser PDF handling, PDF page/content bounds, CUPS request attributes, translations and release/documentation metadata. Earlier integration results in the remediation record describe their named releases; they are not new 1.4.4 integration claims.

| Executed check | Result |
| --- | --- |
| Full PHP suite, PHP 8.5.8, 128 MiB memory limit | **393 tests / 1,838 assertions**, peak 50.52 MiB |
| Full PHP suite, PHP 8.4 container, read-only project and no network | **393 tests / 1,791 assertions**, peak 50.50 MiB; 47 optional Poppler text assertions are available only on the host |
| JavaScript suite | **23 tests** passed |
| Local-agent Python suite | **9 tests** passed |
| Fresh POT and all locale catalogs | **14 catalogs / 371 messages**, checks and compilation passed |
| Updated localized DOCX development guides | **14 editions / 163 pages**, rendered and visually verified; final hashes recorded in the documentation manifest |
| Composer strict validation/platform requirements, PHP/JavaScript syntax, diff whitespace checks | Passed |

Chrome UI testing against a temporary PHP harness using the repository's WordPress doubles and fictional sample data confirmed that the enabled PDF mode displayed one page, and the outer Print button opened native print preview for that PDF with its final total. The dialog was cancelled without physical printing. This is browser interaction evidence, not real WordPress integration.

Manually generated A4 classic (28 items), A5 classic (12 items), Persian 58 mm and 80 mm thermal (28 items) examples were inspected. Each successfully generated fitted document had one page with its totals and closing note retained. Controlled tests cover excessive-content failure without creating jobs and protected PDF-viewer reads without duplicate jobs.

No new real WordPress integration was run during this review because the available database socket was unavailable. Native Windows/physical printer/scanner, production stores and driver-managed cutting were not exercised. The software produces one-page PDFs within documented bounds; physical paper size, scaling, banner enforcement and cutters require acceptance on the selected device. There is no portable raw cutter capability in the existing PDF backends, so no plugin cutting control is introduced.

See [invoice display and paper layout](invoice-layout.md), [operations](operations.md), [development-guide verification](../system-development/VERIFICATION.md) and [the remediation record](../REMEDIATION_TRACEABILITY.md).
