/**
 * SK Vendor Chat - JavaScript
 */
(function ($) {
	'use strict';

	var DVC = {
		init: function () {
			this.bindEvents();
			this.autoScrollMessages();
			this.setupAutoRefresh();
			this.openFromLink();
		},

		bindEvents: function () {
			// Tab switching
			$(document).on('click', '.dvc-tab-btn', this.switchTab);

			// Chat item click
			$(document).on('click', '.dvc-chat-item', this.selectChat);

			// Start chat icon on product page
			$(document).on('click', '.dvc-start-chat-icon', this.openChatModal);

			// Modal close
			$(document).on('click', '.dvc-modal-close', this.closeModal);
			$(document).on('click', '.dvc-modal', function (e) {
				if ($(e.target).hasClass('dvc-modal')) {
					DVC.closeModal();
				}
			});

			// Start chat form submit
			$(document).on('submit', '#dvc-start-chat-form', this.submitStartChat);

			// Send message form submit
			$(document).on('submit', '.dvc-send-message-form', this.submitSendMessage);

			// Archive button
			$(document).on('click', '.dvc-archive-btn', this.archiveChat);

			// Unarchive button
			$(document).on('click', '.dvc-unarchive-btn', this.unarchiveChat);

			// Delete button
			$(document).on('click', '.dvc-delete-btn', this.deleteChat);

			// Enter key to send message (Ctrl+Enter)
			$(document).on('keydown', '.dvc-message-input', function (e) {
				if (e.ctrlKey && e.keyCode === 13) {
					$(this).closest('form').submit();
				}
			});
		},

		switchTab: function (e) {
			e.preventDefault();
			var tab = $(this).data('tab');

			// Update button states
			$('.dvc-tab-btn').removeClass('active');
			$(this).addClass('active');

			// Show/hide lists
			if (tab === 'active') {
				$('#dvc-active-list').show();
				$('#dvc-archived-list').hide();
			} else {
				$('#dvc-active-list').hide();
				$('#dvc-archived-list').show();
			}

			// Update URL
			var url = new URL(window.location);
			url.searchParams.set('view', tab);
			window.history.pushState({}, '', url);
		},

		selectChat: function (e) {
			e.preventDefault();
			var chatId = $(this).data('chat-id');

			// Update URL and reload
			var url = new URL(window.location);
			url.searchParams.set('chat_id', chatId);
			window.location.href = url.toString();
		},

		/**
		 * Direktlink aufs Chatfenster — #chat am Inseratslink.
		 *
		 * Der Telegram-Kanal verlinkt so direkt ins Anschreiben. Bewusst als
		 * Fragment und nicht als Abfrageparameter: das Fragment erreicht den
		 * Server nie, die Seite kommt also weiter aus dem Cache.
		 *
		 * Ausgeloest wird derselbe Klick, den auch das Symbol ausloest — damit
		 * gelten dieselben Regeln: wer nicht angemeldet ist, sieht das
		 * Anmeldefenster, und auf dem eigenen Inserat gibt es kein Symbol und
		 * folglich auch nichts zu oeffnen.
		 */
		openFromLink: function () {
			if (window.location.hash !== '#chat') {
				return;
			}

			var $icon = $('.dkp-contact-icons--single .dvc-start-chat-icon').first();

			if (!$icon.length) {
				$icon = $('.dvc-start-chat-icon').first();
			}

			if ($icon.length) {
				$icon.trigger('click');
			}
		},

		openChatModal: function (e) {
			e.preventDefault();
			// The click target is the <i> element, but data attributes are on the parent <a> element
			var $link = $(this).closest('a.dkp-contact-icon');
			var vendorId = $link.data('vendor-id');
			var productId = $link.data('product-id');
			var isLoggedIn = $link.data('logged-in') === 1 || $link.data('logged-in') === '1';

			// Check if user is logged in
			if (!isLoggedIn) {
				// Show login required modal
				$('#dvc-login-required-modal').fadeIn(200);
				return;
			}

			// User is logged in, show chat form
			$('#dvc-vendor-id').val(vendorId);
			$('#dvc-product-id').val(productId);
			$('#dvc-chat-message').val('');

			$('#dvc-chat-modal').fadeIn(200);
		},

		closeModal: function () {
			$('#dvc-chat-modal').fadeOut(200);
			$('#dvc-login-required-modal').fadeOut(200);
		},

		submitStartChat: function (e) {
			e.preventDefault();

			var $form = $(this);
			var $submitBtn = $form.find('button[type="submit"]');
			var vendorId = $('#dvc-vendor-id').val();
			var productId = $('#dvc-product-id').val();
			var message = $('#dvc-chat-message').val();

			// Disable submit button
			$submitBtn.prop('disabled', true).addClass('dvc-loading');

			$.ajax({
				url: dvcAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'dvc_start_chat',
					nonce: dvcAjax.nonce,
					vendor_id: vendorId,
					product_id: productId,
					message: message,
				},
				success: function (response) {
					if (response.success) {
						// Show success message
						DVC.showNotification(response.data.message, 'success');

						// Close modal
						DVC.closeModal();

						// Redirect to chat dashboard with the chat open
						var dashboardUrl = window.location.origin + '/dashboard/vendor-chat/?chat_id=' + response.data.chat_id;
						window.location.href = dashboardUrl;
					} else {
						DVC.showNotification(response.data.message, 'error');
						$submitBtn.prop('disabled', false).removeClass('dvc-loading');
					}
				},
				error: function () {
					DVC.showNotification('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'error');
					$submitBtn.prop('disabled', false).removeClass('dvc-loading');
				},
			});
		},

		submitSendMessage: function (e) {
			e.preventDefault();

			var $form = $(this);
			var $textarea = $form.find('.dvc-message-input');
			var $submitBtn = $form.find('.dvc-send-btn');
			var chatId = $form.data('chat-id');
			var message = $textarea.val().trim();

			if (!message) {
				return;
			}

			// Disable submit button
			$submitBtn.prop('disabled', true).addClass('dvc-loading');

			$.ajax({
				url: dvcAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'dvc_send_message',
					nonce: dvcAjax.nonce,
					chat_id: chatId,
					message: message,
				},
				success: function (response) {
					if (response.success) {
						// Clear textarea
						$textarea.val('');

						// Reload messages
						DVC.loadMessages(chatId);

						// Re-enable submit button
						$submitBtn.prop('disabled', false).removeClass('dvc-loading');
					} else {
						DVC.showNotification(response.data.message, 'error');
						$submitBtn.prop('disabled', false).removeClass('dvc-loading');
					}
				},
				error: function () {
					DVC.showNotification('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'error');
					$submitBtn.prop('disabled', false).removeClass('dvc-loading');
				},
			});
		},

		loadMessages: function (chatId) {
			$.ajax({
				url: dvcAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'dvc_get_messages',
					nonce: dvcAjax.nonce,
					chat_id: chatId,
				},
				success: function (response) {
					if (response.success && response.data.messages) {
						var existingCount = $('#dvc-messages-area').find('.dvc-message').length;
						var serverCount = response.data.messages.length;

						// Only act if there are new messages.
						if (serverCount > existingCount) {
							var newMessages = response.data.messages.slice(existingCount);
							DVC.appendMessages(newMessages);
							DVC.autoScrollMessages();
						}
					}
				},
			});
		},

		appendMessages: function (messages) {
			var $messagesArea = $('#dvc-messages-area');
			var currentUserId = $messagesArea.data('current-user-id');

			// Remove empty state if present.
			$messagesArea.find('.dvc-empty-chat').remove();

			var html = '';
			messages.forEach(function (message) {
				var isOwn = message.user_id == currentUserId;
				var date = new Date(message.timestamp * 1000);
				var timeStr = date.toLocaleString('de-DE', {
					day: '2-digit',
					month: '2-digit',
					year: 'numeric',
					hour: '2-digit',
					minute: '2-digit',
				});

				// Payment cards are verified server-side and travel as data-sk-card;
				// sk-lightning-pay.js renders them from that attribute only.
				var cardAttr = message.card
					? ' data-sk-card="' + DVC.escapeHtml(JSON.stringify(message.card)) + '"'
					: '';

				html +=
					'<div class="dvc-message ' +
					(isOwn ? 'own' : 'other') +
					'"' +
					cardAttr +
					'>' +
					(message.avatar ? '<div class="dvc-message-avatar"><img src="' + DVC.escapeHtml(message.avatar) + '" alt=""></div>' : '') +
					'<div class="dvc-message-content">' +
					'<div class="dvc-message-header">' +
					'<strong>' +
					DVC.escapeHtml(message.display_name || '') +
					'</strong>' +
					'<span class="dvc-message-time">' +
					timeStr +
					'</span>' +
					'</div>' +
					'<div class="dvc-message-text">' +
					DVC.escapeHtml(message.message).replace(/\n/g, '<br>') +
					'</div>' +
					'</div>' +
					'</div>';
			});

			if (html) {
				$messagesArea.append(html);
			}
		},

		archiveChat: function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (!confirm('Möchtest du diesen Chat wirklich archivieren?')) {
				return;
			}

			var chatId = $(this).data('chat-id');

			$.ajax({
				url: dvcAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'dvc_archive_chat',
					nonce: dvcAjax.nonce,
					chat_id: chatId,
				},
				success: function (response) {
					if (response.success) {
						DVC.showNotification(response.data.message, 'success');
						// Reload page
						setTimeout(function () {
							window.location.reload();
						}, 1000);
					} else {
						DVC.showNotification(response.data.message, 'error');
					}
				},
				error: function () {
					DVC.showNotification('Ein Fehler ist aufgetreten.', 'error');
				},
			});
		},

		unarchiveChat: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var chatId = $(this).data('chat-id');

			$.ajax({
				url: dvcAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'dvc_unarchive_chat',
					nonce: dvcAjax.nonce,
					chat_id: chatId,
				},
				success: function (response) {
					if (response.success) {
						DVC.showNotification(response.data.message, 'success');
						// Reload page
						setTimeout(function () {
							window.location.reload();
						}, 1000);
					} else {
						DVC.showNotification(response.data.message, 'error');
					}
				},
				error: function () {
					DVC.showNotification('Ein Fehler ist aufgetreten.', 'error');
				},
			});
		},

		deleteChat: function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (!confirm('Möchtest du diesen Chat wirklich löschen? Dies kann nicht rückgängig gemacht werden.')) {
				return;
			}

			var chatId = $(this).data('chat-id');

			$.ajax({
				url: dvcAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'dvc_delete_chat',
					nonce: dvcAjax.nonce,
					chat_id: chatId,
				},
				success: function (response) {
					if (response.success) {
						DVC.showNotification(response.data.message, 'success');
						// Redirect to chat dashboard
						setTimeout(function () {
							window.location.href = window.location.origin + '/dashboard/vendor-chat/';
						}, 1000);
					} else {
						DVC.showNotification(response.data.message, 'error');
					}
				},
				error: function () {
					DVC.showNotification('Ein Fehler ist aufgetreten.', 'error');
				},
			});
		},

		autoScrollMessages: function () {
			var $messagesArea = $('#dvc-messages-area');
			if ($messagesArea.length) {
				$messagesArea.scrollTop($messagesArea[0].scrollHeight);
			}
		},

		setupAutoRefresh: function () {
			// Auto-refresh messages every 10 seconds if on chat page
			if ($('.dvc-chat-window').length && $('.dvc-send-message-form').length) {
				var chatId = $('.dvc-send-message-form').data('chat-id');
				if (chatId) {
					setInterval(function () {
						DVC.loadMessages(chatId);
					}, 10000); // 10 seconds
				}
			}
		},

		showNotification: function (message, type) {
			var className = type === 'success' ? 'dvc-message-success' : 'dvc-message-error';
			var $notification = $('<div class="' + className + '">' + message + '</div>');

			// Insert at top of chat window or modal body
			if ($('.dvc-modal:visible').length) {
				$('.dvc-modal-body').prepend($notification);
			} else if ($('.dvc-chat-window').length) {
				$('.dvc-chat-window').prepend($notification);
			} else {
				$('.sk-vendor-chat-dashboard').prepend($notification);
			}

			// Auto-remove after 5 seconds
			setTimeout(function () {
				$notification.fadeOut(function () {
					$(this).remove();
				});
			}, 5000);
		},

		escapeHtml: function (text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return String(text === null || text === undefined ? '' : text).replace(/[&<>\"']/g, function (m) {
				return map[m];
			});
		},
	};

	// Initialize on document ready
	$(document).ready(function () {
		DVC.init();
	});
})(jQuery);
