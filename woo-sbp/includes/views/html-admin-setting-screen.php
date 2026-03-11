<?php global $woocommerce; ?>
<div class="wrap">
	<h2><?php echo  __( 'General Setting', 'woo-sbp' );?></h2>
	<div class="wc-sbp-settings metabox-holder">
		<div class="wc-sbp-sidebar">
			<div class="wc-sbp-credits">
				<h3 class="hndle"><?php echo __( 'SBPS for WooCommerce', 'woo-sbp' ) . ' ' . WC_SBP_VERSION;?></h3>
				<div class="inside">
					<!-- <hr />-->
					<?php $this->jp4wc_plugin->jp4wc_update_notice();?>
					<hr />
				</div>
			</div>
		</div>
		<form id="wc-sbp-setting-form" method="post" action="" enctype="multipart/form-data">
			<div id="main-sortables" class="meta-box-sortables ui-sortable">
<?php
	//Display Setting Screen
	settings_fields( 'wc_sbp' );
	$this->do_settings_sections( 'wc_sbp' );
?>
			<p class="submit">
<?php
	submit_button( '', 'primary', 'save_wc_sbp_options', false );
?>
			</p>
			</div>
		</form>
		<div class="clear"></div>
	</div>
	<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function ($) {
		// close postboxes that should be closed
		$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
		// postboxes setup
		postboxes.add_postbox_toggles('wc_sbp');
	});
	//]]>
	</script>
</div>
