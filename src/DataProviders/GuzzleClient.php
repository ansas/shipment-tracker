<?php

namespace Sauladam\ShipmentTracker\DataProviders;

use GuzzleHttp\Client;

class GuzzleClient implements DataProviderInterface
{
    /**
     * @var Client
     */
    public $client;

    /**
     * GuzzleClient constructor.
     *
     * @param array $config [optional]
     */
    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }


    /**
     * Request the given url.
     *
     * @param $url
     *
     * @return string
     */
    public function get($url)
    {
        return $this->client->get($url)->getBody()->getContents();
    }
}
