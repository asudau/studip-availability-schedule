Testprotokoll Verfügbarkeitsplan (AvailSchedule) 1.0.0
=====================================================

Stand: 27.11.2026 · Branch `studip-6` · getestet von Claude Code (automatisiert und im Browser)
sowie asudau (Browsertests als Autor und Tutor)

Begriffe
--------

Das Protokoll verwendet durchgehend diese Begriffe:

* **Zielveranstaltung**: die Veranstaltung, deren Verfügbarkeitsplan angezeigt wird.
* **Teilnehmende**: Personen mit Status `autor` oder `user` in der Zielveranstaltung. Nur über sie
  gibt der Plan Auskunft. Tutor:innen und Lehrende der Zielveranstaltung sind keine Teilnehmenden.
* **andere Veranstaltung**: jede Veranstaltung außer der Zielveranstaltung, in der eine Teilnehmende
  eingetragen ist, egal mit welchem Status (auch als Tutorin).
* **belegt**: Eine Teilnehmende ist in einem Stundenslot belegt, wenn sie dort einen Termin in einer
  anderen Veranstaltung hat. Ein Termin belegt jeden Stundenslot, den er berührt.
* **Zahl im Raster**: Anzahl der belegten Teilnehmenden in diesem Stundenslot. Jede Person zählt
  höchstens einmal, auch wenn sie mehrere Termine gleichzeitig hat.
* **genannt**: Eine andere Veranstaltung erscheint in der Liste unter dem Raster mit ihrem Namen. Das
  passiert nur, wenn mindestens 3 Teilnehmende sie belegen. Kleinere erscheinen nur als Anzahl
  („n weitere Veranstaltungen“).

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

* **Hauptdaten** `dev/seed-testdata.php`: Zielveranstaltung AVS-000 mit 15 Teilnehmenden, dazu die
  anderen Veranstaltungen AVS-001 bis AVS-008 mit sich überschneidenden regelmäßigen Terminen und
  einem Blockseminar (Fr–So), alle im WiSe.
* **Randfälle** `dev/test-edgecases.php`: Zielveranstaltung AVS-100 und die anderen Veranstaltungen
  AVS-101 bis AVS-113. Die Teilnehmenden sind eigene Testpersonen `avs_edge_a` bis `avs_edge_g`, die
  sonst nirgends eingetragen sind. So beeinflussen sich die beiden Datensätze nicht.

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

`composer/bin/phpunit -c tests/phpunit.xml` prüft die reine Zähllogik ohne Datenbank. Hier gibt es
noch keine Zielveranstaltung, deshalb ist allgemein von „Personen“ die Rede.

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Leeres Raster | alle Zahlen 0 | bestanden |
| Umfang des Rasters | 14 Stundenslots von 8 bis 22 Uhr | bestanden |
| Termin 10:15–11:45 | belegt die Slots 10–11 und 11–12 Uhr, nicht 9–10 und 12–13 Uhr | bestanden |
| Termin 10:00–12:00 | belegt nicht den Slot 12–13 Uhr (Slotgrenzen exklusiv) | bestanden |
| Termin 9:00–9:30 | belegt den Slot 9–10 Uhr (das alte Plugin hat ihn übersehen) | bestanden |
| Eine Person in zwei gleichzeitigen Veranstaltungen | Zahl im Raster 1, nicht 2 | bestanden |
| Zwei Termine derselben Veranstaltung im selben Slot | Zahl im Raster 1 | bestanden |
| Veranstaltungen mit 3 bzw. 1 Person im Slot | erste wird genannt, zweite nur als Anzahl | bestanden |
| Termin am Dienstag | belegt nur Dienstag, nicht Montag | bestanden |
| Sonntag als Wochentag 0 oder 7 gespeichert | beides belegt Sonntag | bestanden |
| Termine vor 8 oder nach 22 Uhr | belegen nur die Slots innerhalb 8–22 Uhr | bestanden |
| Umrechnung `HH:MM[:SS]` in Minuten | korrekt | bestanden |

2 Integrationstest Randfälle
----------------------------

`php public/plugins_packages/elan-ev/AvailSchedule/dev/test-edgecases.php` (aus dem Stud.IP-Verzeichnis)
legt die Daten an, rechnet mit dem echten `AvailabilityCounter` und vergleicht mit Sollwerten.
Zweimal hintereinander ausgeführt: Das Ergebnis ist identisch, es entstehen keine Duplikate.

