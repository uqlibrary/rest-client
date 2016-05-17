<?php

namespace Terah\RestClient;

use function Terah\Assert\Assert;
use function Terah\Assert\Validate;

/**
 * Class RestResponse
 *
 * @package Terah\RestClient
 * @property int count
 * @property int total
 * @property int limit
 * @property int offset
 * @property int page
 * @property int pages
 * @property array order
 * @property string self
 * @property array messages
 * @property string token
 * @property array filters
 * @property array fields
 * @property string method
 * @property mixed data
 * @property int status
 * @property string body
 * @property array headers
 * @property string curlError
 * @property int curlErrorNo
 */
class RestResponse implements \JsonSerializable
{
    /**
     * @var array
     */
    protected $_meta_data = [
        'count'             => 0,
        'total'             => 0,
        'limit'             => 0,
        'offset'            => 0,
        'page'              => 1,
        'pages'             => 1,
        'order'             => [],
        'self'              => '',
        'messages'          => [],
        'token'             => '',
        'filters'           => [],
        'fields'            => [],
        'method'            => '',
        'data'              => null,
        'status'            => 200,
        'body'              => '',
        'headers'           => [],
        'curlError'         => '',
        'curlErrorNo'       => null,
    ];

    /**
     * @param array $data
     */
    public function __construct(array $data=null)
    {
        $this->setArray($data);
    }

    /**
     * @param array $data
     * @return RestResponse
     */
    public function setArray(array $data)
    {
        foreach ( $data as $name => $value )
        {
            $this->set($name, $value);
        }
        return $this;
    }

    /**
     * @param $name
     * @param $args
     * @return RestResponse
     */
    public function __call($name, $args)
    {
        return $this->set($name, $args[0]);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return RestResponse
     * @throws \Terah\Assert\AssertionFailedException
     */
    public function set($name, $value)
    {
        // We don't want this throwing exceptions in invalid keys
        // as this maybe used inside the RestException class and
        // exceptions inside exceptions is a bit to hard to grok
        if ( ! array_key_exists($name, $this->_meta_data) )
        {
            return $this;
        }
        // Don't set it if it's not the same type
        if ( ! is_null($this->_meta_data[$name]) && gettype($this->_meta_data[$name]) !== gettype($value) )
        {
            return $this;
        }
        $this->_meta_data[$name] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Terah\Assert\AssertionFailedException
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Terah\Assert\AssertionFailedException
     */
    public function get($name)
    {
        Assert($this->_meta_data)->keyExists($name, "Invalid property ({$name}) sent to response meta");
        return $this->_meta_data[$name];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->_meta_data;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->_meta_data;
    }


    /**
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->_meta_data['status'];
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return $this->curlErrorNo || $this->status > 300;
    }

    /**
     * @return string
     */
    public function getNotification()
    {
        $httpMessages = preg_grep('/^HTTP/', array_keys($this->_meta_data['headers']));
        if ( empty($httpMessages) )
        {
            return $this->status;
        }
        return $httpMessages[0];
    }
}