<?php


if (!function_exists('curl_init')) {
    Mage::throwException("The Coinco client library requires the CURL PHP extension.");
}


function post($url, $params) {
    $curl = curl_init();
    $opts = array(
        CURLOPT_VERBOSE => true,
        CURLOPT_URL => $url,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => array('Content-Type'=>'application/json'),
        CURLOPT_RETURNTRANSFER => true,
    );

    curl_setopt_array($curl, $opts);
    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_errno($curl);
        $message = curl_error($curl);
        curl_close($curl);
        Mage::throwException("Network error " . $message . " (" . $error . ")");
    }

    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($statusCode != 200)
        Mage::throwException("Status code " . $statusCode);

    try {
        $json = json_decode($response, true);
    } catch (Exception $e) {
        Mage::throwException('Invalid response body ' . $statusCode . ' ' . $response);
    }

    if ($json === null)
        Mage::throwException('Invalid response body ' . $statusCode . ' ' . $response);

    if (isset($json->error))
        Mage::throwException($json->error . ' ' . $statusCode . ' ' . $response);

    else if (isset($json->errors))
        Mage::throwException(implode($json->errors, ', ') . ' ' . $statusCode . ' ' . $response);

    return $json;
}


class Coinco_Coinco_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract {
    protected $_code = 'Coinco';

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture              = false;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;

    /**
     * Can refund online?
     */
    protected $_canRefund               = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = false;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;


    // See http://stackoverflow.com/questions/5366551/magento-payment-flow
    // This function is also referenced from etc/config.xml
    public function authorize(Varien_Object $payment, $amount) {
        $apiKey = Mage::getStoreConfig('payment/Coinco/api_key');
        $callbackSecret = Mage::getStoreConfig('payment/Coinco/callback_secret');
        $testing = Mage::getStoreConfig('payment/Coinco/testing');

        if ($apiKey == null) {
            Mage::throwException('Before using the Coinco plugin, you need to enter an API Key in Magento Admin > Configuration > System > Payment Methods > Coinco.');
        }

        if ($callbackSecret == "generate") {
            Mage::getModel('core/config')->saveConfig('payment/Coinco/callback_secret', hash('sha256', uniqid()))->cleanCache();
            Mage::app()->getStore()->resetConfig();
        }

        $url = 'https://coin.co/1/createInvoice';
        if ($testing === '1')
            $url = 'https://sandbox.coin.co/1/createInvoice';

        $order = $payment->getOrder();
        $customer = $order->getBillingAddress()->getData();

        try {
            // Refer to https://coin.co/developers/endpoints for information on
            // the request parameters.
            $response = post($url, array(
                'APIAccessKey' => $apiKey,
                'currencyType' => $order->getBaseCurrencyCode(),
                'amountInSpecifiedCurrencyType' => $amount,
                'notificationURL' => '',
                /* 'notificationURL' => Mage::getUrl('coinco_coinco'). 'callback/callback/', */
                'setStatusViewed' => 'True',
                'callbackData' => json_encode(array('id'=>$order->getId(), 'secret_key'=>$callbackSecret)),
                'customerRedirectURL' => Mage::getUrl('coinco_coinco') . 'redirect/success/',
                'refundAddress' => '',
                'buyerName' => base64_encode($customer['firstname'].' '.$customer['middlename'].' '.$customer['lastname']),
                'buyerAddress1' => base64_encode($customer['street']),
                'buyerAddress2' => '',
                'buyerCity' => base64_encode($customer['city']),
                'buyerState' => base64_encode($customer['region']),
                'buyerZip' => base64_encode($customer['postcode']),
                'buyerCountry' => base64_encode($customer['country_id']),
                'buyerEmail' => base64_encode($customer['email']),
                'buyerPhone' => base64_encode($customer['telephone']),
            ));
        } catch (Exception $e) {
            Mage::throwException("Could not generate checkout page " . $e->getMessage());
        }

        $order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
        $order->save();
        Mage::getSingleton('customer/session')->setRedirectUrl($response['invoiceURL']);
        return $this;
    }


    /**
     * Return Order place redirect url. Returns a redirect URL after the
     * invoice has been paid? Who knows?
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getSingleton('customer/session')->getRedirectUrl();
    }
}
?>
