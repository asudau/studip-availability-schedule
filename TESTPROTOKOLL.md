Testprotokoll Verfügbarkeitsplan (AvailSchedule) 1.0.0
=====================================================

Stand: 27.11.2026 · Branch `studip-6` · getestet von Claude Code (automatisiert und im Browser)
sowie asudau (Browsertest als Autor)

Umgebung
--------

| | |
|---|---|
| Stud.IP | 6.1.2, danach erneut 6.1.4 (lokale Instanz, `http://localhost/studip/`) |
| PHP | 8.5.4 |
| Datenbank | MariaDB 11.8.6 |
| Webserver | Apache 2.4.66 |
| Plugin | per Symlink nach `public/plugins_packages/elan-ev/AvailSchedule`, als Systemplugin aktiv |
| Semester | SoSe 2026 (vergangen), WiSe 2026/2027 (aktuell) |

Testdaten
---------

* **Hauptdaten** `dev/seed-testdata.php`: AVS-000 (Zielveranstaltung, 15 Studierende) und AVS-001 bis
  AVS-008 mit sich überschneidenden regelmäßigen Terminen und einem Blockseminar (Fr–So) im WiSe.
* **Randfälle** `dev/test-edgecases.php`: AVS-100 (Zielveranstaltung) und AVS-101 bis AVS-113 mit
  eigenen Testpersonen `avs_edge_a` bis `avs_edge_g`, die in keiner anderen Veranstaltung sind. So
  beeinflussen sich die beiden Datensätze nicht.

Zusammenfassung
---------------

| Bereich | Ergebnis |
|---|---|
| Unit-Tests (`SlotGrid`) | 12 von 12 bestanden |
| Integrationstest Randfälle gegen echte DB | 31 von 31 bestanden, auf 6.1.4 |
| SQL-Gegenprobe Hauptdaten | stimmt überein |
| Browser als Lehrende | bestanden |
| Browser als Autor | bestanden (manuell durch asudau) |
| Browser als Tutor | bestanden (manuell durch asudau) |
| PHPStan Level 0, PHP-Lint, Stylelint | keine Fehler |

1 Unit-Tests
------------

`composer/bin/phpunit -c tests/phpunit.xml` prüft die reine Zähllogik ohne Datenbank.

| Fall | Ergebnis |
|---|---|
| Leeres Raster zählt 0 | bestanden |
| Raster umfasst 8 bis 22 Uhr (14 Stundenslots) | bestanden |
| Minutengenau: 10:15–11:45 belegt 10 und 11 Uhr, nicht 9 und 12 | bestanden |
| Slotgrenzen exklusiv: 10:00–12:00 belegt nicht 12 Uhr | bestanden |
| Kurzer Termin 9:00–9:30 belegt 9 Uhr (früher verschluckt) | bestanden |
| Person in zwei parallelen Veranstaltungen zählt einmal | bestanden |
| Zwei Termine derselben Veranstaltung in einem Slot zählen einmal | bestanden |
| Veranstaltungen unter 3 Personen werden nicht genannt, nur gezählt | bestanden |
| Wochentage getrennt | bestanden |
| Sonntag als 0 oder 7 | bestanden |
| Zeiten außerhalb 8–22 Uhr ignoriert | bestanden |
| Umrechnung `HH:MM[:SS]` in Minuten | bestanden |

2 Integrationstest Randfälle
----------------------------

`php public/plugins_packages/elan-ev/AvailSchedule/dev/test-edgecases.php` (aus dem Stud.IP-Verzeichnis)
legt die Daten an, rechnet mit dem echten `AvailabilityCounter` und vergleicht mit Sollwerten.
Zweimal hintereinander ausgeführt: Das Ergebnis ist identisch, es entstehen keine Duplikate.

**Teilnehmendenzahl und Berechtigungsstufen in der Zielveranstaltung**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Genau 5 Teilnehmende | kein Plan | bestanden |
| 5 × `autor` + 1 × `user` (Leser:in) + 2 × `tutor` | 6 gezählt, Plan verfügbar | bestanden |
| Tutor:in der Zielveranstaltung hat andere Termine | zählt nicht | bestanden |
| Eigene Termine der Zielveranstaltung | zählen nicht | bestanden |

**Andere Veranstaltungen, aktuelles Semester (WiSe 2026/2027)**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Unbegrenzte Veranstaltung (ohne Semesterzuordnung) | zählt | bestanden |
| Mehrsemestrige Veranstaltung (SoSe bis WiSe) | zählt | bestanden |
| Veranstaltung nur im Vorsemester | zählt nicht | bestanden |
| 14-täglicher Termin | zählt, als „nicht wöchentlich“ markiert | bestanden |
| Wöchentlicher Termin | nicht markiert | bestanden |
| Teilnehmende ist in anderer Veranstaltung **Tutorin** | zählt als belegt | bestanden |
| Teilnehmende ist in anderer Veranstaltung **Leser:in** (`user`) | zählt als belegt | bestanden |
| Alle Termine eines Turnus ausgefallen (`cancelDate`) | zählt nicht | bestanden |
| Zwei überlappende Veranstaltungen (12:00–13:00 und 12:30–13:30), 3 Personen, eine davon in beiden | 3 (nicht 4), Folgestunde 2 | bestanden |
| Termin 12:00–12:30 | belegt 12 Uhr, nicht 13 Uhr | bestanden |
| Einzeltermin am Sonntag, Option aus | zählt nicht | bestanden |
| Einzeltermin am Sonntag, Option an | zählt, Sonntag wird angezeigt | bestanden |
| Option an ändert regelmäßige Termine nicht | unverändert | bestanden |

