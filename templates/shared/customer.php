<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( $show( 'show_customer_name' ) && $invoice->customer['name'] ) : ?><div><strong><?php echo esc_html( $invoice->customer['name'] ); ?></strong></div><?php endif; ?>
<?php if ( $show( 'show_customer_role' ) && ! empty( $invoice->customer['role'] ) ) : ?><div><?php esc_html_e( 'Customer role', 'wc-invoice-printer' ); ?>: <?php echo esc_html( $invoice->customer['role'] ); ?></div><?php endif; ?>
<?php if ( $show( 'show_customer_company' ) && $invoice->customer['company'] ) : ?><div><?php echo esc_html( $invoice->customer['company'] ); ?></div><?php endif; ?>
<?php if ( $show( 'show_billing_address' ) && $invoice->customer['billing_address'] ) : ?><div><strong><?php esc_html_e( 'Bill to', 'wc-invoice-printer' ); ?>:</strong> <?php echo nl2br( esc_html( $invoice->customer['billing_address'] ) ); ?></div><?php endif; ?>
<?php if ( $show( 'show_shipping_address' ) && $invoice->customer['shipping_address'] ) : ?><div><strong><?php esc_html_e( 'Shipping address', 'wc-invoice-printer' ); ?>:</strong> <?php echo nl2br( esc_html( $invoice->customer['shipping_address'] ) ); ?></div><?php endif; ?>
<?php if ( $show( 'show_customer_phone' ) && $invoice->customer['phone'] ) : ?><div><bdi dir="ltr"><?php echo esc_html( $invoice->customer['phone'] ); ?></bdi></div><?php endif; ?>
<?php if ( $show( 'show_customer_email' ) && $invoice->customer['email'] ) : ?><div><bdi dir="ltr"><?php echo esc_html( $invoice->customer['email'] ); ?></bdi></div><?php endif; ?>
<?php
$payment_title = (string) ( $invoice->fulfillment['payment_method'] ?? '' );
$payment_id = (string) ( $invoice->fulfillment['payment_method_id'] ?? '' );
if ( $show( 'show_payment_method' ) && ( '' !== $payment_title || '' !== $payment_id ) ) : ?>
<div><strong><?php esc_html_e( 'Payment gateway', 'wc-invoice-printer' ); ?>:</strong> <?php echo esc_html( $payment_title ?: $payment_id ); ?><?php if ( '' !== $payment_title && '' !== $payment_id && $payment_title !== $payment_id ) : ?> (<bdi dir="ltr"><?php echo esc_html( $payment_id ); ?></bdi>)<?php endif; ?></div>
<?php endif; ?>
<?php if ( $show( 'show_shipping_method' ) && $invoice->fulfillment['shipping_method'] ) : ?><div><?php echo esc_html( $invoice->fulfillment['shipping_method'] ); ?></div><?php endif; ?>
<?php include WCIP_PATH . 'templates/shared/details.php'; ?>
