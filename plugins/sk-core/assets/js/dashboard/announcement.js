/**
 * Announcement list in the seller dashboard: load the selected notice via AJAX.
 */
(function($) {
	var $list = $('.sk-announcement-list');
	var $content = $('#sk-announcement-content-area');
	var nonce = (window.skAnnouncement || {}).nonce;

	$list.on('click', '.sk-announcement-list-item', function(e) {
		e.preventDefault();
		var $item = $(this);
		var noticeId = $item.data('notice-id');

		// Update active state
		$list.find('.sk-announcement-list-item').removeClass('active');
		$item.addClass('active');

		// Remove unread state
		$item.removeClass('unread').find('.sk-announcement-badge').remove();

		// Show loading
		$content.html(
			'<div class="sk-announcement-empty-detail">' +
				'<i class="fas fa-spinner fa-spin"></i>' +
				'<p>Laden...</p>' +
			'</div>'
		);

		// Fetch content
		$.post(sk.ajaxurl, {
			action: 'sk_announcement_get_notice',
			nonce: nonce,
			notice_id: noticeId
		}, function(response) {
			if (response.success) {
				var d = response.data;
				$content.html(
					'<div class="sk-announcement-detail">' +
						'<div class="sk-announcement-detail-header">' +
							'<h3>' + $('<span>').text(d.title).html() + '</h3>' +
							'<span class="sk-announcement-detail-date">' +
								'<i class="far fa-calendar-alt"></i> ' + d.date +
							'</span>' +
						'</div>' +
						'<div class="sk-announcement-detail-body">' + d.content + '</div>' +
					'</div>'
				);
			} else {
				$content.html(
					'<div class="sk-announcement-empty-detail">' +
						'<i class="fas fa-exclamation-triangle"></i>' +
						'<p>Ankündigung nicht gefunden</p>' +
					'</div>'
				);
			}
		});
	});
})(jQuery);
