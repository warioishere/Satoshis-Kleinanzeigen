/**
 * SK Feed — Like, Report, Load-More, Compose, Filter.
 */
(function ($) {
    'use strict';

    if (typeof skFeed === 'undefined') return;

    // ── Like Toggle ──────────────────────────────────────────────────────

    function requireLogin() {
        window.location.href = skFeed.loginUrl || '/mein-konto/';
    }

    $(document).on('click', '.sk-feed-like-btn', function (e) {
        e.preventDefault();
        if (!skFeed.isLoggedIn) { requireLogin(); return; }
        var $btn = $(this);
        if ($btn.hasClass('loading')) return;
        $btn.addClass('loading');

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_toggle_like',
            _nonce: skFeed.nonce,
            post_id: $btn.data('post-id')
        }, function (res) {
            $btn.removeClass('loading');
            if (res.success) {
                $btn.toggleClass('active', res.data.liked);
                $btn.find('i').toggleClass('fas', res.data.liked).toggleClass('far', !res.data.liked);
                $btn.find('.sk-feed-like-count').text(res.data.count > 0 ? res.data.count : '');
            }
        }).fail(function () {
            $btn.removeClass('loading');
        });
    });

    // ── Share ────────────────────────────────────────────────────────────

    function flashCopied($btn) {
        var $icon = $btn.find('i');
        var origClass = $icon.attr('class');
        $btn.addClass('copied');
        $icon.attr('class', 'fas fa-check');

        $btn.find('.sk-feed-copied-toast').remove();
        var $toast = $('<span class="sk-feed-copied-toast">Link kopiert!</span>');
        $btn.append($toast);
        requestAnimationFrame(function () { $toast.addClass('show'); });

        setTimeout(function () {
            $btn.removeClass('copied');
            $icon.attr('class', origClass);
            $toast.removeClass('show');
            setTimeout(function () { $toast.remove(); }, 200);
        }, 1600);
    }

    $(document).on('click', '.sk-feed-share-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var url  = $btn.data('url');

        if (navigator.share) {
            navigator.share({ url: url }).catch(function () {});
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                flashCopied($btn);
            }).catch(function () {
                window.prompt('Link kopieren:', url);
            });
            return;
        }

        // Fallback for older browsers.
        var $tmp = $('<input>').val(url).appendTo('body').select();
        try { document.execCommand('copy'); flashCopied($btn); } catch (_) { window.prompt('Link kopieren:', url); }
        $tmp.remove();
    });

    // ── Report ───────────────────────────────────────────────────────────

    $(document).on('click', '.sk-feed-report-btn', function (e) {
        e.preventDefault();
        if (!skFeed.isLoggedIn) { requireLogin(); return; }
        var $btn = $(this);
        if ($btn.hasClass('reported')) return;

        var reason = prompt('Grund der Meldung (optional):') || '';
        // User cancelled prompt.
        if (reason === null) return;

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_report_post',
            _nonce: skFeed.nonce,
            post_id: $btn.data('post-id'),
            reason: reason
        }, function (res) {
            if (res.success) {
                $btn.addClass('reported').attr('title', skFeed.i18n.report_success);
                $btn.find('i').removeClass('far').addClass('fas');
            } else if (res.data && res.data.message) {
                alert(res.data.message);
            }
        });
    });

    // ── Infinite Scroll ─────────────────────────────────────────────────

    var feedLoading = false;

    function initInfiniteScroll() {
        var $sentinel = $('.sk-feed-scroll-sentinel');
        if (!$sentinel.length || !('IntersectionObserver' in window)) return;

        var observer = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting || feedLoading) return;
            loadNextPage();
        }, { rootMargin: '400px' });

        observer.observe($sentinel[0]);
        $sentinel.data('observer', observer);
    }

    function loadNextPage() {
        var $sentinel = $('.sk-feed-scroll-sentinel');
        var page      = parseInt($sentinel.data('page') || 2, 10);
        var maxPages  = parseInt($sentinel.data('max') || 1, 10);
        var $wrapper  = $sentinel.closest('.sk-feed-wrapper');
        var vendorId  = $wrapper.data('vendor-id') || 0;
        var filter    = $wrapper.find('.sk-feed-filter-btn.active').data('filter') || 'all';

        if (page > maxPages) return;

        feedLoading = true;
        $sentinel.html('<i class="fas fa-spinner fa-spin"></i>');

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_load_more',
            _nonce: skFeed.nonce,
            page: page,
            vendor_id: vendorId,
            filter: filter
        }, function (res) {
            feedLoading = false;
            if (res.success) {
                $('#sk-feed-list').append(res.data.html);
                if (res.data.has_more) {
                    $sentinel.data('page', page + 1).html('');
                } else {
                    var obs = $sentinel.data('observer');
                    if (obs) obs.disconnect();
                    $sentinel.html('<p class="sk-feed-no-more">' + skFeed.i18n.no_more + '</p>');
                }
            }
        }).fail(function () {
            feedLoading = false;
            $sentinel.html('');
        });
    }

    // Init on page load + after filter change.
    $(function () { initInfiniteScroll(); });

    // ── Filter (Community Page) ──────────────────────────────────────────

    $(document).on('click', '.sk-feed-filter-btn', function () {
        var $btn = $(this);
        if ($btn.hasClass('active')) return;

        var $wrapper = $btn.closest('.sk-feed-wrapper');
        $wrapper.find('.sk-feed-filter-btn').removeClass('active');
        $btn.addClass('active');

        var filter = $btn.data('filter');
        var $list  = $wrapper.find('#sk-feed-list');

        $list.css('opacity', '0.4');

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_load_more',
            _nonce: skFeed.nonce,
            page: 1,
            filter: filter
        }, function (res) {
            if (res.success) {
                $list.html(res.data.html).css('opacity', '1');
                // Reset infinite scroll sentinel.
                var $sentinel = $wrapper.find('.sk-feed-scroll-sentinel');
                var obs = $sentinel.data('observer');
                if (obs) obs.disconnect();
                if (res.data.has_more) {
                    $sentinel.data('page', 2).html('');
                    initInfiniteScroll();
                } else {
                    $sentinel.html('<p class="sk-feed-no-more">' + skFeed.i18n.no_more + '</p>');
                }
            } else {
                $list.css('opacity', '1');
            }
        }).fail(function () {
            $list.css('opacity', '1');
        });
    });

    // ── Feed Type Toggle ──────────────────────────────────────────────────

    $(document).on('change', '.sk-feed-type-option input[type="radio"]', function () {
        $(this).closest('.sk-feed-type-toggle').find('.sk-feed-type-option').removeClass('active');
        $(this).closest('.sk-feed-type-option').addClass('active');
    });

    // ── Compose (Dashboard) ──────────────────────────────────────────────

    $('#sk-feed-compose-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('#sk-feed-submit');

        if ($btn.prop('disabled')) return;
        $btn.prop('disabled', true).text(skFeed.i18n.loading);

        var formData = new FormData($form[0]);
        formData.append('action', 'sk_feed_create_post');
        formData.append('_nonce', skFeed.nonce);

        $.ajax({
            url: skFeed.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Posten');
                if (res.success) {
                    $form.find('#sk-feed-content').val('');
                    $form.find('#sk-feed-chars').text('0');
                    $('#sk-feed-image-preview').hide();
                    $form.find('#sk-feed-image-input').val('');

                    // Prepend to dashboard list or community feed.
                    var $list = $('#sk-feed-dashboard-list');
                    var $feedList = $('#sk-feed-list');
                    if ($list.length) {
                        $list.prepend(res.data.html);
                    } else if ($feedList.length) {
                        $('.sk-feed-empty').remove();
                        $feedList.prepend(res.data.html);
                    }
                    // Update count.
                    var $total = $('.sk-feed-total');
                    if ($total.length) {
                        $total.text(parseInt($total.text() || 0, 10) + 1);
                    }
                    // Remove empty state.
                    $('.sk-feed-empty').remove();
                } else if (res.data && res.data.message) {
                    alert(res.data.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Posten');
                alert(skFeed.i18n.error);
            }
        });
    });

    // Character counter.
    $('#sk-feed-content').on('input', function () {
        $('#sk-feed-chars').text($(this).val().length);
    });

    // Image preview.
    $('#sk-feed-image-input').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#sk-feed-preview-img').attr('src', e.target.result);
            $('#sk-feed-image-preview').show();
        };
        reader.readAsDataURL(file);
    });

    $('#sk-feed-remove-image').on('click', function () {
        $('#sk-feed-image-input').val('');
        $('#sk-feed-image-preview').hide();
    });

    // ── Edit (Dashboard) ─────────────────────────────────────────────────

    $(document).on('click', '.sk-feed-edit-btn', function () {
        var $item = $(this).closest('.sk-feed-dashboard-item');
        $item.find('.sk-feed-dashboard-item-content').hide();
        $item.find('.sk-feed-dashboard-item-edit').show();
        $item.find('.sk-feed-edit-textarea').focus();
    });

    $(document).on('click', '.sk-feed-cancel-edit-btn', function () {
        var $item = $(this).closest('.sk-feed-dashboard-item');
        $item.find('.sk-feed-dashboard-item-edit').hide();
        $item.find('.sk-feed-dashboard-item-content').show();
    });

    // Remove image in edit mode.
    $(document).on('click', '.sk-feed-edit-remove-image', function () {
        var $edit = $(this).closest('.sk-feed-edit-image');
        $edit.find('.sk-feed-edit-image-current').remove();
        $edit.data('remove-image', '1');
        $edit.find('.sk-feed-edit-upload-label').html('<i class="fas fa-image"></i> Bild hinzufügen <input type="file" class="sk-feed-edit-image-input" accept="image/*" style="display:none;" />');
    });

    // Preview new image in edit mode.
    $(document).on('change', '.sk-feed-edit-image-input', function () {
        var file = this.files[0];
        if (!file) return;
        var $edit = $(this).closest('.sk-feed-edit-image');
        $edit.data('remove-image', '');
        var reader = new FileReader();
        reader.onload = function (e) {
            $edit.find('.sk-feed-edit-image-current').remove();
            $edit.prepend('<div class="sk-feed-edit-image-current"><img src="' + e.target.result + '" alt="" /><button type="button" class="sk-feed-edit-remove-image"><i class="fas fa-times"></i></button></div>');
        };
        reader.readAsDataURL(file);
    });

    $(document).on('click', '.sk-feed-save-btn', function () {
        var $btn = $(this);
        if ($btn.prop('disabled')) return;

        var $item   = $btn.closest('.sk-feed-dashboard-item');
        var postId  = $btn.data('post-id');
        var content = $item.find('.sk-feed-edit-textarea').val().trim();

        if (!content) return;

        $btn.prop('disabled', true).text(skFeed.i18n.loading);

        var formData = new FormData();
        formData.append('action', 'sk_feed_edit_post');
        formData.append('_nonce', skFeed.nonce);
        formData.append('post_id', postId);
        formData.append('content', content);

        var $editImage = $item.find('.sk-feed-edit-image');
        if ($editImage.data('remove-image') === '1') {
            formData.append('remove_image', '1');
        }

        var fileInput = $item.find('.sk-feed-edit-image-input')[0];
        if (fileInput && fileInput.files[0]) {
            formData.append('image', fileInput.files[0]);
        }

        $.ajax({
            url: skFeed.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Speichern');
                if (res.success) {
                    // Update displayed text (truncated).
                    var plain = $('<div>').html(res.data.content).text();
                    var words = plain.split(/\s+/).slice(0, 30).join(' ');
                    if (plain.split(/\s+/).length > 30) words += '\u2026';
                    $item.find('.sk-feed-dashboard-item-content').text(words).attr('data-full-content', res.data.content);

                    // Update thumbnail.
                    var $thumb = $item.find('.sk-feed-dashboard-item-thumb');
                    if (res.data.thumb_url) {
                        if ($thumb.length) {
                            $thumb.find('img').attr('src', res.data.thumb_url);
                        } else {
                            $item.prepend('<div class="sk-feed-dashboard-item-thumb"><img src="' + res.data.thumb_url + '" alt="" /></div>');
                        }
                    } else {
                        $thumb.remove();
                    }

                    $item.find('.sk-feed-dashboard-item-edit').hide();
                    $item.find('.sk-feed-dashboard-item-content').show();
                } else if (res.data && res.data.message) {
                    alert(res.data.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Speichern');
            }
        });
    });

    // ── Pin (Dashboard) ─────────────────────────────────────────────────

    $(document).on('click', '.sk-feed-pin-btn', function () {
        var $btn   = $(this);
        var postId = $btn.data('post-id');

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_toggle_pin',
            _nonce: skFeed.nonce,
            post_id: postId
        }, function (res) {
            if (res.success) {
                $btn.toggleClass('active', res.data.pinned);
                $btn.attr('title', res.data.pinned ? 'Loslösen' : 'Anpinnen');
                // Remove active from other pin buttons.
                if (res.data.pinned) {
                    $('.sk-feed-pin-btn').not($btn).removeClass('active');
                }
            }
        });
    });

    // ── Delete (Dashboard) ───────────────────────────────────────────────

    $(document).on('click', '.sk-feed-delete-btn', function () {
        var $btn = $(this);
        if (!confirm(skFeed.i18n.confirm_delete)) return;

        var postId = $btn.data('post-id');

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_delete_post',
            _nonce: skFeed.nonce,
            post_id: postId
        }, function (res) {
            if (res.success) {
                $btn.closest('.sk-feed-dashboard-item, .sk-feed-card').fadeOut(300, function () {
                    $(this).remove();
                    var $total = $('.sk-feed-total');
                    if ($total.length) {
                        var n = parseInt($total.text() || 0, 10) - 1;
                        $total.text(Math.max(0, n));
                    }
                });
            }
        });
    });

    // ── Comments (Single Post Page) ─────────────────────────────────────

    // Scroll to comments.
    $(document).on('click', '.sk-feed-scroll-to-comments', function (e) {
        e.preventDefault();
        var $comments = $('#sk-feed-comments');
        if ($comments.length) {
            $comments[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            $comments.find('#sk-feed-comment-text').focus();
        }
    });

    // Submit top-level comment.
    $(document).on('click', '.sk-feed-comment-submit', function () {
        var $btn  = $(this);
        var $text = $('#sk-feed-comment-text');
        var comment = $text.val().trim();
        if (!comment || $btn.prop('disabled')) return;

        $btn.prop('disabled', true).text(skFeed.i18n.loading);

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_add_comment',
            _nonce: skFeed.nonce,
            post_id: $btn.data('post-id'),
            parent_id: 0,
            comment: comment
        }, function (res) {
            $btn.prop('disabled', false).text('Antworten');
            if (res.success) {
                $text.val('');
                $('.sk-feed-no-comments').remove();
                $('#sk-feed-comment-list').append(res.data.html);
                // Update comment count in stats.
                updateCommentCount(res.data.count);
            } else if (res.data && res.data.message) {
                alert(res.data.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Antworten');
        });
    });

    // Show reply form.
    $(document).on('click', '.sk-feed-reply-btn', function () {
        var commentId = $(this).data('comment-id');
        // Hide all other reply forms.
        $('.sk-feed-reply-form').hide();
        var $form = $(this).siblings('.sk-feed-reply-form');
        $form.show().find('.sk-feed-reply-textarea').focus();
    });

    // Cancel reply.
    $(document).on('click', '.sk-feed-reply-cancel', function () {
        $(this).closest('.sk-feed-reply-form').hide();
    });

    // Submit reply.
    $(document).on('click', '.sk-feed-reply-submit', function () {
        var $btn    = $(this);
        var $form   = $btn.closest('.sk-feed-reply-form');
        var $text   = $form.find('.sk-feed-reply-textarea');
        var comment = $text.val().trim();
        if (!comment || $btn.prop('disabled')) return;

        $btn.prop('disabled', true).text(skFeed.i18n.loading);

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_add_comment',
            _nonce: skFeed.nonce,
            post_id: $btn.data('post-id'),
            parent_id: $btn.data('parent-id'),
            comment: comment
        }, function (res) {
            $btn.prop('disabled', false).text('Antworten');
            if (res.success) {
                $text.val('');
                $form.hide();
                // Append reply after the parent comment's body.
                $form.closest('.sk-feed-comment-body').append(res.data.html);
                updateCommentCount(res.data.count);
            } else if (res.data && res.data.message) {
                alert(res.data.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Antworten');
        });
    });

    function updateCommentCount(count) {
        var $stats = $('.sk-feed-single-stats');
        var $existing = $stats.find('.sk-feed-comment-count-stat');
        var label = count === 1 ? 'Kommentar' : 'Kommentare';
        if ($existing.length) {
            $existing.html('<strong>' + count + '</strong> ' + label);
        } else {
            $stats.append('<span class="sk-feed-comment-count-stat"><strong>' + count + '</strong> ' + label + '</span>');
        }
    }

    // ── Image Lightbox ─────────────────────────────────────────────────

    $(document).on('click', '.sk-feed-card-image img, .sk-feed-single-image img', function () {
        var src = $(this).attr('src');
        var $lb = $('<div class="sk-feed-lightbox"><img src="' + src + '" /><button class="sk-feed-lightbox-close">&times;</button></div>');
        $('body').append($lb);
        $lb.on('click', function (e) {
            if (e.target === $lb[0] || $(e.target).hasClass('sk-feed-lightbox-close')) {
                $lb.remove();
            }
        });
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('.sk-feed-lightbox').remove();
    });

    // ── @Mention Autocomplete ────────────────────────────────────────────

    var mentionTimer = null;
    var $mentionDropdown = null;

    function createDropdown() {
        if ($mentionDropdown) return $mentionDropdown;
        $mentionDropdown = $('<div class="sk-feed-mention-dropdown"></div>').appendTo('body').hide();
        return $mentionDropdown;
    }

    function getMentionContext(textarea) {
        var val = textarea.value;
        var pos = textarea.selectionStart;
        var before = val.substring(0, pos);
        var match = before.match(/@([A-Za-z0-9À-ÿ][A-Za-z0-9À-ÿ .&'-]{0,49})$/);
        if (!match) return null;
        return { term: match[1], start: pos - match[1].length - 1, end: pos };
    }

    $(document).on('input keyup', 'textarea.sk-form-control, textarea.sk-feed-reply-textarea, textarea.sk-feed-edit-textarea', function (e) {
        var textarea = this;
        var ctx = getMentionContext(textarea);

        if (!ctx || ctx.term.length < 1) {
            createDropdown().hide();
            return;
        }

        clearTimeout(mentionTimer);
        mentionTimer = setTimeout(function () {
            $.ajax({
                url: skFeed.ajaxurl,
                data: { action: 'sk_feed_search_stores', _nonce: skFeed.nonce, term: ctx.term },
                success: function (res) {
                    var $dd = createDropdown().empty();
                    if (!res.success || !res.data.length) { $dd.hide(); return; }

                    res.data.forEach(function (store) {
                        $dd.append(
                            '<div class="sk-feed-mention-item" data-name="' + $('<span>').text(store.name).html() + '">' +
                            '<img src="' + store.avatar + '" alt="" />' +
                            '<span>' + $('<span>').text(store.name).html() + '</span>' +
                            '</div>'
                        );
                    });

                    // Position dropdown below textarea cursor.
                    var $ta = $(textarea);
                    var offset = $ta.offset();
                    $dd.css({
                        top: offset.top + $ta.outerHeight() + 4,
                        left: offset.left,
                        width: Math.min($ta.outerWidth(), 300)
                    }).show();

                    // Store reference to the textarea.
                    $dd.data('textarea', textarea);
                    $dd.data('ctx', ctx);
                }
            });
        }, 200);
    });

    // Click on mention item.
    $(document).on('click', '.sk-feed-mention-item', function () {
        var name = $(this).data('name');
        var $dd = createDropdown();
        var textarea = $dd.data('textarea');
        var ctx = $dd.data('ctx');

        if (!textarea || !ctx) { $dd.hide(); return; }

        var val = textarea.value;
        textarea.value = val.substring(0, ctx.start) + '@' + name + ' ' + val.substring(ctx.end);
        textarea.focus();
        var newPos = ctx.start + name.length + 2;
        textarea.setSelectionRange(newPos, newPos);
        $dd.hide();
    });

    // Hide dropdown on blur (with delay for click).
    $(document).on('blur', 'textarea', function () {
        setTimeout(function () { createDropdown().hide(); }, 200);
    });

    // Hide on Escape.
    $(document).on('keydown', 'textarea', function (e) {
        if (e.key === 'Escape') createDropdown().hide();

        // Arrow key navigation in dropdown.
        var $dd = createDropdown();
        if (!$dd.is(':visible')) return;

        var $items = $dd.find('.sk-feed-mention-item');
        var $active = $items.filter('.active');
        var idx = $items.index($active);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = idx < $items.length - 1 ? idx + 1 : 0;
            $items.removeClass('active').eq(idx).addClass('active');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = idx > 0 ? idx - 1 : $items.length - 1;
            $items.removeClass('active').eq(idx).addClass('active');
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if ($active.length) {
                e.preventDefault();
                $active.trigger('click');
            }
        }
    });

})(jQuery);
