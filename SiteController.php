<?php

namespace Plugin\GrooaPayment;

use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Transaction;
use PayPal\Exception\PayPalConnectionException;
use Plugin\Track\Models\TrackModel;

use Plugin\GrooaPayment\Models\PayPalModel;
use Plugin\GrooaPayment\Response\BadRequest;
use Plugin\GrooaPayment\Response\RestError;
use Plugin\GrooaPayment\Models\TrackOrderModel;

class SiteController
{
    /**
     * Accepts the trackId from the query-param `track`,
     * and creates a PayPal payment with the correct course information,
     * to the correct user.
     *
     * The payment information is also stored locally in `ip_track_order`
     * with the generated `paymentId`, `trackId` and `userId`, which is later
     * referenced in `executePayment()`
     *
     */
    public function createPayment()
    {
        ipRequest()->mustBePost();

        if (!ipUser()->isLoggedIn()) {
            return new RestError("Action requires a registered user", 403);
        }

        try {
            $trackId = self::getTrackIdFromQuery(ipRequest()->getQuery());
        } catch (\Ip\Exception $e) {
            return new RestError($e->getMessage(), $e->getCode());
        }

        // Prevent double-purchasing of tracks
        if (TrackOrderModel::hasPurchased($trackId, ipUser()->userId())) {
            return new BadRequest("User already own this track ($trackId)");
        }

        try {
            $transaction = self::generateTransactionForTrack($trackId);
            $payment = PayPalModel::createPayment($transaction);
        } catch (PayPalConnectionException $pce) {
            return new RestError($pce->getData(), $pce->getCode());
        } catch (\Exception $e) {
            return new BadRequest($e->getMessage());
        }

        $id = $payment->getId(); // paymentID

        TrackOrderModel::create([
            'userId' => ipUser()->userId(),
            'trackId' => $trackId,
            'paymentId' => $id,
            'invoiceNumber' => $transaction->getInvoiceNumber()
        ]);

        return new \Ip\Response\Json(['paymentID' => $id]);
    }

    /**
     * With the values generated in createPayment, calls PayPal
     * to execute the payment.
     * Sale ids and timestamps are stored when completed
     *
     * @return \Ip\Response\Json | \Plugin\GrooaPayment\Response\RestError
     */
    public function executePayment()
    {
        ipRequest()->mustBePost();

        if (!ipUser()->isLoggedIn()) {
            return new RestError("Action requires a registered user", 403);
        }

        try {
            $body = self::getPaymentIdAndPayerId(ipRequest()->getPost());
            $paymentId = $body['paymentID'];
            $payerId = $body['payerID'];

            $trackId = self::getTrackIdFromQuery(ipRequest()->getQuery());

            // Get the order created in createPayment
            $order = self::getTrackOrder($trackId, ipUser()->userId());
        } catch (\Ip\Exception $e) {
            return new RestError($e->getMessage(), $e->getCode());
        }

        // Expect paymentId, from createPayment, to match
        // paymentId from PayPal
        $oldId = $order['paymentId'];
        if ($oldId != $paymentId) {
            return new BadRequest("Stored paymentId: $oldId, does not match paypal's paymentId: $paymentId");
        }

        try {
            $transaction = self::generateTransactionForTrack($trackId);
            $payment = PayPalModel::executePayment( // Execute payment
                $paymentId,
                $payerId,
                $transaction
            );
        } catch(\Ip\Exception $e) {
            return new BadRequest($e->getMessage());
        } catch (\Exception $e) {
            ipLog()->error($e->getMessage() . " ", $e);
            return new RestError($e->getMessage(), $e->getCode());
        }

        return self::handleCompletedPayment($payment, $payerId, $order);
    }

    public function deniedPayment() {
        die("Payment denied");
    }

    public function successPayment()
    {
        die('Payment successful');
        die(json_encode(ipRequest()->getPost()));
    }

