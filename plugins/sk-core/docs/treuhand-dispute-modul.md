# Treuhand nach Regelwerk 1.1: was das Modul `sk_escrow` dafür können muss

Stand 17.09.2026. Regelwerk: `docs/treuhand-regelwerk-v1.html`, Fassung 1.1 (§-Verweise unten). Ist-Stand des Moduls: Escrow-Zeile in `sk_lightning_payments` mit `context = 'escrow'`, Status `requested → pending → confirmed → delivered | refunded | disputed | expired`, PSBT-Fluss über die externe API mit drei Schlüsseln (Käufer, Verkäufer, Marktplatz; jede Bewegung braucht zwei), Dispute heute manuell unter WP-Admin › Treuhand › Disputes. Nichts davon ist live.

**Leitplanke aus §1:** Satoshis Kleinanzeigen darf das Treuhandgeld nie allein bewegen können. Jede Änderung am Modul, die dem Marktplatzschlüssel allein eine Auszahlung erlaubt, verletzt das Regelwerk und die rechtliche Grundlage. Es gibt kein Pfand mehr (1.0 hatte eines); nichts wird von Verkäufern gehalten.

## 1. Geld: Gebühr und Kulanzfonds (§2, §7)

- **Servicegebühr 10 %, min. 3'000 Sats**, vom Käufer zusätzlich zum Kaufpreis eingezahlt. Beim Einzahlen als eigener Betrag geführt (Meta auf der Escrow-Zeile: `fee_sats`), nie Teil der Auszahlung an den Verkäufer. Erstattung nur nach §3 Zeile 1 (Verkäufer versendet nicht).
- **Kulanzfonds**: ein Kontobuch über Plattformgeld, keine fremden Gelder. Tabelle `sk_escrow_pool` (`id, ts, escrow_id, kind ∈ {fee_share, claim_payout}, sats, user_id`). Stand = Summe; monatlich per Cron in eine Option, die Regelwerk-Seite zeigt sie. Auszahlung eines Antrags über die bestehende NWC-Verbindung der Plattform-Wallet an eine Lightning-Adresse des Antragstellers.
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
