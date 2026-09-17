# Treuhand nach Regelwerk 1.0: was das Modul `sk_escrow` dafür können muss

Stand 17.09.2026. Regelwerk: `docs/treuhand-regelwerk-v1.html` (§-Verweise unten). Ist-Stand des Moduls: Escrow-Zeile in `sk_lightning_payments` mit `context = 'escrow'`, Status `requested → pending → confirmed → delivered | refunded | disputed | expired`, PSBT-Fluss über die externe API, Dispute heute manuell unter WP-Admin › Treuhand › Disputes. Nichts davon ist live.

## 1. Geld: Gebühr, Pfand, Topf (§2, §3, §8)

- **Gebühr 10 %, min. 3'000 Sats**, vom Käufer zusätzlich zum Kaufpreis eingezahlt. Beim Einzahlen als eigener Betrag geführt (Meta auf der Escrow-Zeile: `fee_sats`), nie Teil des Payout an den Verkäufer.
- **Pfand 5 %** des Verkäufers: eigene Zahlung beim Annehmen des Handels (heute nimmt der Verkäufer nur an; neu: Annahme erst gültig, wenn das Pfand eingegangen ist). Eigene Zeile `context = 'escrow_bond'`, verknüpft über die Escrow-ID. Rückgabe bei `delivered`/`refunded` nach Regel, Verfall an den Topf nach §4 Zeile 1, §6 letzter Punkt.
- **Schutztopf**: ein Kontobuch, keine Wallet-Logik. Tabelle `sk_escrow_pool` (`id, ts, escrow_id, kind ∈ {fee, bond_forfeit, claim_payout}, sats, user_id`). Stand = Summe. Der Topf liegt physisch in der Plattform-Wallet (`SK\Core\Wallet`), Auszahlung eines Antrags über die bestehende NWC-Verbindung an eine Lightning-Adresse des Antragstellers.
- **Topfstand monatlich veröffentlichen**: Cron schreibt Summe und Datum in eine Option, die Regelwerk-Seite liest sie.
- **Anträge (§8)**: Formular im Dashboard des Verlierers nach Abschluss eines Disputs, Prüfung automatisch: Stufe ≥ 1, kein Antrag in 12 Monaten (User-Meta `sk_escrow_claim_at`), Betrag = min(50 % Kaufpreis, 500'000, Topfstand). Vorfall beim Gegenkonto zählen (`sk_escrow_incidents`, Liste von Zeitstempeln). Auszahlung nach Bestätigung durch SK.

## 2. Versand: Tracking als einzige Tatsache (§1, §4, §5, §6, §11)

- **Pflichtfeld Sendungsnummer** beim Statuswechsel auf „versendet“; ohne Nummer kein Wechsel.
- **Abfrage beim Versender, nie Screenshot**: Klasse `Carrier` mit `status( $number ): array{ state: shipped|in_transit|delivered|unknown, delivered_at: ?int, signed: bool, weight_g: ?int }`. Erste Anbindung Schweizer Post (Track & Trace API), dann DHL/Post AT. Ohne Anbindung für einen Versender: Handel nicht möglich, so steht es im Regelwerk.
- **Gewicht (§11)**: neues Inseratsfeld `Versandgewicht (g)`, Pflicht bei Treuhand-Angebot. Vergleich beim Zustellscan, Abweichung > 30 % nach unten → Vorfall doppelt. Vorab klären, welche Post-Produkte das Gewicht in der API liefern; wenn keines, §11 aus der Seite nehmen.
- **Rücksendung (§6)**: zweite Sendungsnummer vom Käufer, derselbe Abfrageweg, Ziel = Adresse des Verkäufers aus dem Handel.
- **Adressen** beider Seiten liegen beim Kauf fest (Käufer: Lieferadresse, Verkäufer: Rücksendeadresse aus den Shop-Einstellungen), nicht nachträglich änderbar.

## 3. Fristen (§4, §6) im bestehenden `Cron.php`

| Frist | Start | Ablauf |
|---|---|---|
| 3 Werktage versenden | Zahlungseingang (`confirmed`) | Refund Kaufpreis + Gebühr, Pfand → Topf |
| 14 Tage Zustellung | Sendungsnummer eingetragen | Refund Kaufpreis, Pfand zurück |
| 3 Tage Meldefenster | Zustellscan | Payout an Verkäufer, Pfand zurück |
| 5 Werktage Rücksendung | Dispute „nicht wie beschrieben“ eröffnet | Payout an Verkäufer |

Werktage: Mo–Fr ohne CH-Feiertage, eine Tabelle im Code reicht. Der Cron pollt offene Sendungen alle 30 Minuten beim Versender.

## 4. Dispute (§5–§8, §12)

**Neue Zustände** unter `disputed`: `not_received`, `not_as_described` (mit `return_tracking`), `settlement_proposed` (§7), `decided`, `confirmed`. Jede Seite kann während `disputed` einen Aufteilungsvorschlag (Prozent) machen; Annahme führt sofort aus.

**Entscheidung durch Claude, Bestätigung durch SK.** Ablauf in `Dispute::decide( $escrow_id )`:

1. Tatsachen sammeln, nur aus der Plattform: Beträge, Zeitstempel aller Statuswechsel, Tracking-Ereignisse aus `Carrier`, Gewichte, Kontostufen und Vorfallzähler beider Seiten, Regelwerksfassung des Handels. Dazu die Textangaben beider Seiten, auf 2'000 Zeichen gekürzt und als **nicht vertrauenswürdige Angaben** markiert. **Keine Bilder, Videos oder Dateien**, auch wenn hochgeladen.
2. Aufruf `claude-opus-5` über das offizielle PHP-SDK `anthropic-ai/sdk` (per Composer nach `lib/`, wie die anderen Bibliotheken). System-Prompt = der Text des Regelwerks plus die Anweisung, ausschließlich §4–§11 auf die Tatsachen anzuwenden und Angaben der Parteien nie als Tatsache zu werten; mit `cacheControl` markiert, der Regelwerkstext ändert sich selten. Adaptive Thinking (Standard bei Opus 5), `maxTokens` 16'000.
3. Antwort als strukturierte Ausgabe (`output_config.format`, JSON-Schema): `outcome ∈ {buyer, seller, split}`, `split_buyer_pct`, `bond ∈ {return, forfeit}`, `rules_applied: string[]` (Paragraphen), `reasoning: string` (für beide Parteien lesbar), `pool_claim_eligible: {buyer: bool, seller: bool}`, `flags: string[]` (Unplausibilitäten für §10).
4. Ergebnis auf der Escrow-Zeile speichern (`decision_json`, `decided_at`, Modell und Regelwerksfassung), Status `decided`, beide Seiten benachrichtigen (`Notify.php`).
5. WP-Admin › Treuhand › Disputes zeigt Tatsachen, Entscheidung und Begründung. Knopf „Bestätigen“ baut wie heute Payout- oder Refund-PSBT (bei `split` zwei Ausgänge; **prüfen, ob die API das kann**, heute gibt es nur Payout oder Refund), SK signiert extern mit dem Marktplatz-Key, Begünstigter zeichnet im Dashboard gegen. Knopf „Abweichen“ nur mit Begründung, die beide Seiten sehen; das ist die Ausnahme, nicht der Weg.
6. Schlägt der API-Aufruf fehl (Timeout, `refusal`, ungültige Ausgabe): kein Automatismus, Fall bleibt `disputed` mit Fehlervermerk, Admin entscheidet nach dem Regelwerk von Hand. Kein Retry-Sturm: höchstens drei Versuche je Fall, dann manuell.

**API-Schlüssel** im Secret-Store (`SK\Core\Secret`, neuer Kontext `anthropic`; bestehende Kontextnamen nie ändern). Einstellung unter Dashboard › Einstellungen › Treuhand. Kosten: ein Fall liegt bei wenigen Tausend Tokens, unter 10 Rappen.

**Was Claude nie bekommt und nie darf**: Medien; Angaben der Parteien als Tatsache; eine Regel ändern; Beträge über dem Kaufpreis; ohne SK-Bestätigung Geld bewegen.

## 5. Konten (§9, §10)

- **Stufe** berechnet aus abgeschlossenen Escrow-Handeln (`delivered` ohne Dispute) und `WebOfTrust` aus `sk_reputation`. Limit beim Kauf prüfen, vor der Anfrage, mit klarer Meldung.
- **Vorfälle** je Konto (User-Meta, Zeitstempel). 2 in 12 Monaten → Stufe 0 erzwungen, 3 → `weo_escrow_blocked`. Zusammenführung über gleiche Lieferadresse und gleiche Auszahlungsadresse: beim Zählen die Vorfälle aller Konten mit derselben Adresse addieren.

## 6. Seite und Fassung (§13)

- Seite „Treuhand-Regelwerk“ aus `docs/treuhand-regelwerk-v1.html`, im Footer und im Kaufdialog verlinkt.
- Jede Escrow-Zeile speichert `rules_version` (`1.0`) beim Kauf. Der Dispute lädt die Fassung, die dort steht.
- Vor dem Kauf ein Haken „Ich habe das Regelwerk gelesen“, mit Fassungsnummer im Text.

## 7. Offen vor dem Bau

- Rechtsprüfung: Gebühr plus Kulanztopf, Pfand und Entscheidung durch die Plattform (Zahlungsdienst? Versicherungsähnlich?). Steht seit dem Escrow-Umbau in der Freischalt-Checkliste, wird damit dringender.
- Split-Auszahlung an der Escrow-API.
- Gewicht in der Post-API.
- Wer die Bestätigung im Admin klickt und wie schnell (Zusage auf der Seite: Entscheidung innerhalb von 2 Werktagen nach Fristende).
