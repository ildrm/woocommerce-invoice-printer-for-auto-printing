<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html lang="<?php echo esc_attr( \WCInvoicePrinter\I18n\Locale::language_tag() ); ?>" dir="<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?>">
<head><meta charset="utf-8"><style>
@page { margin:4mm }
body { width:<?php echo '58mm' === $template->paper_size ? '50' : '72'; ?>mm; margin:0; color:#000; font:10pt/1.4 DejaVu Sans,sans-serif; direction:<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?> }
.center { text-align:center }
.brand { font-size:15pt; font-weight:bold; line-height:1.5 }
.logo { max-width:42mm; max-height:18mm }
.rule { border:0; border-top:1px dashed #000; margin:7px 0 }
.pair { width:100%; border-collapse:collapse; table-layout:fixed }
.pair td { width:50%; padding:1.2mm 0; vertical-align:top }
.first { text-align:<?php echo $invoice->rtl ? 'right' : 'left'; ?> }
.last { text-align:<?php echo $invoice->rtl ? 'left' : 'right'; ?> }
.item { padding:5px 0; border-bottom:1px dotted #777 }
.item-title { font-weight:bold }
.totals td { padding-top:.7mm; padding-bottom:.7mm }
.grand td { font-size:12pt; font-weight:bold; border-top:1px solid #000; padding-top:1.5mm }
.small { font-size:9pt }
</style><?php include WCIP_PATH . 'templates/shared/presentation.php'; ?></head><body>
<div class="center"><?php include WCIP_PATH . 'templates/shared/store.php'; ?></div><hr class="rule">
<div class="center"><strong><?php esc_html_e( 'INVOICE', 'wc-invoice-printer' ); ?> #<?php echo esc_html( $invoice->order['number'] ); ?></strong></div>
<?php if ( $show( 'show_order_date' ) ) : ?><div><?php echo esc_html( $invoice->order['date'] ); ?></div><?php endif; ?>
<?php if ( $show( 'show_order_status' ) ) : ?><div><?php echo esc_html( $invoice->order['status'] ); ?></div><?php endif; ?>
<?php include WCIP_PATH . 'templates/shared/customer.php'; ?>
<hr class="rule">
<?php foreach ( $invoice->items as $item ) : ?><div class="item">
<div class="item-title"><?php echo esc_html( $item['name'] ); ?></div>
<?php if ( $show( 'show_item_variation' ) && $item['variation'] ) : ?><div class="small"><?php echo esc_html( $item['variation'] ); ?></div><?php endif; ?>
<?php if ( $show( 'show_item_sku' ) && $item['sku'] ) : ?><div class="small"><bdi dir="ltr"><?php echo esc_html( $item['sku'] ); ?></bdi></div><?php endif; ?>
<table class="pair"><tr><td class="first"><bdi dir="ltr"><?php echo esc_html( (string) $item['quantity'] ); ?></bdi><?php if ( $show( 'show_item_unit_price' ) ) : ?> × <?php echo wp_kses_post( $item['unit_price'] ); ?><?php endif; ?></td><td class="last"><strong><?php echo wp_kses_post( $item['total'] ); ?></strong></td></tr></table>
<?php foreach ( array( 'discount' => __( 'Discount', 'wc-invoice-printer' ), 'tax' => __( 'Tax', 'wc-invoice-printer' ) ) as $field => $label ) : if ( $show( 'show_item_' . $field ) ) : ?><div class="small"><?php echo esc_html( $label ); ?>: <?php echo wp_kses_post( $item[ $field ] ); ?></div><?php endif; endforeach; ?>
</div><?php endforeach; ?>
<?php if ( $show( 'show_price_note' ) ) : ?><p class="small"><?php esc_html_e( 'Unit prices exclude tax; line totals include discounts and tax.', 'wc-invoice-printer' ); ?></p><?php endif; ?>
<div class="invoice-end"><table class="pair totals">
<?php foreach ( $totals as $index => $total ) : ?><tr class="<?php echo 'order_total' === ( $total['key'] ?? '' ) || ( ! isset( $total['key'] ) && count( $totals ) - 1 === $index ) ? 'grand' : ''; ?>"><td class="first"><?php echo esc_html( $total['label'] ); ?></td><td class="last"><?php echo wp_kses_post( $total['value'] ); ?></td></tr><?php endforeach; ?>
</table>
<?php if ( $show( 'show_customer_note' ) && $invoice->fulfillment['note'] ) : ?><div><strong><?php esc_html_e( 'Note:', 'wc-invoice-printer' ); ?></strong> <?php echo nl2br( esc_html( $invoice->fulfillment['note'] ) ); ?></div><?php endif; ?>
<?php if ( $show( 'show_footer' ) ) : ?><hr class="rule"><div class="center"><?php esc_html_e( 'Thank you for your order.', 'wc-invoice-printer' ); ?></div><?php endif; ?>
<?php include WCIP_PATH . 'templates/shared/code.php'; ?>
</div></body></html>
