<?php

namespace Terah\RestClient\ApiClient;

use function Terah\Assert\Assert;
use function Terah\Assert\Validate;


class RestClient
{
    const FETCH             = 'GET';
    const INSERT            = 'POST';
    const UPDATE            = 'PUT';
    const DELETE            = 'DELETE';

    /** @var int $serviceUrl */
    protected $serviceUrl   = null;

    /** @var string $accessToken */
    protected $accessToken  = null;

    /** @var string $authHeader */
    protected $authHeader   = 'X-Auth-Token';

    /** @var string $authHeader */
    protected $metaHeader   = 'X-Auth-Meta';

    /** @var string $method */
    protected $method       = 'GET';

    /** @var string $format */
    protected $format       = 'json';

    /** @var string $version */
    protected $version      = '1.0';

    /** @var array $headers */
    protected $headers      = [];

    /** @var array $data */
    protected $data         = [];

    /** @var bool  */
    protected $verbose      = false;

    /** @var array */
    protected $credentials  = [];

    /** @var resource */
    protected $curlObj      = null;

    /** @var string */
    protected $exceptionType = 'Terah\RestClient\RestException';

    /** @var null Used for error tracing */
    protected $curlUrl      = null;

    /** @var mixed Data to be sent to the service */
    protected $curlData     = null;

    /** @var RestResponse */
    protected $response     = null;

    /**
     * @param string $serviceUrl
     * @param string $accessToken
     * @param string $authHeader
     * @param string $username
     * @param string $password
     */
    public function __construct($serviceUrl, $accessToken=null, $authHeader=null, $username=null, $password=null)
    {
        Assert($serviceUrl)->notEmpty()->url('The service URL was not specified');
        Assert($accessToken)->nullOr()->notEmpty('The auth token was not specified');
        $this->serviceUrl       = $serviceUrl;//preg_replace('/\/$/', '', $serviceUrl) . '/';
        $this->accessToken      = $accessToken;
        $this->authHeader       = ! is_null($authHeader) ? $authHeader : $this->authHeader;
        $this->credentials($username, $password);
        $this->reset();
    }

