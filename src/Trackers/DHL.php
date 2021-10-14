<?php

namespace Sauladam\ShipmentTracker\Trackers;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use Sauladam\ShipmentTracker\Event;
use Sauladam\ShipmentTracker\Track;
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

        foreach ($this->getEvents($xpath) as $event) {
            if (!isset($event->status)) {
                continue;
            }

            $status = $this->resolveStatus(strip_tags($event->status));
            if ($status == Track::STATUS_MISSING) {
                continue;
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
                'aus der PACKSTATION abgeholt',
                'wurde aus der Packstation entnommen',
                'erfolgreich zugestellt',
                'im Rahmen der kontaktlosen Zustellung zugestellt',
                'am Wunschort abgeholt',
                'hat die Sendung in der Filiale abgeholt',
                'des Nachnahme-Betrags an den Zahlungsempf',
                'Sendung wurde zugestellt an',
                'Die Sendung wurde ausgeliefert',
                'shipment has been successfully delivered',
                'recipient has picked up the shipment from the retail outlet',
                'recipient has picked up the shipment from the PACKSTATION',
                'item has been sent',
                'delivered from the delivery depot to the recipient by simplified company delivery',
                'per vereinfachter Firmenzustellung ab Eingangspaketzentrum zugestellt',
                'direkt ab Paketzentrum dem Geschäftskunden zugestellt',
            ],
            Track::STATUS_IN_TRANSIT => [
                'in das Zustellfahrzeug geladen',
                'Verladung ins Zustellfahrzeug',
                'im Start-Paketzentrum bearbeitet',
                'im Ziel-Paketzentrum bearbeitet',
                'im Paketzentrum bearbeitet',
                'auf dem Weg zur PACKSTATION',
                'wird in eine PACKSTATION weitergeleitet',
                'Die Sendung wurde abgeholt',
                'Sendung wurde im Briefzentrum bearbeitet',
                'Sendung wird an die Hausadresse zugestellt',
                'im Export-Paketzentrum bearbeitet',
                'wird für den Weitertransport',
                'wird für die Auslieferung',
                'im Paketzentrum eingetroffen',
                'zum Weitertransport vorbereitet',
                'für den Weitertransport vorbereitet',
                'für den Transport vorbereitet',
                'zum Weitertransport aus der PACKSTATION entnommen',
                'für die Zustellung vorbereitet',
                'auf dem Weg',
                'Sendung wird ins Zielland transportiert und dort an die Zustellorganisation',
                'vom Absender in der Filiale eingeliefert',
                'Sendung konnte nicht in die PACKSTATION eingestellt werden und wurde in eine Filiale',
                'Sendung konnte nicht zugestellt werden und wird jetzt zur Abholung in die Filiale/Agentur gebracht',
                'shipment has been picked up',
                'instruction data for this shipment have been provided',
                'shipment has been processed',
                'shipment has been posted by the sender',
                'hipment has been loaded onto the delivery vehicle',
                'A 2nd attempt at delivery is being made',
                'shipment is on its way to the PACKSTATION',
                'forwarded to a PACKSTATION',
                'shipment could not be delivered to the PACKSTATION and has been forwarded to a retail outlet',
                'shipment could not be delivered, and the recipient has been notified',
                'A 2nd attempt at delivery is being made',
                'Es erfolgt ein 2. Zustellversuch',
                'Sendung wurde an DHL übergeben',
                'Sendung ist in der Region des Empfängers angekommen',
                'im Zielland/Zielgebiet eingetroffen',
                'Abholauftrag wurde zur Durchführung am nächsten Werktag',
                'Eine Nachricht wurde zugestellt',
                'wird ins Zielland/Zielgebiet transportiert',
                'Import-Paketzentrum im Zielland/Zielgebiet verlassen',
                'wird zur Verzollung im Zielland/Zielgebiet vorbereitet',
                'wurde durch den Zoll im Zielland/Zielgebiet freigegeben',
            ],
            Track::STATUS_PICKUP     => [
                'Die Sendung liegt in der PACKSTATION',
                'Die Sendung liegt ab sofort in der Filiale',
                'Uhrzeit der Abholung kann der Benachrichtigungskarte entnommen werden',
                'earliest time when it can be picked up can be found on the notification card',
                'shipment is ready for pick-up at the PACKSTATION',
                'Sendung wird zur Abholung in die',
                'Sendung wurde zur Abholung in die',
                'wurde in eine Filiale weitergeleitet',
                'wurde an eine Hauspoststelle weitergeleitet',
                'The shipment is being brought to',
                'beim Zoll abholen',
                'liegt für den Empfänger zur Abholung bereit',
            ],
            Track::STATUS_DIGITAL    => [
                'elektronisch an',
            ],
            Track::STATUS_INFO       => [
                'wurde gewählt',
                'als Empfangsoption vorgemerkt',
                'als neue Lieferadresse gewählt',
                'wird die Sendung an eine neue Empfängeradresse gesandt',
                'wird bei uns gelagert',
                'erneuter Zustellversuch am nächsten Werktag',
                'Wunsch des Empfängers',
                'wurde vom Absender in die Packstation eingeliefert',
                'Abgang der Sendung aus der Paketermittlung',
            ],
            Track::STATUS_WARNING    => [
                'Sendung konnte nicht zugestellt werden',
                'Zustellversuch nicht zugestellt werden',
                'nicht zugestellt werden. Die Sendung wird voraussichtlich',
                'Sendung wurde leider fehlgeleitet',
                'Sendung wurde zurückgestellt',
                'Sendung verzögert sich',
                'aufgrund höherer Gewalt',
                'heute nicht möglich',
                'nachverpackt',
                'neu verpackt',
                'Sendung wurde beschädigt',
                'beschädigte Sendung',
                'shipment could not be delivered',
                'attempting to obtain a new delivery address',
                'eine neue Zustelladresse für den Empf',
                'Sendung wurde fehlgeleitet und konnte nicht zugestellt werden. Die Sendung wird umadressiert und an den',
                'shipment was misrouted and could not be delivered. The shipment will be readdressed and forwarded to the recipient',
                'höhere Gewalt',
                'gewünschte Liefertag wurde storniert',
                'konnte leider nicht in die gewünschte Packstation eingestellt werden',
                'Empfänger wurde nicht angetroffen',
            ],
            Track::STATUS_EXCEPTION  => [
                'Annahme der Sendung verweigert',
                'cksendung eingeleitet',
                'Adressfehlers konnte die Sendung nicht zugestellt',
                'Zustelladresse nicht angefahren',
                'war eine Zustellung der Sendung nicht möglich',
                'entspricht nicht den Versandbedingungen',
                'nicht unseren Versandbedingungen',
                'nger ist unbekannt',
                'The address is incomplete',
                'ist falsch',
                'is incorrect',
                'recipient has not picked up the shipment',
                'nicht in der Filiale abgeholt',
                'The shipment is being returned',
                'Es erfolgt eine Rücksendung',
                'an den Absender zurückgesandt',
                'Es erfolgte keine Einlieferung zu der per EDI Daten beauftragten Sendung',
                'leeres Fach in Packstation vorgefunden',
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