**2.1 Wer ist Teilnehmende? (Zielveranstaltung AVS-100)**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Zielveranstaltung mit genau 5 Teilnehmenden | kein Plan, nur Hinweis | bestanden |
| AVS-100: 5 × `autor`, 1 × `user` (Leser:in), 2 × `tutor` | 6 Teilnehmende, Plan wird angezeigt | bestanden |
| Tutor:in der Zielveranstaltung hat Sa 10–12 Uhr einen Termin in einer anderen Veranstaltung | ist keine Teilnehmende, Sa 10 Uhr bleibt 0 | bestanden |
| Leser:in (`user`) der Zielveranstaltung hat Di 12–13 Uhr einen Termin in einer anderen Veranstaltung | ist Teilnehmende und dort belegt, Di 12 Uhr = 1 | bestanden |
| Termin der Zielveranstaltung selbst (Fr 14–16 Uhr) | belegt niemanden, Fr 14 Uhr = 0 | bestanden |

**2.2 Welche Termine anderer Veranstaltungen belegen? (aktuelles Semester WiSe 2026/2027, nur regelmäßige Termine)**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Unbegrenzte Veranstaltung (ohne Semesterzuordnung), Mo 8–10 Uhr, 3 Teilnehmende | Mo 8 und 9 Uhr = 3 | bestanden |
| Mehrsemestrige Veranstaltung (SoSe bis WiSe), Di 8–10 Uhr, 4 Teilnehmende | Di 8 Uhr = 4 | bestanden |
| Veranstaltung nur im Vorsemester, Mi 8–10 Uhr | belegt im WiSe niemanden, Mi 8 Uhr = 0 | bestanden |
| 14-täglicher Termin, Do 8–10 Uhr, 3 Teilnehmende | Do 8 Uhr = 3; in der Liste mit Zusatz „nicht wöchentlich“ | bestanden |
| Wöchentlicher Termin (Gegenprobe zur Zeile davor) | in der Liste **ohne** Zusatz „nicht wöchentlich“ | bestanden |
| Teilnehmende ist in der anderen Veranstaltung Tutorin (Fr 8–10 Uhr, mit 2 weiteren Teilnehmenden) | sie ist belegt, Fr 8 Uhr = 3 | bestanden |
| Alle Termine eines Turnus ausgefallen (Mo 12–14 Uhr) | belegen niemanden, Mo 12 Uhr = 0 | bestanden |
| Zwei überlappende Veranstaltungen 12:00–13:00 und 12:30–13:30 am Mi, 3 Teilnehmende, eine davon in beiden | Mi 12 Uhr = 3 (nicht 4), Mi 13 Uhr = 2 | bestanden |
| Termin Do 12:00–12:30, 2 Teilnehmende | belegt den Slot 12–13 Uhr (= 2), nicht 13–14 Uhr (= 0) | bestanden |
| Höchste Zahl im Raster | 4 | bestanden |

**2.3 Einzel- und Blocktermine**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Einzeltermin So 10–12 Uhr, Ansicht „Nur regelmäßige Termine“ | belegt niemanden, So 10 Uhr = 0 | bestanden |
| Derselbe Einzeltermin, Ansicht „Auch Einzel- und Blocktermine“ | So 10 Uhr = 1, Sonntag erscheint im Raster | bestanden |
| Ansicht „Auch Einzel- und Blocktermine“, regelmäßige Termine | unverändert, Di 8 Uhr weiterhin 4 | bestanden |

**2.4 Vorsemester (SoSe 2026 ausgewählt)**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Veranstaltung nur im Vorsemester, Mi 8–10 Uhr, 5 Teilnehmende | Mi 8 Uhr = 5 | bestanden |
| Mehrsemestrige Veranstaltung | belegt auch hier, Di 8 Uhr = 4 | bestanden |
| Unbegrenzte Veranstaltung, deren Termine erst im WiSe beginnen | belegt im SoSe niemanden, Mo 8 Uhr = 0 | bestanden |
| Veranstaltung nur im aktuellen Semester (14-täglich, Do) | belegt im SoSe niemanden, Do 8 Uhr = 0 | bestanden |

**2.5 Datenschutz: Welche anderen Veranstaltungen werden genannt?**

