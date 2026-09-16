/**
 * User Onboarding - JavaScript
 */
(function ($) {
	'use strict';

	var UOB = {
		currentSlide: 0,
		totalSlides: 7,

		// The shop slide is the only one that asks for input.
		shopSlide: 3,
		place: { lat: 0, lng: 0 },

		init: function () {
			this.showModal();
			this.bindEvents();
		},

		bindEvents: function () {
			// Close button
			$('.uob-close').on('click', this.skipOnboarding.bind(this));

			// Skip button
			$('.uob-btn-skip').on('click', this.skipOnboarding.bind(this));

			// Next button
			$('.uob-btn-next').on('click', this.nextSlide.bind(this));

			// Previous button
			$('.uob-btn-prev').on('click', this.prevSlide.bind(this));

			// Finish button
			$('.uob-btn-finish').on('click', this.completeOnboarding.bind(this));

			// Progress dots
			$('.uob-dot').on('click', function () {
				var slideIndex = parseInt($(this).data('slide'));
				UOB.goToSlide(slideIndex);
			});

			// Nostr Identity creation
		$(document).on('click', '#uob-copy-nsec', function () {
			navigator.clipboard.writeText($('#uob-nsec-value').text());
			var $b = $(this);
			$b.html('<i class="fas fa-check"></i> Kopiert');
			setTimeout(function () { $b.html('<i class="fas fa-copy"></i> Kopieren'); }, 2000);
		});

		$('#uob-create-nostr').on('click', function () {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Wird erstellt...');
			$.ajax({
				url: uobAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'sk_create_nostr_identity',
					nonce: uobAjax.nonce
				},
				success: function (res) {
					if (res.success) {
						$('#uob-nostr-status').html('<span style="color:#5cb85c;"><i class="fas fa-check-circle"></i> ' + res.data.message + '</span>');
						$btn.hide();

						// Fetch and show the key now. It's deliberately not
						// included in the creation response, but comes via
						// the same path used later under "Nostr/LN Link".
						$.post(uobAjax.ajaxurl, {
							action: 'sk_get_nostr_nsec',
							nonce: uobAjax.nonce
						}, function (key) {
							if (key.success && key.data && key.data.nsec) {
								$('#uob-nsec-value').text(key.data.nsec);
								$('#uob-nostr-key').show();
							}
						});
					} else {
						$('#uob-nostr-status').html('<span style="color:#e06c75;">' + (res.data.message || 'Fehler') + '</span>');
						$btn.prop('disabled', false).html('<i class="fas fa-key"></i> Erneut versuchen');
					}
				},
				error: function () {
					$('#uob-nostr-status').html('<span style="color:#e06c75;">Netzwerkfehler</span>');
					$btn.prop('disabled', false).html('<i class="fas fa-key"></i> Erneut versuchen');
				}
			});
		});

		// Close on backdrop click
			$('.uob-modal').on('click', function (e) {
				if ($(e.target).hasClass('uob-modal')) {
					UOB.skipOnboarding();
				}
			});

			this.bindShopSlide();

			// Keyboard navigation
			$(document).on('keydown', function (e) {
				if (!$('.uob-modal').is(':visible')) {
					return;
				}

				// Arrows and Enter belong to the field while one is focused.
				if ($(e.target).is('input, textarea')) {
					return;
				}

				// Escape key - skip
				if (e.keyCode === 27) {
					UOB.skipOnboarding();
				}

				// Left arrow - previous
				if (e.keyCode === 37 && UOB.currentSlide > 0) {
					UOB.prevSlide();
				}

				// Right arrow - next
				if (e.keyCode === 39 && UOB.currentSlide < UOB.totalSlides - 1) {
					UOB.nextSlide();
				}

				// Enter key - next or finish
				if (e.keyCode === 13) {
					if (UOB.currentSlide < UOB.totalSlides - 1) {
						UOB.nextSlide();
					} else {
						UOB.completeOnboarding();
					}
				}
			});
		},

		showModal: function () {
			// Show modal after a short delay for better UX
			setTimeout(function () {
				$('.uob-modal').fadeIn(300);
			}, 500);

			// Immediately mark as completed so it doesn't show again if user navigates away
			$.ajax({
				url: uobAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'uob_complete_onboarding',
					nonce: uobAjax.nonce
				}
			});
		},

		hideModal: function (callback) {
			$('.uob-modal').fadeOut(300, callback);
		},

		goToSlide: function (index) {
			if (index < 0 || index >= this.totalSlides) {
				return;
			}

			// Leaving the shop slide saves it first — by Next, by a dot or by
			// arrow key. A rejected name keeps the user here to correct it.
			if (this.currentSlide === this.shopSlide && index !== this.shopSlide && !this.saving) {
				this.saveShop(function () { UOB.goToSlide(index); });
				return;
			}

			// Update current slide
			this.currentSlide = index;

			// Hide all slides
			$('.uob-slide').removeClass('active');

			// Show current slide
			$('.uob-slide[data-slide="' + index + '"]').addClass('active');

			// Update progress dots
			$('.uob-dot').removeClass('active');
			$('.uob-dot[data-slide="' + index + '"]').addClass('active');

			// Update button visibility
			this.updateButtons();
		},

		nextSlide: function () {
			if (this.currentSlide < this.totalSlides - 1) {
				this.goToSlide(this.currentSlide + 1);
			}
		},

		prevSlide: function () {
			if (this.currentSlide > 0) {
				this.goToSlide(this.currentSlide - 1);
			}
		},

		updateButtons: function () {
			var isFirstSlide = this.currentSlide === 0;
			var isLastSlide = this.currentSlide === this.totalSlides - 1;

			// Show/hide prev button
			if (isFirstSlide) {
				$('.uob-btn-prev').hide();
			} else {
				$('.uob-btn-prev').show();
			}

			// Show/hide next/finish buttons
			if (isLastSlide) {
				$('.uob-btn-next').hide();
				$('.uob-btn-finish').show();
			} else {
				$('.uob-btn-next').show();
				$('.uob-btn-finish').hide();
			}
		},

		// ── Shop slide ────────────────────────────────────────────────

		// Its own config object, and defensively read: a browser holding the
		// previous file version would otherwise throw here.
		shopConfig: function () {
			return window.uobShop || {};
		},

		bindShopSlide: function () {
			$('.uob-image input[type="file"]').on('change', function () {
				UOB.uploadImage($(this).closest('.uob-image'), this.files[0]);
			});

			var timer;

			$('#uob-place').on('input', function () {
				// A typed change invalidates the picked position: the written
				// address would otherwise keep coordinates of another place.
				UOB.place = { lat: 0, lng: 0 };

				var query = $.trim(this.value);

				clearTimeout(timer);

				if (!UOB.shopConfig().hasGeo || query.length < 3) {
					$('.uob-place-list').empty().prop('hidden', true);
					return;
				}

				timer = setTimeout(function () { UOB.suggestPlaces(query); }, 300);
			});

			// mousedown, not click: the list sits inside a <label>, so a click
			// would refocus the input and reopen what was just chosen.
			$('.uob-place-list').on('mousedown', 'li', function (e) {
				e.preventDefault();

				UOB.place = { lat: $(this).data('lat'), lng: $(this).data('lng') };
				$('#uob-place').val($(this).text());
				$('.uob-place-list').empty().prop('hidden', true);
			});

			$(document).on('click', function (e) {
				if (!$(e.target).closest('.uob-field--place').length) {
					$('.uob-place-list').empty().prop('hidden', true);
				}
			});
		},

		suggestPlaces: function (query) {
			$.post(uobAjax.ajaxurl, {
				action: 'sk_geo_geocode',
				nonce: UOB.shopConfig().geoNonce,
				q: query
			}, function (res) {
				var features = (res && res.success && res.data && res.data.features) || [];
				var $list = $('.uob-place-list').empty();

				$.each(features, function (i, feature) {
					$('<li></li>')
						.text(feature.place_name)
						.attr('data-lng', feature.geometry.coordinates[0])
						.attr('data-lat', feature.geometry.coordinates[1])
						.appendTo($list);
				});

				$list.prop('hidden', !features.length);
			});
		},

		uploadImage: function ($box, file) {
			if (!file) {
				return;
			}

			var data = new FormData();
			data.append('action', 'uob_upload_image');
			data.append('nonce', uobAjax.nonce);
			data.append('kind', $box.data('kind'));
			data.append('file', file);

			$box.addClass('is-busy');

			$.ajax({
				url: uobAjax.ajaxurl,
				type: 'POST',
				data: data,
				processData: false,
				contentType: false
			}).always(function () {
				$box.removeClass('is-busy');
			}).done(function (res) {
				if (res && res.success) {
					$box.find('img').attr('src', res.data.url).prop('hidden', false);
					$box.find('i').prop('hidden', true);
					UOB.shopStatus('');
					return;
				}

				UOB.shopStatus((res && res.data && res.data.message) || 'Upload fehlgeschlagen', true);
			}).fail(function () {
				UOB.shopStatus('Upload fehlgeschlagen', true);
			});
		},

		saveShop: function (done) {
			this.saving = true;

			$.post(uobAjax.ajaxurl, {
				action: 'uob_save_shop',
				nonce: uobAjax.nonce,
				store_name: $('#uob-store-name').val() || '',
				place: $('#uob-place').val() || '',
				lat: this.place.lat,
				lng: this.place.lng
			}).done(function (res) {
				if (res && res.success) {
					UOB.shopStatus('');
					done();
					return;
				}

				UOB.shopStatus((res && res.data && res.data.message) || 'Konnte nicht gespeichert werden', true);
				$('#uob-store-name').trigger('focus');
			}).fail(function () {
				// A failing request must not trap anybody in the modal.
				done();
			}).always(function () {
				UOB.saving = false;
			});
		},

		shopStatus: function (message, isError) {
			$('#uob-shop-status')
				.toggleClass('is-error', !!isError)
				.text(message || '');
		},

		skipOnboarding: function () {
			// Ask for confirmation
			if (!confirm('Möchtest du die Einführung wirklich überspringen?')) {
				return;
			}

			this.completeOnboarding();
		},

		completeOnboarding: function () {
			// Send AJAX request to mark as completed
			$.ajax({
				url: uobAjax.ajaxurl,
				type: 'POST',
				data: {
					action: 'uob_complete_onboarding',
					nonce: uobAjax.nonce
				},
				success: function (response) {
					// Hide modal
					UOB.hideModal(function () {
						// Remove from DOM
						$('.uob-modal').remove();
					});
				},
				error: function () {
					// Still hide modal even if AJAX fails
					UOB.hideModal(function () {
						$('.uob-modal').remove();
					});
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function () {
		// Only init if modal exists
		if ($('.uob-modal').length > 0) {
			UOB.init();
		}
	});

})(jQuery);
