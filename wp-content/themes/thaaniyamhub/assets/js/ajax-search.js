jQuery(document).ready(function($) {
    function performSearch() {
        var searchTerm = $('#blog-search').val();
        if (searchTerm.length > 1) {
            $.ajax({
                url: ajax_object.ajax_url, // Updated from 'ajaxurl' to 'ajax_object.ajax_url'
                type: 'POST',
                data: {
                    action: 'ajax_blog_search',
                    search: searchTerm
                },
                success: function(response) {
                    $('#search-results').html(response).fadeIn();
                }
            });
        } else {
            $('#search-results').fadeOut();
        }
    }

    // Search when typing
    $('#blog-search').on('keyup', function() {
        performSearch();
    });

    // Search when clicking the button
    $('.search-container button').on('click', function(e) {
        e.preventDefault(); // Prevent any form submission
        performSearch();
    });

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-container').length) {
            $('#search-results').fadeOut();
        }
    });
});
