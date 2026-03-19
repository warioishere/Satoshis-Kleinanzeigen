/**
 * User Onboarding - JavaScript
 */
(function ($) {
	'use strict';

	var UOB = {
		currentSlide: 0,
		totalSlides: 5,

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

			// Close on backdrop click
			$('.uob-modal').on('click', function (e) {
				if ($(e.target).hasClass('uob-modal')) {
					UOB.skipOnboarding();
				}
			});

			// Keyboard navigation
			$(document).on('keydown', function (e) {
				if (!$('.uob-modal').is(':visible')) {
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
