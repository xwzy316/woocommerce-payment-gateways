<?php
/**
 * Plugin Name: Japanized for WooCommerce
 * Framework Version : 2.0.3
 * Author: xwzy1130
 * Author URI: https://www.m6shop.com/dzkf/zfcj
 *
 * @category JP4WC_Framework
 * @author xwzy1130 
 */
 
namespace ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_3;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!class_exists('\\ArtisanWorkshop\\WooCommerce\\PluginFramework\\v2_0_2\\JP4WC_Plugin')):
class JP4WC_Plugin {

    /**
     * create checkbox input form to compliant Setting API.
     *
     * @param array $args
     * @param string $description
     * @param string $option_key
     */
    public function jp4wc_setting_input_checkbox($args, $description, $option_key){
        echo '<label for="'.$args['label_for'].'">';
        $options = get_option( $option_key );

	// 确保 $options 是数组
        if (!is_array($options)) {
                $options = array(); // 如果不是数组，则初始化为空数组
        }

        // 检查并设置默认值
        $value = isset($options[$args['slug']]) ? $options[$args['slug']] : 0;
        ?>
	<input type="checkbox" name="wc_sbp_options[<?php echo esc_attr($args['slug']);?>]" value="1" <?php checked( $value, 1 ); ?>
        <?php if(isset($description))echo $description;
        echo '</label>';
    }
    /**
     * create input text form to compliant Setting API.
     *
     * @param array $args
     * @param string $description
     * @param string $option_key
     * @param string $default_value
     */
    public function jp4wc_setting_input_text($args, $description, $option_key, $default_value = null){
        $options = get_option( $option_key );
        ?>
        <label for="<?php echo esc_attr( $args['label_for'] ); ?>">
            <input type="text" name="wc_sbp_options[<?php echo esc_attr($args['slug']);?>]"  size="<?php echo esc_attr( $args['text_num'] ); ?>" value="<?php echo isset( $options[ $args['slug'] ] ) ? ( esc_html($options[ $args['slug'] ]) ) : ( $default_value ); ?>" ><br />
            <?php echo $description; ?>
        </label>
        <?php
    }

    /**
     * allowed html tag setting.
     *
     * @var array
     */
    public $allowed_html = array(
        'a' => array( 'href' => array (), 'target' => array(), ),
        'br' => array(),
        'strong' => array(),
        'b' => array(),
    );

    /**
     * create option setting.
     *
     * @param string $slug
     * @param string $prefix
     * @param string $array_name
     *
     * @return array
     */
    public function jp4wc_option_setting($slug, $prefix, $array_name = null){
        $wc4jp_options_setting = null;
        $wc4jp_meta_name = $prefix.$slug;
        if(get_option($wc4jp_meta_name)){
            $wc4jp_options_setting = get_option($wc4jp_meta_name);
        }elseif(get_option($array_name)){
            $setting = get_option($array_name);
            $wc4jp_options_setting = $setting[$slug];
        }
        return $wc4jp_options_setting;
    }

