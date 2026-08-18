/**
 * Module grid of the PHP admin dashboard: toggle a module via AJAX.
 */
(function() {
    var config = window.skPhpModules || {};

    document.querySelectorAll('.sk-module-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var moduleId = this.getAttribute('data-module-id');
            var active = this.checked ? '1' : '0';
            var label = this.nextElementSibling;

            var data = new FormData();
            data.append('action', 'sk_php_toggle_module');
            data.append('nonce', config.nonce);
            data.append('module_id', moduleId);
            data.append('active', active);

            fetch(config.ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).then(function(response) {
                return response.json();
            }).then(function(result) {
                if (result.success) {
                    label.textContent = active === '1' ? config.activeLabel : config.inactiveLabel;
                } else {
                    checkbox.checked = !checkbox.checked;
                    alert(config.errorMessage);
                }
            }).catch(function() {
                checkbox.checked = !checkbox.checked;
                alert(config.errorMessage);
            });
        });
    });
})();
