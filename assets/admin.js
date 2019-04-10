var $j = jQuery.noConflict();

$j(document).ready(function() {
    var merchantsTimeout = null;
    var accessTokenTimeout = null;

    var $testModeInput = $j('#edd_settings\\[test_mode\\]');

    var $merchantSelect = $j('#edd_settings\\[nocks_checkout_merchant_account\\]');
    // $merchantSelect.prop('required',true);
    $merchantSelect.before('<p id="payment_nockspaymentgateway_merchants_message" style="display: none; color: red"></p>');

    var $accessTokenInput = $j('#edd_settings\\[nocks_checkout_api_key\\]');
    // $accessTokenInput.prop('required', true);
    $accessTokenInput.before('<p id="payment_nockspaymentgateway_accesstoken_message" style="display: none; color: red">' + nocksAdminVars.invalidAccessToken + '</p>');

    if ($merchantSelect.find('option').length === 0) {
        $j('#payment_nockspaymentgateway_merchants_message')
            .html(nocksAdminVars.noMerchantsFoundText)
            .show();
    }

    function getMerchants() {
        $merchantSelect.prop('disabled', true);
        $j('#payment_nockspaymentgateway_merchants_message')
            .html(nocksAdminVars.loadingMerchantsText)
            .show();

        clearTimeout(merchantsTimeout);
        merchantsTimeout = setTimeout(function() {
            var testmode = $testModeInput.is(':checked');
            var accessToken = $accessTokenInput.val();

            $j.ajax({
                method: 'POST',
                url: nocksAdminVars.ajaxUrl,
                data: {
                    action: 'edd_nocks_get_merchants',
                    accessToken: accessToken,
                    testMode: testmode ? '1' : '0'
                }
            }).done(function(data) {
                $merchantSelect.find('option').remove().end();
                $merchantSelect.prop('disabled', false);

                if (data.merchants.length > 0) {
                    for (var i = 0; i < data.merchants.length; i++) {
                        var merchant = data.merchants[i];
                        $merchantSelect.append('<option value="' + merchant.value + '">' + merchant.label + '</option>');
                    }

                    $j('#payment_nockspaymentgateway_merchants_message').hide();
                } else {
                    $j('#payment_nockspaymentgateway_merchants_message')
                        .html(nocksAdminVars.noMerchantsFoundText)
                        .show();
                }
            });
        }, 200);
    }

    function checkAccessToken() {
        clearTimeout(accessTokenTimeout);
        accessTokenTimeout = setTimeout(function() {
            var testmode = $testModeInput.is(':checked');
            var accessToken = $accessTokenInput.val();

            $j.ajax({
                method: 'POST',
                url: nocksAdminVars.ajaxUrl,
                data: {
                    action: 'edd_nocks_check_access_token',
                    accessToken: accessToken,
                    testMode: testmode ? '1' : '0'
                }
            }).done(function(data) {
                if (data.valid) {
                    $j('#payment_nockspaymentgateway_accesstoken_message').hide();
                } else {
                    $j('#payment_nockspaymentgateway_accesstoken_message').show();
                }
            });
        }, 200);
    }

    $testModeInput.on('change', function() {
        checkAccessToken();
        getMerchants();
    });

    checkAccessToken();
    var lastAccessToken = null;
    $accessTokenInput.on('change keyup', function() {
        var val = $j(this).val();

        if (lastAccessToken !== val) {
            checkAccessToken();
            getMerchants();
            lastAccessToken = val;
        }
    });
});