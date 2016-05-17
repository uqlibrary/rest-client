<?php

namespace Terah\RestClient;

use function Terah\Assert\Assert;
use function Terah\Assert\Validate;


class RestClient
{
    const FETCH             = 'GET';
    const INSERT            = 'POST';
    const UPDATE            = 'PUT';
    const GET               = 'GET';
    const POST              = 'POST';
    const PUT               = 'PUT';
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

    /** @var string $accept */
    protected $accept       = 'json';

    /** @var string $contentType */
    protected $contentType  = 'json';

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

    /** @var bool */
    protected $ignoreErrors = false;

    /** @var array */
    protected $formats      = [
        'json'      => [['application/json', 'application/x-json'], 'application/json'],
        'xml'       => [['text/xml', 'application/xml', 'application/x-xml'], 'application/xml'],
        'txt'       => [ ['text/plain'], 'application/x-www-form-urlencoded'],
        'html'      => [['text/html', 'application/xhtml+xml'], 'application/x-www-form-urlencoded'],
        'png'       => [['image/png'], 'image/png'],
        'any'       => [['*/*'], 'application/x-www-form-urlencoded'],
        'js'        => [['application/javascript', 'application/x-javascript', 'text/javascript'], 'application/javascript'],
        'css'       => [['text/css'], 'text/css'],
        'rdf'       => [['application/rdf+xml'], 'application/rdf+xml'],
        'atom'      => [['application/atom+xml'], 'application/atom+xml'],
        'rss'       => [['application/rss+xml'], 'application/rss+xml'],
    ];

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
        $this->data         = [];
        $this->method       = 'GET';
        $this->curlObj      = null;
        $this->response     = null;
        $this->ignoreErrors = false;
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
        $method         = strtoupper($method);
        $this->method   = in_array($method, ['GET', 'POST', 'PUT', 'DELETE']) ? $method : 'GET';
        return $this;
    }

    /**
     * @param string $format
     *
     * @return $this
     */
    /**
     * @param string $format
     * @param string $contentType
     * @return $this
     */
    public function format($format, $contentType=null)
    {
        $contentType = is_null($contentType) ? $format : $contentType;
        return $this->accept($format)->contentType($contentType);
    }

    /**
     * @param string $format
     *
     * @return $this
     */
    public function accept($format)
    {
        $this->accept = ! array_key_exists($format, $this->formats) ? 'json' : $format;
        return $this;
    }

    /**
     * @param string $format
     *
     * @return $this
     */
    public function contentType($format)
    {
        $this->contentType = ! array_key_exists($format, $this->formats) ? 'json' : $format;
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
     * @param bool $verbose
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
     * @param bool $ignore
     *
     * @return $this
     */
    public function ignoreErrors($ignore=true)
    {
        Assert($ignore)->boolean();
        $this->ignoreErrors = $ignore;
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
     * @param mixed|null
     * @return mixed|null
     */
    public function post($entity=null)
    {
        return $this->method('post')->sendRequest($entity);
    }

    /**
     * @param mixed|null
     * @return mixed|null
     */
    public function get($entity=null)
    {
        return $this->method('get')->sendRequest($entity);
    }

    /**
     * @param mixed|null
     * @return mixed|null
     */
    public function put($entity=null)
    {
        return $this->method('put')->sendRequest($entity);
    }

    /**
     * @param mixed|null
     * @return mixed|null
     */
    public function delete($entity=null)
    {
        return $this->method('delete')->sendRequest($entity);
    }

    /**
     * @param $entity
     * @return string|null
     * @throws \Exception
     */
    public function sendRequest($entity=null)
    {
        $result = $this
            ->buildRequest($entity)
            ->curlExec();
        $this->reset();
        return $result;
    }

    /**
     * @param $entity
     * @return mixed|null
     * @throws \Exception
     */
    public function getRawRequest($entity=null)
    {
        $this
            ->ignoreErrors(true)
            ->setCurlOpt(CURLINFO_HEADER_OUT, true)
            ->buildRequest($entity)
            ->curlExec();
        return $this->getCurlInfo(CURLINFO_HEADER_OUT);
    }

    /**
     * @param string $entity
     * @return $this
     */
    protected function buildRequest($entity=null)
    {
        $this
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
            ->setCurlData();
        return $this;
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
     * @param int $opt
     * @return mixed
     */
    protected function getCurlInfo($opt)
    {
        return curl_getinfo($this->curlObj, $opt);
    }

    /**
     * @return $this
     */
    protected function setCurlData()
    {
        if ( ! in_array(strtoupper($this->method), ['POST', 'PUT']) )
        {
            return $this;
        }
        if ( $this->contentType === 'json' )
        {
            $this->curlData = ! is_string($this->data) ? json_encode($this->data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : $this->data;
            return $this->setCurlOpt(CURLOPT_POST, true)->setCurlOpt(CURLOPT_POSTFIELDS, $this->curlData);
        }
        if ( $this->contentType === 'xml' )
        {
            if ( is_array($this->data) )
            {
                $xmlObj = new \SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");
                $this->arrayToXml($this->data, $xmlObj);
                $this->curlData   = $xmlObj->asXML();
            }
            return $this->setCurlOpt(CURLOPT_POST, true)->setCurlOpt(CURLOPT_POSTFIELDS, $this->curlData);
        }
        $this->curlData = is_array($this->data) ? http_build_query($this->data) : $this->data;
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
     * @return mixed
     * @throws \Exception
     */
    protected function curlExec()
    {
        $response           = curl_exec($this->curlObj);
        $httpCode           = curl_getinfo($this->curlObj, CURLINFO_HTTP_CODE);
        $curlError          = curl_error($this->curlObj);
        $curlErrNo          = curl_errno($this->curlObj);
        $this->response     = $this->parseResponse($response, $httpCode, $curlError, $curlErrNo);
        if ( ! $this->ignoreErrors && $this->response->isError() )
        {
            $exceptionType      = $this->exceptionType;
            throw new $exceptionType($this->response->getNotification(), $this->response->getHttpStatusCode(), null, $this->response);
        }
        return $this->response->body;
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
        list($headers, $body)       = $this->parseHeadersAndBody($response);
        $response                   = isset($headers[$this->metaHeader]) ? json_decode($headers[$this->metaHeader], true) : [];
        $response                   = is_array($response) ? $response : [];
        unset($headers[$this->metaHeader]);
        $response['headers']        = $headers;
        $response['body']           = $body;
        $response['status']         = $httpStatusCode;
        $response['curlError']      = $curlError;
        $response['curlErrorNo']    = $curlErrorNo;
        $contentType                = $this->parseContentType($headers, $this->formats[$this->accept][0][0]);
        switch ( $contentType )
        {
            case 'json':

                $response['body']   = json_decode($response['body'], false);
                break;
            case 'xml':

                $xml                = simplexml_load_string($response['body'], 'SimpleXMLElement', LIBXML_NOCDATA);
                $response['body']   = $this->xml2array($xml);
                break;
        }
        return new RestResponse($response);
    }

    /**
     * @param $responseText
     * @return array
     */
    protected function parseHeadersAndBody($responseText)
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
     * @param  array $headers
     * @param  string $defaultContentType
     * @return string  string
     */
    protected function parseContentType($headers, $defaultContentType)
    {
        foreach ( $headers as $header => $value )
        {
            if ( strtolower($header) === 'content-type' )
            {
                $defaultContentType = $value;
                break;
            }
        }
        foreach ( $this->formats as $type => $contentTypes )
        {
            foreach ( $contentTypes[0] as $contentType )
            {
                if ( stripos($contentType, $defaultContentType) !== false )
                {
                    return $type;
                }
            }
        }
        return '';
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
        $this->header('Accept', $this->formats[$this->accept][0][0]);
        $this->header('Content-Type', $this->formats[$this->contentType][1]);
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

    /**
     * @param \SimpleXMLElement $xmlObject
     * @param array $out
     * @return array
     */
    protected function xml2array($xmlObject, $out=[])
    {
        foreach( $xmlObject->attributes() as $attr => $val )
        {
            $out['@attributes'][$attr] = (string)$val;
        }
        $hasChildren = false;
        foreach( $xmlObject as $index => $node )
        {
            $hasChildren     = true;
            $out[$index][]  = $this->xml2array($node);
        }
        if ( ! $hasChildren && $val = (string)$xmlObject )
        {
            $out['@value'] = $val;
        }
        foreach ( $out as $key => $vals )
        {
            if ( is_array($vals) && count($vals) === 1 && array_key_exists(0, $vals) )
            {
                $out[$key] = $vals[0];
            }
        }
        return $out;
    }
}

