document.addEventListener("DOMContentLoaded", function () {
    const satsElements = document.querySelectorAll(".woocommerce-Price-amount");

    if (!satsElements.length) return;

    // Detect CHF vs EUR from browser locale (no external API needed)
    const lang = (navigator.language || navigator.languages?.[0] || "").toLowerCase();
    const currency = (lang === "de-ch" || lang === "fr-ch" || lang === "it-ch" || lang === "rm-ch") ? "chf" : "eur";

    fetch("https://blockchain.info/ticker")
        .then(res => res.json())
        .then(prices => {
            const rate = prices?.[currency.toUpperCase()]?.last;
            if (!rate) return;

            satsElements.forEach(el => {
                let sats = parseInt(el.textContent.replace(/[^0-9]/g, ""), 10);
                if (!isNaN(sats)) {
                    let fiat = (sats * rate / 100_000_000).toFixed(2);
                    let fiatLine = document.createElement("div");
                    fiatLine.style.fontSize = "0.8em";
                    fiatLine.style.color = "#888";
                    fiatLine.innerText = `≈ ${fiat} ${currency.toUpperCase()}`;
                    el.parentElement.appendChild(fiatLine);
                }
            });
        })
        .catch(err => console.error("BTC price Fehler:", err));
});
