jQuery(function ($) {

    var ajax_url = typeof thaaniyam_ajax !== 'undefined' && thaaniyam_ajax.ajax_url ? thaaniyam_ajax.ajax_url : '/wp-admin/admin-ajax.php';

    function updateHeaderCount() {
        $.post(
            ajax_url,
            { action: 'tt5_get_wishlist_count' },
            function (res) {
                if (res.success) {
                    var count = parseInt(res.data);
                   
                    $('.custom-wishlist-count').text(count);
                }
            }
        );
    }

    // Intercept modern YITH REST API fetch calls for INSTANT header updates
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function (...args) {
            return originalFetch(...args).then(function (response) {
                try {
                    var url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url);
                    if (url && url.indexOf('/yith/wishlist/v1/items') !== -1) {
                        response.clone().json().then(function (data) {
                            if (data && typeof data.total_wishlist_count !== 'undefined') {
                                var count = parseInt(data.total_wishlist_count);
                               
                                $('.custom-wishlist-count').text(count);
                            }
                        }).catch(function (err) {
                            console.error("yith-refresh.js: Error parsing JSON:", err);
                        });
                    }
                } catch (e) {
                    console.error("yith-refresh.js: Error in fetch interceptor:", e);
                }
                return response;
            });
        };
    }

    // Fallback: Listen ONLY for legacy jQuery events.
    // Modern REST API updates are handled instantly above, so we don't listen to reload_fragments here to prevent duplicate requests.
    $(document).on('added_to_wishlist removed_from_wishlist', function () {
        updateHeaderCount();
    });

});
