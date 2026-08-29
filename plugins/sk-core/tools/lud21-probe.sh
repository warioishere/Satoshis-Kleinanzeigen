#!/bin/bash
#
# Prueft, ob eine Lightning-Adresse LUD-21 beherrscht.
#
# LUD-21 ist die Bedingung dafuer, dass wir eine Adresse ueberhaupt annehmen:
# nur damit koennen wir nachtraeglich feststellen, ob eine Rechnung bezahlt
# wurde, und nur darauf laesst sich eine Provision stuetzen. Welche Wallet es
# kann, steht in keiner verlaesslichen Liste — die offizielle Uebersicht im
# lnurl/luds-Repo fuehrt BTCPay bis heute ohne LUD-21, obwohl es der Quelltext
# laengst umsetzt. Deshalb wird gemessen statt nachgeschlagen.
#
# Die Pruefung folgt Schritt fuer Schritt StoreSettings::check_lud21(), damit
# dieses Werkzeug und die Shop-Einstellungen nie unterschiedlich urteilen:
# Adresse aufloesen, Rechnung ueber max(1000, minSendable) anfordern, in der
# Antwort nach "verify" sehen. Zusaetzlich ruft dieses Skript die verify-URL
# einmal auf — eine URL, die ins Leere laeuft, ist so wertlos wie keine, und
# genau das faellt der Pruefung auf der Seite nicht auf.
#
# Es fliesst dabei kein Geld: angefordert wird eine Rechnung, bezahlt wird sie
# nicht, und der verify-Aufruf liest nur einen Zustand.
#
# Aufruf:
#   tools/lud21-probe.sh adresse@domain [weitere ...]
#
# Rueckgabe: 0, wenn jede Adresse LUD-21 kann, sonst 1.

set -u

if [ "$#" -eq 0 ]; then
    echo "Aufruf: $0 adresse@domain [weitere ...]" >&2
    exit 2
fi

TIMEOUT=12

# Liest einen Wert aus JSON auf der Standardeingabe. PHP statt python3, weil
# auf diesem Server ohnehin PHP laeuft und wir uns keine zweite Abhaengigkeit
# einhandeln wollen.
json_get() {
    php -r '
        $d = json_decode( stream_get_contents( STDIN ), true );
        if ( ! is_array( $d ) ) { exit( 2 ); }
        $v = $d[ $argv[1] ] ?? "";
        // Ein echo auf false gaebe einen leeren String und liesse einen
        // vorhandenen Wert wie fehlend aussehen.
        if ( is_bool( $v ) ) { echo $v ? "true" : "false"; exit; }
        echo is_scalar( $v ) ? $v : "";
    ' "$1" 2>/dev/null
}

json_keys() {
    php -r '
        $d = json_decode( stream_get_contents( STDIN ), true );
        if ( ! is_array( $d ) ) { exit( 2 ); }
        $k = array_keys( $d );
        sort( $k );
        echo implode( ", ", $k );
    ' 2>/dev/null
}

melde() { printf '  %-36s  %s\n' "$1" "$2"; }

fehler=0

for adresse in "$@"; do
    case "$adresse" in
        *@*) ;;
        *) melde "$adresse" "KEINE LIGHTNING-ADRESSE (user@domain erwartet)"; fehler=1; continue ;;
    esac

    domain=${adresse#*@}
    name=${adresse%@*}

    meta=$( curl -sL --max-time "$TIMEOUT" "https://$domain/.well-known/lnurlp/$name" 2>/dev/null )

    if [ -z "$meta" ]; then
        melde "$adresse" "ADRESSE ANTWORTET NICHT"
        fehler=1
        continue
    fi

    callback=$( printf '%s' "$meta" | json_get callback )

    if [ -z "$callback" ]; then
        melde "$adresse" "ANTWORT IST KEINE LNURL-PAY-BESCHREIBUNG"
        fehler=1
        continue
    fi

    # Wie check_lud21(): der Anbieter bestimmt den Mindestbetrag, aber nie
    # weniger als 1000 msat, sonst weisen manche die Anfrage ab.
    min=$( printf '%s' "$meta" | json_get minSendable )
    [ -n "$min" ] || min=1000
    [ "$min" -ge 1000 ] 2>/dev/null || min=1000

    trenner='?'
    case "$callback" in *\?*) trenner='&' ;; esac

    rechnung=$( curl -sL --max-time "$TIMEOUT" "${callback}${trenner}amount=${min}" 2>/dev/null )
    verify=$( printf '%s' "$rechnung" | json_get verify )

    if [ -z "$verify" ]; then
        felder=$( printf '%s' "$rechnung" | json_keys )
        if [ -z "$felder" ]; then
            melde "$adresse" "RECHNUNG FEHLGESCHLAGEN"
        else
            melde "$adresse" "LUD-21 NEIN  (Felder: $felder)"
        fi
        fehler=1
        continue
    fi

    # Die URL ist da — beantwortet sie auch etwas? Erwartet wird nach LUD-21
    # ein Objekt mit "settled"; bei einer unbezahlten Rechnung also false.
    antwort=$( curl -sL --max-time "$TIMEOUT" "$verify" 2>/dev/null )
    status=$( printf '%s' "$antwort" | json_get settled )

    if [ -z "$( printf '%s' "$antwort" | json_keys )" ]; then
        melde "$adresse" "LUD-21 FRAGLICH  verify antwortet nicht: $verify"
        fehler=1
        continue
    fi

    melde "$adresse" "LUD-21 JA  (settled=${status:-?})  $verify"
done

exit "$fehler"
