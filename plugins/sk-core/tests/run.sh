#!/usr/bin/env bash
#
# sk-core security tests. No PHPUnit, no WordPress, no database — each test
# stubs the WordPress functions its unit touches and loads that single file.
# Needs only PHP with openssl and gd. Whole suite runs in under a minute.
#
#   ./tests/run.sh          # summary, exits non-zero if anything fails
#   ./tests/run.sh -v       # every single check
#   php tests/test-secret.php
#
# What is covered:
#
#   test-nostr-auth   UAC_Nostr_Login_Integration. Signs REAL Schnorr events
#                     with the bundled nostr-php lib. Forged signature, foreign
#                     key, token for another site, replay, expired and
#                     future-dated tokens are rejected; the key owner gets in.
#                     Includes the replayed attack "log in with nothing but the
#                     victim's public key".
#   test-paymentcard  PaymentCard. A typed [lightning_invoice] marker produces
#                     no card, markers are stripped from user text,
#                     [nostr_order] stays readable, bad hashes never hit the DB.
#   test-bolt11       Bolt11Parser. BOLT-11 spec vectors, all networks, rejects
#                     amountless invoices and sub-msat amounts, plus the
#                     create_invoice amount check and QR payload validation.
#   test-secret       Secret. AES-256-GCM round trip, tamper detection
#                     (ciphertext bit, auth tag, nonce), legacy CBC values still
#                     readable and upgraded, unreadable under a foreign salt.
#   test-clientip     sk_get_client_ip. Runs the three proxy configurations in
#                     separate processes: forged headers ignored, local and
#                     listed proxies honoured, XFF chains, IPv6.
#   test-origin       sk_is_same_origin_request, sk_account_has_password.
#                     cross-site rejected even with a friendly Origin,
#                     lookalike domains and subdomains fail, five account types.
#
# These tests are built to fail when a CHECK DISAPPEARS, not just when code
# crashes. If one turns red after your change, it is saying "there was a
# security check here and now it is gone" — adjusting the test is the wrong
# reaction until it is clear why the check was dispensable.
#
set -uo pipefail

cd "$(dirname "$0")"

verbose=0
[ "${1:-}" = "-v" ] && verbose=1

php_bin="${PHP:-php}"
if ! command -v "$php_bin" >/dev/null 2>&1; then
    echo "php not found (set PHP=/path/to/php)" >&2
    exit 2
fi

failed=0
total=0

for test in test-*.php; do
    total=$((total + 1))
    output="$("$php_bin" "$test" 2>&1)"
    status=$?

    # A test that prints nothing did not run its checks — plugin files bail out
    # with a bare `exit` when a required constant is missing, which would
    # otherwise look like a pass.
    if [ -z "$output" ]; then
        failed=$((failed + 1))
        printf '  FAIL  %-24s produced no output\n' "$test"
        continue
    fi

    if [ "$status" -eq 0 ]; then
        printf '  ok    %-24s %s\n' "$test" "$(printf '%s' "$output" | tail -1)"
        [ "$verbose" -eq 1 ] && printf '%s\n' "$output"
    else
        failed=$((failed + 1))
        printf '  FAIL  %-24s\n' "$test"
        printf '%s\n' "$output" | sed 's/^/        /'
    fi
done

echo
if [ "$failed" -eq 0 ]; then
    echo "all ${total} test files passed"
else
    echo "${failed} of ${total} test files FAILED"
fi

exit $(( failed > 0 ? 1 : 0 ))
