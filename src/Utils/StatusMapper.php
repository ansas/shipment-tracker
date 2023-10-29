<?php

namespace Sauladam\ShipmentTracker\Utils;

use Sauladam\ShipmentTracker\Track;

class StatusMapper
{
    public static function fromDescription(string $description): string
    {
        $statuses = [
            Track::STATUS_DELIVERED  => [
                'Zustellung erfolgreich',
                'Abholung in der Filiale ist erfolgt',
                'Abholung aus Packstation',
                'aus der PACKSTATION abgeholt',
                'wurde aus der Packstation entnommen',
                'erfolgreich zugestellt',
                'im Rahmen der kontaktlosen Zustellung zugestellt',
                'am Wunschort abgeholt',
                'hat die Sendung in der Filiale abgeholt',
                'des Nachnahme-Betrags an den Zahlungsempf',
                'Sendung wurde zugestellt',
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
                'für Weitertransport vorbereitet',
                'In Zustellung',
                'Zustellung an Packstation', // no pickup yet, in transit
                'Weiterleitung an Filiale',
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
                'Sendung im Weitertransport',
                'für den Weitertransport vorbereitet',
                'für den Transport vorbereitet',
                'zum Weitertransport aus der PACKSTATION entnommen',
                'für die Zustellung vorbereitet',
                'Zustellung in den nächsten Werktagen',
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
                'im Start-Paketzentrum eingetroffen',
                'Die Sendung wird bearbeitet',
                'wurde zur Zustellung übergeben',
                'Export-Paketzentrum eingetroffen',
                'Abholung erfolgreich',
                'Abholung wurde erfolgreich',
                'Vorbereitung für Weitertransport',
                'Auslands-Sendung an DHL übergeben',
                'Bearbeitung in der Zustellbasis',
                'Bearbeitung im Briefzentrum',
            ],
            Track::STATUS_PICKUP     => [
                'Die Sendung liegt in der',
                'Die Sendung liegt zur Abholung',
                'liegt in der Filiale zur Abholung',
                'in der Filiale hinterlegt',
                'Die Sendung liegt in der PACKSTATION',
                'Die Sendung liegt ab sofort in der',
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
                'Neue Zustellanschrift:',
                'wurde gewählt',
                'als Empfangsoption vorgemerkt',
                'als neue Lieferadresse gewählt',
                'wird die Sendung an eine neue Empfängeradresse gesandt',
                'wird bei uns gelagert',
                'erneuter Zustellversuch am nächsten Werktag',
                'Zustellung am nächsten Werktag',
                'Wunsch des Empfängers',
                'wurde vom Absender in die Packstation eingeliefert',
                'Abgang der Sendung aus der Paketermittlung',
                'Paketmitnahme vom Ablageort gebucht',
                'Weiterleitung an neue Empfängeradresse',
            ],
            Track::STATUS_WARNING    => [
                'Verzögerte Zustellung',
                'Einstellung in Packstation nicht möglich',
                'Zweiter Zustellversuch erfolglos',
                'Zur Abholung benötigte Benachrichtigungskarte wird per Brief zugestellt',
                'Sendung konnte nicht zugestellt werden',
                'Zustellversuch nicht zugestellt werden',
                'heute leider nicht zugestellt werden',
                'nicht zugestellt werden. Die Sendung wird voraussichtlich',
                'Sendung wurde leider fehlgeleitet',
                'Sendung wurde zurückgestellt',
                'Sendung verzögert sich',
                'aufgrund höherer Gewalt',
                'heute nicht möglich',
                'heute leider nicht möglich',
                'nachverpackt',
                'neu verpackt',
                'Neuverpackung',
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
                'Aufgrund eines Nachsendeauftrags',
                'Aufgrund einer Beschädigung',
                'aufgrund Beschädigung',
                'Nachverpackungsstelle',
                'wegen Streik nicht möglich',
            ],
            Track::STATUS_EXCEPTION  => [
                'Aufgrund fehlender Adressangaben wird aktuell der Empfänger der Sendung ermittelt',
                'Lagerfrist überschritten',
                'Annahme der Sendung verweigert',
                'Annahme verweigert',
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
                'an den Absender zurück',
                'Rücksendung an Absender',
                'Es erfolgte keine Einlieferung zu der per EDI Daten beauftragten Sendung',
                'leeres Fach in Packstation vorgefunden',
                'Paketermittlung',
                'Rücknahme der Sendung verweigert',
                'Sendung ist beschädigt',
                'Versandbedingungen nicht erfüllt',
            ],
        ];

        foreach ($statuses as $status => $needles) {
            foreach ($needles as $needle) {
                if (stripos($description, $needle) !== false) {
                    return $status;
                }
            }
        }

        return Track::STATUS_UNKNOWN;
    }
}
