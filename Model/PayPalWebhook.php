<?php

namespace Plugin\GrooaPayment;

use PayPal\Api\VerifyWebhookSignature;

/**
 * TODO:ffl - Ensure the process works
 */
class PayPalWebhook
{

    private $apiContext, $webhookId, $signatureVerification;
    private $output = null;

    public function __construct($webhookId, $apiContext)
    {
        $this->webhookId = $webhookId;
        $this->apiContext = $apiContext;
    }

    /**
     * Runs the verification for SignatureVerification,
     * and ensures the verification-status is 'SUCCESS' (alternative is 'FAILURE')
     *
     * @throws \Exception
     * @return PayPalWebhook
     */
    public function verify()
    {
        $output = $this->signatureVerification->post($this->apiContext);

        if ($output->getVerificationStatus() !== 'SUCCESS') {
            throw new \Exception('Webhook failed verification');
        }

        $this->output = $output;
        return $this;
    }


    /**
     * Build the SignatureVerification object, used to verify
     * webhooks received from PayPal.
     *
     * @param array $headers
     * @param string $body
     * @return PayPalWebhook
     */
    public function createSignatureVerification($headers, $body)
    {
        // Transform request-headers to be in uppercase
        $headers = array_change_key_case($headers, CASE_UPPER);

        $signatureVerification = new VerifyWebhookSignature();
        $signatureVerification->setAuthAlgo($headers['HTTP_PAYPAL_AUTH_ALGO']);
        $signatureVerification->setTransmissionId($headers['HTTP_PAYPAL_TRANSMISSION_ID']);
        $signatureVerification->setCertUrl($headers['HTTP_PAYPAL_CERT_URL']);
        $signatureVerification->setWebhookId($this->webhookId);
        $signatureVerification->setTransmissionSig($headers['HTTP_PAYPAL_TRANSMISSION_SIG']);
        $signatureVerification->setTransmissionTime($headers['HTTP_PAYPAL_TRANSMISSION_TIME']);

        $signatureVerification->setRequestBody($body);

        $this->signatureVerification = $signatureVerification;

        return $this;
    }

    public function getOutput()
    {
        return $this->output;
    }
}