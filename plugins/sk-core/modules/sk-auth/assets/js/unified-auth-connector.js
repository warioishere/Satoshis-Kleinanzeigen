/**
 * Unified Auth Connector JavaScript
 */

(function($) {
    'use strict';

    /**
     * Initialize the plugin functionality
     */
    const UACConnector = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Link Nostr account
            $(document).on('click', '#uac-link-nostr', this.handleNostrLink.bind(this));

            // Link LNURL account
            $(document).on('click', '#uac-link-lnurl', this.handleLNURLLink.bind(this));

            // Unlink authentication method
            $(document).on('click', '.uac-unlink-btn', this.handleUnlink.bind(this));

            // Sync preference buttons
            $(document).on('click', '#uac-enable-sync', this.handleEnableSync.bind(this));
            $(document).on('click', '#uac-disable-sync', this.handleDisableSync.bind(this));

            // Manual sync button
            $(document).on('click', '#uac-manual-sync-btn', this.handleManualSync.bind(this));
        },

        /**
         * Handle Nostr account linking
         */
        handleNostrLink: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $status = $('#uac-nostr-status');

            // Check if window.nostr is available
            if (typeof window.nostr === 'undefined') {
                this.showMessage($status, 'error', 'Nostr extension not found. Please install a Nostr browser extension like nos2x or Alby.');
                return;
            }

            const syncProfile = false;

            $button.prop('disabled', true).text(uacData.i18n.linking);
            this.showMessage($status, 'info', 'Requesting authentication from Nostr extension...');

            const sendNostrLink = (authtoken, force) => {
                return $.ajax({
                    url: uacData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uac_link_nostr',
                        nonce: uacData.nonce,
                        authtoken: authtoken,
                        sync_profile: syncProfile,
                        force: force ? '1' : '0'
                    }
                });
            };

            const getSignedToken = () => {
                return window.nostr.getPublicKey().then((pubkey) => {
                    const authEvent = {
                        kind: 27235,
                        created_at: Math.floor(Date.now() / 1000),
                        tags: [['u', uacData.ajaxurl], ['method', 'post']],
                        content: ''
                    };
                    return window.nostr.signEvent(authEvent);
                }).then((signedEvent) => btoa(JSON.stringify(signedEvent)));
            };

            getSignedToken().then((authtoken) => {
                return sendNostrLink(authtoken, false);
            }).then((response) => {
                if (response.success) {
                    if (response.data.merged) {
                        this.showMessage($status, 'success', response.data.message);
                        setTimeout(() => { location.reload(); }, 1500);
                        return;
                    }
                    this.showMessage($status, 'success', response.data.message);
                    setTimeout(() => { location.reload(); }, 2000);
                } else if (response.data && response.data.code === 'existing_standalone') {
                    if (confirm(response.data.message)) {
                        this.showMessage($status, 'info', 'Konten werden zusammengeführt...');
                        // Re-sign and retry with force
                        getSignedToken().then((authtoken) => {
                            return sendNostrLink(authtoken, true);
                        }).then((r) => {
                            if (r.success) {
                                this.showMessage($status, 'success', r.data.message);
                                setTimeout(() => { location.reload(); }, 1500);
                            } else {
                                this.showMessage($status, 'error', r.data.message || uacData.i18n.error);
                                $button.prop('disabled', false).text('Nostr-Konto verknüpfen');
                            }
                        }).catch(() => {
                            this.showMessage($status, 'error', uacData.i18n.error);
                            $button.prop('disabled', false).text('Nostr-Konto verknüpfen');
                        });
                    } else {
                        $button.prop('disabled', false).text('Nostr-Konto verknüpfen');
                        $status.hide();
                    }
                } else {
                    this.showMessage($status, 'error', response.data.message || uacData.i18n.error);
                    $button.prop('disabled', false).text('Nostr-Konto verknüpfen');
                }
            }).catch((error) => {
                console.error('Nostr linking error:', error);
                this.showMessage($status, 'error', error.message || uacData.i18n.error);
                $button.prop('disabled', false).text('Nostr-Konto verknüpfen');
            });
        },

        /**
         * Handle LNURL account linking
         */
        handleLNURLLink: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $qrContainer = $('#uac-lnurl-qr');
            const $status = $('#uac-lnurl-status');

            $button.prop('disabled', true).text(uacData.i18n.linking);
            this.showMessage($status, 'info', 'Generating QR code...');

            // Request QR code from server
            $.ajax({
                url: uacData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uac_link_lnurl',
                    nonce: uacData.nonce
                }
            }).then((response) => {
                if (response.success) {
                    // Display QR code
                    $qrContainer.find('.uac-qr-code').html(response.data.qrcode);
                    $qrContainer.show();
                    $status.hide();
                    $button.hide();

                    // Start polling for authentication
                    this.pollLNURLAuth(response.data.k1, $status);
                } else {
                    this.showMessage($status, 'error', response.data.message || uacData.i18n.error);
                    $button.prop('disabled', false).text('Link Lightning Wallet');
                }
            }).catch((error) => {
                console.error('LNURL QR generation error:', error);
                this.showMessage($status, 'error', uacData.i18n.error);
                $button.prop('disabled', false).text('Link Lightning Wallet');
            });
        },

        /**
         * Poll for LNURL authentication completion
         */
        pollLNURLAuth: function(k1, $status) {
            let attempts = 0;
            const maxAttempts = 60; // 5 minutes with 5-second intervals
            const pollInterval = 5000; // 5 seconds

            const poll = () => {
                if (attempts >= maxAttempts) {
                    this.showMessage($status, 'error', 'Authentication timeout. Please try again.');
                    $('#uac-link-lnurl').prop('disabled', false).text('Link Lightning Wallet').show();
                    $('#uac-lnurl-qr').hide();
                    return;
                }

                attempts++;

                // Update timer
                const remainingTime = Math.floor((maxAttempts - attempts) * pollInterval / 1000);
                const minutes = Math.floor(remainingTime / 60);
                const seconds = remainingTime % 60;
                $('#uac-lnurl-qr .uac-qr-timer').text(
                    `Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}`
                );

                $.ajax({
                    url: uacData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uac_verify_lnurl_link',
                        nonce: uacData.nonce,
                        k1: k1,
                        force: '0'
                    }
                }).then((response) => {
                    if (response.success) {
                        if (response.data.merged) {
                            this.showMessage($status, 'success', response.data.message);
                            $('#uac-lnurl-qr').hide();
                            setTimeout(() => { location.reload(); }, 1500);
                            return;
                        }
                        this.showMessage($status, 'success', response.data.message);
                        $('#uac-lnurl-qr').hide();
                        setTimeout(() => { location.reload(); }, 2000);
                    } else if (response.data && response.data.code === 'existing_standalone') {
                        // Stop polling, ask for confirmation
                        $('#uac-lnurl-qr').hide();
                        if (confirm(response.data.message)) {
                            this.showMessage($status, 'info', 'Konten werden zusammengeführt...');
                            $.ajax({
                                url: uacData.ajaxurl,
                                type: 'POST',
                                data: { action: 'uac_verify_lnurl_link', nonce: uacData.nonce, k1: k1, force: '1' }
                            }).then((r) => {
                                if (r.success) {
                                    this.showMessage($status, 'success', r.data.message);
                                    setTimeout(() => { location.reload(); }, 1500);
                                } else {
                                    this.showMessage($status, 'error', r.data.message || uacData.i18n.error);
                                    $('#uac-link-lnurl').prop('disabled', false).text('Lightning-Wallet verknüpfen').show();
                                }
                            });
                        } else {
                            this.showMessage($status, 'info', 'Verknüpfung abgebrochen.');
                            $('#uac-link-lnurl').prop('disabled', false).text('Lightning-Wallet verknüpfen').show();
                        }
                    } else if (response.data && response.data.status === 'waiting') {
                        // Continue polling
                        setTimeout(poll, pollInterval);
                    } else {
                        this.showMessage($status, 'error', response.data.message || uacData.i18n.error);
                        $('#uac-link-lnurl').prop('disabled', false).text('Link Lightning Wallet').show();
                        $('#uac-lnurl-qr').hide();
                    }
                }).catch((error) => {
                    console.error('LNURL polling error:', error);
                    // Continue polling on error (network issues, etc.)
                    setTimeout(poll, pollInterval);
                });
            };

            // Start polling
            poll();
        },

        /**
         * Handle unlinking authentication method
         */
        handleUnlink: function(e) {
            e.preventDefault();

            if (!confirm(uacData.i18n.confirm_unlink)) {
                return;
            }

            const $button = $(e.currentTarget);
            const authType = $button.data('auth-type');

            $button.prop('disabled', true).text(uacData.i18n.unlinking);

            $.ajax({
                url: uacData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uac_unlink_auth',
                    nonce: uacData.nonce,
                    auth_type: authType
                }
            }).then((response) => {
                if (response.success) {
                    // Reload page
                    location.reload();
                } else {
                    alert(response.data.message || uacData.i18n.error);
                    $button.prop('disabled', false).text('Unlink');
                }
            }).catch((error) => {
                console.error('Unlink error:', error);
                alert(uacData.i18n.error);
                $button.prop('disabled', false).text('Unlink');
            });
        },

        /**
         * Handle enabling Nostr sync
         */
        handleEnableSync: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $notice = $('#uac-sync-choice-notice');

            $button.prop('disabled', true).text('Aktiviere...');

            $.ajax({
                url: uacData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uac_set_sync_preference',
                    nonce: uacData.nonce,
                    enabled: true
                }
            }).then((response) => {
                if (response.success) {
                    $notice.removeClass('sk-alert-warning').addClass('sk-alert-success');
                    $notice.html('<p><strong>✓ ' + response.data.message + '</strong></p>');

                    // Reload after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    alert(response.data.message || uacData.i18n.error);
                    $button.prop('disabled', false).text('Ja, jetzt synchronisieren');
                }
            }).catch((error) => {
                console.error('Sync preference error:', error);
                alert(uacData.i18n.error);
                $button.prop('disabled', false).text('Ja, jetzt synchronisieren');
            });
        },

        /**
         * Handle disabling Nostr sync
         */
        handleDisableSync: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $notice = $('#uac-sync-choice-notice');

            $button.prop('disabled', true).text('Deaktiviere...');

            $.ajax({
                url: uacData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uac_set_sync_preference',
                    nonce: uacData.nonce,
                    enabled: false
                }
            }).then((response) => {
                if (response.success) {
                    $notice.removeClass('sk-alert-warning').addClass('sk-alert-info');
                    $notice.html('<p><strong>✓ ' + response.data.message + '</strong></p>');

                    // Reload after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    alert(response.data.message || uacData.i18n.error);
                    $button.prop('disabled', false).text('Nein, überspringen');
                }
            }).catch((error) => {
                console.error('Sync preference error:', error);
                alert(uacData.i18n.error);
                $button.prop('disabled', false).text('Nein, überspringen');
            });
        },

        /**
         * Handle manual profile sync
         */
        handleManualSync: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $status = $('#uac-manual-sync-status');

            $button.prop('disabled', true).text('Synchronisiere...');
            this.showMessage($status, 'info', 'Synchronisiere Nostr-Profil...');

            $.ajax({
                url: uacData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uac_manual_sync',
                    nonce: uacData.nonce
                }
            }).then((response) => {
                if (response.success) {
                    this.showMessage($status, 'success', response.data.message);
                    $button.prop('disabled', false).text('Jetzt synchronisieren');

                    // Reload after 2 seconds to show updated profile
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    this.showMessage($status, 'error', response.data.message || uacData.i18n.error);
                    $button.prop('disabled', false).text('Jetzt synchronisieren');
                }
            }).catch((error) => {
                console.error('Manual sync error:', error);
                this.showMessage($status, 'error', uacData.i18n.error);
                $button.prop('disabled', false).text('Jetzt synchronisieren');
            });
        },

        /**
         * Show status message
         */
        showMessage: function($element, type, message) {
            $element
                .removeClass('success error info')
                .addClass(type)
                .html('<p>' + message + '</p>')
                .show();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        UACConnector.init();
    });

})(jQuery);
