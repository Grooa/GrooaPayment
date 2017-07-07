<?php

$routes['paypal/create-payment'] = [
    'name' => 'GrooaPayment_createPayment',
    'controller' => 'SiteController',
    'action' => 'createPayment'
];

$routes['paypal/execute-payment'] = [
    'name' => 'GrooaPayment_executePayment',
    'controller' => 'SiteController',
    'action' => 'executePayment'
];

$routes['paypal/success-payment'] = [
    'name' => 'GrooaPayment_paymentSuccess',
    'controller' => 'SiteController',
    'action' => 'successPayment'
];

$routes['paypal/cancel-payment'] = [
    'name' => 'GrooaPayment_paymentCancel',
    'controller' => 'SiteController',
    'action' => 'cancelPayment'
];