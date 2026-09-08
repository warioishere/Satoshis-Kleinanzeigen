# SK Reputation — nachprüfbare Vertrauenssignale

## Grundsätze

SK Reputation zeigt neben einem Anbieter Fakten, die der Betrachter selbst
nachprüfen kann. Drei Regeln bestimmen alles Weitere:

1. **Relativ zum Betrachter, nicht zum Betreiber.** SK behauptet nie „dieser
   Anbieter ist vertrauenswürdig". Signale wie „3 deiner Kontakte folgen
   diesem Anbieter" rechnet der Browser des Betrachters aus seinem eigenen
   Nostr-Graphen. Der Marktplatz-Schlüssel spielt dabei keine Rolle.
2. **Abwesenheit ist stumm.** Kein Signal heißt: der Anbieter sieht aus wie
   vorher. Es gibt keine Negativanzeige, kein „unverifiziert", keine Null.
3. **Jedes Signal hat ein „Warum".** Quelle und Prüfweg sind sichtbar
   (Kontakte mit Namen, Events auf den Relays, Payment-Hashes).

Signale stehen nebeneinander und werden nie zu einer Zahl verrechnet.

## Aufbau

```
includes/Trust/TrustSignals.php   # Core: zentrale Registry der Chips (unabhängig vom Modul)
modules/sk-reputation/
├── module.php                    # Schalter, bootet die Signalquellen ab init
├── includes/
│   ├── Settings.php              # eigene Einstellungssektion sk_reputation
│   ├── Calculator.php            # Payments-Signal: Sybil-Checks + Score
│   ├── Cron.php                  # Payments-Signal: 6h-Cron, NIP-32-Label
│   └── ProofPage.php             # Payments-Signal: Store-Tab + Proof-Seite
└── templates/store-lightning-proof.php
```

### Registry `SK\Core\Trust\TrustSignals`

Module registrieren einen Renderer pro Signal:

```php
TrustSignals::register( 'zaps', [ ZapStats::class, 'chip' ], 20 );
```

Der Renderer bekommt `( int $vendor_id, string $context )` und liefert das
fertige HTML des Chips oder `''`. Kontexte: `store` (ein `<li>` in der
Info-Liste des Store-Banners), `product` (inline in der Anbieterbox der
Produktseite), `feed` (Feed-Karte). Die Templates rufen
`TrustSignals::render( $vendor_id, $context )` auf; es gibt keinen
`do_action`-Slot mehr.

Die Registry liegt in Core, damit ein Chip wie die Zap-Summe auch dann
erscheint, wenn das Reputation-Modul aus ist.

### Schalter

Eigene Sektion „SK Reputation" in den Admin-Einstellungen
(`sk_reputation_enabled`, Option `sk_reputation`). Der Schalter hing früher in
der SK-Payments-Sektion; das Modul ist jetzt unabhängig von Payments.

## Schlüsselbindung `SK\Core\Trust\VendorKey`

Welcher Nostr-Schlüssel für einen Anbieter zählt. `VendorKey::bound( $id )`
liefert nur einen Schlüssel, dessen Inhaber die Kontrolle gegenüber dieser
Seite bewiesen hat, sonst `''`. Ein bloß in die Store-Einstellungen
getippter npub ist eine Behauptung und zählt nicht. Niemand muss dafür etwas
eintragen; der Nachweis entsteht aus dem, was ohnehin passiert:

| Herkunft des Schlüssels | Nachweis |
|---|---|
| Nostr-Login | Das signierte NIP-98-Event des Logins setzt den Schlüssel auf den Nutzer; `NostrLogin` schreibt ihn sonst nie ohne Signatur (der Profil-Sync darf den Schlüssel nicht mehr setzen oder ändern). Das Login-Event wird als `sk_nostr_login_proof` aufbewahrt. |
| Von SK erzeugte Identität, Plattformkonto | SK hält den Schlüssel und signiert die Bindung selbst. |
| Getippter npub | Beim nächsten Dashboard-Besuch mit Erweiterung fragt `assets/js/sk-key-binding.js` still nach dem Schlüssel; ist es derselbe, signiert die Erweiterung einmal das Bindungs-Event. Ein anderer Schlüssel oder ein abgelehntes Popup ändert nichts. |

Das Bindungs-Event ist Kind 30078 (NIP-78), `d` = Host der Seite, `r` =
Store-URL, `p` = Marktplatz-Schlüssel. Es liegt als `sk_nostr_binding` auf
dem Nutzer (dazu `sk_nostr_bound_pubkey` für die Eindeutigkeitsprüfung) und
wird per Cron `sk_trust_publish_binding` auf die Relays veröffentlicht
(`sk_nostr_binding_relays` merkt, wer es angenommen hat). Ein Schlüssel kann
nur an ein Konto gebunden sein. Wird der getippte npub geändert, gilt die
alte Bindung nicht mehr.

