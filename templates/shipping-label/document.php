<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html lang="<?php echo esc_attr( \WCInvoicePrinter\I18n\Locale::language_tag() ); ?>" dir="<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?>">
<head><meta charset="utf-8"><style>
body { margin:0; color:#000; font:14px/1.4 DejaVu Sans,sans-serif; direction:<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?> }
h1 { font-size:20px } .reference { font-size:18px; font-weight:bold } .contact { font-size:16px }
img { max-width:100%; height:auto } p { margin:10px 0 }
</style><?php include WCIP_PATH . 'templates/shared/presentation.php'; ?></head><body>
<h1><?php esc_html_e( 'Shipping label', 'wc-invoice-printer' ); ?></h1>
<?php if ( $invoice->fulfillment['label_show_sender'] ?? false ) : ?><p><?php if ( $show( 'show_store_name' ) ) { echo esc_html( $invoice->store['name'] ); } ?><?php if ( $show( 'show_store_address' ) ) : ?><br><?php echo nl2br( esc_html( $invoice->store['address'] ) ); ?><?php endif; ?></p><?php endif; ?>
<p class="reference"><bdi dir="ltr">#<?php echo esc_html( $invoice->order['number'] ); ?></bdi></p>
<?php if ( $show( 'show_shipping_address' ) ) : ?><p><strong><?php esc_html_e( 'Recipient', 'wc-invoice-printer' ); ?></strong><br><?php echo nl2br( esc_html( $invoice->customer['recipient']['formatted_address'] ?? $invoice->customer['shipping_address'] ) ); ?></p><?php endif; ?>
<?php if ( $show( 'show_customer_role' ) && ! empty( $invoice->customer['role'] ) ) : ?><p><?php esc_html_e( 'Customer role', 'wc-invoice-printer' ); ?>: <?php echo esc_html( $invoice->customer['role'] ); ?></p><?php endif; ?>
<?php include WCIP_PATH . 'templates/shared/details.php'; ?>
<?php if ( $show( 'show_shipping_method' ) ) : ?><p><?php echo esc_html( $invoice->fulfillment['shipping_method'] ); ?></p><?php endif; ?>
<?php if ( $show( 'show_customer_note' ) && $invoice->fulfillment['note'] ) : ?><p><?php echo nl2br( esc_html( $invoice->fulfillment['note'] ) ); ?></p><?php endif; ?>
<?php include WCIP_PATH . 'templates/shared/code.php'; ?>
</body></html>
