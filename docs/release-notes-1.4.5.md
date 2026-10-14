# WooCommerce Invoice Printer 1.4.5

Release prepared 14 October 2026 from the supplied 1.4.5 package.

- Remove the automatic WordPress administrator-email fallback. Store email is hidden by default and uses only the business email configured in General. Store email and Customer email have independent visibility switches under Templates.
- Show a labeled payment gateway title and saved gateway ID, for example **Credit card (stripe)**. The Templates switch can hide it, independently of the totals breakdown. Missing titles fall back to the gateway ID; missing payment details omit the line. Existing saved show/hide preferences remain effective.
- Prevent hidden payment-method totals from reappearing through the legacy totals fallback.
- Use bundled PDF fonts when another plugin loads mPDF configuration first, including Persian compact/thermal invoices and multiple-copy PDF bundles.
- Split sample-data construction into small methods to avoid a reproducible PHP 7.4 OPcache optimizer crash on repeated sample previews.
- Keep a direct Print/Preview recovery link when a new tab is blocked or closed.
- Roll back failed browser print batches; invalidate unconfirmed remnants on older nontransactional tables while preserving concurrent confirmations/cancellations.
- Tolerate malformed billing-address data, including the recipient billing fallback, and optional payment-presentation failures. Automatic payment eligibility stays strict.
- Add safe WooCommerce failure diagnostics and translated error guidance. Compile all 14 bundled locale catalogs, including the new gateway label and email guidance.
- Verify production dependencies and platform requirements during packaging, including reused vendor directories; support Windows Composer launchers.

## After upgrading

Upload the complete `wc-invoice-printer-1.4.5.zip` through WordPress Plugins and replace the existing plugin. Under **WooCommerce → Invoice Printer → Templates**, clear **Store email** to hide a previously enabled address. Clear **Customer email** if it should also be hidden. Enable **Payment gateway** to display the payment title and gateway ID, then save. Gateway display is enabled when no preference exists; explicitly saved choices are preserved. A previously saved business email remains editable under General.

Plugin version is **1.4.5**. Database schema remains **1.4.3**, capabilities remain **1.4.0**, and provider-retirement migration remains **1.3.0**. The 1.4.4 developer DOCX editions remain applicable with this supplement.

See [the review and verification record](review-1.4.5.md) for the checks run for this branch and their limits. Physical printer/driver behavior still requires a test print on the installation’s printer.
