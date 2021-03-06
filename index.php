<?php
/**
 * Plugin Name: WoocommerceAtos
 * Text Domain: woocommerce-atos
 * Plugin URI: https://github.com/chtipepere/woocommerceAtosPlugin
 * Description: Extends Woocommerce with Atos SIPS gateway (French bank).
 * Version: 1.1
 * Author: πR, MB WebAgency
**/

// Exit if accessed directly
if (false === defined('ABSPATH')) {
	exit;
}

if (! class_exists( 'WooCommerce' )) {
	function woocommerce_required(){
		echo '<div class="error"><p>'.
		     __('<strong>Error!</strong> Woocommerce is mandatory. Please install it.', 'woocommerce-atos').
		     '</p></div>';
		return;
	}
	add_action('admin_notices', 'woocommerce_required');
}

define('WOOCOMMERCEATOS_PHP_VERSION', '5.4');
define('WOOCOMMERCE_MINIMUM_VERSION', '2.3.0');

if(!version_compare(PHP_VERSION, WOOCOMMERCEATOS_PHP_VERSION, '>=')) {
	function woocommerce_required_version(){
		echo '<div class="error"><p>'.
		     sprintf(__('<strong>Error!</strong> WoocommerceAtos requires at least PHP %s! Your version is: %s. Please upgrade.', 'woocommerce-atos'), WOOCOMMERCEATOS_PHP_VERSION, PHP_VERSION).
		     '</p></div>';
		return;
	}
	add_action('admin_notices', 'woocommerce_required_version');
}

if(!version_compare(Woocommerce::instance()->version, WOOCOMMERCE_MINIMUM_VERSION, '>=')) {
	function woocommerce_minimum_version(){
		echo '<div class="error"><p>'.
		     sprintf(__('<strong>Error!</strong> WoocommerceAtos requires at least Woocommerce %s! Your version is: %s. Please upgrade.', 'woocommerce-atos'), WOOCOMMERCE_MINIMUM_VERSION, Woocommerce::instance()->version).
		     '</p></div>';
		return;
	}
	add_action('admin_notices', 'woocommerce_minimum_version');
}

if (function_exists('add_action')) {
	add_action( 'plugins_loaded', 'woocommerce_atos_init', 0 );
}

