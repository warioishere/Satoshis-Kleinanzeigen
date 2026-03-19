/**
 * SK Customizer Controls — Toggle sidebar-related controls based on layout
 */
(function () {
    var api = wp.customize;

    api.bind('ready', function () {
        function toggleSidebarControls(show) {
            api.control('sidebar_heading').active.set(show);
            api.control('store_map').active.set(show);
            api.control('contact_seller').active.set(show);
        }

        api.control('store_layout', function (control) {
            control.setting.bind(function (val) {
                var hasSidebar = val !== 'full';
                api.control('enable_theme_sidebar').active.set(hasSidebar);
                toggleSidebarControls(hasSidebar);
            });
        });

        api.control('enable_theme_sidebar', function (control) {
            control.setting.bind(function (val) {
                toggleSidebarControls(!val);
            });
        });
    });
})();
