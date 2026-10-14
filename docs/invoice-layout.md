# Invoice display and paper layout

Version 1.4.5 retains field switches and typography controls under **WooCommerce → Invoice Printer → Templates**. Changes apply to bundled invoice, packing-list and shipping-label layouts, sample previews, browser documents and newly generated PDFs. They change presentation rather than order data, payment, saved amounts or previously printed paper. Custom companion templates must adopt the presentation helpers to follow these controls.

## Defaults and field switches

New or previously absent visibility settings show the store name and only the shipping address in the customer block. Payment gateway is also visible in invoices when its preference is absent. Customer name, customer role, customer/store email, billing/store addresses and other optional details are hidden. Existing explicitly saved options remain effective; review your saved settings after upgrading. Billing fallback and sender details are off when no preference was saved. A missing shipping address does not silently add a billing address unless you enable fallback.

The invoice reference, item names, quantities, payable item amounts and final order total remain available in invoice layouts. Packing lists and shipping labels continue to omit prices. Hiding a breakdown does not change the payable amount.

| Group | Independently selectable fields |
| --- | --- |
| Business | Name, logo, details, phone, email, store address |
| Customer | Name, company, current account role, shipping address, billing address, phone, email |
| Order and delivery | Created date, status, payment gateway, shipping method, paid date, recipient phone, mapped recipient extras, customer note |
| Items | SKU, public variation metadata, unit price, discount, tax |
| Closing information | Price explanation, totals breakdown, footer |

Controls hide the corresponding labels and empty wrappers as well as their values. Relevant controls apply where a template includes the field. The footer switch controls the translated thank-you text; business contacts have separate switches. The independent barcode/QR and shipping-label sender options remain in their existing sections.

**Store email** and **Customer email** are independent switches. Clear **Store email** under Templates to hide the business contact address; clear **Customer email** to hide the order’s billing email. Set the business contact address under **General**. The WordPress administrator email is no longer used as an automatic default. Previously saved business addresses and visibility choices remain effective, so clear the Store email checkbox after upgrading if an address was previously enabled.

**Payment gateway** uses the existing `show_payment_method` setting. It prints the order’s saved payment title and gateway ID, for example **Credit card (stripe)**. If the title is missing, the gateway ID still appears; if both are absent, the whole line is omitted. Clear the switch to hide the gateway line and WooCommerce payment-method total rows. The switch works independently of the totals breakdown. Existing saved false values remain hidden; enable the switch after upgrading when needed. Showing a gateway does not claim that the order is paid. Labels and packing lists continue to omit payment details.

The customer role is the localized role name of the currently linked WordPress account; it is not an order-time snapshot. Multiple roles can appear. Orders without a linked customer account show **Guest**. Role names and stored business/customer/product text are escaped as text.

**Documents & shipping** retains recipient mapping, billing fallback, label sender and timezone settings. Recipient addresses use shipping components first, explicitly mapped metadata next and enabled billing fallback last. Enable billing fallback only when that is your intended delivery policy. Customer billing address visibility and recipient fallback are separate choices.

## Fonts and compact spacing

Choose **DejaVu Sans**, **DejaVu Serif** or **DejaVu Sans Mono** and a size from **6 to 16 pt**; the default is DejaVu Sans at 10 pt. mPDF retains automatic script/language font fallback for Persian/Arabic, Hindi, Chinese/Japanese and other scripts. Browser output uses the selected font family when available on the printing computer and falls back to its installed fonts.

**Compact layout** reduces margins between sections and item cells without removing enabled content. It is off by default. Use the existing `compact` template for a dense invoice style, or enable compact spacing with another bundled format. Preview an actual order with your longest item names, address and optional note before accepting a smaller font.

## Keep an invoice on one sheet

**Fit each invoice on one sheet** is off by default. When enabled, PDF generation renders the complete content and uniformly scales it onto one fixed-size page, with a minimum scale of **60%**. It checks the rendered PDF page count. If the invoice cannot fit within that bound, generation fails clearly instead of truncating items, totals or notes. Reduce optional fields, use compact spacing or select larger paper and retry. Scaling changes the effective printed font size along with the rest of the document.

Totals and closing sections stay together when their combined height fits the page. An arbitrarily long order cannot always fit on fixed A4/A5/A6 paper at a readable size. The one-page guarantee applies to successfully generated PDFs with this option enabled. Browser preview/printing uses an authenticated PDF viewer for either page-fitting option; ordinary output remains HTML. Confirmation remains available outside the viewer. Browser print dialogs and drivers control scaling, media and final physical pagination, so select matching paper and inspect the preview.

**End thermal paper after the invoice content** is also off by default. For `thermal` and `thermal58` PDFs, it creates a single roll page at the selected 80/58 mm width and sizes its height around the complete content, up to **2,000 mm**. Font size and width are preserved. When off, thermal PDFs use 297 mm page height and can paginate. Content-height mode uses a longer sheet rather than omitting information. Content beyond its limit fails clearly. Your printer must accept the generated custom page size; browser/driver roll length remains driver-controlled.

The plugin sends PDFs through browser printing, the local agent or CUPS. These paths do not provide a portable raw cutter command. Configure a supported printer driver's cut-at-end setting to cut after the document; no plugin cutter setting or hardware-cut guarantee is introduced. Content-height PDF pages help place the document end near the last invoice information on drivers that honor custom roll sizes.

## Extension contract

`SettingsRepository::presentation()` supplies normalized font/layout options and flat `show_*` booleans. `InvoiceData->fulfillment['presentation']` carries these values to rendering. `HtmlRenderer` exposes `$presentation`, a `$show( $key )` visibility closure and filtered `$totals` to templates. Include `templates/shared/presentation.php`, `store.php`, `customer.php` and `details.php` as appropriate rather than printing optional model fields unconditionally. The normalized presentation array is flat; it has no nested `visibility` key. The final amount is identified by `order_total`; payment-method total rows follow their independent switch. A custom template remains trusted PHP; visibility controls are not an access-control boundary, and the full authorized invoice model remains available to extensions.

Check default hiding, explicit enable/disable, escaping, multilingual text, totals and one-page failure behavior when adapting custom templates. See [architecture](architecture.md), [template example](../system-development/README.md) and [1.4.4 review](review-1.4.4.md).
