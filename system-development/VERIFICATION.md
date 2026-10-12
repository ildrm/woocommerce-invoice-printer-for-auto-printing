# Development guide verification

## Version 1.4.4 review

Reviewed **12 October 2026** against the **WooCommerce Invoice Printer 1.4.4** working tree. The earlier verification record below remains historical evidence for version 1.4.0.

All **14 DOCX editions** include a localized 1.4.4 supplement covering independent visibility controls, shipping-only customer information by default, optional customer name/role/email, font choices and size bounds, compact spacing, one-sheet fitting, content-length thermal paper, browser PDF viewing and driver-managed cutting. Explicitly saved display choices remain effective. Plugin version is 1.4.4; schema remains 1.4.3, capabilities 1.4.0 and provider retirement 1.3.0.

The supplement describes the current flat `SettingsRepository::presentation()` array in `InvoiceData->fulfillment['presentation']`, the optional customer role and template presentation helpers. The footer example guards store details and email with their independent `$show` controls. The companion template uses the shared fragments and controls; its purchase-order field is hidden unless explicitly enabled through the documented customization filter.

The final guides were rendered through the documents skill's canonical bundled LibreOffice/Poppler renderer, using an isolated temporary profile with a local CJK-capable font. All **163 pages** were visually inspected at original resolution. Unchanged pages were reused only after byte-for-byte PNG comparison between render passes. Review covered shaping, fonts, RTL/LTR direction, code wrapping, tables, headers, continuous page numbers and clipping. No font, PDF or PNG intermediates are distributed. Final checks preserve nine tables in each edition, Persian/Arabic RTL paragraphs and mirrored tables, and LTR technical code. Rendered counts and final DOCX checksums are in [manifest.json](manifest.json).

Both companion-plugin PHP files passed syntax checks. A standalone check using repository WordPress doubles exercised template registration, hidden-by-default optional fields, explicitly enabled purchase-order content, escaping, LTR/RTL rendering and the final total. The full plugin suite passed **393 tests / 1,838 assertions** on PHP 8.5.8 with a 128 MiB limit, and **393 tests / 1,791 assertions** in a read-only PHP 8.4 container without network. The host runs 47 additional optional Poppler text assertions. JavaScript passed **23 tests** and the local agent **9 tests**. Composer strict validation/platform requirements, PHP/JavaScript syntax and all fourteen **371-message** catalogs passed.

Chrome interaction against a temporary PHP harness with fictional sample data confirmed the one-page PDF viewer and its outer Print button opening native print preview with the final total. The print dialog was cancelled. Rendered A4/A5 and 58/80 mm thermal examples retained totals and closing content on one PDF page. Excessively long content fails within the documented 60% fitting or 2,000 mm thermal bounds; it is not clipped.

No new real WordPress/database integration, native Windows printer, physical printer/scanner, production store or cutter was exercised for 1.4.4. The available database socket was unavailable. Physical sheet size, scaling, banners and driver cutting need acceptance on the chosen device. Translations have not had independent native-speaker editorial review; desktop Word rendering can vary with installed fonts. See the [1.4.4 review](../docs/review-1.4.4.md) and [invoice display and paper layout guide](../docs/invoice-layout.md) for implementation details and limits.

The 1.4.4 plugin ZIP includes the Markdown guides, remediation record, this folder's index/verification/manifest and companion examples so their documentation links resolve. The fourteen DOCX editions remain in the separate documentation ZIP and source; DOCX links and manifest file entries refer to that full documentation bundle.

## Historical version 1.4.0 review

Reviewed **10 October 2026** against the **WooCommerce Invoice Printer 1.4.0** working tree, based on commit `c894eb05d2120a4bba09aa3500fe2842a0e48d74`.

## Review scope

The guides were checked against source, Composer requirements, all 14 translation catalogs, release configuration and test entry points. Review includes template/data/provider contracts, protected REST/capabilities, confirmed-payment eligibility, event/reconciliation cutoff, preview tokens, document-specific confirmation, fulfillment revisions, opaque references, scanning, signed export intent/receipt/retention, migration and uninstall. Structural graph exploration was supplemented with source reads for templates and coverage gaps.

Version 1.4.0 separates the WordPress host from the printer computer. Its outbound HTTPS pull agent supports Windows with SumatraPDF and Linux/macOS with CUPS `lp`; the website needs neither printer software nor shell access. Shared hosting, VPS and dedicated hosting use the same PHP plugin, subject to the documented PHP/WooCommerce, database, HTTPS REST and Application Password requirements. A powered, awake printer computer must run the agent for unattended output. Direct HTTPS CUPS and browser printing remain documented alternatives.

