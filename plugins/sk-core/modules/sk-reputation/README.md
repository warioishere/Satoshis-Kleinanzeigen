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

## Signalquellen

| Signal | Rechnet | Sichtbar wenn |
|---|---|---|
| Erhaltene Zaps | Server (sk-zaps, `ZapStats`) | Anbieter hat Zaps erhalten |
| Verifizierter Link | Server (`sk_verified_badge`) | Link bestätigt |
| Lightning-Proofs | Server, nur mit SK Payments | Payments aktiv und Zahlungen verifiziert |

Geplant, in dieser Reihenfolge: Nostr-Schlüsselbindung (nur signierte
Schlüssel zählen), Graph-Signal im Browser („Du folgst" / „X deiner Kontakte
folgen"), Vertrauensseite statt Proof-Seite, NIP-05 pro Shop als Angebot,
Kind-1984-Meldungen aus dem Graphen des Betrachters.

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
