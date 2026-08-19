/**
 * Nostr login button of the [nostr_login_box] shortcode.
 */
document.addEventListener('DOMContentLoaded', () => {
  const config = window.skNostrLogin || {};
  const btn = document.getElementById('nostr-login-button');
  if (!btn) return;

  const ajaxUrl = config.ajaxUrl;
  const dashboardUrl = config.dashboardUrl;
  let redirecting = false;

  // Fetch fresh nonce via AJAX (avoids cached-page stale nonce).
  let freshNonce = '';
  const nonceController = new AbortController();
  fetch(ajaxUrl + '?action=sk_auth_check', { credentials: 'same-origin', signal: nonceController.signal })
    .then(r => r.json())
    .then(d => {
      if (d.data && d.data.logged_in) {
        // Already logged in — redirect immediately, no need for login form.
        redirecting = true;
        window.location.href = d.data.redirect || dashboardUrl;
        return;
      }
      if (d.data && d.data.nonce) freshNonce = d.data.nonce;
    })
    .catch(() => {});

  // Fetch the user's real kind:0 profile via Primal cache API, with
  // a short timeout so login UX doesn't stall when the relay is slow.
  // Returns a metadata object ready for our backend (fields already
  // mapped: Nostr's `picture` → our backend's `image`). Returns {} if
  // nothing is found or the call fails — login still proceeds, just
  // without rich profile data.
  const fetchNostrProfile = async (pubkey) => {
    try {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), 4000);
      const resp = await fetch('https://cache.primal.net/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(['user_profile', { pubkey: pubkey }]),
        signal: ctrl.signal,
      });
      clearTimeout(timer);
      if (!resp.ok) return {};
      const events = await resp.json();
      if (!Array.isArray(events)) return {};
      const kind0 = events.find(e => e && e.kind === 0 && e.content);
      if (!kind0) return {};
      const profile = JSON.parse(kind0.content);
      const out = {};
      // Prefer Nostr display_name (full name) over handle `name`.
      if (profile.display_name) out.name = String(profile.display_name);
      else if (profile.name)     out.name = String(profile.name);
      if (profile.about)   out.about   = String(profile.about);
      if (profile.nip05)   out.nip05   = String(profile.nip05);
      if (profile.picture) out.image   = String(profile.picture); // NIP-01 picture → backend 'image'
      if (profile.website) out.website = String(profile.website);
      if (profile.lud16)   out.lud16   = String(profile.lud16);
      return out;
    } catch (e) {
      return {};
    }
  };

  btn.addEventListener('click', async () => {
    if (redirecting) return;
    if (!window.nostr) {
      alert(config.noExtension);
      return;
    }

    try {
      const pubkey = await window.nostr.getPublicKey();

      // Fetch profile + sign auth event in parallel for snappier login.
      const profilePromise = fetchNostrProfile(pubkey);

      const metadata = Object.assign(
        { pubkey: pubkey },
        await profilePromise
      );

      const authEvent = await window.nostr.signEvent({
        kind: 27235,
        created_at: Math.floor(Date.now() / 1000),
        tags: [
          ["u", ajaxUrl],
          ["method", "post"]
        ],
        content: "Login via Nostr",
        pubkey
      });

      const formData = new URLSearchParams();
      formData.append("action", "nostr_login");
      formData.append("nonce", freshNonce || config.nonce);
      formData.append("metadata", JSON.stringify(metadata));
      formData.append("authtoken", btoa(JSON.stringify(authEvent)));

      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formData
      });

      let result;
      try {
        result = await response.json();
      } catch (parseErr) {
        // JSON parse failed — likely PHP warnings before JSON output.
        // If response was 200, login probably succeeded (cookie set server-side).
        if (response.ok) {
          redirecting = true;
          nonceController.abort();
          window.location.href = dashboardUrl;
          return;
        }
        throw parseErr;
      }

      if (result.success) {
        redirecting = true;
        nonceController.abort();
        window.location.href = (result.data && result.data.redirect) || result.redirect || dashboardUrl;
      } else {
        alert("Login fehlgeschlagen: " + (result.data?.message || "Unbekannter Fehler"));
      }

    } catch (err) {
      // NetworkError can happen when wp_set_auth_cookie changes the session
      // mid-request, causing the browser to abort the fetch.
      // Check if we're actually logged in now.
      try {
        const check = await fetch(config.ajaxUrl + '?action=sk_auth_check', { credentials: "same-origin" });
        const checkResult = await check.json();
        if (checkResult.success && checkResult.data && checkResult.data.logged_in) {
          redirecting = true;
          nonceController.abort();
          window.location.href = checkResult.data.redirect || dashboardUrl;
          return;
        }
      } catch (e) { /* check failed too */ }
      alert("Login nicht möglich.");
    }
  });
});