| Fall | Erwartet | Ergebnis |
|---|---|---|
| Mo 8 Uhr: eine andere Veranstaltung mit 3 Teilnehmenden | wird genannt: „AVS-101 AVS unbegrenzt (3)“ | bestanden |
| Di 12 Uhr: eine andere Veranstaltung mit 1 Teilnehmenden | nicht genannt, nur „1 weitere Veranstaltung …“ | bestanden |
| Mi 12 Uhr: zwei andere Veranstaltungen mit je 2 Teilnehmenden | keine genannt, nur „2 weitere Veranstaltungen …“ | bestanden |

3 Gegenprobe Hauptdaten
-----------------------

Zahlen im Raster für AVS-000 per unabhängiger SQL-Abfrage nachgezählt:

| Stundenslot | Plugin | SQL | Altes Verfahren |
|---|---|---|---|
| Mo 10–11 Uhr, nur regelmäßige Termine | 9 | 9 | 12 |
| Fr 10–11 Uhr, auch Einzel- und Blocktermine | 10 | 10 | – |

Das alte Verfahren hat die Teilnehmenden pro anderer Veranstaltung addiert. Wer zwei gleichzeitige
Termine hatte, kam doppelt vor. Jetzt zählt jede belegte Teilnehmende einmal, deshalb 9 statt 12.

4 Browsertests
--------------

| Rolle / Fall | Erwartet | Ergebnis |
|---|---|---|
| Lehrende (`test_dozent`) in AVS-000 | Eintrag „Verfügbarkeit“ unter Teilnehmende; Raster mit denselben Zahlen und Prozentwerten wie die Textausgabe von `dump-plan.php` | bestanden |
| Lehrende, Ansicht „Auch Einzel- und Blocktermine“ | Sa und So erscheinen; durch das Blockseminar 6 belegte Teilnehmende | bestanden |
| Lehrende, Liste bei Mi 18 Uhr (2 belegte Teilnehmende) | die andere Veranstaltung wird nicht genannt | bestanden |
| Lehrende, Semesterwahl SoSe 2026 in M0897 | Mo 9–11 Uhr alle 12 Teilnehmenden belegt (100 %) | bestanden |
| Lehrende, Zielveranstaltung mit 5 Teilnehmenden (AVS-005) | Hinweis statt Plan | bestanden |
| Lehrende, Veranstaltung, in der sie keine Rechte hat | „Zugriff verweigert“ | bestanden |
| Farbskala | Stylesheet geladen; Stundenslots mit mehr belegten Teilnehmenden sind dunkler; schwarze Schrift (Kontrast ca. 9:1) | bestanden (per DOM geprüft, Screenshot nicht möglich) |
| Autor (Studierende) | kein Eintrag „Verfügbarkeit“, kein Zugriff auf die Seite | bestanden (manuell durch asudau) |
| Tutor (`test_tutor`) in AVS-100 | Eintrag sichtbar, Plan mit denselben Zahlen wie im Integrationstest, z. B. Di 8 Uhr 4 (67 %), Mi 12 Uhr 3 (50 %) ohne genannte Veranstaltungen | bestanden (manuell durch asudau) |
| Nicht eingetragene Person (`test_tutor` in AVS-000) | Veranstaltung taucht nicht auf; direkt aufgerufene URLs der Plugin-Seite und der Teilnehmerseite liefern „Zugriff verweigert“ | bestanden (automatisiert und manuell durch asudau) |

5 Nicht getestet und bekannte Grenzen
-------------------------------------

* **Admin und Root** sind nicht getestet. Sie haben in Stud.IP Tutor-Rechte in allen Veranstaltungen
  und sollten den Plan sehen.
* **Studiengruppen** sind nicht getestet. Laut Code wird dort weder der Eintrag angezeigt noch die
  Seite ausgeliefert.
* **Status `user` in einer anderen Veranstaltung** ist nicht eigens getestet. Laut Code ist eine
  Teilnehmende dort genauso belegt wie mit jedem anderen Status (siehe Tutorin in 2.2).
* **Verschobene Einzeltermine eines Turnus:** Für die Belegung zählt die Uhrzeit des Turnus, nicht die
  einzeln geänderte Uhrzeit eines Termins. Das war im alten Plugin genauso.
* **Unbegrenzte Veranstaltungen** belegen nur in Semestern, in denen sie tatsächlich Termine haben.
* **Einzeltermine über Mitternacht** belegen am ersten Tag bis 24 Uhr, der Folgetag bleibt frei (laut
  Code, nicht getestet).
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
