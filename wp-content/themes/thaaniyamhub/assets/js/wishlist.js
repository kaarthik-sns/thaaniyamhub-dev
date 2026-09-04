jQuery(function($){

    $(document).on('click', '.add-to-wishlist', function(e){
        e.preventDefault();

        var product_id = $(this).data('product-id');

        $.ajax({
            type: 'POST',
            url: tt5_ajax.ajax_url,
            data: {
                action: 'add_to_wishlist',
                product_id: product_id
            },
            success: function(response) {
                if(response.success){
                    $('.wishlist-count').text(response.data.count);
                }
            }
        });
    });

});
