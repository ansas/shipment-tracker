<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
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
        $additionalParams = !empty($params) ? $params : $this->trackingUrlParams;

        /** @noinspection SpellCheckingInspection */
        $urlParams = array_merge(
            [
                'form.sendungsnummer' => $trackingNumber,
            ],
            $additionalParams
        );

        return $this->trackingUrl . '?' . http_build_query($urlParams);
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
     * getDateFormDescription
     *
     * @param string $description
     *
     * @return Carbon|null
     * @throws Exception
     */
    protected function getDateFormDescription(string $description)
    {
        $dateAllowedForStatus = [
            Track::STATUS_DELIVERED,
            Track::STATUS_WARNING,
            Track::STATUS_EXCEPTION,
        ];

        if (
            preg_match("/[0-9]{2}[.\-][0-9]{2}[.\-][0-9]{4}/", $description, $matches)
            && in_array($this->resolveStatus($description), $dateAllowedForStatus)
        ) {
            return new Carbon($matches[0], 'UTC');
        }

        return null;
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
        $track = new Track();

        if (isset($xpath)) {
            $description = $this->getEventText($xpath);
            $status      = $this->resolveStatus($description);

            if ($status != Track::STATUS_MISSING) {
                $track->addEvent(Event::fromArray([
                    'description' => $description,
                    'status'      => $status,
                    'date'        => $this->getDateFormDescription($description),
                    'location'    => null,
                ]));
            }
        }

        $track->setTraceable(false);

        return $track;
    }

    /**
     * Parse the JSON from the script tag.
     *
     * @param DOMXPath $xpath
     *
     * @return string
     * @throws Exception
     */
    protected function getEventText(DOMXPath $xpath)
    {
        $node = $xpath->query("//td[@class='grey']");
        if ($node->length != 1) {
            throw new Exception("Unable to parse PostDE tracking data for [{$this->parcelNumber}].");
        }

        return trim($node->item(0)->textContent);
    }

    /**
     * Match a shipping status from the given description.
     *
     * @param $statusDescription
     *
     * @return string
     * @noinspection SpellCheckingInspection
     */
    protected function resolveStatus($statusDescription)
    {
        $statuses = [
            Track::STATUS_DELIVERED  => [
                'Die Sendung wurde am',
                'in der Filiale abgeholt',
            ],
            Track::STATUS_IN_TRANSIT => [
                'orts erfasst',
                'verzögert sich',
                'in unserem Logistikzentrum',
                'bearbeitet und wird voraussichtlich',
            ],
            Track::STATUS_PICKUP     => [
                'zur Abholung bereit liegt',
            ],
            Track::STATUS_WARNING    => [
                'erfolglosen Zustellversuch',
                'nicht zugestellt werden',
                'wurde nicht abgeholt',
                'Die Sendung konnte weder dem Empfänger noch dem Absender zugestellt werden',
            ],
            Track::STATUS_EXCEPTION  => [
                'Annahme verweigert',
                'Die Annahme der Sendung wurde am',
            ],
            Track::STATUS_MISSING    => [
                'keine Informationen',
            ],
        ];

        foreach ($statuses as $status => $needles) {
            foreach ($needles as $needle) {
                if (stripos($statusDescription, $needle) !== false) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }
}
