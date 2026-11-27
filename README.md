Stud.IP Verfügbarkeitsplan
==========================

Das Plugin ergänzt in Veranstaltungen unter **Teilnehmende → Verfügbarkeit** einen Wochenplan.
Er zeigt Lehrenden und Tutor:innen, wie viele Teilnehmende ihrer Veranstaltung zu welcher Zeit
durch andere Veranstaltungen belegt sind. Das hilft zum Beispiel bei der Suche nach einem Termin
für eine Zusatzsitzung, eine Klausur oder eine Exkursion.

Voraussetzungen
---------------

* Stud.IP 6.0 bis 6.1 (entwickelt mit 6.1.2, getestet mit 6.1.2 und 6.1.4)
* Keine weiteren Abhängigkeiten. Die frühere Abhängigkeit von der Osnabrücker `lib/raumplan`
  besteht nicht mehr.

Funktionsweise
--------------

* Berücksichtigt werden alle Teilnehmenden mit dem Status *Studierende* (`autor`/`user`).
* Für das gewählte Semester werden ihre übrigen Veranstaltungen ermittelt, auch unbegrenzte.
  Ein regelmäßiger Termin zählt nur, wenn er in diesem Semester tatsächlich stattfindet.
* Das Raster zeigt pro Wochentag und Stunde (8 bis 22 Uhr), wie viele **verschiedene**
  Teilnehmende belegt sind, absolut und in Prozent. Ein Termin belegt jede Stunde, die er berührt:
  10:15–11:45 zählt für 10 und 11 Uhr.
* Wahlweise werden auch Einzel- und Blocktermine einbezogen. Samstag und Sonntag erscheinen nur,
  wenn dort Termine liegen.
* Die Liste unter dem Raster nennt pro Zeitfenster die beteiligten Veranstaltungen.

Datenschutz
-----------

* Angezeigt werden nur aggregierte Zahlen, nie Namen von Teilnehmenden.
* Der Plan steht erst ab **mehr als 5** Teilnehmenden zur Verfügung.
* Andere Veranstaltungen werden nur genannt, wenn **mindestens 3** Teilnehmende sie belegen.
  Kleinere werden zusammengefasst, damit sich keine Veranstaltung einer einzelnen Person
  zuordnen lässt.
* Zugriff haben nur Tutor:innen, Lehrende und Admins der Veranstaltung, nicht in Studiengruppen.

Installation
------------

ZIP bauen (siehe unten) und in Stud.IP unter *Admin → System → Plugins* hochladen.
Als Systemplugin ist es danach in allen Veranstaltungen aktiv.

Entwicklung
-----------

```bash
# Unit-Tests (PHPUnit aus einer Stud.IP-Installation)
/pfad/zu/studip/composer/bin/phpunit -c tests/phpunit.xml

# Testdaten (AVS-000 … AVS-008) anlegen, aus dem Stud.IP-Verzeichnis
php public/plugins_packages/elan-ev/AvailSchedule/dev/seed-testdata.php

# Plan einer Veranstaltung als Text ausgeben
php public/plugins_packages/elan-ev/AvailSchedule/dev/dump-plan.php AVS-000 --single

# Release-ZIP aus dem committeten Stand bauen
dev/build-zip.sh
```

`dev/` und `tests/` sind nicht Teil des Pakets (siehe `.gitattributes`). Welche Fälle getestet sind, steht im [Testprotokoll](TESTPROTOKOLL.md).

Geschichte
----------

Ursprünglich 2013 von Till Glöggler (ELAN e.V.) auf Basis der Osnabrücker Stud.IP-Anpassungen
entwickelt. 2026 auf Stud.IP 6 portiert.
