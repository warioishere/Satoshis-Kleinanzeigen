# Treuhand nach Regelwerk 1.1: was das Modul `sk_escrow` dafür können muss

Stand 17.09.2026. Regelwerk: `docs/treuhand-regelwerk-v1.html`, Fassung 1.1 (§-Verweise unten). Ist-Stand des Moduls: Escrow-Zeile in `sk_lightning_payments` mit `context = 'escrow'`, Status `requested → pending → confirmed → delivered | refunded | disputed | expired`, PSBT-Fluss über die externe API mit drei Schlüsseln (Käufer, Verkäufer, Marktplatz; jede Bewegung braucht zwei), Dispute heute manuell unter WP-Admin › Treuhand › Disputes. Nichts davon ist live.

**Leitplanke aus §1:** Satoshis Kleinanzeigen darf das Treuhandgeld nie allein bewegen können. Jede Änderung am Modul, die dem Marktplatzschlüssel allein eine Auszahlung erlaubt, verletzt das Regelwerk und die rechtliche Grundlage. Es gibt kein Pfand mehr (1.0 hatte eines); nichts wird von Verkäufern gehalten.

## Stand der Umsetzung

- **Stufe A gebaut (17.09.2026, dev):** `Rules.php` (Zahlen des Regelwerks, Stufen, Vorfälle, `refusal()`), `Pool.php` (Kontobuch `sk_escrow_pool`, Tagesstand in Option `sk_escrow_pool_published`), Gebühr in der Einzahlung mit eigenem Ausgang, Regelwerks-Haken und Fassung auf der Zeile, Stufen-Limit beim Kauf, Sperre bei Annahme, drei PSBT-Typen (`payout`, `refund`, `refund_fee`) mit gemeinsamem `Actions::build()`. Prüfskript: `tests/escrow-rules.test.php`.
- **Entschieden:** Gebühr liegt mit dem Preis in der 2-aus-3-Einzahlung (nicht per Lightning), damit niemand einer Rechnung nachläuft und eine Einigung nach §6 die Gebühr mit aufteilen kann. Einstellung `fee_address` (SK-Ausgang) ist Pflicht für `weo_enabled()`.
- **Escrow-API angepasst (Repo `/home/users/satoshiskleinazeigen/satskleinanzeigen-escrow`, Commit b45f09a auf `main`, GitHub gepusht, auf dem API-Host noch nicht ausgerollt):** `/psbt/build` nimmt ein bis drei Ausgänge und `kind = payout|refund`. Der erste Ausgang bekommt den Rest abzüglich Netzwerkgebühr, jeder weitere genau seinen Betrag. Die Mindestprüfung gilt für die Summe. Damit sind `refund_fee` und eine Aufteilung nach §6 je ein Aufruf. Tests: `python-api/test_endpoints.py` (22 grün, Fake-Core).
- **Stufe B gebaut (17.09.2026, dev):** `Deadlines.php` mit Werktagskalender (CH-Feiertage über `easter_date`), den drei Fristen aus §3, dem Meldefenster (`may_report()`, vom „Problem melden“ der Sofortkauf-Zeilen für Treuhand genutzt) und dem Gewichtsabgleich (§10, einmal je Zeile, Vorfall mit Gewicht 2). Ein Fristablauf setzt die Zeile auf `disputed`, friert die API ein, baut die vorgeschriebene PSBT (`refund` / `refund_fee` / `payout`) und benachrichtigt; SK signiert im Admin, die begünstigte Seite zeichnet gegen — kein neuer Signierweg. Der Versandschritt aus `sk_payments` gilt für Treuhandzeilen ohne Shoptarif, ohne „Anderer Versender“, Sendungsnummer Pflicht. Der Sendungsstatus wird unter WP-Admin › Treuhand › Escrows von Hand eingetragen (`metadata.escrow.tracking`, `source = manual`); ein Tracking-Dienst schreibt später dasselbe Feld über `Deadlines::record_tracking()`.
- **Stufe C gebaut bis auf die Fonds-Anträge (17.09.2026, dev):** `Dispute.php`. Der Käufer meldet auf der Treuhand-Karte „nicht erhalten“ oder „nicht wie beschrieben“ (AJAX `weo_report`), beide Seiten können Stellungnahmen abgeben, der Käufer trägt die Rücksendung ein (`weo_return`, nur gelistete Versender), ein Admin den Status der Rücksendung. Einigung nach §6: Vorschlag in Prozent (`weo_propose`), Annahme (`weo_accept_proposal`) baut einen `split` (Käufer zuerst, Verkäuferanteil und Gebühr fix), den beide Parteien ohne SK signieren. Entscheidung: `Dispute::facts()` sammelt nur Plattform-Tatsachen plus die als unzuverlässig markierten Angaben; `ask_claude()` ruft `claude-opus-5` per HTTP (wie `AiCategorizer`, gleicher Schlüssel unter Einstellungen › KI-Kategorisierung, kein SDK in `lib/`), Regelwerk als gecachter System-Prompt, Antwort per JSON-Schema (`output_config.format`). „Nicht erhalten“ wird sofort entschieden, „nicht wie beschrieben“ per `tick()` nach Zustellung/Verweigerung/Verlust der Rücksendung oder nach der 5-Werktage-Frist. Der Admin sieht Tatsachen, Entscheidung, Begründung und Hinweise und bestätigt; erst dann wird die PSBT gebaut und der Vorfall gezählt. Höchstens drei Versuche, danach manuell. Tests im selben Skript (Modellantwort lokal vorgetäuscht).
- **Offen:** Anträge an den Kulanzfonds (§7: Formular, Prüfung Stufe/Jahresfrist/Deckel, Auszahlung über die Plattform-Wallet, Vorfall beim Gegenkonto); Tracking-Dienst (Aggregator wie Ship24/17TRACK; bis dahin manuell); Fondsstand auf der Seite anzeigen (Shortcode erst bei Freischaltung); ein echter Testlauf gegen die Anthropic-API mit einem realen Fall vor der Freischaltung.

