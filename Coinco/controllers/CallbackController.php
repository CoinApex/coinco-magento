<?php

/* curl -X POST --data '{"callbackData":"{\"id\":\"100000006\", \"secret_key\":\"443aa1ac4011a0cadaca1481744bb269e29b742a7c242dd6cb2b2a9426623974\"}"}' http://localhost:8000/index.php/coinco_coinco/callback/callback */

class Coinco_Coinco_CallbackController extends Mage_Core_Controller_Front_Action {        
    public function callbackAction() {
        $json = json_decode(file_get_contents('php://input'), true);
        $callback_data = json_decode($json['callbackData'], true);
        $secret_key = Mage::getStoreConfig('payment/Coinco/callback_secret');

        if (!array_key_exists('secret_key', $callback_data) || $callback_data['secret_key'] != $secret_key) {
            Mage::log('Missing or invalid "secret_key" field from CoinCo callback');
            Mage::throwException('Missing or invalid "secret_key" field from CoinCo callback');
        }

        if (!array_key_exists('id', $callback_data) || !Mage::getModel('sales/order')->load($callback_data['id'])) {
            Mage::log('Missing or invalid "id" field from CoinCo callback');
            Mage::throwException('Missing or invalid "id" field from CoinCo callback');
        }


        $order = Mage::getModel('sales/order')->load($callback_data['id'], 'increment_id');

        switch (strtolower($json['invoiceStatus'])):
            case 'viewed':
                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true);
                break;
            case 'paid':
                $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
                break;
            case 'confirmed':
            case 'completed':
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
                break;
            case 'invalid':
            case 'expired':
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                break;
            default:
                Mage::log('Got unrecognized order status from Coin.Co');
                Mage::throwException('Got unrecognized order status from Coin.Co');
        endswitch;

        $order->save();
    }
}

?>