Beim Speichern der Events `wp_slash()` verwenden: die Meta-API entfernt
Backslashes und macht aus `ü` ein `u00fc`, womit die Event-ID nicht
mehr stimmt.

## Graph-Signal `SocialGraph` + `assets/js/sk-social-graph.js`

„Du folgst diesem Anbieter" und „X deiner Kontakte folgen". Der Server
rendert nur einen leeren, versteckten Chip mit dem gebundenen Schlüssel des
Anbieters (`.sk-trust-graph[data-pubkey]`), in allen drei Kontexten. Alles
Weitere passiert im Browser des Betrachters:

1. Schlüssel des Betrachters: bei eingeloggten Nutzern der gebundene
   Schlüssel aus `skTrustGraph.viewer` (keine Erweiterung nötig), sonst
   `window.nostr.getPublicKey()`. Abgelehnt → einen Tag nicht mehr gefragt.
2. Eigene Kontaktliste (Kind 3) von **allen** Relays parallel, neueste
   gewinnt. Kein Relay hat alles; die Liste des Marktplatz-Schlüssels lag
   z. B. nur auf nos.lol.
3. Grad 2 billig: eine REQ mit `kinds:[3]`, `authors` = eigene Kontakte
   (200 je Filter, 10 Filter je REQ), `#p` = Anbieter-Schlüssel der Seite.
   Zurück kommen nur die Kontaktlisten, die einen Anbieter enthalten;
   neueste je Autor, dann zählen. Zwei Relays parallel.
4. Namen der Folgenden (Kind 0, bis 8 je Anbieter) für das „Warum": Klick
   auf den Chip öffnet die Liste mit Links nach njump.

Alles einen Tag in `localStorage` (`skTrust:v1:*`). Kein Schlüssel, keine
Kontakte, keine Treffer, Anbieter = Betrachter: der Chip bleibt versteckt.
Feed-Karten, die per AJAX nachkommen, findet ein MutationObserver.

Gemessen (Plattformkonto als Betrachter, 75 Kontakte): 2,9 s bis der Chip
steht, „Du folgst · 7 deiner Kontakte folgen".

## Signalquellen

| Signal | Rechnet | Sichtbar wenn |
|---|---|---|
| Du folgst / X Kontakte folgen | Browser des Betrachters | Betrachter hat Schlüssel und Treffer |
| Erhaltene Zaps | Server (sk-zaps, `ZapStats`) | Anbieter hat Zaps erhalten (nicht im Feed) |
| Verifizierter Link | Server (`sk_verified_badge`) | Link bestätigt |
| Lightning-Proofs | Server, nur mit SK Payments | Payments aktiv und Zahlungen verifiziert |

## Vertrauensseite `TrustPage` + `templates/store-trust.php`

`/store/{slug}/vertrauen/`, Store-Tab „Vertrauen". Jedes Signal als Karte
mit Quelle und Prüfweg: Dein Netzwerk (Graph-Chip in voller Länge, dazu die
Hinweise „ohne Schlüssel" / „keine Überschneidung", die das JS nur hier
einblendet), Nostr-Schlüssel (npub mit njump-Link, Art des Nachweises,
Relays, signiertes Event zum Aufklappen), bestätigte Links, erhaltene Zaps,
und mit SK Payments die belegten Zahlungen (`store-trust-lightning.php`,
die frühere Proof-Liste). Der Tab erscheint nur, wenn der Server mindestens
ein Signal kennt (`TrustPage::has_signals`). Die alte Adresse
`/lightning-proof/` zeigt dieselbe Seite.

## NIP-05 pro Shop

`/.well-known/nostr.json?name={slug}` (sk-auth) antwortet für jeden
Anbieter mit nachgewiesenem Schlüssel, nie mit einem behaupteten
(`VendorKey::bound`). Die Vertrauensseite nennt die Adresse; wer sie in
sein Nostr-Profil einträgt, bekommt in jedem Client das Häkchen der Seite.
Einrichten muss niemand etwas.

## Meldungen (Kind 1984, NIP-56)

