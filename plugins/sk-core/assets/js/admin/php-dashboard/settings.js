/**
 * Settings page of the PHP admin dashboard: show_if conditional rows and the
 * WP media picker for file fields.
 */
(function() {
    // show_if conditional display
    document.querySelectorAll('[data-show-if]').forEach(function(row) {
        var condition = JSON.parse(row.getAttribute('data-show-if'));
        var keys = Object.keys(condition);

        function checkVisibility() {
            var visible = true;
            keys.forEach(function(key) {
                var expected = condition[key];
                var el = document.querySelector('[data-field-name="' + key + '"]');
                if (!el) {
                    var input = document.querySelector('[name$="[' + key + ']"]');
                    if (input) el = input;
                }
                if (el) {
                    var val;
                    if (el.type === 'checkbox') {
                        val = el.checked ? 'on' : 'off';
                    } else {
                        val = el.value;
                    }
                    if (Array.isArray(expected)) {
                        if (expected.indexOf(val) === -1) visible = false;
                    } else if (val !== expected) {
                        visible = false;
                    }
                }
            });
            row.style.display = visible ? '' : 'none';
        }

        checkVisibility();

        keys.forEach(function(key) {
            var el = document.querySelector('[data-field-name="' + key + '"]');
            if (!el) {
                el = document.querySelector('[name$="[' + key + ']"]');
            }
            if (el) {
                el.addEventListener('change', checkVisibility);
            }
        });
    });

    // WP Media uploader for file fields
    document.querySelectorAll('.sk-upload-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(btn.getAttribute('data-target'));
            if (!target) return;

            if (typeof wp !== 'undefined' && wp.media) {
                var frame = wp.media({
                    title: 'Select File',
                    button: { text: 'Use this file' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    target.value = attachment.url;
                });
                frame.open();
            }
        });
    });
})();
