<?php
if( ! defined ('WP_UNINSTALL_PLUGIN') )
exit();
function wc_sbp_delete_plugin(){
	global $wpdb;

	//delete option settings
	$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'woocommerce\_sbp\_%';");
	$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'wc-sbp-%';");
}

wc_sbp_delete_plugin();