function woocommerce_atos_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/** Translations */
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain('woocommerce-atos', false, $plugin_dir . '/languages/');

	/**
	 * Add the gateway to Woocommerce
	 */
	add_filter('woocommerce_payment_gateways', function ($methods) {
		$methods[] = 'Woocommerce_atos';
		return $methods;
	});

	include_once( 'automatic_response.php' );

	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links' );

	function add_action_links ( $links ) {
		$mylinks = [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_atos' ) . '">'.__('Settings', 'woocommerce') . '</a>',
			'<a href="https://github.com/chtipepere/woocommerceAtosPlugin/blob/master/README.md">'.__('Docs', 'woocommerce-atos') . '</a>'
		];
		return array_merge( $links, $mylinks );
	}

	/**
	 * Gateway class
	 */
	class Woocommerce_atos extends WC_Payment_Gateway {

		public $msg = [];
		public $settings;

		public function __construct() {

			// Go wild in here
			$this->id                       = 'woocommerce_atos';
			$this->icon                     = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/images/logo.gif';
			$this->has_fields               = false;
			$this->method_title             = 'Atos SIPS';
			$this->method_description 		= __('France based ATOS Worldline SIPS is the leading secure payment solution in Europe. Atos works by sending the user to your bank to enter their payment information.', 'woocommerce-atos');

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled                  = $this->get_option('woocommerce_atos_is_enabled');
			$this->title                    = $this->get_option('woocommerce_atos_title');
			$this->description              = $this->get_option('woocommerce_atos_description');
			$this->merchant_id              = $this->get_option('woocommerce_atos_merchant_id');
			$this->merchant_name            = $this->get_option('woocommerce_atos_merchant_name');
			$this->pathfile                 = $this->get_option('woocommerce_atos_pathfile');
			$this->path_bin_request         = $this->get_option('woocommerce_atos_path_bin_request');
			$this->path_bin_response        = $this->get_option('woocommerce_atos_path_bin_response');
			$this->cancel_return_url        = $this->get_option('woocommerce_atos_cancel_return_url');
			$this->automatic_response_url   = $this->get_option('woocommerce_atos_automatic_response_url');
			$this->normal_return_url        = $this->get_option('woocommerce_atos_normal_return_url');
			$this->logo_id2                 = $this->get_option('woocommerce_atos_logo_id2');
			$this->advert                   = $this->get_option('woocommerce_atos_advert');

			$this->msg['message']           = '';
			$this->msg['class']             = '';

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
		}

		function init_form_fields(){

			$this -> form_fields = [
				'woocommerce_atos_is_enabled' => [
					'title'     => __('Enable Atos', 'woocommerce-atos'),
					'type'      => 'checkbox',
					'label'     => ' ',
					'default'   => 'yes'
				],
				'woocommerce_atos_title' => [
					'title'         => sprintf(__('Atos Standard %s', 'woocommerce-atos'), '<img style="vertical-align:middle;margin-top:-4px;margin-left:10px;" src="' . WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/images/logo.gif" alt="Atos">'),
					'type'          => 'text',
					'description'   => __('Payment title displayed during checkout.', 'woocommerce-atos'),
					'default'       => __('Credit card', 'woocommerce-atos')
				],
				'woocommerce_atos_description' => [
					'title'         => __('Description', 'woocommerce-atos'),
					'type'          => 'textarea',
					'description'   => __('Payment description displayed during checkout.', 'woocommerce-atos'),
					'default'       => __('Paiement sécurisé par Carte Bancaire (Atos)', 'woocommerce-atos' )
				],
				'woocommerce_atos_merchant_id' => [
					'title'         => __('Merchant id', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   => __('Merchant id given by your bank', 'woocommerce-atos'),
					'default'       => '014022286611111'
				],
				'woocommerce_atos_merchant_name' => [
					'title'         => __('Merchant name', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   => __('Merchant name', 'woocommerce-atos'),
					'default'       => 'My Company'
				],
				'woocommerce_atos_pathfile' => [
					'title'         => __('Pathfile file', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __('Path to the pathfile file given by your bank', 'woocommerce-atos'),
					'default'       => ABSPATH . 'config/atos/param/pathfile'
				],
				'woocommerce_atos_path_bin_request' => [
					'title'         => __('Request bin file path', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __('Path to the request bin file given by your bank', 'woocommerce-atos'),
					'default'       => ABSPATH . 'config/atos/bin/request'
				],
				'woocommerce_atos_path_bin_response' => [
					'title'         => __('Response bin file path', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __('Path to the response bin file given by your bank', 'woocommerce-atos'),
					'default'       => ABSPATH . 'config/atos/bin/response'
				],
				'woocommerce_atos_cancel_return_url' => [
					'title'         => __('Cancel return url', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __('Return url in case of canceled transaction', 'woocommerce-atos'),
					'default'       => site_url('/cancel')
				],
				'woocommerce_atos_normal_return_url' => [
					'title'         => __('Normal return url', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   => __('Return url when a user click on the Back to the shop button', 'woocommerce-atos'),
					'default'       => site_url('/return')
				],
				'woocommerce_atos_automatic_response_url' => [
					'title'         => __('Automatic response url', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __('URL called in case of success payment', 'woocommerce-atos'),
					'default'       => site_url( '/success' )
				],
				'woocommerce_atos_logo_id2' => [
					'title'         => __('Logo id2', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __('Right image on Atos page', 'woocommerce-atos'),
					'default'       => 'logo_id2.gif'
				],
				'woocommerce_atos_advert' => [
					'title'         => __('Advert', 'woocommerce-atos'),
					'type'          => 'text',
					'description'   =>  __( 'Center image on Atos page', 'woocommerce-atos' ),
					'default'       => 'advert.gif'
				]
			];
		}

		/**
		 * Process the payment and return the result
		 */
		public function process_payment($order_id)
		{
			$order = new WC_Order($order_id);
			return array(
				'result'    => 'success',
				'redirect'  => $order->get_checkout_payment_url(true)
			);
		}

		/**
		 * Display ATOS SIPS form
		 */
		function receipt_page( $order_id ) {
			echo '<p>' . __('Thank you for your order, please click the button below to pay.', 'woocommerce-atos') . '</p>';
			echo $this->generate_atos_form( $order_id );
		}

		/**
		 *  OBSOLETE
		 **/
		public function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}
		public function thankyou_page() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->mercitxt ) );
			}
		}

		public function showMessage( $content ) {
			return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
		}

		/**
		 * Generate atos button link
		 *
		 * @param $order_id
		 *
		 * @return string
		 */
		public function generate_atos_form( $order_id )
		{
			$order = new WC_Order( $order_id );

			$pathfile = $this->pathfile;

			$path_bin_request = $this->path_bin_request;
			$parm             = 'merchant_id=' . $this->merchant_id;

			$parm   = "$parm merchant_country=fr";
			$amount = ( $order->order_total ) * 100;

			$amount = str_pad( $amount, 3, '0', STR_PAD_LEFT );

			$parm = "$parm amount=" . $amount;

			$parm = "$parm currency_code=978";

			$parm = "$parm pathfile=" . $pathfile;

			$parm = "$parm normal_return_url=" . $this->normal_return_url;

			$parm = "$parm cancel_return_url=" . $this->cancel_return_url;

			$parm = "$parm automatic_response_url=" . $this->automatic_response_url;

			$parm = "$parm language=fr";

			$parm = "$parm payment_means=CB,2,VISA,2,MASTERCARD,2";

			$parm = "$parm header_flag=no";

			$parm = "$parm order_id=$order_id";

			$parm = "$parm logo_id2=" . $this->logo_id2;

			$parm = "$parm advert=" . $this->advert;

			$parm = escapeshellcmd($parm);
			$result = exec( "$path_bin_request $parm" );

			$tableau = explode( "!", "$result" );

			$code = $tableau[1];

			$error = $tableau[2];

			if ( ( $code == '' ) && ( $error == '' ) ) {

				$message = '<p>' . __( 'Error calling the atos api: exec request not found',
						'woocommerce-atos' ) . "  $path_bin_request</p>";

			} elseif ( $code != 0 ) {

				$message = '<p>' . __( 'Atos API error:', 'woocommerce-atos' ) . " $error</p>";

			} else {

				// Affiche le formulaire avec le choix des cartes bancaires :
				$message = $tableau[3];
			}

			return $message;
		}
	}
}