**Für Käufer, relativ zum Betrachter:** dieselbe REQ wie für Grad 2 fragt
zusätzlich `kinds:[1984]` mit `authors` = eigene Kontakte und `#p` =
Anbieter. Treffer mit Typ `spam`, `impersonation`, `illegal` oder
`malware` (nicht `nudity`/`profanity`, nicht „Automated …") ergeben einen
eigenen bernsteinfarbenen Chip neben dem Graph-Chip: „2 deiner Kontakte
haben diesen Anbieter gemeldet", mit Melder, Typ und Link zum Event. Nur
aus den eigenen Kontakten; der Marktplatz filtert nichts.

**Für den Betreiber (`Reports`, Cron `sk_reputation_fetch_reports`,
täglich):** liest Meldungen gegen alle nachgewiesenen Anbieter-Schlüssel
(und den Marktplatz-Schlüssel) von den Relays, prüft Signaturen, behält
nur Melder aus dem Web of Trust: Follows des Marktplatz-Schlüssels, deren
Follows (Transient `sk_reputation_wot`, Präfixe von 16 Hex, 1 Tag) und die
nachgewiesenen Schlüssel registrierter Anbieter. Ergebnis je Anbieter in
`sk_nostr_reports` (ersetzt, nie gemischt), Übersicht unter „SK Reputation"
im Admin-Menü mit „Jetzt abrufen", eine Mail pro Tag bei neuen Meldungen.
Käufer sehen davon nichts.

Befund beim Bau (2026-09-08): sechs Meldungen gegen 59 Anbieter-Schlüssel,
davon vier von einem NSFW-Bot, eine leer, eine „wrong click"; keine aus
dem Web of Trust. Ohne Filter wäre das Signal reines Rauschen.

## Payments-Signal (nur mit SK Payments)

### Reputation-Flow

1. Käufer bezahlt via Lightning oder Onchain → Payment-Status wird `confirmed`
2. `reputation_at` wird auf confirmed_at + 7 Tage gesetzt
3. **Weg A**: Käufer klickt „Produkt erhalten" → Reputation wird sofort gutgeschrieben
4. **Weg B**: 7 Tage vergehen ohne Aktion → Cron creditiert automatisch
5. Vor Gutschrift: Sybil-Checks + Validierung

### Sybil-Detection

| Check | Was geprüft wird | Flag |
|-------|-------------------|------|
| **IP-Overlap** | Verkäufer hat selbst von diesem IP-Hash gekauft, ODER ≥3 verschiedene Käufer-Accounts desselben Verkäufers teilen sich einen IP-Hash | `same_network` |
| **Circular Payment** | A bezahlt B UND B bezahlt A innerhalb 30 Tagen | `circular_payment` |
| **Ring Detection** | A→B, B→C, C→A innerhalb 7 Tagen | `ring_detected` |
| **Burst Detection** | >5 **bezahlte** Zahlungen von Accounts <14 Tage alt in 24h | `burst_new_accounts` |

Die IP des Verkäufers wird nirgends erfasst. Die Schwelle von 3 beim zweiten
Signal ist bewusst gesetzt: zwei Personen hinter einem CGNAT sind normal.
Burst zählt nur `confirmed`/`delivered`, sonst könnte jeder mit sechs nie
bezahlten Invoices einem Konkurrenten die Reputation abdrehen. Bei Burst wird
der Admin per E-Mail benachrichtigt.

### Validierung

Eine Zahlung zählt, wenn `confirmed_via` in `nwc`, `lndhub`, `lud21`,
`onchain`, `preimage` liegt, das Produkt existiert und ≥ 24h vor der Zahlung
veröffentlicht war, der Betrag ≥ 1.000 Sats ist, der Käufer-Account ≥ 7 Tage
alt ist und keine Sybil-Flags gesetzt sind. Der Knopf „Zahlung bestätigen"
(`confirmed_via = 'vendor'`) zählt nie.

### Score und Badges

Score: Unique Buyers (10 je, max 500) + valide TXs (5 je, max 250) + Volumen
(1 je 10.000 Sats, max 250). Badges ab 5 / 25 / 100 verifizierten TXs
(Lightning Starter / Händler / Veteran), als NIP-32-Label (Kind 1985) mit dem
Marktplatz-Schlüssel veröffentlicht.

### Proof Page

`/store/{vendor}/lightning-proof/` zeigt alle verifizierten Transaktionen mit
Payment-Hash und bolt11 bzw. Adresse und TX-Link; JSON unter
`/wp-json/sk/v1/lightning/proof/{vendor_id}`.

### Datenbank

`wp_sk_lightning_payments` (gelesen): `reputation_valid`, `reputation_state`
(`pending` | `credited` | `rejected`, jede Zahlung wird genau einmal
entschieden), `reputation_flags`, `reputation_at`, `confirmed_via`.
`wp_sk_reputation_scores` (geschrieben): Zähler und Score je `vendor_id`.

### Cron

`sk_recalculate_reputation_scores` alle 6 Stunden: offene Zahlungen
entscheiden, Scores neu rechnen, abgelaufene Invoices schließen.
