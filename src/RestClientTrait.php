<?php

namespace Terah\RestClient;

trait RestClientTrait
{
    /** @var RestClient */
    protected $restClient;

    /**
     * Sets a cache.
     *
     * @param RestClient $restClient
     * @return $this
     */
    public function setRestClient(RestClient $restClient)
    {
        $this->restClient = $restClient;
        return $this;
    }
}
