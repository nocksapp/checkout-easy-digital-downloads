<?php
/*
Plugin Name: Easy Digital Downloads - Nocks Checkout
Plugin URL: http://nocks.com
Description: An EDD gateway for Nocks Checkout
Version: 1.1.0
Author: Nocks B.V.
Author URI: http://nocks.com
Contributors: spasma
*/

require_once dirname(__FILE__).'/autoload.php';
Nocks_EDD_Autoload::register();

// Register NLG currency
function edd_nocks_checkout_register_currencies($currencies) {
    $currencies['NLG'] = __('Gulden', 'edd_nocks_gateway');

	return $currencies;
};

function edd_nocks_checkout_register_currency_symbol($symbol, $currency) {
	if ($currency === 'NLG') {
		return 'G';
	}

	return $symbol;
}

add_filter('edd_currencies', 'edd_nocks_checkout_register_currencies');
add_filter('edd_currency_symbol', 'edd_nocks_checkout_register_currency_symbol', 10, 2);

// Register the gateway
function edd_nocks_checkout_register_gateways($gateways) {
	$currency = edd_get_currency();

	if(!in_array($currency, ['NLG', 'EUR'])) {
	    // Can't add gateway in other currency than NLG or EUR
		return $gateways;
	}

	$gateways['nocks_gulden'] = [
		'admin_label' => 'Nocks Gulden',
		'checkout_label' => __('Gulden', 'edd_nocks_gateway'),
	];

	$gateways['nocks_ideal'] = [
		'admin_label' => 'Nocks iDEAL',
		'checkout_label' => __('iDEAL', 'edd_nocks_gateway'),
	];

	$gateways['nocks_sepa'] = [
		'admin_label' => 'Nocks SEPA',
		'checkout_label' => __('SEPA', 'edd_nocks_gateway'),
	];

	$gateways['nocks_balance'] = [
		'admin_label' => 'Nocks Balance',
		'checkout_label' => __('Nocks Balance', 'edd_nocks_gateway'),
	];

	return $gateways;
}
add_filter('edd_payment_gateways', 'edd_nocks_checkout_register_gateways');

function edd_nocks_ideal_form() {
	global $edd_options;
	$nc = new Nocks_Checkout($edd_options['nocks_checkout_api_key'], $edd_options['nocks_checkout_merchant_account']);
	$issuers = $nc->getIdealIssuers();

	ob_start(); ?>
	<fieldset>
		<legend><?php _e('Select your bank', 'edd_nocks_gateway'); ?></legend>
		<select name="nocks_ideal_issuer" class="edd-select">
			<?php foreach ($issuers as $key => $label) { ?>
				<option value="<?php echo $key; ?>"><?php echo $label; ?></option>
			<?php } ?>
		</select>
	</fieldset>
	<?php
	echo ob_get_clean();
}

// Set forms
add_action('edd_nocks_gulden_cc_form', '__return_false');
add_action('edd_nocks_ideal_cc_form', 'edd_nocks_ideal_form');
add_action('edd_nocks_sepa_cc_form', '__return_false');
add_action('edd_nocks_balance_cc_form', '__return_false');

// process the payment
function edd_nocks_checkout_process_payment($purchase_data) {
	global $edd_options;

	$nc = new Nocks_Checkout($edd_options['nocks_checkout_api_key'], $edd_options['nocks_checkout_merchant_account']);

	$errors = edd_get_errors();
	if (!$errors) {
		$payment = [
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'status'       => 'pending'
		];

		$eddPaymentID = edd_insert_payment($payment);
		$return_url = add_query_arg([
			'payment-confirmation' => $purchase_data['gateway'],
			'payment-id' => $eddPaymentID,
		], get_permalink(edd_get_option('success_page', false)));

		$webhook_url = get_site_url() . '/?edd-listener=NOCKS&payment-id=' . $eddPaymentID;

		$transaction = $nc->createTransaction([
			'source_currency' => $purchase_data['gateway'] === 'nocks_gulden' ? 'NLG' : ($purchase_data['gateway'] === 'nocks_balance' ? null : 'EUR'),
			'amount' => $payment['price'],
			'currency' => $payment['currency'],
			'method' => substr($purchase_data['gateway'], 6),
			'issuer' => isset($_POST['nocks_ideal_issuer']) ? $_POST['nocks_ideal_issuer'] : '',
			'webhookUrl' => $webhook_url,
			'redirectUrl' => $return_url,
		]);

		$transaction_id = $transaction['data']['uuid'];
		$payment_id = $transaction['data']['payments']['data'][0]['uuid'];

		if ($transaction) {
			edd_insert_payment_note($eddPaymentID, 'Transaction ID: ' . $transaction_id);
			edd_insert_payment_note($eddPaymentID, 'Payment ID: ' . $payment_id);
		}

		edd_update_payment_meta($eddPaymentID, 'nocks_tx_id', $transaction_id);
		EDD()->session->set('edd_resume_payment', $eddPaymentID);
		wp_redirect($transaction['data']['payments']['data'][0]['metadata']['url']);
		exit;
	}

	edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
}

