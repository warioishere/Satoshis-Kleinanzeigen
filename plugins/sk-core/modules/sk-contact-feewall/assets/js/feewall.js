/**
 * Contact Details Feewall - JavaScript
 */
(function($) {
    'use strict';

    const CDF = {
        currentInvoiceId: null,
        currentVendorId: null,
        currentProductId: null,
        pollInterval: null,

        init: function() {
            this.bindEvents();
            this.createModal();
            this.createSuccessOverlay();
            this.checkCookiesOnLoad();
        },

        /**
         * Check cookies on page load and unlock icons if access is granted
         * This handles cached pages where server-side checks don't run
         */
        checkCookiesOnLoad: function() {
            // Find all locked icons
            const $lockedIcons = $('.dkp-contact-icon[href="#cdf-locked"]');

            if ($lockedIcons.length === 0) {
                return; // No locked icons on this page
            }

            // Check each locked icon to see if we have access
            $lockedIcons.each((index, element) => {
                const $icon = $(element);
                const vendorId = $icon.data('vendor-id');

                if (vendorId && this.hasAccessCookie(vendorId)) {
                    // We have access! Unlock this icon
                    const originalHref = $icon.data('original-href');
                    const originalTitle = $icon.data('original-title');

                    if (originalHref && originalTitle) {
                        $icon.attr('href', originalHref);
                        $icon.attr('title', originalTitle);
                        $icon.attr('aria-label', originalTitle);
                        $icon.removeClass('cdf-locked');
                    }
                }
            });
        },

        /**
         * Check if we have access cookie for a vendor
         */
        hasAccessCookie: function(vendorId) {
            const cookieName = 'cdf_access_' + vendorId;
            const cookies = document.cookie.split(';');

            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.startsWith(cookieName + '=')) {
                    const value = cookie.substring(cookieName.length + 1);
                    return value === 'paid';
                }
            }

            return false;
        },

        bindEvents: function() {
            $(document).on('click', '.dkp-contact-icon[href="#cdf-locked"]', (e) => {
                e.preventDefault();
                const $icon = $(e.currentTarget);
                const vendorId = $icon.data('vendor-id');
                const productId = $icon.data('product-id');
                this.showPaymentModal(vendorId, productId);
            });
        },

        createModal: function() {
            const modal = `
                <div class="cdf-payment-modal" id="cdf-payment-modal">
                    <div class="cdf-payment-content">
                        <button class="cdf-payment-close" id="cdf-modal-close" aria-label="Schließen">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="cdf-payment-header">
                            <div class="cdf-payment-icon">🔓</div>
                            <h3>Kontaktdetails freischalten</h3>
                            <p>Dieser Verkäufer schützt seine Kontaktdaten mit einer kleinen Paywall</p>
                        </div>
                        <div class="cdf-payment-details">
                            <div class="cdf-payment-amount">
                                <span class="cdf-payment-amount-value">${cdfData.amount}</span>
                                <span class="cdf-payment-amount-label">Sats</span>
                            </div>
                            <div class="cdf-payment-info">
                                <p><strong>💡 Warum ${cdfData.amount} Sats?</strong></p>
                                <div class="cdf-protection-box">
                                    <p><span class="cdf-protection-icon">🛡️</span><strong>Schutz vor Spam & Bots</strong></p>
                                    <p>Die kleine Gebühr verhindert, dass Bots die Kontaktdaten automatisch scrapen und der Verkäufer mit Spam überflutet wird.</p>
                                </div>
                                <p style="margin-top: 16px;">Die Zahlung erfolgt über <strong>BTCPay Server</strong> (Lightning oder On-Chain) und unterstützt die Weiterentwicklung von <strong>Satoshi's Kleinanzeigen</strong>.</p>
                            </div>
                        </div>
                        <div class="cdf-payment-actions">
                            <button class="cdf-btn cdf-btn-secondary" id="cdf-cancel-btn">Abbrechen</button>
                            <button class="cdf-btn cdf-btn-primary" id="cdf-pay-btn">
                                ⚡ Jetzt zahlen
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modal);

            $('#cdf-modal-close, #cdf-cancel-btn').on('click', () => this.closeModal());
            $('#cdf-pay-btn').on('click', () => this.createInvoice());
        },

        createSuccessOverlay: function() {
            const overlay = `
                <div class="cdf-success-overlay" id="cdf-success-overlay">
                    <div class="cdf-success-content">
                        <div class="cdf-success-icon">✅</div>
                        <div class="cdf-success-message">Zahlung erfolgreich!</div>
                        <div class="cdf-success-submessage">Kontakte werden freigeschaltet...</div>
                    </div>
                </div>
            `;
            $('body').append(overlay);
        },

        showPaymentModal: function(vendorId, productId) {
            this.currentVendorId = vendorId;
            this.currentProductId = productId;
            $('#cdf-payment-modal').addClass('active');
        },

        closeModal: function() {
            $('#cdf-payment-modal').removeClass('active');
            this.stopPolling();
            this.resetPayButton();
        },

        createInvoice: function() {
            const $btn = $('#cdf-pay-btn');
            $btn.prop('disabled', true);
            $btn.html('<span class="cdf-loading"></span>' + cdfData.i18n.creating);

            $.ajax({
                url: cdfData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cdf_create_invoice',
                    nonce: cdfData.nonce,
                    vendor_id: this.currentVendorId,
                    product_id: this.currentProductId
                },
                success: (response) => {
                    if (response.success) {
                        // Check if this is a vendor/admin bypass
                        if (response.data.bypassed) {
                            // Silently show success and reload
                            this.showSuccess();
                            return;
                        }

                        this.currentInvoiceId = response.data.invoice_id;

                        // Close the initial modal
                        this.closeModal();

                        // Open BTCPay modal using their official library
                        this.showBTCPayModal(response.data.invoice_id);

                        // Start polling for payment
                        this.startPolling();
                    } else {
                        alert(response.data.message || cdfData.i18n.error);
                        this.resetPayButton();
                    }
                },
                error: () => {
                    alert(cdfData.i18n.error);
                    this.resetPayButton();
                }
            });
        },

        showBTCPayModal: function(invoiceId) {
            // Use BTCPay's official modal library
            if (typeof window.btcpay !== 'undefined') {
                // Set the API URL prefix
                window.btcpay.setApiUrlPrefix(cdfData.btcpayUrl);

                // Show the invoice in modal
                window.btcpay.showInvoice(invoiceId);

                // Listen to events from BTCPay modal
                window.btcpay.onModalReceiveMessage((event) => {
                    if (typeof event.data === 'object' && event.data.status) {
                        const status = event.data.status.toLowerCase();

                        // Check if payment is complete
                        if (status === 'complete' || status === 'paid' ||
                            status === 'processing' || status === 'settled') {
                            // Payment detected! Call server to grant access
                            this.confirmPaymentOnServer(() => {
                                // Stop polling
                                this.stopPolling();

                                // Let BTCPay show its success screen for 3 seconds
                                setTimeout(() => {
                                    // Hide BTCPay modal
                                    window.btcpay.hideFrame();

                                    // Unlock icons without showing custom success overlay
                                    this.unlockIconsInstantly();
                                }, 3000);
                            });
                        } else if (status === 'expired' || status === 'invalid') {
                            // Hide modal and show error
                            window.btcpay.hideFrame();
                            this.stopPolling();
                            alert('Rechnung abgelaufen oder ungültig. Bitte versuchen Sie es erneut.');
                        }
                    } else if (event.data === 'close') {
                        // User closed the modal
                        this.stopPolling();
                    }
                });
            } else {
                console.error('BTCPay modal library not loaded');
                alert('BTCPay Modal konnte nicht geladen werden. Bitte laden Sie die Seite neu.');
            }
        },

        confirmPaymentOnServer: function(callback) {
            // Call server to confirm payment and grant access
            $.ajax({
                url: cdfData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cdf_check_payment',
                    nonce: cdfData.nonce,
                    invoice_id: this.currentInvoiceId,
                    vendor_id: this.currentVendorId,
                    product_id: this.currentProductId
                },
                success: (response) => {
                    if (response.success && response.data.status === 'paid') {
                        // Access granted on server! Continue with callback
                        callback();
                    } else {
                        // Something went wrong, but continue anyway
                        callback();
                    }
                },
                error: () => {
                    // Continue anyway since BTCPay confirmed payment
                    callback();
                }
            });
        },

        startPolling: function() {
            this.pollInterval = setInterval(() => {
                this.checkPaymentStatus();
            }, 3000); // Check every 3 seconds
        },

        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        checkPaymentStatus: function() {
            $.ajax({
                url: cdfData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cdf_check_payment',
                    nonce: cdfData.nonce,
                    invoice_id: this.currentInvoiceId,
                    vendor_id: this.currentVendorId,
                    product_id: this.currentProductId
                },
                success: (response) => {
                    if (response.success && response.data.status === 'paid') {
                        this.stopPolling();
                        this.showSuccess();
                    }
                }
            });
        },

        showSuccess: function() {
            this.closeModal();
            $('#cdf-success-overlay').addClass('active');

            // Unlock icons immediately without page reload
            setTimeout(() => {
                this.unlockIconsInstantly();

                // Hide success overlay after icon animation
                setTimeout(() => {
                    $('#cdf-success-overlay').removeClass('active');
                }, 1500);
            }, 500);
        },

        unlockIconsInstantly: function() {
            // Find all locked icons for this vendor
            const $lockedIcons = $(`.dkp-contact-icon[data-vendor-id="${this.currentVendorId}"]`);

            $lockedIcons.each((index, element) => {
                const $icon = $(element);

                // Get original data
                const originalHref = $icon.data('original-href');
                const originalTitle = $icon.data('original-title');
                const originalClass = $icon.data('original-class');

                // Only unlock if we have the original data
                if (originalHref && originalTitle) {
                    // Restore original link and title
                    $icon.attr('href', originalHref);
                    $icon.attr('title', originalTitle);
                    $icon.attr('aria-label', originalTitle);

                    // Remove lock styling
                    $icon.removeClass('cdf-locked');

                    // Add unlock animation
                    $icon.addClass('cdf-unlocked');

                    // Remove the animation class after animation completes
                    setTimeout(() => {
                        $icon.removeClass('cdf-unlocked');
                    }, 600);
                }
            });
        },

        resetPayButton: function() {
            const $btn = $('#cdf-pay-btn');
            $btn.prop('disabled', false);
            $btn.html('⚡ Jetzt zahlen');
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        CDF.init();
    });

    // Handle payment complete redirect
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('cdf_payment_complete') === '1') {
        // Remove query param and reload
        window.history.replaceState({}, document.title, window.location.pathname);
        window.location.reload();
    }

})(jQuery);
