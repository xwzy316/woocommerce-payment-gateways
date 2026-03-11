<div class="wrap woocommerce">
    <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=jp4wc-sbp-output') ?>" class="nav-tab <?php echo ($tab == 'setting') ? 'nav-tab-active' : ''; ?>"><?php echo __( 'Setting', 'woo-sbp' )?></a><a href="<?php echo admin_url('admin.php?page=jp4wc-sbp-output&tab=info') ?>" class="nav-tab <?php echo ($tab == 'info') ? 'nav-tab-active' : ''; ?>"><?php echo __( 'Infomations', 'woo-sbp' )?></a>
    </h2>
	<?php
		switch ($tab) {
			case "setting" :
				$this->admin_sbp_setting_page();
			break;
			default :
				$this->admin_sbp_info_page();
			break;
		}
	?>
</div>