	/**
	 * create checkbox input form.
	 *
     * @param string $slug
     * @param string $description
     * @param string $prefix
     * @param string $array_name
	 */
	public function jp4wc_input_checkbox($slug, $description, $prefix, $array_name = null){
		?>
		<?php if(isset($description))echo '<label for="woocommerce_input_'.esc_attr($slug).'">';
        $wc4jp_options_setting = $this->jp4wc_option_setting($slug, $prefix, $array_name);
			?>
			<input type="checkbox" name="<?php echo esc_attr($slug);?>" value="1" <?php checked( $wc4jp_options_setting, 1 ); ?>>
        <?php if(isset($description))echo wp_kses($description, $this->allowed_html);
		if(isset($description))echo '</label>';
	}
    /**
     * create input select form.
     *
     * @param string $slug
     * @param string $description
     * @param array  $select_options
     * @param string $prefix
     * @param string $array_name
     */
    public function jp4wc_input_select($slug, $description, $select_options, $prefix, $array_name = null){
        ?>
        <label for="woocommerce_input_<?php echo esc_attr($slug);?>">
            <?php
            $wc4jp_options_setting = $this->jp4wc_option_setting($slug, $prefix, $array_name);
            echo '<select name="'.esc_attr($slug).'">';
            foreach($select_options as $key => $value){
                $checked = '';
                if($wc4jp_options_setting == $key){
                    $checked = ' selected="selected"';
                }
                echo '<option value="'.$key.'"'.$checked.'>'.$value.'</option>';
            }
            echo '</select><br />';
            echo wp_kses($description, $this->allowed_html); ?>
            </select>
        </label>
        <?php
    }
	/**
	 * create input text form.
     *
     * @param string $slug
     * @param string $description
     * @param number $num
     * @param string $default_value
     * @param string $prefix
     * @param string $array_name
	 */
	public function jp4wc_input_text($slug, $description, $num, $default_value, $prefix, $array_name = null){
        $wc4jp_options_setting = $this->jp4wc_option_setting($slug, $prefix, $array_name);
        if($wc4jp_options_setting)$default_value = $wc4jp_options_setting;
        ?>
		<label for="woocommerce_input_<?php echo esc_attr($slug);?>">
			<input type="text" name="<?php echo esc_attr($slug);?>"  size="<?php echo esc_attr($num);?>" value="<?php echo esc_attr($default_value); ?>" ><br />
			<?php echo wp_kses($description, $this->allowed_html); ?>
		</label>
		<?php
	}
    /**
     * create input textarea form.
     *
     * @param string $slug
     * @param string $description
     * @param string $default_value
     * @param string $prefix
     * @param array $size_array
     * @param string $array_name
     */
    public function jp4wc_input_textarea($slug, $description, $default_value, $prefix, $size_array = array( 'cols' => 55, 'rows' => 4), $array_name = null){
        $wc4jp_options_setting = $this->jp4wc_option_setting($slug, $prefix, $array_name);
        $size_text ='';
        foreach( $size_array as $key => $value ){
            $size_text .= $key.'="'.$value.'" ';
        }
        if($wc4jp_options_setting)$default_value = $wc4jp_options_setting;
        ?>
        <label for="woocommerce_input_<?php echo esc_attr($slug);?>">
            <textarea name="<?php echo esc_attr($slug);?>" <?php echo $size_text; ?>><?php echo esc_attr($default_value); ?></textarea><br />
            <?php echo wp_kses($description, $this->allowed_html); ?>
        </label>
        <?php
    }
	/**
	 * create input number form.
     *
     * @param string $slug
     * @param string $description
     * @param string $default_value
     * @param string $prefix
     * @param string $array_name
	 */
	public function jp4wc_input_number($slug, $description, $default_value, $prefix, $array_name = null){
        $wc4jp_options_setting = $this->jp4wc_option_setting($slug, $prefix, $array_name);
        if($wc4jp_options_setting)$default_value = $wc4jp_options_setting;
		 ?>
		<label for="woocommerce_input_<?php echo esc_attr($slug);?>">
			<input type="number" name="<?php echo esc_attr($slug);?>" value="<?php echo esc_attr($default_value); ?>" ><br />
			<?php echo wp_kses($description, $this->allowed_html); ?>
		</label>
		<?php
	}
	/**
	 * create input time.
	 *
     * @param string $slug
     * @param string $description
     * @param string $default_value
     * @param string $prefix
	 */
	public function jp4wc_input_time($slug, $description, $default_value, $prefix){
	    ?>
		<label for="woocommerce_input_<?php echo $slug;?>">
		<?php 
			$meta_name = $prefix.$slug;
			if(get_option($meta_name)){
				$options_setting = get_option($meta_name) ;
			}else{
				$options_setting = $default_value;
			}
			?>
			<input type="time" name="<?php echo $slug;?>" value="<?php echo $options_setting; ?>" ><br />
			<?php echo wp_kses($description, $this->allowed_html); ?>
		</label>
	<?php }
	/**
	 * create date time.
	 *
     * @param array $start_date
     * @param array $end_date
     * @param string $description
     * @param string $prefix
	 */
	public function jp4wc_input_date_term($start_date, $end_date, $description, $prefix){
	    ?>
		<label for="woocommerce_input_date_term">
		<?php 
			$start_meta_name = $prefix.$start_date['name'];
			$start_date_value = '';
			if(get_option($start_meta_name)){
				$start_date_value = get_option($start_meta_name) ;
			}
			$end_date_value = '';
			$end_meta_name = $prefix.$end_date['name'];
			if(get_option($end_meta_name)){
				$end_date_value = get_option($end_meta_name) ;
			}
			?>
			<?php echo $start_date['label']; ?><input id="<?php echo $start_date['id'];?>" name="<?php echo $start_date['name'];?>" type="date" value="<?php echo $start_date_value; ?>" > - <?php echo $end_date['label']; ?><input id="<?php echo $end_date['id'];?>" name="<?php echo $end_date['name'];?>" type="date" value="<?php echo $end_date_value; ?>" ><br />
			<?php echo wp_kses($description, $this->allowed_html); ?>
		</label>
	<?php }
	/**
	 * create input text form.
     *
     * @param string $slug
     * @param string $description
	 */
	public function jp4wc_display_message( $slug, $description ){
		 ?>
		<label for="woocommerce_show_<?php echo $slug;?>">
			<?php echo wp_kses($description, $this->allowed_html); ?>
		</label>
		<?php
	}
	/**
	 * create description for check pattern.
     *
     * @param string $title
     *
     * @return string $description
	 */
	public function jp4wc_description_check_pattern($title){
		$description = sprintf(__( 'Please check it if you want to use %s.', 'woocommerce-for-japan' ), $title);
		return wp_kses($description, $this->allowed_html);
	}
	/**
	 * create description for payment pattern.
	 *
     * @param string $title
     *
     * @return string $description
	 */
	public function jp4wc_description_payment_pattern($title){
		$description = sprintf(__( 'Please check it if you want to use the payment method of %s.', 'woocommerce-for-japan' ), $title);
		return wp_kses($description, $this->allowed_html);
	}
	/**
	 * create description for input pattern.
	 *
     * @param string $title
     *
     * @return string $description
	 */
	public function jp4wc_description_input_pattern($title){
		$description = sprintf(__( 'Please input %s.', 'woocommerce-for-japan' ), $title);
		return wp_kses($description, $this->allowed_html);
	}
	/**
	 * create description for input pattern.
	 *
     * @param string $title
     *
     * @return string $description
	 */
	public function jp4wc_description_select_pattern($title){
		$description = sprintf(__( 'Please select one from these as %s.', 'woocommerce-for-japan' ), $title);
		return wp_kses($description, $this->allowed_html);
	}
	/**
	 * Sidebar Support notice html
	 *
     * @param string $support_form_url
	 */
	public function jp4wc_support_notice( $support_form_url ){?>
		<h4 class="inner"><?php echo __( 'Need support?', 'woocommerce-for-japan' );?></h4>
        <p class="inner"><?php echo sprintf(__( 'If you are having problems with this plugin, talk about them in the <a href="%s" target="_blank" title="Pro Version">Support forum</a>.', 'woocommerce-for-japan' ),$support_form_url.'?utm_source=wc4jp-settings&utm_medium=link&utm_campaign=top-pro');?></p>
        <p class="inner"><?php echo sprintf(__( 'If you need professional support, please consider about <a href="%1$s" target="_blank" title="Site Construction Support service">Site Construction Support service</a> or <a href="%2$s" target="_blank" title="Maintenance Support service">Maintenance Support service</a>.', 'woocommerce-for-japan' ),'https://wc.artws.info/product-category/setting-support/?utm_source=wc4jp-settings&utm_medium=link&utm_campaign=setting-support','https://wc.artws.info/product-category/maintenance-support/?utm_source=wc4jp-settings&utm_medium=link&utm_campaign=maintenance-support');?></p>
     <?php
	}
    /**
     * Sidebar Pro version notice html
     *
     * @param string $jp4wc_pro_url
     */
    public function jp4wc_pro_notice( $jp4wc_pro_url ){?>
        <h4 class="inner"><?php echo __( 'Pro version', 'woocommerce-for-japan' );?></h4>
        <p class="inner"><?php echo sprintf(__( 'The pro version is available <a href="%s" target="_blank" title="Support forum">here</a>.', 'woocommerce-for-japan' ),$jp4wc_pro_url.'?utm_source=wc4jp-settings&utm_medium=link&utm_campaign=top-support');?></p>
        <p class="inner"><?php echo __( 'The pro version includes support for bulletin boards. Please consider purchasing the pro version.', 'woocommerce-for-japan' );?></p>
        <?php
    }
	/**
	 * Sidebar Update notice html
	 */
	public function jp4wc_update_notice(){?>
		<h4 class="inner"><?php echo __( 'Finished Latest Update, WordPress and WooCommerce?', 'woocommerce-for-japan' );?></h4>
		<p class="inner"><?php echo __( 'One the security, latest update is the most important thing. If you need site maintenance support。', 'woocommerce-for-japan' );?>
		</p>
     <?php
	}
	/**
	 * This function is similar to the function in the Settings API, only the output HTML is changed.
	 * Print out the settings fields for a particular settings section
	 *
	 * @global $wp_settings_fields Storage array of settings fields and their pages/sections
	 *
	 * @since 1.2
	 *
	 * @param string $page Slug title of the admin page who's settings fields you want to show.
	 */
	public function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;
	 
