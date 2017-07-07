<?php

namespace Plugin\GrooaPayment\Models;

use Ip\Exception;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;

class PayPalModel
{

    private static $context;
    private static $useSandbox = true;
    private static $account;

    /**
     * Inits static properties, such as context
     */
    public static function init() {

        $oauth = new \PayPal\Auth\OAuthTokenCredential(
            ipGetOption('GrooaPayment.clientId'),
            ipGetOption('GrooaPayment.secret')
        );

        self::$useSandbox = ipGetOption('GrooaPayment.useSandbox');
        self::$account = ipGetOption('GrooaPayment.account');
        self::$context = new \PayPal\Rest\ApiContext($oauth);
    }

    public static function executePayment($paymentId, $payerId, $transaction)
    {
        if (empty($paymentId) || empty($payerId)) {
            throw new \Ip\Exception('Missing field `paymentID` or `payerID`');
        }

        $payment = Payment::get($paymentId, self::$context);

        $execution = self::createPaymentExecution($payerId);

        $execution->addTransaction($transaction);

        $result = $payment->execute($execution, self::$context);
        $payment = Payment::get($paymentId, self::$context);

        return $payment;
    }

    /**
     *
     * @param \PayPal\Api\Transaction $transaction
     * @return \PayPal\Api\Payment Payment
     * @throws Exception
     */
    public static function createPayment($transaction)
    {
        if (empty($transaction)) {
            throw new Exception("No transaction in payment");
        }

        if (empty($transaction->getInvoiceNumber())) {
            $transaction->setInvoiceNumber(uniqid());
        }

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale')
            ->setRedirectUrls(self::createRedirects(ipConfig()->baseUrl()))
            ->setPayer(self::createPayer())
            ->setTransactions([$transaction]);

        $payment->create(self::$context);

        assert($payment->getState() == 'created', 'Expect PayPal payment to be created');

        return $payment;
    }

    /**
     *
     * @param string $payerID
     * @return \PayPal\Api\PaymentExecution
     */
    private static function createPaymentExecution($payerID) {
        $execution = new PaymentExecution();
        $execution->setPayerId($payerID);
        return $execution;
    }

    /**
     * @return \PayPal\Api\Payer
     */
    private static function createPayer()
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        return $payer;
    }

    /**
     * @param \PayPal\Api\ItemList $items
     * @return \PayPal\Api\Transaction
     */
    private static function createTransaction($amount, $items = null)
    {
        $trans = new Transaction();
        $trans
            ->setAmount($amount)
            ->setDescription("Grooa Online course")
            ->setInvoiceNumber(uniqid());

        if (!empty($items)) {
            $trans->setItemList($items);
        }

        return $trans;
    }

    /**
     * @param string $baseUrl
     * @return \PayPal\Api\RedirectUrls
     */
    private static function createRedirects($baseUrl)
    {
        $redirect = new RedirectUrls();
        $redirect
            ->setReturnUrl("$baseUrl/paypal/success-payment")
            ->setCancelUrl("$baseUrl/paypal/cancel-payment");

        return $redirect;
    }
}
PayPalModel::init();