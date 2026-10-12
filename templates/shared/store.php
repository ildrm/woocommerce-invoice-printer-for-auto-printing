<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( $show( 'show_store_logo' ) && $invoice->store['logo_data_uri'] ) : ?><img class="logo" src="<?php echo esc_attr( $invoice->store['logo_data_uri'] ); ?>" alt=""><?php endif; ?>
<?php if ( $show( 'show_store_name' ) && $invoice->store['name'] ) : ?><div class="brand"><strong><?php echo esc_html( $invoice->store['name'] ); ?></strong></div><?php endif; ?>
<?php if ( $show( 'show_store_details' ) && $invoice->store['details'] ) : ?><div><?php echo nl2br( esc_html( $invoice->store['details'] ) ); ?></div><?php endif; ?>
<?php if ( $show( 'show_store_address' ) && $invoice->store['address'] ) : ?><div><?php echo nl2br( esc_html( $invoice->store['address'] ) ); ?></div><?php endif; ?>
<?php if ( $show( 'show_store_phone' ) && $invoice->store['phone'] ) : ?><div><bdi dir="ltr"><?php echo esc_html( $invoice->store['phone'] ); ?></bdi></div><?php endif; ?>
<?php if ( $show( 'show_store_email' ) && $invoice->store['email'] ) : ?><div><bdi dir="ltr"><?php echo esc_html( $invoice->store['email'] ); ?></bdi></div><?php endif; ?>
