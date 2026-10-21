/**
 * Recovery code: make one and show it once, copy it, snooze the banner.
 * Used on the settings page, in the onboarding and by the dashboard banner.
 */
jQuery(function ($) {
	var config = window.skRecovery || {};
	var i18n = config.i18n || {};

	$(document).on('click', '.sk-recovery-generate', function () {
		var $btn = $(this);
		var $box = $btn.closest('.sk-recovery');
		var question = $btn.data('confirm');

		if (question && !confirm(question)) {
			return;
		}

		var label = $btn.html();
		$btn.prop('disabled', true).text(i18n.working);
		$box.find('.sk-recovery-status').text('');

		$.post(config.ajaxUrl, {
			action: 'sk_recovery_generate',
			nonce: config.nonce
		}).done(function (res) {
			if (res && res.success && res.data && res.data.code) {
				$box.find('.sk-recovery-value').text(res.data.code);
				$box.find('.sk-recovery-result').prop('hidden', false);
				$btn.remove();
				$('.sk-recovery-banner').remove();
				return;
			}
			$box.find('.sk-recovery-status').text((res && res.data && res.data.message) || i18n.error);
			$btn.prop('disabled', false).html(label);
		}).fail(function () {
			$box.find('.sk-recovery-status').text(i18n.error);
			$btn.prop('disabled', false).html(label);
		});
	});

	$(document).on('click', '.sk-recovery-copy', function () {
		var $btn = $(this);
		var label = $btn.html();

		navigator.clipboard.writeText($btn.closest('.sk-recovery').find('.sk-recovery-value').text());
		$btn.text(i18n.copied);
		setTimeout(function () { $btn.html(label); }, 2000);
	});

	$(document).on('click', '.sk-recovery-snooze', function () {
		$(this).closest('.sk-recovery-banner').remove();
		$.post(config.ajaxUrl, { action: 'sk_recovery_snooze', nonce: config.nonce });
	});
});