add_action('edd_gateway_nocks_gulden', 'edd_nocks_checkout_process_payment');
add_action('edd_gateway_nocks_ideal', 'edd_nocks_checkout_process_payment');
add_action('edd_gateway_nocks_sepa', 'edd_nocks_checkout_process_payment');
add_action('edd_gateway_nocks_balance', 'edd_nocks_checkout_process_payment');

function ebb_nocks_checkout_process_transaction() {
	global $edd_options;

	if (isset($_GET['payment-id'])) {
		$payment_id = absint($_GET['payment-id']);
		$nc = new Nocks_Checkout($edd_options['nocks_checkout_api_key'], $edd_options['nocks_checkout_merchant_account']);
		$transaction = $nc->getTransaction(edd_get_payment_meta($payment_id, 'nocks_tx_id'));
		$payment = new EDD_Payment($payment_id);
		if ($payment->ID > 0 && $payment->status === 'pending') {
			if ($transaction->isPaid()) {
				$payment->update_status('completed');
			} elseif ($transaction->isCancelled()) {
				$payment->update_status('failed');
			}
		}

		return $payment;
    }

	return null;
}

function edd_listen_for_nocks_ipn() {
	if (isset($_GET['edd-listener']) && $_GET['edd-listener'] === 'NOCKS') {
		ebb_nocks_checkout_process_transaction();
		exit;
	}
}

add_action('init', 'edd_listen_for_nocks_ipn');

/**
 * @return string
 */
function edd_nocks_checkout_success_page_content($content) {
	edd_empty_cart();
	$payment = ebb_nocks_checkout_process_transaction();

	if ($payment) {
		ob_start();
		edd_get_template_part('payment', 'processing');
		$content = ob_get_clean();
    }

	return $content;
}

add_filter('edd_payment_confirm_nocks_gulden', 'edd_nocks_checkout_success_page_content');
add_filter('edd_payment_confirm_nocks_ideal', 'edd_nocks_checkout_success_page_content');
add_filter('edd_payment_confirm_nocks_sepa', 'edd_nocks_checkout_success_page_content');
add_filter('edd_payment_confirm_nocks_balance', 'edd_nocks_checkout_success_page_content');

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
			'desc' => __('The Nocks API key needs to following scopes: <strong>transaction.create, transaction.read, merchant.read</strong><br/>If <strong>test modus</strong> is enabled you need a <strong>Nocks Sandbox API key!</strong>', 'easy-digital-downloads'),
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
	);

	return array_merge( $settings, $nocks_checkout_settings );
}
add_filter('edd_settings_gateways', 'edd_nocks_checkout_add_settings');

function edd_nocks_checkout_admin_script() {
	wp_register_script( 'nocks_checkout_admin_script', plugins_url('nocks-checkout/assets/admin.js', dirname(__FILE__)));
	wp_localize_script( 'nocks_checkout_admin_script', 'nocksAdminVars', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
		'loadingMerchantsText' => __('Loading merchants', 'edd_nocks_checkout'),
		'noMerchantsFoundText' => __('No merchants found', 'edd_nocks_checkout'),
		'invalidAccessToken' => __('Invalid access token', 'edd_nocks_checkout'),
	]);
	wp_enqueue_script( 'nocks_checkout_admin_script');
}

add_action('admin_enqueue_scripts', 'edd_nocks_checkout_admin_script');

function edd_nocks_checkout_ajax_get_merchants() {
	$client = new Nocks_Checkout($_POST['accessToken'], null, $_POST['testMode'] === '1');
	$merchants = $client->getMerchants();

	$options = [];
	foreach ($merchants as $key => $label) {
		$options[] = ['value' => $key, 'label' => $label];
	}

	$return = ['merchants' => $options];

	wp_send_json($return);}

add_action('wp_ajax_edd_nocks_get_merchants', 'edd_nocks_checkout_ajax_get_merchants');

function edd_nocks_checkout_ajax_check_access_token() {
	$requiredScopes = ['merchant.read', 'transaction.create', 'transaction.read'];

	$client = new Nocks_Checkout($_POST['accessToken'], null, $_POST['testMode'] === '1');
	$scopes = $client->getTokenScopes();

	$requiredAccessTokenScopes = array_filter($scopes, function($scope) use ($requiredScopes) {
		return in_array($scope['id'], $requiredScopes);
	});

	$return = ['valid'  => sizeof($requiredAccessTokenScopes) === sizeof($requiredScopes)];

	wp_send_json($return);
}

add_action('wp_ajax_edd_nocks_check_access_token', 'edd_nocks_checkout_ajax_check_access_token');