		if ( ! isset( $wp_settings_sections[$page] ) )
			return;

		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo '<div id="" class="stuffbox postbox '.$section['id'].'">';
			echo '<button type="button" class="handlediv button-link" aria-expanded="true"><span class="screen-reader-text">' . __('Toggle panel', 'woocommerce-for-paygent-payment-main') . '</span><span class="toggle-indicator" aria-hidden="true"></span></button>';
			if ( $section['title'] )
				echo "<h3 class=\"hndle\"><span>{$section['title']}</span></h3>\n";
	 
			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );

			if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) )
				continue;
			echo '<div class="inside"><table class="form-table">';
			do_settings_fields( $page, $section['id'] );
			echo '</table></div>';
			echo '</div>';
		}
	}
    /**
     * create debug log as each plugin.
     *
     * @param string $message
     * @param boolean $flag
     * @param string $slug
     * @param string $version
     * @param string $framework_version
     * @param string $start_time
     * @param string $end_time
     *
     * @return mixed $log
     */
    public function jp4wc_debug_log( $message, $flag, $slug, $version, $framework_version, $start_time = null, $end_time = null){
        if (apply_filters('wc_wc4jp_logging', true, $message )) {
            $logger = wc_get_logger();
            if ( $flag != 'yes') {
                return;
            }
            if ( ! is_null( $start_time ) ) {

                $formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
                $end_time             = is_null( $end_time ) ? current_time( 'timestamp' ) : $end_time;
                $formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
                $elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

                $log_entry  = "\n" . '===='.$slug.':'.$version.', Framework Version: ' . $framework_version . '====' . "\n";
                $log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
                $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

            } else {
                $log_entry  = "\n" . '===='.$slug.':'.$version.', Framework Version: ' . $framework_version . '====' . "\n";
                $log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

            }
            $logger->debug( $log_entry, array( 'source' => $slug ) );

	    //打印日志
	    error_log(__METHOD__ . PHP_EOL .print_r($log_entry, true));
        }
    }
    /**
     * create debug log as each plugin.
     *
     * @param array $array
     *
     * @return string $message
     */
    public function jp4wc_array_to_message( $array ){
        if(is_array($array)){
            $message = '';
            foreach($array as $key => $value){
                if(is_array($value)){
                    foreach ($value as $key2 => $value2){
                        if(is_array($value2)){
                            $value2 = 'This is array.';
                        }
                        $message .= $key . ' : ' . $key2 . ' : ' . $value2. "\n";
                    }
                }else{
                    $message .= $key . ' : ' . $value. "\n";
                }
            }
            return $message;
        }else{
            return null;
        }
    }

    /**
     * Finds an Order ID based on an order key.
     *
     * @param string $transaction_id An order key has generated by.
     * @return int The ID of an order, or 0 if the order could not be found
     */
    public function get_order_id_by_transaction_id( $transaction_id ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_transaction_id' AND meta_value = %s", $transaction_id ) );
    }
}
endif;

