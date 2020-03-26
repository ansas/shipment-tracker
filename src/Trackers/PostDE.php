<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Utils\XmlHelpers;

class PostDE extends AbstractTracker
{
    use XmlHelpers;

    /**
     * @var string
     */
    protected $trackingUrl = 'https://www.deutschepost.de/sendung/simpleQueryResult.html';

    /**
     * @var object
     */
    protected $description;

    /**
     * Hook into the parent method to clear the cache before calling it.
     *
     * @param string $number
     * @param null $language
     * @param array $params
     *
     * @return Track
     */
    public function track($number, $language = null, $params = [])
    {
        $this->description = null;

        return parent::track($number, $language, $params);
    }

    /**
     * @param string $contents
     *
     * @return Track
     * @throws \Exception
     */
    protected function buildResponse($contents)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($contents);
        $dom->preserveWhiteSpace = false;

        $domxpath = new DOMXPath($dom);

        return $this->getTrack($domxpath);
    }

    /**
     * getDateFormDescription
     *
     * @param  string $description
     * @return Carbon|null
     */
    protected function getDateFormDescription(string $description)
    {
        $pattern = "/[0-9]{2}(\.|-)[0-9]{2}(\.|-)[0-9]{4}/";
        if (preg_match($pattern, $description, $matches) && $this->resolveStatus($description) == Track::STATUS_DELIVERED) {
            return new Carbon($matches[0]);
        }
        return null;
    }

    /**
     * Get the shipment status history.
     *
     * @param DOMXPath $xpath
     *
     * @return Track
     * @throws \Exception
     */
    protected function getTrack(DOMXPath $xpath)
    {
        $description = $this->getEventText($xpath);
        $track = new Track;
        $track->addEvent(Event::fromArray([
            'description' => $description,
            'status' => isset($xpath) ? $this->resolveStatus($description) : '',
            'date' => $this->getDateFormDescription($description),
            'location' => null,
        ]));
        return $track->setTraceable(false);
    }

    /**
     * Parse the JSON from the script tag.
     *
     * @param DOMXPath $xpath
     * @return mixed|object
     * @throws \Exception
     */
    protected function getEventText(DOMXPath $xpath)
    {
        if ($this->description) {
            return $this->description;
        }
        $node = $xpath->query("//td[@class='grey']");
        if ($node->length != 1) {
            throw new \Exception("Unable to parse PostDE tracking data for [{$this->parcelNumber}].");
        }
        return trim($node->item(0)->textContent);
    }

    /**
     * Match a shipping status from the given description.
     *
     * @param $statusDescription
     *
     * @return string
     */
    protected function resolveStatus($statusDescription)
    {
        $statuses = [
            Track::STATUS_DELIVERED => [
                'Die Sendung wurde am',
            ],
            Track::STATUS_IN_TRANSIT => [
                'orts erfasst',
                'verzögert sich',
                'in unserem Logistikzentrum',
                'bearbeitet und wird voraussichtlich',
            ],
            Track::STATUS_PICKUP => [

            ],
            Track::STATUS_WARNING => [
                'keine Informationen',
                'erfolglosen Zustellversuch',
                'Annahme verweigert',
            ],
            Track::STATUS_EXCEPTION => [

            ],
        ];

        foreach ($statuses as $status => $needles) {
            foreach ($needles as $needle) {
                if (strpos($statusDescription, $needle) !== false) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }

    /**
     * Build the url for the given tracking number.
     *
     * @param string $trackingNumber
     * @param string|null $language
     * @param array $params
     *
     * @return string
     */
    public function trackingUrl($trackingNumber, $language = null, $params = [])
    {
        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        $urlParams = array_merge([
            'form.sendungsnummer' => $trackingNumber,
        ], $additionalParams);

        $qry = http_build_query($urlParams);
        return $this->trackingUrl . '?' . $qry;
    }
}