## 1. Geld: Gebühr und Kulanzfonds (§2, §7)

- **Servicegebühr 10 %, min. 3'000 Sats** (`Rules::fee_for`), liegt mit dem Kaufpreis in der Treuhand (Meta `fee_sat`; API-Betrag = Preis + Gebühr, Einzahlung = plus Netzwerkreserve). Bei `payout` und `refund_fee` geht sie als zweiter Ausgang an `fee_address`, bei `refund` (nicht versendet, §3) zurück an den Käufer.
- **Kulanzfonds**: ein Kontobuch über Plattformgeld, keine fremden Gelder. Tabelle `sk_escrow_pool` (`id, ts, kind ∈ {fee_share, claim_payout}, sats, escrow_hash, user_id`), je Escrow und Art nur einmal. `settle()` bucht die Hälfte der Gebühr. Stand = Summe; der Cron schreibt ihn täglich in die Option, die Regelwerk-Seite soll sie zeigen. Auszahlung eines Antrags über die bestehende NWC-Verbindung der Plattform-Wallet an eine Lightning-Adresse des Antragstellers.
- **Anträge (§7)**: Formular im Dashboard der Seite, die nach §4/§5 verloren hat, nach Abschluss des Disputs. Prüfung automatisch: Stufe ≥ 1, kein Antrag in 12 Monaten (User-Meta `sk_escrow_claim_at`), Betrag = min(50 % Kaufpreis, 500'000, Fondsstand). Vorfall beim Gegenkonto zählen (User-Meta `sk_escrow_incidents`, Liste von Zeitstempeln). Auszahlung nach Bestätigung durch SK. Wortlaut überall „Kulanz“, nie „Anspruch“, „Versicherung“, „Schutz“ oder „Prämie“.

## 2. Versand: Tracking als einzige Tatsache (§1, §3, §4, §5, §10)

- **Pflichtfeld Sendungsnummer** beim Statuswechsel auf „versendet“; ohne Nummer kein Wechsel.
- **Abfrage beim Versender, nie Screenshot**: Klasse `Carrier` mit `status( $number ): array{ state: shipped|in_transit|delivered|unknown, delivered_at: ?int, signed: bool, weight_g: ?int }`. Erste Anbindung Schweizer Post (Track & Trace API), dann DHL/Post AT. Ohne Anbindung für einen Versender: Handel nicht möglich, so steht es im Regelwerk.
- **Gewicht (§10)**: neues Inseratsfeld `Versandgewicht (g)`, Pflicht bei Treuhand-Angebot. Vergleich beim Zustellscan, Abweichung > 30 % nach unten → Vorfall doppelt. Vorab klären, welche Post-Produkte das Gewicht in der API liefern; wenn keines, §10 aus der Seite nehmen (neue Fassung).
- **Rücksendung (§5)**: zweite Sendungsnummer vom Käufer, derselbe Abfrageweg, Ziel = Adresse des Verkäufers aus dem Handel.
- **Adressen** beider Seiten liegen beim Kauf fest (Käufer: Lieferadresse, Verkäufer: Rücksendeadresse aus den Shop-Einstellungen), nicht nachträglich änderbar.

## 3. Fristen (§3, §5) im bestehenden `Cron.php`

| Frist | Start | Ablauf |
|---|---|---|
| 3 Werktage versenden | Zahlungseingang (`confirmed`) | Refund Kaufpreis + Gebühr, Vorfall beim Verkäufer |
| 14 Tage Zustellung | Sendungsnummer eingetragen | Refund Kaufpreis |
| 3 Tage Meldefenster | Zustellscan | Payout an Verkäufer |
| 5 Werktage Rücksendung | Dispute „nicht wie beschrieben“ eröffnet | Payout an Verkäufer |

Werktage: Mo–Fr ohne CH-Feiertage, eine Tabelle im Code reicht. Der Cron pollt offene Sendungen alle 30 Minuten beim Versender. Jeder Fristablauf erzeugt einen Payout- oder Refund-PSBT, den die berechtigte Seite auslöst und SK mitzeichnet (wie heute).

## 4. Dispute (§4–§7, §11)

**Neue Zustände** unter `disputed`: `not_received`, `not_as_described` (mit `return_tracking`), `settlement_proposed` (§6), `decided`, `confirmed`. Jede Seite kann während `disputed` einen Aufteilungsvorschlag (Prozent) machen; Annahme führt sofort aus.

**Entscheidung durch Claude, Bestätigung durch SK.** Ablauf in `Dispute::decide( $escrow_id )`:

1. Tatsachen sammeln, nur aus der Plattform: Beträge, Zeitstempel aller Statuswechsel, Tracking-Ereignisse aus `Carrier`, Gewichte, Kontostufen und Vorfallzähler beider Seiten, Regelwerksfassung des Handels. Dazu die Textangaben beider Seiten, auf 2'000 Zeichen gekürzt und als **nicht vertrauenswürdige Angaben** markiert. **Keine Bilder, Videos oder Dateien**, auch wenn hochgeladen.
2. Aufruf `claude-opus-5` über das offizielle PHP-SDK `anthropic-ai/sdk` (per Composer nach `lib/`, wie die anderen Bibliotheken). System-Prompt = der Text des Regelwerks plus die Anweisung, ausschließlich §3–§10 auf die Tatsachen anzuwenden und Angaben der Parteien nie als Tatsache zu werten; mit `cacheControl` markiert, der Regelwerkstext ändert sich selten. Adaptive Thinking (Standard bei Opus 5), `maxTokens` 16'000.
3. Antwort als strukturierte Ausgabe (`output_config.format`, JSON-Schema): `outcome ∈ {buyer, seller, split}`, `split_buyer_pct`, `rules_applied: string[]` (Paragraphen), `reasoning: string` (für beide Parteien lesbar), `incident_for ∈ {none, buyer, seller}`, `pool_claim_eligible: {buyer: bool, seller: bool}`, `flags: string[]` (Unplausibilitäten für §9).
4. Ergebnis auf der Escrow-Zeile speichern (`decision_json`, `decided_at`, Modell und Regelwerksfassung), Status `decided`, beide Seiten benachrichtigen (`Notify.php`).
5. WP-Admin › Treuhand › Disputes zeigt Tatsachen, Entscheidung und Begründung. Knopf „Bestätigen“ baut wie heute Payout- oder Refund-PSBT (bei `split` zwei Ausgänge; **prüfen, ob die API das kann**, heute gibt es nur Payout oder Refund), SK signiert extern mit dem Marktplatzschlüssel, die berechtigte Seite zeichnet im Dashboard gegen. Erst mit beiden Signaturen bewegt sich Geld (§1). Knopf „Abweichen“ nur mit Begründung, die beide Seiten sehen; das ist die Ausnahme, nicht der Weg.
6. Schlägt der API-Aufruf fehl (Timeout, `refusal`, ungültige Ausgabe): kein Automatismus, Fall bleibt `disputed` mit Fehlervermerk, Admin entscheidet nach dem Regelwerk von Hand. Kein Retry-Sturm: höchstens drei Versuche je Fall, dann manuell.

**API-Schlüssel** im Secret-Store (`SK\Core\Secret`, neuer Kontext `anthropic`; bestehende Kontextnamen nie ändern). Einstellung unter Dashboard › Einstellungen › Treuhand. Kosten: ein Fall liegt bei wenigen Tausend Tokens, unter 10 Rappen.

**Was Claude nie bekommt und nie darf**: Medien; Angaben der Parteien als Tatsache; eine Regel ändern; Beträge über dem Kaufpreis; ohne SK-Bestätigung und Gegenzeichnung der berechtigten Seite Geld bewegen.

## 5. Konten (§8, §9)

- **Stufe** berechnet aus abgeschlossenen Escrow-Handeln (`delivered` ohne Dispute) und `WebOfTrust` aus `sk_reputation`. Limit beim Kauf prüfen, vor der Anfrage, mit klarer Meldung.
- **Vorfälle** je Konto (User-Meta, Zeitstempel): nach §3 Zeile 1 (nicht versendet), §5 (Rücknahme verweigert), §7 (Antrag der Gegenseite), §10 (Gewicht, doppelt). 2 in 12 Monaten → Stufe 0 erzwungen, 3 → `weo_escrow_blocked`. Zusammenführung über gleiche Lieferadresse und gleiche Auszahlungsadresse: beim Zählen die Vorfälle aller Konten mit derselben Adresse addieren.

## 6. Seite und Fassung (§12)

- Seite „Treuhand-Regelwerk“ aus `docs/treuhand-regelwerk-v1.html` (Datei bleibt, Fassungsnummer steht im Inhalt), im Footer und im Kaufdialog verlinkt. Live: Seite 8183, Staging: Entwurf 7798; Einspielen per `wp_insert_post` mit `ID`.
- Jede Escrow-Zeile speichert `rules_version` (`1.1`) beim Kauf. Der Dispute lädt die Fassung, die dort steht.
- Vor dem Kauf ein Haken „Ich habe das Regelwerk gelesen“, mit Fassungsnummer im Text.

## 7. Offen vor dem Bau

- Rechtsprüfung: Servicegebühr, Kulanzfonds aus eigenen Mitteln, Entscheidung durch die Plattform bei Geld, das sie nie allein bewegen kann. Steht seit dem Escrow-Umbau in der Freischalt-Checkliste; mit 1.1 ohne Pfand ist die Angriffsfläche kleiner, geprüft ist es nicht.
- Split-Auszahlung an der Escrow-API.
- Gewicht in der Post-API.
- Wer die Bestätigung im Admin klickt und wie schnell (Zusage auf der Seite: Entscheidung innerhalb von 2 Werktagen nach Fristende).