**Vorsemester (SoSe 2026)**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Veranstaltung nur im Vorsemester | zählt | bestanden |
| Mehrsemestrige Veranstaltung | zählt auch hier | bestanden |
| Unbegrenzte Veranstaltung, deren Termine erst im WiSe beginnen | zählt nicht | bestanden |
| Veranstaltung nur im aktuellen Semester | zählt nicht | bestanden |

**Datenschutz: Nennung in der Liste (ab 3 Personen)**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Veranstaltung mit 3 Teilnehmenden | wird genannt | bestanden |
| Veranstaltung mit 1 Teilnehmenden | nicht genannt, als „1 weitere Veranstaltung“ gezählt | bestanden |
| Zwei Veranstaltungen mit je 2 | nicht genannt, als „2 weitere“ gezählt | bestanden |

3 Gegenprobe Hauptdaten
-----------------------

Zellen des Plans für AVS-000 per unabhängiger SQL-Abfrage nachgezählt:

| Zelle | Plugin | SQL | Altes Verfahren (Summe) |
|---|---|---|---|
| Mo 10 Uhr, nur regelmäßige Termine | 9 | 9 | 12 |
| Fr 10 Uhr, mit Einzelterminen | 10 | 10 | – |

Die Abweichung zum alten Verfahren ist beabsichtigt: Wer in zwei parallelen Veranstaltungen ist, zählt
jetzt einmal.

4 Browsertests
--------------

| Rolle / Fall | Prüfung | Ergebnis |
|---|---|---|
| Lehrende (`test_dozent`) in AVS-000 | Eintrag „Verfügbarkeit“ unter Teilnehmende, Raster mit Zahlen und Prozent wie in der Textausgabe | bestanden |
| Lehrende, Ansicht „Auch Einzel- und Blocktermine“ | Sa/So erscheinen, Blockseminar mit 6 Personen | bestanden |
| Lehrende, Liste | Mi 18 Uhr (2 Personen): Veranstaltung nicht genannt | bestanden |
| Lehrende, Semesterwahl SoSe 2026 bei M0897 | Überschneidung Mo 9–11 mit 12 Personen (100 %) | bestanden |
| Lehrende, Veranstaltung mit 5 Teilnehmenden (AVS-005) | Hinweis statt Plan | bestanden |
| Lehrende, Veranstaltung ohne eigene Rechte | „Zugriff verweigert“ | bestanden |
| Farbskala | Stylesheet geladen, Abstufung weiß → dunkel nach Auslastung, schwarze Schrift (Kontrast ca. 9:1) | bestanden (per DOM geprüft, Screenshot nicht möglich) |
| Autor (Studierende) | kein Eintrag „Verfügbarkeit“, kein Zugriff | bestanden (manuell durch asudau) |
| Tutor (`test_tutor`) in AVS-100 | Eintrag sichtbar, Plan wird angezeigt, Zahlen wie im Integrationstest (z. B. Di 8 Uhr 4 (67 %), Mi 12 Uhr 3 (50 %) ohne Veranstaltungsnamen) | bestanden (manuell durch asudau) |
| Nicht eingeschriebene Person (`test_tutor` in AVS-000) | Veranstaltung taucht nicht auf; direkt aufgerufene URLs der Plugin-Seite und der Teilnehmerseite liefern „Zugriff verweigert“ | bestanden (automatisiert und manuell durch asudau) |

5 Nicht getestet und bekannte Grenzen
-------------------------------------

* **Admin und Root** sind nicht getestet. Sie haben in Stud.IP Tutor-Rechte in allen Veranstaltungen
  und sollten den Plan sehen.
* **Studiengruppen** sind nicht getestet. Laut Code wird dort weder der Eintrag angezeigt noch die
  Seite ausgeliefert.
* **Verschobene Einzeltermine eines Turnus:** Gezählt wird die Uhrzeit des Turnus, nicht die einzeln
  geänderte Uhrzeit eines Termins. Das war im alten Plugin genauso.
* **Unbegrenzte Veranstaltungen** zählen nur in Semestern, in denen tatsächlich Termine liegen. Eine
  unbegrenzte Veranstaltung ohne erzeugte Termine erscheint nicht.
* **Einzeltermine über Mitternacht** werden am ersten Tag um 24 Uhr abgeschnitten (laut Code, nicht getestet).
* **Performance** bei großen Veranstaltungen (mehrere hundert Teilnehmende) ist nicht gemessen. Die
  Daten werden mit 3 bis 4 gebündelten Abfragen geladen.
* **ZIP-Installation** über Admin → System → Plugins ist nicht getestet, nur die Symlink-Installation.
* **Stud.IP 6.0 und ab 6.2** sind nicht getestet (Manifest: 6.0 bis 6.1.99).
* **Englische Oberfläche:** Es gibt noch keine Übersetzung, die Texte erscheinen auf Deutsch.

Tests wiederholen
-----------------

```bash
# im Plugin-Ordner
/pfad/zu/studip/composer/bin/phpunit -c tests/phpunit.xml

# im Stud.IP-Ordner
php public/plugins_packages/elan-ev/AvailSchedule/dev/seed-testdata.php
php public/plugins_packages/elan-ev/AvailSchedule/dev/test-edgecases.php
php public/plugins_packages/elan-ev/AvailSchedule/dev/dump-plan.php AVS-000 --single

# Testdaten wieder entfernen
php public/plugins_packages/elan-ev/AvailSchedule/dev/test-edgecases.php --clean
php public/plugins_packages/elan-ev/AvailSchedule/dev/seed-testdata.php --clean
```
