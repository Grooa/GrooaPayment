<?php
namespace Plugin\GrooaPayment\Response;

class RestError extends \Ip\Response\Json {

    /**
     * @param mixed $data
     * @param int $statusCode
     */
    public function __construct($error, $statusCode = 500)
    {
        parent::__construct(['error' => $error]);
        parent::setStatusCode($statusCode);
    }


}