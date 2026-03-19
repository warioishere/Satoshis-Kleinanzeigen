/**
 * SK Page Views — Track unique daily product views via AJAX
 */
jQuery(document).ready(function ($) {
    if (!localStorage || !window.skPageViewsParams) return;

    var today = new Date().toISOString().slice(0, 10);
    var data = JSON.parse(localStorage.getItem('sk_pageview_count'));
    var postId = window.skPageViewsParams.post_id;

    if (!data || (data.today && data.today !== today)) {
        data = { today: today, post_ids: [] };
    }

    if (!data.post_ids.includes(postId)) {
        $.post(window.skPageViewsParams.ajax_url, {
            action: 'sk_pageview',
            _ajax_nonce: window.skPageViewsParams.nonce,
            post_id: postId
        });
        data.post_ids.push(postId);
        localStorage.setItem('sk_pageview_count', JSON.stringify(data));
    }
});