Templates are PHP files registered by a companion plugin; the settings screen has no HTML/PHP editor or theme override. Registering a push provider alone does not add it to the built-in UI/REST selection. The built-in agent uses a separate claim/start/receipt protocol. Submitted status indicates spooler acceptance or browser preparation, never proof of paper output.

## Completed checks

- All **14 DOCX editions** match the locale catalogs declared in `scripts/check-translations.py`. They cover **13 languages**, including separate Portuguese editions for Portugal and Brazil.
- Final package/XML checks cover release/date/hook/route/DTO references, nine tables, and Persian/Arabic RTL paragraphs with nine mirrored tables. Technical code passages remain LTR. No authoring source directory was recreated.
- Every final DOCX was rendered through the documents skill's canonical bundled LibreOffice/Poppler renderer. All **149 version 1.4.0 pages** were visually inspected at original resolution for shaping, fonts, direction, table flow, code wrapping and clipping. Table headers repeat, borders remain visible, and rows stay together. All editions include translated cross-platform setup, pairing, operating requirements, recovery and testing details. Persian and Arabic have ten pages each.
- Chinese and Japanese rendering used a locally installed CJK-capable font in an isolated temporary renderer profile. No font, PDF or PNG intermediates are distributed in this folder.
- Both companion-plugin PHP files passed `php -l`.
- A standalone check using the project's test bootstrap helpers exercised template registration, empty sample data, an optional order-meta field, HTML escaping and both LTR and RTL invoice rendering.
- Current PHP suites passed **318 tests / 1,160 assertions** on PHP 8.5.8 and PHP 7.4.33. JavaScript passed **23 tests**; the local agent passed **9 unit tests**. Composer validation, PHP/JavaScript syntax and all fourteen **332-message** catalogs passed, including plural and GNU format checks.
- Real WordPress 6.6.2 / WooCommerce 9.0.2 / PHP 8.3 / MariaDB 10.11 testing passed **20 HTTPS agent assertions** with real Application Password authentication and a Linux virtual CUPS printer. Coverage includes route restrictions, PDF checksum, expired claim recovery, stale-token rejection, one-use start, idempotent/conflicting receipts, automation cancellation, two-copy dispatch and crash recovery without printer replay. Another **11 real-database assertions** verify binary route matching, lease expiry, user/token isolation and valid late receipts.
- Earlier version 1.3.0 evidence includes HPOS/legacy remediation and migration checks, 98 localized PDFs, digital barcode decoding, browser-printing HTTP checks and direct HTTPS CUPS acceptance. Historical counts are distinguished from current results in [REMEDIATION_TRACEABILITY.md](../REMEDIATION_TRACEABILITY.md).
- The actual 1.4.0 production ZIP passed fresh-prefix installation/activation, schema/capability/role/index checks, all seven PDF layouts and exact locked production-dependency checks. Populated 1.1.2 and 1.3.0 CUPS upgrade fixtures passed against the packaged source, preserving historical job/confirmation values and the existing CUPS setup/cutoff.

Rendered page counts and final DOCX checksums are in [manifest.json](manifest.json). Page counts can change with the Word/LibreOffice version or available fonts.

## Practical limits

No native Windows printer, physical printer/scanner, production store, multisite or hosting-provider matrix was tested. Windows command construction is tested; native SumatraPDF/driver/paper acceptance remains a deployment check. The Windows/Linux/macOS Python CI workflow is configured but was not run remotely. Linux end-to-end testing used a virtual printer. HTTPS REST and Application Password authentication must be permitted by the host; a provider that blocks them needs configuration changes before unattended printing can work.

Translations have not had independent native-speaker editorial review. The documents were checked in the headless renderer; rendering in desktop Word can vary with font availability. Before distributing a revised edition, follow the editing and visual-review instructions in [README.md](README.md).

The version 1.4.0 documentation folder was excluded from the production plugin ZIP; its separate documentation ZIP was provided. That plugin ZIP included the local-agent source and operator setup guides. See [cross-platform printing](../docs/cross-platform-printing.md), [direct CUPS setup and historical migration](../docs/open-source-printing.md), and the [remediation record](../REMEDIATION_TRACEABILITY.md) for deployment requirements and verification limits.
