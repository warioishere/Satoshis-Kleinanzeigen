jQuery(document).ready(function($) {
    const searchInput = $('#wkf-cat-search');
    const resultsBox = $('#wkf-results');

    searchInput.on('input', function() {
        const searchTerm = $(this).val();

        if (searchTerm.length < 2) {
            resultsBox.empty();
            return;
        }

        $.post(wkf_ajax_object.ajax_url, {
            action: 'wkf_search_categories',
            term: searchTerm
        }, function(data) {
            resultsBox.empty();

            if (data.length === 0) {
                resultsBox.append('<li>Keine Treffer.</li>');
            } else {
                data.forEach(function(item) {
                    resultsBox.append('<li><a href="' + item.link + '">' + item.name + '</a></li>');
                });
            }
        });
    });

    // Wenn außerhalb geklickt wird, Ergebnisse ausblenden
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wkf-autocomplete-wrapper').length) {
            resultsBox.empty();
        }
    });
});

