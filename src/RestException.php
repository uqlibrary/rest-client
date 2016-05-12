<?php

namespace Terah\RestClient;

class RestException extends \Exception
{
    protected $response = null;

    public function __construct($message='', $code=0, \Exception $previous=null, RestResponse $response)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return RestResponse
     */
    public function getResponse()
    {
        return $this->response;
    }
}