    public function cancelPayment()
    {
        die('Payment cancelled');
        die(json_encode(ipRequest()->getPost()));
    }

    /**
     * Called when payment has been _executed_.
     * Updates the `track_order`, with the values from the execution
     * and a timestamp.
     *
     *
     * @param \PayPal\Api\Payment $payment
     * @param string $payerId
     * @param mixed $order
     * @return \Ip\Response\Json|RestError | \Ip\Response\Json
     */
    private static function handleCompletedPayment($payment, $payerId, $order) {
        $sale = $payment->getTransactions()[0]
            ->getRelatedResources()[0]
            ->getSale();

        $saleId = $sale->getId();
        $saleState = $sale->getState();

        if ($saleState == 'pending' || $saleState == 'completed') {
            // The payment is now treated completed (even though state can be pending)
            TrackOrderModel::update($order['orderId'], [
                'payerId' => $payerId,
                'saleId' => $saleId,
                'state' => $saleState,
                'paymentExecuted' => date('Y-m-d H:i:s')
            ]);

            if ($saleState == 'completed') {
                // Completes the order, and stores it's timestamp
                TrackOrderModel::completeOrder($order['orderId']);
            }
        } else {
            // If it's not completed or pending it could be an error
            TrackOrderModel::update($order['orderId'], ['state' => 'cancelled']);
            return new BadRequest("Invalid purchase state: $saleState");
        }

        return new \Ip\Response\Json(['saleId' => $saleId, 'state' => $saleState]);
    }

    /**
     * Will find the correct track, and convert it
     * to a PayPal-friendly Transaction object
     *
     * @param int $trackId
     * @throws \Exception
     * @return \PayPal\Api\Transaction
     */
    private function generateTransactionForTrack($trackId)
    {
        $track = TrackModel::get($trackId);

        if (empty($track)) {
            throw new \Ip\Exception("No track with id: $trackId");
        }

        $amount = new Amount();
        $amount->setCurrency('EUR')// We work with Euro
        ->setTotal($track['price']);

        $item = new Item();
        $item->setCurrency('EUR')
            ->setQuantity(1)
            ->setName($track['title'])
            ->setDescription($track['shortDescription'])
            ->setPrice($track['price']);

        $list = new ItemList();
        $list->addItem($item);

        $transaction = new Transaction();
        $transaction
            ->setDescription("Online courses at grooa.com")
            ->setItemList($list)
            ->setAmount($amount);

        return $transaction;
    }

    /**
     * Validates that `paymentID` and `payerID` was provided in post-body
     *
     * @param $body
     * @return mixed
     * @throws \Ip\Exception
     */
    private static function getPaymentIdAndPayerId($body) {
        if (empty($body['paymentID'])) {
            throw new \Ip\Exception("Missing field `paymentID`", null, 400);
        } else if (empty($body['payerID'])) {
            throw new \Ip\Exception("Missing field `payerID`", null, 400);
        }

        return $body;
    }

    /**
     * Validates that `track` is provided as query-param,
     * and is numeric.
     *
     * @param mixed $query
     * @return int
     * @throws \Ip\Exception
     */
    private static function getTrackIdFromQuery($query) {
        if (empty($query['track'])) {
            throw new \Ip\Exception("Missing query-param `track`", null, 400);
        }

        if (!is_numeric($query['track'])) {
            throw new \Ip\Exception("Query-param `track` isn't numeric", null, 400);
        }

        return $query['track'];
    }

    /**
     * Validates that a `track_order` exists with the
     * provided `trackId` and `userId`.
     *
     * @param int $trackId
     * @param int $userId
     * @return array
     * @throws \Ip\Exception
     */
    private static function getTrackOrder($trackId, $userId) {
        $order = TrackOrderModel::getByTrackAndUser($trackId, $userId);

        if (empty($order)) {
            throw new \Ip\Exception(
                "User has not yet created an paypal-payment for the track: $trackId",
                null,
                400);
        }

        return $order;
    }
}