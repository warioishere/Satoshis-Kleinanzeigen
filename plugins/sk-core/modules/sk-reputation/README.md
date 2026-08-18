# SK Reputation — Sybil-resistentes Reputationssystem

## Übersicht

SK Reputation ist ein eigenständiges sk-core Modul das ein kryptographisch verifizierbares Reputationssystem für den Marketplace bereitstellt. Basiert auf tatsächlichen Bitcoin-Zahlungen (Lightning + Onchain) aus dem SK Payments Modul.

## Wie es funktioniert

### Reputation-Flow

1. Käufer bezahlt via Lightning oder Onchain → Payment-Status wird `confirmed`
2. `reputation_at` wird auf confirmed_at + 7 Tage gesetzt
3. **Weg A**: Käufer klickt "Produkt erhalten" → Reputation wird sofort gutgeschrieben
4. **Weg B**: 7 Tage vergehen ohne Aktion → Cron creditiert automatisch
5. Vor Gutschrift: Sybil-Checks + Validierung

### Sybil-Detection

Jede Zahlung wird auf Manipulation geprüft bevor sie als Reputation zählt:

| Check | Was geprüft wird | Flag |
|-------|-------------------|------|
| **IP-Overlap** | Verkäufer hat selbst von diesem IP-Hash gekauft, ODER ≥3 verschiedene Käufer-Accounts desselben Verkäufers teilen sich einen IP-Hash | `same_network` |
| **Circular Payment** | A bezahlt B UND B bezahlt A innerhalb 30 Tagen | `circular_payment` |
| **Ring Detection** | A→B, B→C, C→A innerhalb 7 Tagen | `ring_detected` |
| **Burst Detection** | >5 **bezahlte** Zahlungen von Accounts <14 Tage alt in 24h | `burst_new_accounts` |

Die IP des Verkäufers wird nirgends erfasst — er ist am Zahlungs-Request nicht beteiligt. Der IP-Check arbeitet deshalb ausschließlich mit `buyer_ip_hash`. Die Schwelle von 3 beim zweiten Signal ist bewusst gesetzt: zwei Personen hinter einem CGNAT oder Büroanschluss sind normal.

Burst zählt nur `confirmed`/`delivered`. Würden auch `pending`-Zeilen zählen, könnte jeder mit sechs frischen Accounts und sechs nie bezahlten Invoices einem beliebigen Konkurrenten die Reputation abdrehen.

Bei Burst-Detection wird der Admin per E-Mail benachrichtigt.

### Validierungs-Kriterien

Eine Zahlung qualifiziert sich für Reputation wenn:

- **Die Zahlung ist nachgewiesen** — `confirmed_via` ist `nwc`, `lndhub`, `lnurl` oder `onchain`
- Produkt existiert und war >= 24h vor Zahlung veröffentlicht
- Betrag >= 1.000 Sats
- Käufer-Account >= 7 Tage alt
- Keine Sybil-Flags

Der Knopf „Zahlung bestätigen" im Verkäufer-Dashboard setzt `confirmed_via = 'vendor'`. Er bleibt für den Handelsablauf erhalten, prüft aber nichts und **zählt deshalb nie für Reputation**. Verkäufer ohne angebundene Wallet und ohne Onchain-Adresse bauen keine Reputation auf — das ist Absicht, sonst wäre die Zahl beliebig erfindbar.

Das Produkt muss zum Zeitpunkt der Gutschrift nur noch *existieren*, nicht mehr `publish` sein. Auf einem Kleinanzeigen-Marktplatz wird ein verkauftes Einzelstück sofort depubliziert — mit der alten Regel hätte genau der Normalfall nie gezählt.

Alle Zeitvergleiche laufen in UTC. `created_at` steht in Site-Zeit und wird konvertiert.

### Reputation Score

Gewichteter Score aus drei Faktoren:
- **Unique Buyers** — max 500 Punkte (10 pro Buyer)
- **Valide Transaktionen** — max 250 Punkte (5 pro TX)
- **Volumen** — max 250 Punkte (1 pro 10.000 Sats)

### Badges

| Verifizierte TXs | Badge | Label |
|-------------------|-------|-------|
| >= 5 | ⚡ | Lightning Starter |
| >= 25 | ⚡⚡ | Lightning Händler |
| >= 100 | ⚡⚡⚡ | Lightning Veteran |

### Proof Page

Öffentliche Seite unter `/store/{vendor}/lightning-proof/` die alle verifizierten Transaktionen zeigt:

- **Lightning-Proofs**: Payment-Hash + bolt11 Invoice → verifizierbar auf lightningdecoder.com
- **Onchain-Proofs**: BTC-Adresse + TX-Link auf mempool.space
- **JSON API**: `/wp-json/sk/v1/lightning/proof/{vendor_id}` für maschinelle Verifizierung

Motto: **Don't trust, verify.**

## Technische Architektur

```
modules/sk-reputation/
├── module.php                    # Entry Point, Namespace SK\Modules\Reputation
├── includes/
│   ├── Calculator.php            # Sybil-Detection + Score-Berechnung
│   ├── Cron.php                  # 6h Cron: Auto-Credit + Invoice Expiry
│   └── ProofPage.php             # Store-Tab + Proof Template
└── templates/
    └── store-lightning-proof.php  # Öffentliche Proof-Seite
```

## Datenbank

### wp_sk_lightning_payments (gelesen)
- `reputation_valid` — 1 wenn Reputation gutgeschrieben
- `reputation_state` — `pending` | `credited` | `rejected`, die getroffene Entscheidung
- `reputation_flags` — JSON Array der Sybil-Flags
- `reputation_at` — Zeitpunkt ab dem Reputation creditiert wird
- `confirmed_via` — `nwc` | `lndhub` | `lnurl` | `onchain` | `vendor`
- `context` — `chat`, `direct`, `onchain` (alle zählen gleich)

`reputation_state` ist der Grund, warum der Cron jede Zahlung genau einmal entscheidet. Vorher selektierte er auf `reputation_valid = 0` und nahm abgelehnte Zahlungen bei jedem Lauf erneut vor — die Burst-Mail ging alle 6 Stunden erneut raus, und ab 100 dauerhaft abgelehnten Zeilen füllten diese das `LIMIT 100` und keine neue Zahlung wurde je wieder gutgeschrieben.

### wp_sk_reputation_scores (geschrieben)
- `vendor_id` — Primary Key
- `total_transactions`, `valid_transactions`, `unique_buyers`
- `total_volume_sats`, `valid_volume_sats`
- `reputation_score` — Gewichteter Score
- `last_calculated_at`

## Cron

Läuft alle 6 Stunden (`sk_recalculate_reputation_scores`):
1. Findet Zahlungen mit `reputation_state = 'pending'` wo `reputation_at` erreicht und Käufer nichts getan hat
2. Validiert jede Zahlung (Sybil-Checks)
3. Creditiert oder flaggt
4. Recalculated Vendor-Scores
5. Expired alte pending Invoices (>15 Min)

## Admin Settings

Unabhängig aktivierbar/deaktivierbar im PHP Dashboard unter "SK Payments" → "Reputation-System aktivieren" (Switcher).

## Abhängigkeit

- **sk-core** (Host-Plugin)
- **SK Payments Modul** — optional aber empfohlen. Ohne Payments gibt es keine Zahlungen zum Tracken. ProofPage prüft graceful ob StoreSettings-Klasse existiert.
