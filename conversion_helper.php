<?php
/*
Plugin Name: Conversion Helper
Plugin URI: https://borowicz.me/conversion-helper/
Description: Add google adwords conversion code and goal trackers
Author: Wojciech Borowicz
Version: 1.12
Author URI: https://borowicz.me
License: GPLv2
Text Domain: conversion-helper
*/

// Register a setting page
function conversion_helper_add_settings_page() {
    add_options_page( 'Conversion Helper', 'Conversion Helper', 'manage_options', 'conversion_helper', 'conversion_helper_render_plugin_settings_page' );
}
add_action( 'admin_menu', 'conversion_helper_add_settings_page' );



// Render setting page for conversion helper
function conversion_helper_render_plugin_settings_page() {
    $options = get_option( 'conversion_helper_options' );
		?>
    <h2>Google Ads Conversion tracking panel</h2>
    <form action="options.php" method="post">
        <?php 
        settings_fields( 'conversion_helper_options' );
        do_settings_sections( 'conversion_helper_plugin' ); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
    </form>
		 <?php
}



// Create a box for individual conversion codes on page edit page
function conversion_helper_register_meta_boxes() {
    add_meta_box( 'conversion_helper_box', __( 'Conversion helper', 'textdomain' ), 'conversion_helper_display_callback', 'page' );
}
add_action( 'add_meta_boxes', 'conversion_helper_register_meta_boxes' );



// Create inputs for individual conversion codes on page edit page
function conversion_helper_display_callback( $post ) {
?> 
<label><b>Conversion code to fire on the page visit <small>(without tags)</small>:</b><br/>
	<textarea name="conversion_helper_page_conversion_code" style='width:100%;min-height:200px;' placeholder="gtag('event', 'conversion', {'send_to': 'AW-XXXXXXXXX/XXXXXXXXXXXXXXX-XXXXX'});"><?php echo get_post_meta(get_the_ID(), 'conversion_helper_page_conversion_code')[0]; ?></textarea></label>
<?php if ( has_shortcode( $post->post_content, 'contact-form-7') ) { ?>
	<label><b>Conversion code to fire on contact form submit</small>:</b><br/>
	<textarea name="conversion_helper_page_contact_form7_code" style='width:100%;min-height:200px;' placeholder="gtag('event', 'conversion', {'send_to': 'AW-XXXXXXXXX/XXXXXXXXXXXXXXX-XXXXX'});"><?php echo get_post_meta(get_the_ID(), 'conversion_helper_page_contact_form7_code')[0]; ?></textarea></label>
<?php } else{
		echo "<small>If you wish to add a code to fire after a contact form submit, please add contact form shortcode and update the page.</small>";
	  }	
}
 

// Save individual conversion codes on page edit page, if they're being changed.
function conversion_helper_save_meta_box( $post_id ) {
     if( isset( $_POST['conversion_helper_page_conversion_code'] ) ){
		         update_post_meta( $post_id, 'conversion_helper_page_conversion_code', wp_kses( $_POST['conversion_helper_page_conversion_code'], $allowed ) );
	 }
	 if( isset( $_POST['conversion_helper_page_contact_form7_code'] ) ){
		         update_post_meta( $post_id, 'conversion_helper_page_contact_form7_code', wp_kses( $_POST['conversion_helper_page_contact_form7_code'], $allowed ) );
	 }
}
add_action( 'save_post', 'conversion_helper_save_meta_box' );


// Register global settings for plugin, needed for firing on Thank You Page and adding scripts in the header of a website globally
function conversion_helper_register_settings() {
    register_setting( 'conversion_helper_options', 'conversion_helper_options');
   	add_settings_section( 'conversion_helper_scripts_settings', 'General Settings', '', 'conversion_helper_plugin' );	
    
	add_settings_field( 'conversion_helper_setting_header_scripts', 'Header Scripts', 'conversion_helper_setting_header_scripts', 'conversion_helper_plugin', 'conversion_helper_scripts_settings' );
    add_settings_field( 'conversion_helper_setting_checkout_sendto', 'Checkout Page send_to', 'conversion_helper_setting_checkout_sendto', 'conversion_helper_plugin', 'conversion_helper_scripts_settings' );
    add_settings_field( 'conversion_helper_test_mode', 'Test Mode', 'conversion_helper_test_mode', 'conversion_helper_plugin', 'conversion_helper_scripts_settings' );
	
}
add_action( 'admin_init', 'conversion_helper_register_settings' );



