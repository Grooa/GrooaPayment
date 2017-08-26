<?php

namespace Plugin\GrooaPayment;

use Ip\Exception;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Transaction;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Exception\PayPalConnectionException;
use Plugin\Track\Model\Track;

use Plugin\GrooaPayment\Model\PayPal;
use Plugin\GrooaPayment\Model\TrackOrder;
use Plugin\GrooaPayment\Response\BadRequest;
use Plugin\GrooaPayment\Response\RestError;

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
        try {
            self::validateRequest();
        } catch (Exception $e) {
            return new RestError($e->getMessage(), $e->getCode());
        }

        if (!ipUser()->isLoggedIn()) {
            return new RestError("Action requires a registered user", 403);
        }

        try {
            $trackId = self::getTrackIdFromQuery(ipRequest()->getQuery());
        } catch (Exception $e) {
            return new RestError($e->getMessage(), $e->getCode());
        }

        // Prevent double-purchasing of tracks
        if (TrackOrder::hasPurchased($trackId, ipUser()->userId())) {
            return new BadRequest("User already own this track ($trackId)");
        }

        try {
            $transaction = self::generateTransactionForTrack($trackId);
            $payment = PayPal::createPayment($transaction);
        } catch (PayPalConnectionException $pce) {
            return new RestError($pce->getData(), $pce->getCode());
        } catch (\Exception $e) {
            return new BadRequest($e->getMessage());
        }

        $id = $payment->getId(); // paymentID

        TrackOrder::create([
            'userId' => ipUser()->userId(),
            'trackId' => $trackId,
            'paymentId' => $id,
            'invoiceNumber' => $transaction->getInvoiceNumber(),
            'isSandbox' => ipGetOption('GrooaPayment.useSandbox', false)
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
        try {
            self::validateRequest();
        } catch (Exception $e) {
            return new RestError($e->getMessage(), $e->getCode());
        }

        if (!ipUser()->isLoggedIn()) {
            return new RestError("Action requires a registered user", 403);
        }

        try {
            $body = self::getPaymentIdAndPayerId(ipRequest()->getPost());
            $paymentId = $body['paymentID'];
            $payerId = $body['payerID'];

            $trackId = self::getTrackIdFromQuery(ipRequest()->getQuery());

            // Get the order created in createPayment
            $order = self::getTrackOrder($trackId, ipUser()->userId(), $paymentId);
        } catch (Exception $e) {
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
            $payment = PayPal::executePayment( // Execute payment
                $paymentId,
                $payerId,
                $transaction
            );
        } catch (Exception $e) {
            return new BadRequest($e->getMessage());
        } catch (\Exception $e) {
            ipLog()->error($e->getMessage() . " ", $e);
            return new RestError($e->getMessage(), $e->getCode());
        }

        return self::handleCompletedPayment($payment, $payerId, $order);
    }

    /**
     * Build the SignatureVerification object, used to verify
     * webhooks received from PayPal.
     *
     * @param array $headers
     * @param string $body
     * @return \PayPal\Api\VerifyWebhookSignature
     */
    private function createSignatureVerification($headers, $body)
    {
        // Transform request-headers to be in uppercase
        $headers = array_change_key_case($headers, CASE_UPPER);

        $signatureVerification = new VerifyWebhookSignature();
        $signatureVerification->setAuthAlgo($headers['HTTP_PAYPAL_AUTH_ALGO']);
        $signatureVerification->setTransmissionId($headers['HTTP_PAYPAL_TRANSMISSION_ID']);
        $signatureVerification->setCertUrl($headers['HTTP_PAYPAL_CERT_URL']);
        $signatureVerification->setWebhookId("2WU59986VH019783E"); // TODO:ffl - Replace with value from config
        $signatureVerification->setTransmissionSig($headers['HTTP_PAYPAL_TRANSMISSION_SIG']);
        $signatureVerification->setTransmissionTime($headers['HTTP_PAYPAL_TRANSMISSION_TIME']);

        $signatureVerification->setRequestBody($body);

        return $signatureVerification;
    }


    public function successPayment()
    {
        // Only allow post requests
        ipRequest()->mustBePost();

        $requestBodyRaw = file_get_contents('php://input'); // Request body
        $requestBody = json_decode($requestBodyRaw, true);

        // No point to process an empty webhook. Also this method only
        // Supports "completed sales" webhooks.
        if (empty($requestBody) || $requestBody['event_type'] !== 'PAYMENT.SALE.COMPLETED') {
            ipLog()->warning('Unknown webhook event type', $requestBody);
            return new RestError('Unknown event type', 400);
        }

        $signatureVerification = self::createSignatureVerification(
            ipRequest()->getServer(),
            $requestBodyRaw
        );

        $invoiceNumber = $requestBody['resource']['invoice_number'];
        $summary = $requestBody['summary'];

        $request = clone $signatureVerification;
        $output = null;

        try {
            // Run the verification of the Webhook
            $output = $signatureVerification->post(PayPal::getApiContext());
        } catch (\Exception $e) {
            ipLog()->error("Problem validating the webhook for invoice: ${invoiceNumber}", [
                'invoice' => $invoiceNumber,
                'webhookSummary' => $summary,
                'error' => $e->getMessage(),
                'data' => $e->getData()
            ]);

            return new RestError('Unknown Error', 500);
        }

        $status = $output->getVerificationStatus();

        // Catch all webhooks which ISN'T verified
        if ($status !== 'SUCCESS') {
            ipLog()->notice("Webhook for invoice: ${invoiceNumber} failed validation", [
                'status' => !empty($output) ? $output->getVerificationStatus() : '[failed]',
                'request' => $request->toJSON(),
                'invoice' => $invoiceNumber,
                'webhookSummary' => $summary,
                'output' => $output
            ]);
            return new RestError('Bad invoice', 400); // Hide response
        }

        TrackOrder::completeOrder($invoiceNumber); // Update the payment to completed

        ipLog()->info("Invoice: ${invoiceNumber} validated", [
            'message' => "SALE completed for invoice: ${invoiceNumber}",
            'summary' => $summary
        ]);

        return new \Ip\Response\Json([]); // Return an empty body
    }

    public function cancelPayment()
    {
        $requestBodyRaw = file_get_contents('php://input');
        $requestBody = json_decode($requestBodyRaw, true);

        $data = [
            'body' => $requestBodyRaw,
            'server' => ipRequest()->getServer()
        ];

        if (!ipRequest()->isPost()) {
            ipLog()->warning('GrooaPayment_cancelPaymen: Called webhook without post', $data);
            return new RestError('Forbidden', 403);
        }

        ipLog()->info('GrooaPayment_cancelPayment', $data);

        return new \Ip\Response\Json(['message' => 'hello']);
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
    private static function handleCompletedPayment($payment, $payerId, $order)
    {
        $sale = $payment->getTransactions()[0]
            ->getRelatedResources()[0]
            ->getSale();

        $saleId = $sale->getId();
        $saleState = $sale->getState();

        if ($saleState == 'pending' || $saleState == 'completed') {
            // The payment is now treated completed (even though state can be pending)
            TrackOrder::update($order['orderId'], [
                'payerId' => $payerId,
                'saleId' => $saleId,
                'state' => $saleState,
                'paymentExecuted' => date('Y-m-d H:i:s')
            ]);

            if ($saleState == 'completed') {
                // Completes the order, and stores it's timestamp
                TrackOrder::completeOrder($order['orderId']);
            }
        } else {
            // If it's not completed or pending it could be an error
            TrackOrder::update($order['orderId'], ['state' => 'cancelled']);
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
        $track = Track::get($trackId);

        if (empty($track)) {
            throw new Exception("No track with id: $trackId");
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

    private static function validateRequest()
    {
        ipRequest()->mustBePost();

        // Env is production, and doesn't use https
        if (!PayPal::getUseSandbox() && !ipRequest()->isHttps()) {
            throw new Exception("Request must use https in production", 403);
        }
    }

    /**
     * Validates that `paymentID` and `payerID` was provided in post-body
     *
     * @param $body
     * @return mixed
     * @throws Exception
     */
    private static function getPaymentIdAndPayerId($body)
    {
        if (empty($body['paymentID'])) {
            throw new Exception("Missing field `paymentID`", null, 400);
        } else if (empty($body['payerID'])) {
            throw new Exception("Missing field `payerID`", null, 400);
        }

        return $body;
    }

    /**
     * Validates that `track` is provided as query-param,
     * and is numeric.
     *
     * @param mixed $query
     * @return int
     * @throws Exception
     */
    private static function getTrackIdFromQuery($query)
    {
        if (empty($query['track'])) {
            throw new Exception("Missing query-param `track`", null, 400);
        }

        if (!is_numeric($query['track'])) {
            throw new Exception("Query-param `track` isn't numeric", null, 400);
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
     * @throws Exception
     */
    private static function getTrackOrder($trackId, $userId, $paymentId)
    {
        $order = TrackOrder::getByTrackUserAndPaymentId($trackId, $userId, $paymentId);

        if (empty($order)) {
            throw new Exception(
                "User has not yet created an paypal-payment for the track: $trackId",
                null,
                400);
        }

        return $order;
    }
}