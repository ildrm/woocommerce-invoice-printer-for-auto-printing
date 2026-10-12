<?php defined( 'ABSPATH' ) || exit;
$font = in_array( $presentation['invoice_font'] ?? '', array( 'dejavusans', 'dejavuserif', 'dejavusansmono' ), true ) ? $presentation['invoice_font'] : 'dejavusans';
$families = array( 'dejavusans' => 'DejaVu Sans,sans-serif', 'dejavuserif' => 'DejaVu Serif,serif', 'dejavusansmono' => 'DejaVu Sans Mono,monospace' );
$font_size = max( 6, min( 16, (int) ( $presentation['invoice_font_size'] ?? 10 ) ) );
$layout = array( 'single_page' => $show( 'single_page' ), 'thermal_auto_height' => $show( 'thermal_auto_height' ) );
?>
<meta name="wcip-layout" content="<?php echo esc_attr( wp_json_encode( $layout ) ); ?>">
<style>
body { font-family:<?php echo esc_attr( $families[ $font ] ); ?>; font-size:<?php echo (int) $font_size; ?>pt; line-height:<?php echo $show( 'compact_layout' ) ? '1.15' : '1.3'; ?> }
table { font-family:<?php echo esc_attr( $families[ $font ] ); ?>; font-size:<?php echo (int) $font_size; ?>pt }
tr,.item,.totals,.invoice-end { page-break-inside:avoid }
p { margin:3px 0 }
.item-meta,.meta,.small,.label { font-size:0.85em }
<?php if ( $show( 'compact_layout' ) ) : ?>
td,th { padding:2px 3px } .layout td { padding:2px 3px }
.header,.top,.addresses,.grid { margin-bottom:5px }
.addresses td { padding:4px } .addresses h2 { margin:0 0 3px }
.brand { line-height:1.15 } .brand h2 { margin:3px 0 }
.title h1,.top h1 { font-size:1.6em }
.totals,.bottom,.notes,.footer { margin-top:5px }
.notes { padding-top:3px } .rule { margin:3px 0 }
.pair td { padding:0.5mm 0 } .item { padding:2px 0 }
<?php endif; ?>
</style>