function conversion_helper_test_mode() {
    $options = get_option( 'conversion_helper_options' );
   ?> <p class='ConversionHelperTestMode'>	
				<input type='checkbox' name='conversion_helper_options[testmode]'  value='1'  <?php checked(  isset($options['testmode']) ) ?> /><br/>
				<small>This option will allow you to test your conversion tracking on cart page without placing an order.<br/>
				In order to fire a test conversion, enable this mode, go to cart page and add GET parameters to the URL.<br/>
				Two parameters are supported, <i>"id"</i>, which is used for transaction ID and <i>"value"</i>, which is used for order total <br/>
				Example : yoursite.com/cart/?id=222&value=300.22</small>
		</p>
		
		<?php
	
	
}

// Render Header Scripts textarea
function conversion_helper_setting_header_scripts() {
    $options = get_option( 'conversion_helper_options' );
    echo "<textarea id='conversion_helper_setting_header_scripts' name='conversion_helper_options[header_scripts]' style='min-width:500px;min-height:200px;'>".$options["header_scripts"]."</textarea>";
}


// Render Checkout Scripts value textarea
function conversion_helper_setting_checkout_sendto() {
    $options = get_option( 'conversion_helper_options' );
    echo "<input id='conversion_helper_setting_checkout_sendto' name='conversion_helper_options[checkout_sendto]' type='text' placeholder='AW-659142764/KPFJCP7AmsoBEOzwproC' style='min-width:500px;' value='".$options["checkout_sendto"]."'/>";
}


// Render scripts on the front end, to make conversion tracking live
function conversion_helper_inject_scripts_header() {
	$options = get_option( 'conversion_helper_options' );
	if(isset($options["header_scripts"])){
		// Just a comment line for the view source
		echo "\r\n <!------- Added by conversion helper ----------> \r\n\r\n   ";		
			// Render a header gtag definition
			echo $options["header_scripts"];
			if(isset($options["checkout_sendto"])){
			// If checkout codes are configured, check if it's a thank you page and render conversion code				
				if ( is_checkout() && !empty( is_wc_endpoint_url('order-received') ) && !isset($_GET['id'])) {
					$orderID = wc_get_order_id_by_order_key( $_GET['key'] );
					$order = wc_get_order($orderID);
					if($order){
						$total = $order->get_total();
					} else{
						$total = 1;
					}
				?>
					<script>
					  gtag('event', 'conversion', {
						   'send_to': '<?php echo  esc_html_e($options["checkout_sendto"])  ; ?>',
						  'value': '<?php echo esc_html_e($total); ?>',
						  'currency': 'GBP',
						  'transaction_id': '<?php echo esc_html_e($orderID); ?>'
						  });
					</script>    
				<?php	    
				}
				if(isset($options["testmode"])){
					if (is_wc_endpoint_url( 'order-received' ) || is_cart(get_the_ID()) ){
					    if(isset($_GET['id']) && isset($_GET['value'])){
							// Is test mode on? If so, fire scripts on cart page to avoid having to place an order.
							
						?>
							<script>
							  gtag('event', 'conversion', {
								   'send_to': '<?php echo  esc_html_e($options["checkout_sendto"])  ; ?>',
								  'value': '<?php echo $_GET["value"]; ?>',
								  'currency': 'GBP',
								  'transaction_id': '<?php echo $_GET["id"]; ?>'
								  });
							</script>    
						<?php	    
					}
				}
				}
				
				
				
			// end thank you page conversion code
			}
			
				$post = get_post();
				if($post){
				$pageConversion = get_post_meta($post->ID, 'conversion_helper_page_conversion_code');
				if($pageConversion){
					echo "<script>".$pageConversion[0]."</script>";
				}
				if ( has_shortcode( $post->post_content, 'contact-form-7') ) { 
					$contactFormSubmitConversion = get_post_meta($post->ID, 'conversion_helper_page_contact_form7_code');
					if($contactFormSubmitConversion){
						echo "<script>document.addEventListener( 'wpcf7mailsent', function( event ) {".$contactFormSubmitConversion[0]."}, false );</script>";
					}
				}
				}
		echo "\r\n<!------- Added by conversion helper ----------> \r\n\r\n";
	}
}
add_action('wp_head','conversion_helper_inject_scripts_header');

?>