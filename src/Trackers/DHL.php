<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use Exception;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
use Sauladam\ShipmentTracker\Utils\StatusMapper;

class DHL extends AbstractTracker
{
    protected string $trackingUrl = 'https://www.dhl.de/int-verfolgen/data/search';

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
        $params   = $params ?: $this->trackingUrlParams;

        $urlParams = ['piececode' => $trackingNumber, 'noRedirect' => 'true', 'language' => $language] + $params;

        return $this->trackingUrl . '?' . http_build_query($urlParams);
    }

    /**
     * @param string $contents
     *
     * @return Track
     * @throws Exception
     */
    protected function buildResponse($response)
    {
        return $this->getTrack($response);
    }

    /**
     * Get the shipment status history.
     *
     * @return Track
     * @throws Exception
     */
    protected function getTrack(string $response)
    {
        $track = new Track;

        $track->setZipRequired($this->isZipRequired($response));

        foreach ($this->getEvents($response) as $event) {
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
                'description' => strip_tags($event->status),
                'status'      => $status,
                'date'        => isset($event->datum) ? Carbon::parse($event->datum) : null,
                'location'    => $event->ort ?? '',
            ]));

            if ($status == Track::STATUS_DELIVERED && $recipient = $this->getRecipient($response)) {
                $track->setRecipient($recipient);
            }
        }

        $track->sortEvents();

        return $track;
    }

    /**
     * @throws \Exception
     */
    protected function isZipRequired(string $response): bool
    {
        foreach ($this->parseJson($response)->sendungen ?? [] as $shipping) {
            if ($shipping->plzBenoetigt ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the events.
     *
     * @throws Exception
     */
    protected function getEvents(string $response): array
    {
        $progress = $this->parseJson($response)->sendungen[0]->sendungsdetails->sendungsverlauf;

        return $progress->fortschritt > 0
            ? (array) $progress->events
            : [];
    }

    /**
     * Parse the recipient.
     *
     * @throws Exception
     */
    protected function getRecipient(string $response): ?string
    {
        return $this->parseJson($response)->sendungen[0]->sendungsdetails->zustellung->empfaenger->name ?? null;
    }

    /**
     * @throws Exception
     */
    protected function parseJson(string $response): object
    {
        if (!$this->parsedJson) {
            $this->parsedJson = json_decode($response);
            if (!$this->parsedJson) {
                throw new Exception("Unable to parse DHL tracking data for [{$this->parcelNumber}].");
            }
        }

        return $this->parsedJson;
    }
}