    /**
     * @param $name
     * @param $value
     *
     * @return $this
     */
    public function header($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @param array $headers
     *
     * @return $this
     */
    public function headers(array $headers)
    {
        foreach ( $headers as $name => $value )
        {
            $this->header($name, $value);
        }
        return $this;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function data(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param null|string $username
     * @param null|string $password
     * @return $this
     */
    public function credentials($username, $password)
    {
        Assert($username)->nullOr()->notEmpty();
        Assert($password)->nullOr()->notEmpty();
        $this->credentials = [$username, $password];
        return $this;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->data     = [];
        $this->method   = 'GET';
        $this->curlObj  = null;
        $this->response = null;
        $this->format('json');
        $this->header('X-Api-Version', '1.0');
        return $this;
    }

    /**
     * @param $method
     *
     * @return $this
     */
    public function method($method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * @param string $format
     *
     * @return $this
     */
    public function format($format)
    {
        $this->format = ! in_array($format, ['json', 'xml', 'text', 'png', 'any']) ? 'json' : $format;
        return $this;
    }

    /**
     * @param $version
     *
     * @return $this
     */
    public function version($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @param $verbose
     *
     * @return $this
     */
    public function verbose($verbose=true)
    {
        Assert($verbose)->boolean();
        $this->verbose = $verbose;
        return $this;
    }

    /**
     * @param $exception
     *
     * @return $this
     */
    public function exception($exception)
    {
        //Assert($verbose)->boolean();
        $this->exceptionType = $exception;
        return $this;
    }

    /**
     * @param $entity
     * @return mixed|null
     * @throws \Exception
     */
    public function sendRequest($entity=null)
    {
        $result = $this
            ->setCurlOpt(CURLOPT_URL, $this->getUrl($entity))
            ->setCurlOpt(CURLOPT_HTTPHEADER, $this->getHeaders())
            ->setCurlOpt(CURLOPT_HEADER, 1)
            ->setCurlOpt(CURLOPT_RETURNTRANSFER, true)
            ->setCurlOpt(CURLOPT_VERBOSE, $this->verbose ? 1 : 0)
            ->setCurlOpt(CURLOPT_SSL_VERIFYHOST, false)
            ->setCurlOpt(CURLOPT_SSL_VERIFYPEER, false)
            ->setCurlOpt(CURLOPT_CUSTOMREQUEST, $this->method)
            ->setCurlOpt(CURLOPT_ENCODING, '')
            ->setCurlBasicAuth()
            ->setCurlCookies()
            ->setCurlData($this->method,  $this->data, $this->format)
            ->curlExec($this->format);
        $this->reset();
        return $result;
    }

    /**
     * @param int $opt
     * @param mixed $val
     * @return $this
     */
    protected function setCurlOpt($opt, $val)
    {
        $this->curlObj = is_null($this->curlObj) ? curl_init() : $this->curlObj;
        curl_setopt($this->curlObj, $opt, $val);
        return $this;
    }

    /**
     * @param string $method
     * @param array|string $data
     * @param null $format
     * @return $this
     */
    protected function setCurlData($method, $data, $format=null)
    {
        if ( ! in_array(strtoupper($method), ['POST', 'PUT']) )
        {
            return $this;
        }
        if ( $format === 'json' )
        {
            $this->curlData = is_array($data) ? json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : $data;
            return $this->setCurlOpt(CURLOPT_POST, true)->setCurlOpt(CURLOPT_POSTFIELDS, $this->curlData);
        }
        if ( $format === 'xml' )
        {
            if ( is_array($data) )
            {
                $xmlObj = new \SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");
                $this->arrayToXml($data, $xmlObj);
                $this->curlData   = $xmlObj->asXML();
            }
            return $this->setCurlOpt(CURLOPT_POST, true)->setCurlOpt(CURLOPT_POSTFIELDS, $this->curlData);
        }
        $this->curlData = is_array($data) ? http_build_query($data) : $data;
        return $this->setCurlOpt(CURLOPT_POST, true)->setCurlOpt(CURLOPT_POSTFIELDS, $this->curlData);
    }

    /**
     * @return $this|RestClient
     */
    protected function setCurlBasicAuth()
    {
        list($username, $password) = $this->credentials;
        if ( ! is_null($username) )
        {
            return $this->setCurlOpt(CURLOPT_USERPWD, "{$username}:{$password}");
        }
        return $this;
    }


    /**
     * @return $this|RestClient
     */
    protected function setCurlCookies()
    {
        // This allows me to debug api request originating from the phpunit test case
        if ( ! empty($_SERVER['PHP_IDE_CONFIG']) && empty($_SERVER['XDEBUG_CONFIG']) )
        {
            return $this->setCurlOpt(CURLOPT_COOKIE, "XDEBUG_SESSION=PHPSTORM");
        }
        return $this;
    }

    /**
     * @param array $data
     * @param \SimpleXMLElement $xmlObj
     */
    protected function arrayToXml($data, &$xmlObj)
    {
        foreach ( $data as $key => $value )
        {
            if ( ! is_array($value) )
            {
                $xmlObj->addChild("$key", htmlspecialchars("$value"));
                continue;
            }
            if ( ! is_numeric($key) )
            {
                $subnode = $xmlObj->addChild("$key");
                $this->arrayToXml($value, $subnode);
                continue;
            }
            $subnode = $xmlObj->addChild("item$key");
            $this->arrayToXml($value, $subnode);
        }
    }

    /**
     * @param string $format
     * @return mixed
     * @throws \Exception
     */
    protected function curlExec($format)
    {
        $this->response = $this->parseResponse(
            curl_exec($this->curlObj),
            curl_getinfo($this->curlObj, CURLINFO_HTTP_CODE),
            curl_error($this->curlObj),
            curl_errno($this->curlObj)
        );

        if ( $this->response->isError() )
        {
            $exceptionType  = $this->exceptionType;
            throw new $exceptionType($this->response->getNotification(), $this->response->getHttpStatusCode(), null, $this->response);
        }
        return $format === 'json' ? json_decode($this->response->body, false) : $this->response->body;
    }

    /**
     * @param string $response
     * @param int $httpStatusCode
     * @param string $curlError
     * @param int $curlErrorNo
     * @return RestResponse
     */
    protected function parseResponse($response, $httpStatusCode, $curlError, $curlErrorNo)
    {
        list($headers, $body)       = $this->_parseHeadersAndBody($response);
        $response                   = isset($headers[$this->metaHeader]) ? json_decode($headers[$this->metaHeader], true) : [];
        $response                   = is_array($response) ? $response : [];
        unset($headers[$this->metaHeader]);
        $response['headers']        = $headers;
        $response['body']           = $body;
        $response['status']         = $httpStatusCode;
        $response['curlError']      = $curlError;
        $response['curlErrorNo']    = $curlErrorNo;
        return new RestResponse($response);
    }

    /**
     * @param $responseText
     * @return array
     */
    protected function _parseHeadersAndBody($responseText)
    {
        if ( ! $responseText )
        {
            return [[], ''];
        }
        list($headerData, $body)    = explode("\r\n\r\n", $responseText, 2);
        $headerData                 = explode("\r\n", $headerData);
        $headers                    = [];
        foreach ( $headerData as $line )
        {
            $line               = trim($line);
            if ( strpos($line, ':') === false )
            {
                $headers[$line]     = $line;
                continue;
            }
            list($name, $text)  = explode(':', trim($line), 2);
            $headers[trim($name)]     = trim($text);
        }
        return [$headers, $body];
    }

    /**
     * @param $entity
     * @return string
     */
    protected function getUrl($entity)
    {
        $entity         = preg_replace('/^\//', '', $entity);
        $this->curlUrl  = $this->serviceUrl . $entity;

        if ( $this->method === 'GET' && ! empty($this->data) )
        {
            $this->curlUrl = rtrim($this->curlUrl, '?') . '?' . http_build_query($this->data);
        }
        return $this->curlUrl;
    }

    /**
     * @return array
     */
    protected function getHeaders()
    {
        $types      = [
            'json'      => 'application/json',
            'xml'       => 'application/xml',
            'png'       => 'image/png',
            'any'       => '*/*',
        ];
        $this->header('Accept', $types[$this->format]);
        $types['any']   = 'application/x-www-form-urlencoded';
        $this->header('Content-Type', $types[$this->format]);
        if ( $this->accessToken )
        {
            $this->header($this->authHeader, $this->accessToken);
        }
        $headers = [];
        foreach ( $this->headers as $name => $value )
        {
            if ( $value )
            {
                $headers[$name] = "{$name}:{$value}";
            }
        }
        return $headers;
    }
}

