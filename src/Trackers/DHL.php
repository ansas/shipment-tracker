<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Utils\StatusMapper;
use Sauladam\ShipmentTracker\Utils\XmlHelpers;

class DHL extends AbstractTracker
{
    use XmlHelpers;

    /**
     * @var string
     */
    protected $serviceEndpoint = 'https://www.dhl.de/int-verfolgen/search';

    /**
     * @var string
     */
    protected $trackingUrl = 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do';

    /**
     * @var string
     */
    protected $language = 'de';

    /**
     * @var object
     */
    protected $parsedJson;

    /**
     * Hook into the parent method to clear the cache before calling it.
     *
     * @param string $number
     * @param null   $language
     * @param array  $params
     *
     * @return Track
     */
    public function track($number, $language = null, $params = [])
    {
        $this->parsedJson = null;

        return parent::track($number, $language, $params);
    }

    /**
     * Build the url for the given tracking number.
     *
     * @param string      $trackingNumber
     * @param string|null $language
     * @param array       $params
     *
     * @return string
     */
    public function trackingUrl($trackingNumber, $language = null, $params = [])
    {
        $language = $language ?: $this->language;

        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        $urlParams = array_merge([
            'lang' => $language,
            'idc'  => $trackingNumber,
        ],
            $additionalParams);

        $qry = http_build_query($urlParams);

        return $this->trackingUrl . '?' . $qry;
    }

    /**
     * @param string $contents
     *
     * @return Track
     * @throws Exception
     */
    protected function buildResponse($contents)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($contents);
        $dom->preserveWhiteSpace = false;

        return $this->getTrack(new DOMXPath($dom));
    }

    /**
     * Get the shipment status history.
     *
     * @param DOMXPath $xpath
     *
     * @return Track
     * @throws Exception
     */
    protected function getTrack(DOMXPath $xpath)
    {
        $track = new Track;

        $track->setZipRequired($this->isZipRequired($xpath));

        foreach ($this->getEvents($xpath) as $event) {
            if (!isset($event->status)) {
                continue;
            }

            $status = StatusMapper::fromDescription(strip_tags($event->status));
            if ($status == Track::STATUS_MISSING) {
                continue;
            }

            if ($status == Track::STATUS_PICKUP) {
                $track->setHasPickup(true);
            }

            $track->addEvent(Event::fromArray([
                'description' => isset($event->status) ? strip_tags($event->status) : '',
                'status'      => $status,
                'date'        => isset($event->datum) ? Carbon::parse($event->datum) : null,
                'location'    => isset($event->ort) ? $event->ort : '',
            ]));

            if ($status == Track::STATUS_DELIVERED && $recipient = $this->getRecipient($xpath)) {
                $track->setRecipient($recipient);
            }
        }

        $track->sortEvents();

        return $track;
    }

    protected function isZipRequired(DOMXPath $xpath): bool
    {
        $shipings = $this->parseJson($xpath)->sendungen ?? [];

        foreach ($shipings as $shiping){
            if ($shiping->plzBenoetigt ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the events.
     *
     * @param DOMXPath $xpath
     *
     * @return array
     * @throws Exception
     */
    protected function getEvents(DOMXPath $xpath)
    {
        $progress = $this->parseJson($xpath)->sendungen[0]->sendungsdetails->sendungsverlauf;

        return $progress->fortschritt > 0
            ? (array) $progress->events
            : [];
    }

    /**
     * Parse the recipient.
     *
     * @param DOMXPath $xpath
     *
     * @return null|string
     * @throws Exception
     */
    protected function getRecipient(DOMXPath $xpath)
    {
        $deliveryDetails = $this->parseJson($xpath)->sendungen[0]->sendungsdetails->zustellung;

        return isset($deliveryDetails->empfaenger) && isset($deliveryDetails->empfaenger->name)
            ? $deliveryDetails->empfaenger->name
            : null;
    }

    /**
     * Parse the JSON from the script tag.
     *
     * @param DOMXPath $xpath
     *
     * @return mixed|object
     * @throws Exception
     */
    protected function parseJson(DOMXPath $xpath)
    {
        if (!$this->parsedJson) {
            $scriptTags = $xpath->query("//script");

            /** @var \DOMNode $tag */
            foreach ($scriptTags as $tag) {
                $matched = preg_match("/initialState: JSON\.parse\((.*)\),/m", $tag->nodeValue, $matches);
                if ($matched) {
                    $this->parsedJson = json_decode(json_decode($matches[1]));
                }
            }

            if (!$this->parsedJson) {
                throw new Exception("Unable to parse DHL tracking data for [{$this->parcelNumber}].");
            }
        }

        return $this->parsedJson;
    }

    /**
     * Build the endpoint url
     *
     * @param string      $trackingNumber
     * @param string|null $language
     * @param array       $params
     *
     * @return string
     */
    protected function getEndpointUrl($trackingNumber, $language = null, $params = [])
    {
        $language = $language ?: $this->language;

        $additionalParams = !empty($params) ? $params : $this->endpointUrlParams;

        $urlParams = array_merge(
            [
                'lang'     => $language,
                'language' => $language,
                'idc'      => $trackingNumber,
                'domain'   => 'de',
            ],
            $additionalParams
        );

        return $this->serviceEndpoint . '?' . http_build_query($urlParams);
    }
}
