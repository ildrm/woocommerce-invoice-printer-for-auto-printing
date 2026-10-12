<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html lang="<?php echo esc_attr( \WCInvoicePrinter\I18n\Locale::language_tag() ); ?>" dir="<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?>">
<head><meta charset="utf-8"><style>
@page { margin:8mm }
body { margin:0; color:#202124; font:10px/1.35 DejaVu Sans,sans-serif; direction:<?php echo $invoice->rtl ? 'rtl' : 'ltr'; ?> }
table { border-collapse:collapse; width:100% }
thead { display:table-header-group }
th { border-bottom:2px solid #2c3338; text-align:<?php echo $invoice->rtl ? 'right' : 'left'; ?>; padding:5px 4px }
td { border-bottom:1px solid #ddd; padding:5px 4px; vertical-align:top }
.layout td { border:0 }
.top { border-bottom:1px solid #8c8f94; margin-bottom:10px }
.top h1 { font-size:18px; margin:0 }
.brand { font-size:13px; font-weight:bold }
.grid { margin-bottom:10px }
.grid td { width:50% }
.label { font-size:8px; font-weight:bold; color:#646970 }
.num { text-align:<?php echo $invoice->rtl ? 'left' : 'right'; ?>; white-space:nowrap }
.meta { font-size:8px; color:#646970 }
.bottom { margin-top:10px }
.note { width:60% }
.totals td { padding:3px 4px }
.totals .grand { font-size:12px; font-weight:bold }
.logo { max-width:100px; max-height:36px }
</style><?php include WCIP_PATH . 'templates/shared/presentation.php'; ?></head>
<body>
<table class="layout header top"><tr><td><?php include WCIP_PATH . 'templates/shared/store.php'; ?></td>
<td class="title num"><h1><?php esc_html_e( "Invoice", 'wc-invoice-printer' ); ?></h1><strong>#<?php echo esc_html( $invoice->order['number'] ); ?></strong>
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
