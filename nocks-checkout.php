<?php
/*
Plugin Name: Easy Digital Downloads - Nocks Checkout
Plugin URL: http://nocks.com
Description: An EDD gateway for Nocks Checkout
Version: 1.0
Author: Nocks B.V.
Author URI: http://nocks.com
Contributors: spasma
*/

require_once dirname(__FILE__).'/autoload.php';
Nocks_EDD_Autoload::register();

// register the gateway
function edd_nocks_checkout_register_gateway( $gateways ) {
	$gateways['nocks_checkout'] = array( 'admin_label' => 'Nocks Checkout', 'checkout_label' => __( 'Nocks Checkout', 'edd_nocks_checkout' ) );

	return $gateways;
}
add_filter( 'edd_payment_gateways', 'edd_nocks_checkout_register_gateway' );
// No Credit Card Form
add_action( 'edd_nocks_checkout_cc_form', '__return_false' );


// process the payment
function edd_nocks_checkout_process_payment( $purchase_data ) {
	global $edd_options;


	$nc = new Nocks_Checkout($edd_options['nocks_checkout_api_key'], isset($edd_options['nocks_checkout_merchant_account'])?$edd_options['nocks_checkout_merchant_account']:false);

	if ( edd_is_test_mode() ) {
		// set test credentials here
	} else {
		// set live credentials here
	}

	/*
	// errors can be set like this
	if( ! isset($_POST['card_number'] ) ) {
		// error code followed by error message
		edd_set_error('empty_card', __('You must enter a card number', 'edd'));
	}
	*/


	/**********************************
    $purchase_data = array(
        'downloads'     => array of download IDs,
        'tax' 			=> taxed amount on shopping cart
        'fees' 			=> array of arbitrary cart fees
        'discount' 		=> discounted amount, if any
        'subtotal'		=> total price before tax
        'price'         => total price of cart contents after taxes,
        'purchase_key'  =>  // Random key
        'user_email'    => $user_email,
        'date'          => date( 'Y-m-d H:i:s' ),
        'user_id'       => $user_id,
        'post_data'     => $_POST,
        'user_info'     => array of user's information and used discount code
        'cart_details'  => array of cart details,
     );
    */



	$errors = edd_get_errors();
	if ( ! $errors ) {



		$purchase_summary = edd_get_purchase_summary( $purchase_data );
		$payment = array(
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => $edd_options['currency'],
			'downloads'    => $purchase_data['downloads'],
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'status'       => 'pending'
		);

		$payment = edd_insert_payment( $payment );

		$return_url = add_query_arg( array(
			'payment-confirmation' => 'nocks_checkout',
			'payment-id' => $payment
		), get_permalink( edd_get_option( 'success_page', false ) ) );

		$transaction = $nc->createTransaction(array(
			'amount' => $purchase_data['price'],
			'currency' => $edd_options['currency'],
			'webhookUrl' => '',
			'redirectUrl' => $return_url
		));

		$transaction_id = $transaction['data']['uuid'];//$nocks_checkout_transaction['success']['transactionId'];
		$payment_id = $transaction['data']['payments']["data"][0]['uuid'];


		if ($transaction) {
			edd_insert_payment_note( $payment, 'Transaction ID: '.$transaction_id );
			edd_insert_payment_note( $payment, 'Payment ID: '.$payment_id );
		}

		edd_update_payment_meta($payment, 'nocks_tx_id', $transaction_id);

		EDD()->session->set( 'edd_resume_payment', $payment );

		wp_redirect( $nc->getPaymentUrl($payment_id) );
		exit;
	} else {
		$fail = true; // errors were detected
	}

	if ( $fail !== false ) {
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}
}
add_action( 'edd_gateway_nocks_checkout', 'edd_nocks_checkout_process_payment' );


/**
 * @return string
 */
function edd_nocks_checkout_success_page_content( $content ) {
	global $edd_options;
	if ( ! isset( $_GET['payment-id'] ) && ! edd_get_purchase_session() ) {
		return $content;
	}
	edd_empty_cart();
	$payment_id = isset( $_GET['payment-id'] ) ? absint( $_GET['payment-id'] ) : false;
	if ( ! $payment_id ) {
		$session    = edd_get_purchase_session();
		$payment_id = edd_get_purchase_id_by_key( $session['purchase_key'] );
	}
	$nc = new Nocks_Checkout($edd_options['nocks_checkout_api_key'], isset($edd_options['nocks_checkout_merchant_account'])?$edd_options['nocks_checkout_merchant_account']:false);
	$transaction = $nc->getTransaction(edd_get_payment_meta($payment_id, 'nocks_tx_id'));
	$payment = new EDD_Payment( $payment_id );
	if ( $payment->ID > 0 && ('pending' == $payment->status || 'failed' == $payment->status )  ) {
		if ($transaction->isPaid()) {
			$payment->update_status('completed');
		} elseif ($transaction->isCancelled()) {
			$payment->update_status('failed');
		}
		ob_start();
		edd_get_template_part( 'payment', 'processing' );
		$content = ob_get_clean();
	}

	if ($transaction->isPaid()) 
		$payment->update_status('completed');

	return $content;
}
add_filter( 'edd_payment_confirm_nocks_checkout', 'edd_nocks_checkout_success_page_content' );

// adds the settings to the Payment Gateways section
function edd_nocks_checkout_add_settings( $settings ) {
	global $edd_options;
	$nc = new Nocks_Checkout($edd_options['nocks_checkout_api_key'], isset($edd_options['nocks_checkout_merchant_account'])?$edd_options['nocks_checkout_merchant_account']:false);
	$nocks_checkout_settings = array(
		array(
			'id' => 'nocks_checkout_settings',
			'name' => '<strong>' . __( 'Nocks Checkout Settings', 'edd_nocks_checkout' ) . '</strong>',
			'desc' => __( 'Configure the gateway settings', 'edd_nocks_checkout' ),
			'type' => 'header'
		),
		array(
			'id' => 'api_help',
			'desc' => sprintf( __( 'Please enter your <a target="_blank" href="%s">Nocks API key</a> to select a merchant account.<br/><br/>No API-key? Create one <a target="_blank" href="https://www.nocks.com/account/api/personal-tokens">here</a> and provide the following permissions: <br/><strong>transaction.create<br/>transaction.read<br/>merchant.read</strong>', 'easy-digital-downloads' ), 'https://www.nocks.com/account/api/personal-tokens' ),
			'type' => 'descriptive_text',
		),
		array(
			'id' => 'nocks_checkout_api_key',
			'name' => __( 'Nocks API Key', 'edd_nocks_checkout' ),
			'desc' => __( 'Enter your Nocks API key', 'edd_nocks_checkout' ),
			'type' => 'textarea',
			'size' => 'regular'
		),
		array(
			'id' => 'nocks_checkout_merchant_account',
			'name' => __( 'Nocks Merchant Account', 'edd_nocks_checkout' ),
			'desc' => __( 'Select your Nocks Merchant Account', 'edd_nocks_checkout' ),
			'type' => 'select',
			'size' => 'regular',
			'default'     => '',
			'options' => $nc->getMerchants(),
		)

		//$edd_options['nocks_checkout_api_key']
	);

	return array_merge( $settings, $nocks_checkout_settings );
}
add_filter( 'edd_settings_gateways', 'edd_nocks_checkout_add_settings' );
