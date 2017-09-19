<?php
/**
 * Plugin Name: WC Azul Payment Gateway
 * Plugin URI: https://ideologic.do/
 * Description: WooCommerce Plugin for accepting payment through Azul Payment Gateway (POST).
 * Version: 1.0.0
 * Author: ideologic.do
 * Author URI: https://ideologic.do
 * Contributors: Ideologic SRL
 * Requires at least: 4.0
 * Tested up to: 4.7.*
 *
 * Text Domain: wc-azul-paymentgateway
 * Domain Path: /lang/
 *
 * @package WC Azul Payment Gateway
 * @author Ideologic SRL
 */

add_action('plugins_loaded', 'init_wc_gateway_azul', 0);

function init_wc_gateway_azul() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	load_plugin_textdomain('wc-azul-paymentgateway', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');

	class wc_gateway_azul extends WC_Payment_Gateway {

		public function __construct() {
			global $woocommerce;

			$this->id						= 'wc_gateway_azul';
			$this->method_title = __( 'Azul Payments', 'wc-azul-paymentgateway' );
			$this->icon					= apply_filters( 'wc_gateway_azul_icon', 'azul-logo.png' );
			$this->has_fields 	= false;

			$default_card_type_options = array(
													'VISA' => 'VISA',
													'MC' => 'MasterCard',
													'AMEX' => 'American Express'
													);

			$this->card_type_options = apply_filters( 'wc_gateway_azul_card_types', $default_card_type_options );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title 					= $this->settings['title'];
			$this->description 		= $this->settings['description'];
			$this->private_key  	= $this->settings['private_key'];
			$this->merchant_name 	= $this->settings['merchant_name'];
			$this->mode         	= $this->settings['mode'];
			$this->merchant_id   	= $this->settings['merchant_id'];
			$this->tax_percentage = $this->settings['tax_percentage'];
			$this->redirecting_message = $this->settings['redirecting_message'];



			$this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_gateway_azul', home_url( '/' ) ) );
			$this->CancelUrl 		= $this->settings['cancel_url'];

			// Actions
			add_action( 'init', array( $this, 'successful_request' ) );
			add_action( 'woocommerce_api_wc_gateway_azul', array( $this, 'successful_request' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	    }

		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		function get_icon() {
			global $woocommerce;

			$icon = '';
			if ( $this->icon ) {
				// default behavior
				$icon = '<img src="' . plugins_url('images/' . $this->icon, __FILE__)  . '" alt="' . $this->title . '" />';

			} elseif ( $this->cardtypes ) {

				// display icons for the selected card types
				foreach ( $this->cardtypes as $cardtype ) {
					if ( file_exists( plugin_dir_path( __FILE__ ) . '/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
						$icon .= '<img src="' . $this->force_ssl( plugins_url( '/images/card-' . strtolower( $cardtype ) . '.png', __FILE__ ) ) . '" alt="' . strtolower( $cardtype ) . '" />';
					}
				}
			}

			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {

	    	?>
	    	<h3><?php _e('Azul Payment Gateway by ideologic.do', 'wc-azul-paymentgateway'); ?></h3>
	    	<p><?php _e('Azul Payment Gateway works by redirecting the buyer to Azul Payment page.
				After a successful transaction, the order will be updated in WooCommerce to Processing
				(adding a note to the order with the Aprobation Number and the masked credit card used in the transacction).' , 'wc-azul-paymentgateway'); ?></p>
	    	<table class="form-table">
	    	<?php

	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();

	    	?>
			</table><!--/.form-table-->
				<p><?php _e('<br> <hr> <br>
				<div style="float:right;text-align:right;">
					Made with &hearts; at <a href="https://ideologic.do?ref=wc-azul-paymentgateway" target="_blank">ideologic.do</a> | ¿Need help or custom integrations? <a href="https://ideologic.do?ref=wc-azul-paymentgateway" target="_blank">Contact Us</a><br><br>
					<a href="https://ideologic.do?ref=wc-azul-paymentgateway" target="_blank"><img src="' . plugins_url('images/ideologic-logo.png', __FILE__) . '">
					</a>
				</div>' , 'wc-azul-paymentgateway'); ?></p>
	    	<?php
	    } // End admin_options()

		/**
	     * Initialise Gateway Settings Form Fields
	     */
	    function init_form_fields() {

	    	$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable/Disable', 'wc-azul-paymentgateway' ),
						'type' => 'checkbox',
						'label' => __( 'Enable Azul Payments', 'wc-azul-paymentgateway' ),
						'default' => 'yes'
					),
					'mode' => array(
						'title' => __('Mode', 'wc-azul-paymentgateway'),
						'type' => 'select',
						'options' => array(
							'test' => 'Test / Sandbox',
							'live' => 'Live / Production'
						),
						'description' => __( 'Select Test or Live mode.', 'wc-azul-paymentgateway' ),
						'default' => 'test'
					),
					'title' => array(
						'title' => __( 'Title', 'wc-azul-paymentgateway' ),
						'type' => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-azul-paymentgateway' ),
						'default' => __( 'Azul Payments', 'wc-azul-paymentgateway' )
					),
					'description' => array(
						'title' => __( 'Description', 'wc-azul-paymentgateway' ),
						'type' => 'textarea',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-azul-paymentgateway' ),
						'default' => __("Pay with your Credit Card vía Azul payment page.", 'wc-azul-paymentgateway')
					),
					'redirecting_message' => array(
						'title' => __( 'Redirecting Message', 'wc-azul-paymentgateway' ),
						'type' => 'textarea',
						'description' => __( 'Message displayed once the buyer finish the order and is redirected to Azul.', 'wc-azul-paymentgateway' ),
						'default' => __("Thank you for your order. We are now redirecting to Azul Payment Page to finish your order.", 'wc-azul-paymentgateway')
					),
					'merchant_name' => array(
						'title' => __( 'Merchant Name', 'wc-azul-paymentgateway' ),
						'type' => 'text',
						'description' => __( 'Please enter your Merchant Name provided by Azul.', 'wc-azul-paymentgateway' ),
						'default' => ''
					),
					'merchant_id' => array(
						'title' => __( 'MerchantId', 'wc-azul-paymentgateway' ),
						'type' => 'text',
						'description' => __( 'Please enter your MerchantId provided by Azul.', 'wc-azul-paymentgateway' ),
						'default' => ''
					),
					'private_key' => array(
						'title' => __( 'Private Key', 'wc-azul-paymentgateway' ),
						'type' => 'textarea',
						'description' => __( 'Please enter your PrivateKey provided by Azul.', 'wc-azul-paymentgateway' ),
						'default' => ''
					),
					'cancel_url' => array(
						'title' => __( 'Cancel URL', 'wc-azul-paymentgateway' ),
						'type' => 'text',
						'description' => __( 'The URL to redirect if the user cancel the payment at azul payment page.', 'wc-azul-paymentgateway' ),
						'default' => home_url('/my-account/orders/')
					),
					'tax_percentage' => array(
						'title' => __( 'Tax Percentage', 'wc-azul-paymentgateway' ),
						'type' => 'text',
						'description' => __( 'The percentage to calculate the tax of the order total. Example: 0.18', 'wc-azul-paymentgateway' ),
						'default' => '0.18'
					)
				);
			} // End init_form_fields()

	    /**
		 * Not payment fields, but show the description of the payment.
		 **/
    function payment_fields() {
    	if ($this->description) echo wpautop(wptexturize($this->description));
    }

		/**
		 * Generate the form with the params
		 **/
	  public function generate_azul_form( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );

			if( $this->mode == 'test' ){
				$gateway_url = 'https://pruebas.azul.com.do/PaymentPage/default.aspx';
			}else if( $this->mode == 'live' ){
				$gateway_url = 'https://pagos.azul.com.do/PaymentPage/default.aspx';
				// TO-DO: Implement alternate server request in case of failure
				//$alt_gateway_url = 'https://contpagos.azul.com.do/PaymentPage';
			}

      $time_stamp = date("ymdHis");
			$merchantType = 'ecommerce';
			$currencyCode = '$';

			$orderTotal = $order->get_total();
			$orderTotal = str_replace( '.', '', $orderTotal);
			$orderTotal = str_replace( ',', '', $orderTotal);


			//Form Post Params
			//Important: The order of the following parameters are ESSENTIAL for the encryption to work.
			$params['MerchantId'] 		 = $this->merchant_id;
			$params['MerchantName']		 = $this->merchant_name;
			$params['MerchantType'] 	 = $merchantType;
			$params['CurrencyCode'] 	 = $currencyCode;
			$params['OrderNumber'] 		 = $order_id;
			$params['Amount'] 				 = $orderTotal;
			$params['ITBIS'] 					 = $params['Amount'] * $this->tax_percentage;
			$params['ApprovedUrl'] 		 = $this->notify_url;
			$params['DeclinedUrl'] 		 = $this->notify_url;
			$params['CancelUrl'] 			 = $this->CancelUrl;
			$params['ResponsePostUrl'] = $this->notify_url;
			$params['UseCustomField1'] = '0';
			$params['CustomField1Label'] = 'Custom1';
			$params['CustomField1Value'] = 'Value1';
			$params['UseCustomField2'] = '0';
			$params['CustomField2Label'] = 'Custom2';
			$params['CustomField2Value'] = 'Value2';

			//Encrypt values to create the AuthHash
			$post_values = "";
      foreach( $params as $key => $value ) {
          $post_values .= $value;
      }
			$post_values .= $this->private_key;

			$params['ShowTransactionResult'] = 0;
			//Adding to the form params the AuthHash
		  $params['AuthHash'] = $this->encryptAndEncode($post_values);

			$azul_arg_array = array();
			foreach ($params as $key => $value) {
				$azul_arg_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			$redirectingMessage = $this->redirecting_message;

			wc_enqueue_js('
				jQuery("body").block({
						message: "'. $redirectingMessage .'",
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
					        padding:        20,
					        textAlign:      "center",
					        color:          "#555",
					        border:         "3px solid #aaa",
					        backgroundColor:"#fff",
					        cursor:         "wait",
					        lineHeight:		"32px"
					    }
					});
					jQuery("#submit_azul_payment_form").click();
			');

			return  '<form action="'.esc_url( $gateway_url ).'" method="post" id="azul_payment_form">
					' . implode('', $azul_arg_array) . '
					<input type="submit" class="button" id="submit_azul_payment_form" value="'.__('Pay Now', 'wc-azul-paymentgateway').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'wc-azul-paymentgateway').'</a>
				</form>';

		}

		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);

		}

		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {

			echo '<p>'.__('Thank you for your order, please click the button below to pay with Azul Payments.', 'wc-azul-paymentgateway').'</p>';
			echo $this->generate_azul_form( $order );

		}


		/**
		 * Successful Payment!
		 **/
		function successful_request() {
			global $woocommerce;

				//Important: The order of the following parameters are ESSENTIAL for the encryption to work.
				$params['OrderNumber'] 				= $_GET['OrderNumber'];
				$params['Amount'] 						= $_GET['Amount'];
				$params['AuthorizationCode'] 	= $_GET['AuthorizationCode'];
				$params['DateTime'] 					= $_GET['DateTime'];
				$params['ResponseCode'] 			= $_GET['ResponseCode'];
				$params['IsoCode'] 						= $_GET['IsoCode'];
				$params['ResponseMessage'] 		= $_GET['ResponseMessage'];
				$params['ErrorDescription'] 	= $_GET['ErrorDescription'];
				$params['RRN'] 								= $_GET['RRN'];

				$post_values = "";
	      foreach( $params as $key => $value ) {
	          $post_values .= $value;
	      }

				$post_values .= $this->private_key;
				$localHash = $this->encryptAndEncode($post_values);

			  if ($localHash == $_GET['AuthHash']) {

					if ($params['IsoCode'] === '00') {

						//Transacción Aceptada.
						$order = new WC_Order( $params['OrderNumber'] );
						$order->add_order_note(sprintf(__('Azul Payment Successful. The Aprobation Number is %s. Credit Card: %s', 'wc-azul-paymentgateway'), $params['AuthorizationCode'], $_GET['CardNumber']));
						$order->payment_complete();

						wp_redirect( $this->get_return_url( $order ) ); exit;

					}

				}

				//Transacción declinada.
				wc_add_notice( sprintf(__('Transaction Failed. The Error Message was %s', 'wc-azul-paymentgateway'), $params['ResponseMessage'] ), $notice_type = 'error' );
				wp_redirect( get_permalink(get_option( 'woocommerce_checkout_page_id' )) ); exit;

		}

		private function encryptAndEncode($strIn) {
			//The encryption required by azul is SHA-512
			$result = mb_convert_encoding($strIn, 'UTF-16LE', 'ASCII');
			$result = hash('sha512', $result);
			return $result;
		}

		private function force_ssl($url){
			if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}

			return $url;
		}

	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_azul_gateway( $methods ) {
		$methods[] = 'wc_gateway_azul'; return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_azul_gateway' );

}
