<?php

namespace Sauladam\ShipmentTracker;

use Sauladam\ShipmentTracker\Utils\AdditionalDetails;
use Sauladam\ShipmentTracker\Utils\Utils;

/**
 *
 */
class Track
{
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_DIGITAL    = 'digital';
    const STATUS_EXCEPTION  = 'exception';
    const STATUS_INFO       = 'info';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_MISSING    = 'missing';
    const STATUS_PICKUP     = 'pickup';
    const STATUS_UNKNOWN    = 'unknown';
    const STATUS_WARNING    = 'warning';
    const STATUS_BACK       = 'back';
    const STATUS_LOCATION   = 'location';

    use AdditionalDetails;

    /**
     * @var Event[]
     */
    protected $events = [];

    /**
     * @var bool
     */
    protected $zipRequired = false;

    /**
     * @var bool
     */
    protected $hasPickup = false;

    /**
     * @var bool
     */
    protected $traceable = true;

    /**
     * @var bool
     */
    protected $eventsAreSorted = false;

    /**
     * @var string
     */
    protected $recipient;

    /**
     * Track constructor.
     *
     * @param Event[] $events
     */
    public function __construct($events = [])
    {
        $this->events = $events;
    }

    /**
     * Get all events.
     *
     * @return Event[]
     */
    public function events()
    {
        return $this->events;
    }

    /**
     * Add an event.
     *
     * @param Event $event
     *
     * @return $this
     */
    public function addEvent(Event $event)
    {
        $this->events[] = $event;

        return $this;
    }

    /**
     * Check if the shipment has been delivered.
     *
     * @return bool
     */
    public function delivered()
    {
        $deliveredEvents = array_filter(
            $this->events,
            function (Event $event) {
                return $event->getStatus() == self::STATUS_DELIVERED;
            }
        );

        return !empty($deliveredEvents);
    }

    /**
     * Get the current status.
     *
     * @return string
     */
    public function currentStatus()
    {
        $latestEvent = $this->latestEvent();

        return $latestEvent ? $latestEvent->getStatus() : self::STATUS_UNKNOWN;
    }

    /**
     * Get the latest event.
     *
     * @return null|Event
     */
    public function latestEvent()
    {
        if (!$this->hasEvents()) {
            return null;
        }

        return $this->eventsAreSorted ? reset($this->events) : end($this->events);
    }

    /**
     * Check if this track has any events.
     *
     * @return bool
     */
    public function hasEvents()
    {
        return !empty($this->events);
    }

    /**
     * Get the recipient.
     *
     * @return string
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * Set the recipient.
     *
     * @param string $recipient
     *
     * @return $this
     */
    public function setRecipient($recipient)
    {
        $this->recipient = Utils::ensureUtf8($recipient);

        return $this;
    }

    /**
     * setTraceable
     *
     * @param bool $bool
     *
     * @return $this
     */
    public function setTraceable(bool $bool)
    {
        $this->traceable = $bool;

        return $this;
    }

    public function setHasPickup(bool $hasPickup): Track
    {
        $this->hasPickup = $hasPickup;

        return $this;
    }

    /**
     * @param bool $zipRequired
     *
     * @return $this
     */
    public function setZipRequired(bool $zipRequired)
    {
        $this->zipRequired = $zipRequired;

        return $this;
    }

    /**
     * Sort the events by date in descending order, so that the latest event is always
     * the first item in the array. Exception: digital events are put at end of array.
     *
     * @return $this
     */
    public function sortEvents()
    {
        // Sort by date desc (newest event first)
        usort(
            $this->events,
            function (Event $a, Event $b) {
                return ($a->getDate()->toDateTimeString() > $b->getDate()->toDateTimeString()) ? -1 : 1;
            }
        );

        // Push all digital events to end of list
        usort(
            $this->events,
            function (Event $a, Event $b) {
                return ($a->getStatus() != Track::STATUS_DIGITAL) ? -1 : 1;
            }
        );

        $this->eventsAreSorted = true;

        return $this;
    }

    /**
     *
     * @return bool
     */
    public function traceable()
    {
        return $this->traceable;
    }

    public function isZipRequired(): bool
    {
        return $this->zipRequired;
    }

    public function hasPickup(): bool
    {
        return $this->hasPickup;
    }
}
