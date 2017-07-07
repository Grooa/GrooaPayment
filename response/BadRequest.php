<?php
namespace Plugin\GrooaPayment\Response;

class BadRequest extends RestError {

    public function __construct($error)
    {
        parent::__construct($error, 400);
    }

    public function setStatusCode($code)
    {
        return parent::setStatusCode(400); // Bad request is always 400
    }

}