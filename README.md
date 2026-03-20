# Satoshis Kleinanzeigen

Der Bitcoin-native Marketplace für den deutschsprachigen Raum. Kaufen und verkaufen ohne Banken, ohne Mittelmänner, ohne Bullshit.

## Was ist Satoshis Kleinanzeigen?

Ein offener Marktplatz auf dem Vendoren ihre Produkte und Dienstleistungen anbieten und direkt in Bitcoin bezahlt werden — Lightning und Onchain. Kein Custodian hält dein Geld. Kein KYC. Keine Gebühren an uns. Jede Zahlung geht direkt vom Käufer an den Verkäufer.

## Wie Zahlungen funktionieren

Vendoren verbinden ihre eigene Bitcoin-Wallet in den Store-Einstellungen:

**Lightning** (sofort, niedrige Gebühren):
- Nostr Wallet Connect (NWC) — z.B. Alby Hub, LNbits
- LNDHub — z.B. BlueWallet, LNbits, BTCPay Server
- Lightning-Adresse — z.B. user@getalby.com

**Onchain** (klassisch, jeder Betrag):
- Statische BTC-Adresse
- Extended Public Key (xpub) — jeder Käufer bekommt eine eigene frische Adresse

Käufer klicken auf "Sofortkauf", wählen Lightning oder Onchain, und bezahlen. Zahlungen werden automatisch verifiziert — Lightning über NWC/LNDHub, Onchain über unseren eigenen Bitcoin Full Node.

## Reputation: Don't trust, verify

Jeder Vendor baut sich eine kryptographisch verifizierbare Reputation auf. Basierend auf echten Bitcoin-Zahlungen, nicht auf Fake-Reviews.

- Jede bestätigte Zahlung wird nach 7 Tagen als Reputation gutgeschrieben
- Käufer können den Erhalt sofort bestätigen
- Sybil-Detection verhindert gefakte Reputation (IP-Analyse, Circular Payment Detection, Ring Detection)
- Alle Proofs sind öffentlich einsehbar auf der Store-Seite unter "LN Reputation"
- Lightning-Invoices können auf lightningdecoder.com dekodiert werden
- Onchain-Transaktionen sind auf mempool.space verifizierbar

Niemand muss uns vertrauen. Jeder kann selbst prüfen.

## Open Source Stack

Satoshis Kleinanzeigen läuft auf Open Source Software. Hier ist was wir verwenden:

| Komponente | Was es tut |
|-----------|-----------|
| **WordPress + WooCommerce** | Basis-CMS und Shop-System |
| **SK-Core** | Multi-Vendor Marketplace Plugin |
| **SK Payments** | Eigenes Modul — Non-custodial Lightning + Onchain Payments |
| **SK Reputation** | Eigenes Modul — Sybil-resistentes Reputationssystem |
| **BTCPay Greenfield** | WooCommerce Payment Gateway für BTCPay Server |
| **Nostr Login** | Login via Nostr Keys (NIP-07) |
| **LNURL Auth** | Login via Lightning Wallet |
| **Nostr Auto Poster** | Neue Produkte automatisch auf Nostr posten |
| **Vendor Chat** | Direktnachrichten zwischen Käufer und Verkäufer |
| **Fulcrum** | Eigener Electrum Server für Blockchain-Verifizierung |

## Technische Details

### Lightning Payment Flow
1. Käufer klickt "Sofortkauf" auf der Produktseite
2. Kaufanfrage wird im VendorChat erstellt
3. Verkäufer erstellt Lightning Invoice (automatisch via NWC/LNDHub)
4. Käufer sieht QR-Code + bolt11 im Chat
5. Auto-Polling prüft Zahlungseingang alle 5 Sekunden
6. Bei Bestätigung: Nachricht im Chat, Reputation-Timer startet

### Onchain Payment Flow
1. Käufer klickt "Sofortkauf" → wählt "Onchain"
2. Frische BTC-Adresse wird abgeleitet (bei xpub) oder statische Adresse angezeigt
3. QR-Code (BIP21) + Copy-Button + "In Wallet öffnen"
4. Blockchain-Verifizierung via eigenem Fulcrum Electrum Server (mempool.space als Fallback)
5. Bei 1+ Confirmation: automatische Bestätigung

### Verschlüsselung
Alle Wallet-Credentials (NWC Strings, LNDHub URLs, xpubs) werden AES-256-CBC verschlüsselt in der Datenbank gespeichert. Nur der WordPress Auth Salt kann sie entschlüsseln.

### Keine Custody
Zu keinem Zeitpunkt hat Satoshis Kleinanzeigen Zugriff auf Vendor-Guthaben. NWC-Verbindungen nutzen nur `make_invoice` + `lookup_invoice` Berechtigungen. LNDHub nutzt die Invoice-URL (nur lesen + Invoices erstellen). xpubs erlauben nur das Generieren von Empfangsadressen.

## Technische Dokumentation

- [SK Payments — Non-Custodial Bitcoin Payments](plugins/sk-core/modules/sk-payments/README.md)
- [SK Reputation — Sybil-resistentes Reputationssystem](plugins/sk-core/modules/sk-reputation/README.md)

## Kontakt

- **Website**: satoshiskleinanzeigen.space
- **E-Mail**: info@satoshiskleinazeigen.space
- **Nostr**: folge uns auf Nostr
