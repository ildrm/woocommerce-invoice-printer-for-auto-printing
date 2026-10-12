# Admin UI specification

All screens live under WooCommerce → Invoice Printer and share compact tab navigation.

| Screen | Goal | Primary action | Secondary actions | Empty/loading/error/success |
|---|---|---|---|---|
| General | Set invoice identity and defaults | Save changes | Choose logo/display fields | Inline save notice and field errors |
| Templates | Compare layouts and control invoice fields | Save template/display options | Font/size, compact spacing, one-page/thermal-height PDF, preview/RTL preview | Labeled independent switches; accessible notice on failure/success |
| Automatic Printing | Configure the paid-order workflow | Enable and save | Select template/copies | Dependencies hidden while disabled; setup summary |
| Printers | Pair the local agent or connect CUPS | Save pairing/printer | Agent last-seen timestamp; CUPS connection, refresh and test | Explicit pairing/configuration/loading/error/submitted states |
| Print Jobs | Resolve operational issues | View details | Safe retry, reprint, cancel queued | Explanatory empty state; paginated/filterable table |
| Order print panel | Print with minimal clicks | Print | Preview, browser/CUPS/agent destination, template/copies | Busy controls, queued versus physical-confirmation wording, inline errors |

The layout uses semantic controls, visible focus, text-bearing statuses, logical CSS properties for RTL, and collapses to one column below the WordPress mobile breakpoint.

## Implemented 1.2 controls

Documents & shipping exposes country-aware recipient fallback, trusted metadata mapping, paid date/phone/sender visibility, timezone and optional fulfillment stage after confirmation. Reconciliation has UTC date inputs, dry-run rows, explicit selection/authorization and interval/policy settings. Barcode & integrations contains keyboard-scanner lookup, a semantic item table, separate stage changes and privileged exports; credentials remain masked. Diagnostics exposes bounded operational JSON for support. Buttons announce pending, success and failure through a polite live region. A failed scan clears previous order actions. Code format guidance recommends QR for 58 mm.

## Version 1.4.4 presentation controls

Templates exposes business, customer, order/delivery, item and closing-content visibility. Email, billing/store addresses and other optional fields are off when absent from saved settings. Store name and shipping address start visible; customer name and role are hidden; explicit saved choices remain effective. Existing Documents & shipping options for mapped recipient fields, billing fallback, paid date/phone/sender and timezone remain available. Fallback and sender display start off when no preference was saved.

Font controls offer bundled DejaVu Sans, Serif or Sans Mono, with 6–16 pt size. Compact layout, one-page PDF fitting and thermal content-height PDF are independent, default-off controls. Help text explains the 60% minimum scale, the 2,000 mm thermal bound, explicit failure without clipping, browser/driver paper settings and driver-managed cutting. Preview and tracked browser printing use an authenticated PDF viewer for enabled page modes, with explicit physical confirmation outside the viewer. See [operator instructions](invoice-layout.md).

Orders filters and text distinguish ineligible, missing job, queued, printing, submitted/awaiting confirmation, unknown, failed and explicitly printed. A prior confirmed invoice remains printed when a later manual attempt fails. Label and packing-list confirmation are separate. Controls have labels and keyboard support; a formal assistive-technology/WCAG certification has not been performed.
