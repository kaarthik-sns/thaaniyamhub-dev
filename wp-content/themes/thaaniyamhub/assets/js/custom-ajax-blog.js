jQuery(document).ready(function($) {
    function loadBlogPosts(page = 1) {
        $.ajax({
            url: ajaxpagination.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'load_more_blogs',
                page: page
            },
            success: function(response) {
                if (response.content) {
                    $('.blog-posts-container').html(response.content);
                }
                if (response.pagination) {
                    $('.pagination-container').html(response.pagination);
                }
            }
        });
    }

    // On page click
    $(document).on('click', '.ajax-page', function(e) {
        e.preventDefault();
        let page = $(this).data('page');
        loadBlogPosts(page);
    });

    // Initial load
    loadBlogPosts(1);
});
