<?php defined( 'ABSPATH' ) || exit; ?>
<?php $compact_paper = 'A5' === $template->paper_size; ?>
<!doctype html>
<html lang="<?php echo esc_attr( \WCInvoicePrinter\I18n\Locale::language_tag() ); ?>" dir="<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?>">
<head><meta charset="utf-8"><style>
@page { margin:12mm }
body { margin:0; color:#1d2327; font:12px/1.5 DejaVu Sans,sans-serif; direction:<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?> }
table { width:100%; border-collapse:collapse }
thead { display:table-header-group }
th { background:#1d2327; color:#fff; font-size:10px; text-align:<?php echo $invoice->rtl ? 'right' : 'left'; ?>; padding:9px 6px }
td { border-bottom:1px solid #dcdcde; padding:10px 6px; vertical-align:top }
.num,.title { text-align:<?php echo $invoice->rtl ? 'left' : 'right'; ?> }
.num { white-space:nowrap }
.layout td { border:0; padding:0 0 18px }
.header { border-bottom:3px solid #1d2327; margin-bottom:24px }
.brand { width:60% }
.logo { max-width:160px; max-height:64px }
.title h1 { font-size:28px; margin:0 }
.meta,.item-meta { color:#646970; font-size:10px }
.addresses { margin-bottom:24px }
.addresses td { width:50%; background:#f6f7f7; padding:14px }
.addresses h2 { font-size:11px; margin:0 0 8px }
.item-name { font-weight:bold }
.totals { width:45%; margin-top:18px; margin-<?php echo $invoice->rtl ? 'right' : 'left'; ?>:auto }
.totals td { padding:5px 7px }
.totals .grand td { border-top:2px solid #1d2327; font-size:14px }
.notes { margin-top:28px; padding-top:14px; border-top:1px solid #c3c4c7 }
.footer { margin-top:24px; text-align:center; color:#646970 }
</style><?php include WCIP_PATH . 'templates/shared/presentation.php'; ?></head>
<body>
<table class="layout header top"><tr><td><?php include WCIP_PATH . 'templates/shared/store.php'; ?></td>
<td class="title num"><h1><?php esc_html_e( "INVOICE", 'wc-invoice-printer' ); ?></h1><strong>#<?php echo esc_html( $invoice->order['number'] ); ?></strong>
<?php if ( $show( 'show_order_date' ) ) : ?><div class="meta"><?php echo esc_html( $invoice->order['date'] ); ?></div><?php endif; ?>
<?php if ( $show( 'show_order_status' ) ) : ?><div class="meta"><?php echo esc_html( $invoice->order['status'] ); ?></div><?php endif; ?>
</td></tr></table>
<div class="addresses grid"><?php include WCIP_PATH . 'templates/shared/customer.php'; ?></div>
<table class="items"><thead><tr>
<th><?php esc_html_e( 'Item', 'wc-invoice-printer' ); ?></th>
<?php if ( $show( 'show_item_sku' ) ) : ?><th><?php esc_html_e( 'SKU', 'wc-invoice-printer' ); ?></th><?php endif; ?>
<th class="num"><?php esc_html_e( 'Qty', 'wc-invoice-printer' ); ?></th>
<?php if ( $show( 'show_item_unit_price' ) ) : ?><th class="num"><?php esc_html_e( 'Unit', 'wc-invoice-printer' ); ?></th><?php endif; ?>
<?php if ( $show( 'show_item_discount' ) ) : ?><th class="num"><?php esc_html_e( 'Discount', 'wc-invoice-printer' ); ?></th><?php endif; ?>
<?php if ( $show( 'show_item_tax' ) ) : ?><th class="num"><?php esc_html_e( 'Tax', 'wc-invoice-printer' ); ?></th><?php endif; ?>
<th class="num"><?php esc_html_e( 'Total', 'wc-invoice-printer' ); ?></th>
</tr></thead><tbody>
<?php foreach ( $invoice->items as $item ) : ?><tr>
<td><div class="item-name"><strong><?php echo esc_html( $item['name'] ); ?></strong></div>
<?php if ( $show( 'show_item_variation' ) && $item['variation'] ) : ?><div class="item-meta"><?php echo esc_html( $item['variation'] ); ?></div><?php endif; ?></td>
<?php if ( $show( 'show_item_sku' ) ) : ?><td><bdi dir="ltr"><?php echo esc_html( $item['sku'] ); ?></bdi></td><?php endif; ?>
<td class="num"><bdi dir="ltr"><?php echo esc_html( (string) $item['quantity'] ); ?></bdi></td>
<?php foreach ( array( 'unit_price', 'discount', 'tax' ) as $field ) : if ( $show( 'show_item_' . $field ) ) : ?><td class="num"><?php echo wp_kses_post( $item[ $field ] ); ?></td><?php endif; endforeach; ?>
<td class="num"><?php echo wp_kses_post( $item['total'] ); ?></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php if ( $show( 'show_price_note' ) ) : ?><p class="meta"><?php esc_html_e( 'Unit prices and discounts exclude tax; line totals include tax.', 'wc-invoice-printer' ); ?></p><?php endif; ?>
<div class="invoice-end">
<table class="totals">
<?php foreach ( $totals as $index => $total ) : ?><tr class="<?php echo 'order_total' === ( $total['key'] ?? '' ) || ( ! isset( $total['key'] ) && count( $totals ) - 1 === $index ) ? 'grand' : ''; ?>"><td><?php echo esc_html( $total['label'] ); ?></td><td class="num"><?php echo wp_kses_post( $total['value'] ); ?></td></tr><?php endforeach; ?>
</table>
<?php if ( $show( 'show_customer_note' ) && $invoice->fulfillment['note'] ) : ?><div class="notes"><strong><?php esc_html_e( 'Customer note', 'wc-invoice-printer' ); ?></strong><br><?php echo nl2br( esc_html( $invoice->fulfillment['note'] ) ); ?></div><?php endif; ?>
<?php if ( $show( 'show_footer' ) ) : ?><div class="footer"><?php esc_html_e( 'Thank you for your order.', 'wc-invoice-printer' ); ?></div><?php endif; ?>
<?php include WCIP_PATH . 'templates/shared/code.php'; ?>
</div></body></html>
