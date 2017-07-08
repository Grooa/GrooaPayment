<?php
namespace Plugin\GrooaPayment;

class Slot {

    public static function paypalCheckout($params) {
        $params['useSandbox'] = Model\PayPal::getUseSandbox();
        return ipView('view/paypalCheckout.php', $params)->render();
    }
}