(function($) {
    'use strict';

    $(document).ready(function() {
        // Intercept clicks on the small hover icon button
        $(document).on('click', '.hover-icons .custom_hover_add_to_cart', function(e) {
            var $button = $(this);
            
            // Check if it's already loading
            if ($button.hasClass('loading')) {
                return;
            }

            var href = $button.attr('href');
            var productId = getUrlParameter(href, 'add-to-cart');
            
            if (!productId) {
                productId = $button.data('product_id');
            }

            if (productId) {
                e.preventDefault();
                addToCartAjax($button, productId, 1, 'icon');
            }
        });

        // Intercept submits on the large bottom button form
        $(document).on('submit', '.shop-add-cart-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $button = $form.find('.shop-add-cart-btn');

            // Check if it's already loading
            if ($button.hasClass('loading')) {
                return;
            }

            var productId = $form.find('input[name="add-to-cart"]').val();
            if (productId) {
                addToCartAjax($button, productId, 1, 'button');
            }
        });

        // Helper to extract query parameters
        function getUrlParameter(url, name) {
            if (!url) return null;
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(url);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        var addToCartQueue = [];
        var isProcessingQueue = false;

        function processNextInQueue() {
            if (isProcessingQueue || addToCartQueue.length === 0) {
                return;
            }

            isProcessingQueue = true;
            var item = addToCartQueue.shift();

            executeAddToCartAjax(item.$button, item.productId, item.quantity, item.type, item.originalHtml, item.originalIconClass, function() {
                isProcessingQueue = false;
                processNextInQueue();
            });
        }

        // AJAX Add to Cart function (Queued wrapper)
        function addToCartAjax($button, productId, quantity, type) {
            if ($button.hasClass('loading')) {
                return;
            }

            // Capture original HTML and icon BEFORE modifying the DOM
            var originalHtml = $button.data('original-html');
            if (!originalHtml) {
                originalHtml = $button.html();
                $button.data('original-html', originalHtml);
            }

            var $icon = $button.find('i');
            var originalIconClass = $button.data('original-icon-class');
            if (!originalIconClass && $icon.length) {
                originalIconClass = $icon.attr('class');
                $button.data('original-icon-class', originalIconClass);
            }

            $button.addClass('loading');

            // Apply loading state UI immediately on click
            if (type === 'icon' && $icon.length) {
                $icon.attr('class', 'fas fa-spinner fa-spin');
            } else if (type === 'button') {
                $button.html('<i class="fas fa-spinner fa-spin"></i> Adding...');
                $button.prop('disabled', true);
            }

            addToCartQueue.push({
                $button: $button,
                productId: productId,
                quantity: quantity,
                type: type,
                originalHtml: originalHtml,
                originalIconClass: originalIconClass || ''
            });

            processNextInQueue();
        }

        // Execute single AJAX request
        function executeAddToCartAjax($button, productId, quantity, type, originalHtml, originalIconClass, onComplete) {
            var $icon = $button.find('i');

            var ajaxUrl = '';
            if (typeof custom_ajax_cart_params !== 'undefined' && custom_ajax_cart_params.ajax_url) {
                ajaxUrl = custom_ajax_cart_params.ajax_url;
            } else if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
                ajaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart');
            } else {
                ajaxUrl = '/?wc-ajax=add_to_cart';
            }

            var data = {
                'product_id': productId,
                'quantity': quantity
            };

            // Trigger event before adding to cart (some plugins/themes listen to this)
            $(document.body).trigger('adding_to_cart', [$button, data]);

            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: data,
                dataType: 'json',
                success: function(response) {
                    $button.removeClass('loading');

                    if (!response) {
                        // Reset if no response
                        resetButton($button, originalHtml, $icon, originalIconClass, type);
                        if (typeof onComplete === 'function') onComplete();
                        return;
                    }

                    if (response.error && response.product_url) {
                        // Redirect to product page if there's an error (e.g. variable product, out of stock)
                        window.location.href = response.product_url;
                        if (typeof onComplete === 'function') onComplete();
                        return;
                    }

                    // Apply success state UI
                    if (type === 'icon' && $icon.length) {
                        $icon.attr('class', 'fas fa-check');
                        $button.addClass('added-success');
                    } else if (type === 'button') {
                        $button.html('<i class="fas fa-check"></i> Added!');
                    }

                    // Track added product ID globally
                    if (!window.thaaniyamCartProductIds) {
                        window.thaaniyamCartProductIds = [];
                    }
                    var pIdNum = parseInt(productId, 10);
                    if (!isNaN(pIdNum) && window.thaaniyamCartProductIds.indexOf(pIdNum) === -1) {
                        window.thaaniyamCartProductIds.push(pIdNum);
                    }

                    // Trigger WooCommerce events so that fragments update and FunnelKit opens
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);

                    // Reset button state after a delay
                    setTimeout(function() {
                        resetButton($button, originalHtml, $icon, originalIconClass, type);
                    }, 2000);

                    if (typeof onComplete === 'function') onComplete();
                },
                error: function(xhr, status, error) {
                    $button.removeClass('loading');
                    resetButton($button, originalHtml, $icon, originalIconClass, type);
                    console.error('AJAX Add to Cart failed:', error);
                    if (typeof onComplete === 'function') onComplete();
                }
            });
        }

        // Helper to reset button state
        function resetButton($button, originalHtml, $icon, originalIconClass, type) {
            if (type === 'icon' && $icon.length) {
                $icon.attr('class', originalIconClass || 'fas fa-cart-plus');
                $button.removeClass('added-success');
            } else if (type === 'button') {
                $button.html(originalHtml || 'Add to Cart');
                $button.prop('disabled', false);
            }
            $button.removeClass('loading');
            $button.removeData('original-html');
            $button.removeData('original-icon-class');
        }

        // Helper to read cookies
        function getCookie(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        // Function to update "View cart" buttons based on actual cart items
        function updateViewCartLinks() {
            var productIdsInCart = [];

            // 1. Check markers generated by server hook in side cart
            $('.fkcart-item-product-id-marker').each(function() {
                var pid = parseInt($(this).attr('data-product_id'), 10);
                if (!isNaN(pid) && productIdsInCart.indexOf(pid) === -1) {
                    productIdsInCart.push(pid);
                }
            });

            // 2. Gather product IDs from mini-cart/drawer elements & data attributes
            $('#fkcart-modal .fkcart-remove-item, #fkcart-modal a.remove, .woocommerce-mini-cart a.remove, #fkcart-modal .fkcart--item, #fkcart-modal .fkcart-select-options').each(function() {
                var pid = $(this).attr('data-product_id') || $(this).attr('data-id') || $(this).attr('data-product') || $(this).attr('data-variation');
                if (pid) {
                    var parsedId = parseInt(pid, 10);
                    if (!isNaN(parsedId) && productIdsInCart.indexOf(parsedId) === -1) {
                        productIdsInCart.push(parsedId);
                    }
                }
            });

            // 3. Match product link hrefs between side cart items and product cards on the page
            $('#fkcart-modal .fkcart--item a[href]').each(function() {
                var href = $(this).attr('href');
                if (href && href.indexOf('/product/') !== -1) {
                    $('.custom-product-card, li.product, .product-card-inner').each(function() {
                        var $card = $(this);
                        if ($card.find('a[href="' + href + '"]').length > 0) {
                            var pid = $card.find('input[name="add-to-cart"]').val();
                            if (pid) {
                                var parsedId = parseInt(pid, 10);
                                if (!isNaN(parsedId) && productIdsInCart.indexOf(parsedId) === -1) {
                                    productIdsInCart.push(parsedId);
                                }
                            }
                        }
                    });
                }
            });

            // 4. Combine with locally tracked added IDs
            if (window.thaaniyamCartProductIds && Array.isArray(window.thaaniyamCartProductIds)) {
                window.thaaniyamCartProductIds.forEach(function(pid) {
                    if (productIdsInCart.indexOf(pid) === -1) {
                        productIdsInCart.push(pid);
                    }
                });
            }

            // 5. Fallback check for overall cart quantity
            var totalQty = 0;
            if (typeof fkcart_app_data !== 'undefined' && fkcart_app_data.cookie_names) {
                var qtyCookie = getCookie(fkcart_app_data.cookie_names.quantity);
                if (qtyCookie) {
                    totalQty = parseInt(qtyCookie, 10);
                }
            } else {
                var countText = $('.fkcart-item-count').first().text();
                if (countText) {
                    totalQty = parseInt(countText, 10);
                }
            }

            // If overall quantity is 0, cart is empty
            if (isNaN(totalQty) || totalQty === 0) {
                productIdsInCart = [];
                window.thaaniyamCartProductIds = [];
            }

            // 6. Scan each product card and update buttons based on presence in cart
            $('.custom-product-card, li.product, .product-card-inner').each(function() {
                var $card = $(this);
                var productId = $card.find('input[name="add-to-cart"]').val();
                
                if (!productId) {
                    var href = $card.find('.add_to_cart_button, .custom_hover_add_to_cart').attr('href');
                    productId = getUrlParameter(href, 'add-to-cart');
                }

                if (productId) {
                    var parsedProductId = parseInt(productId, 10);
                    if (!isNaN(parsedProductId)) {
                        var $addBtn = $card.find('.add_to_cart_button, .shop-add-cart-btn');

                        if (productIdsInCart.indexOf(parsedProductId) === -1) {
                            // Product is NOT in the cart: restore buttons, remove View Cart
                            $card.find('.added_to_cart').remove();
                            $addBtn.removeClass('added');
                            $addBtn.show();
                            $addBtn.css('display', '');
                        } else {
                            // Product IS in the cart: hide original buttons, ensure View Cart exists
                            $addBtn.addClass('added');
                            $addBtn.hide();

                            var cartUrl = (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url) ? wc_add_to_cart_params.cart_url : '/cart/';
                            var cartText = (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.i18n_view_cart) ? wc_add_to_cart_params.i18n_view_cart : 'View cart';

                            $addBtn.each(function() {
                                var $btn = $(this);
                                if ($btn.parent().find('.added_to_cart').length === 0) {
                                    $btn.after('<a href="' + cartUrl + '" class="added_to_cart wc-forward" title="' + cartText + '">' + cartText + '</a>');
                                }
                            });
                        }
                    }
                }
            });
        }

        // Run when page loads to clear any stale View Cart links
        setTimeout(updateViewCartLinks, 500);

        // Run when items are added, removed, or fragments are updated
        $(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded fkcart_fragments_refreshed', function() {
            setTimeout(updateViewCartLinks, 200);
        });

        // Handle post-logout cache cleanup to fix guest cart count & items mismatch
        function handleLogoutCartCleanup() {
            var logoutCookie = getCookie('thaaniyam_just_logged_out');
            var isGuest = !document.body.classList.contains('logged-in');

            if (logoutCookie || isGuest) {
                var currentQty = getCookie('fkcart_cart_qty') || getCookie('fkcart_qty');
                var isCartEmpty = !currentQty || currentQty === '0' || isNaN(parseInt(currentQty, 10));

                if (logoutCookie || isCartEmpty) {
                    try {
                        if (typeof sessionStorage !== 'undefined') {
                            for (var i = sessionStorage.length - 1; i >= 0; i--) {
                                var key = sessionStorage.key(i);
                                if (key && (key.indexOf('fkcart_') === 0 || key.indexOf('wc_') === 0 || key.indexOf('woocommerce_') === 0)) {
                                    sessionStorage.removeItem(key);
                                }
                            }
                        }
                        if (typeof localStorage !== 'undefined') {
                            for (var j = localStorage.length - 1; j >= 0; j--) {
                                var lkey = localStorage.key(j);
                                if (lkey && (lkey.indexOf('fkcart_') === 0 || lkey.indexOf('wc_') === 0 || lkey.indexOf('woocommerce_') === 0)) {
                                    localStorage.removeItem(lkey);
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Error clearing cart storage:', e);
                    }

                    if (logoutCookie) {
                        var cookiesToDestroy = [
                            'fkcart_cart_qty', 'fkcart_cart_total', 'fkcart_qty', 'fkcart_total',
                            'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'fkcart_cart_hash'
                        ];
                        cookiesToDestroy.forEach(function(cookieName) {
                            document.cookie = cookieName + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                            document.cookie = cookieName + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + window.location.hostname + ';';
                        });

                        // Clear logout signal cookie
                        document.cookie = 'thaaniyam_just_logged_out=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    }

                    if (isCartEmpty) {
                        // Reset badge counts & hide drawer item contents
                        $('.fkcart-item-count, .cart-icon .badge, .cart-click span.count').html('0').attr('data-item-count', '0');
                        $('#fkcart-floating-toggler').css({visibility: 'hidden'});
                        $('.fkcart-modal-container').removeClass('fkcart-has-items');
                        $('#fkcart-modal .fkcart--item').remove();

                        // Reset cart item markers
                        window.thaaniyamCartProductIds = [];
                    }
                }
            }
        }

        // Execute cleanup immediately
        handleLogoutCartCleanup();

        // Also intercept cart icon clicks for guests to ensure fresh state with zero items
        $(document).on('click', '.cart-icon a, #fkcart-floating-toggler, .cart-click, .fkcart-mini-open', function() {
            handleLogoutCartCleanup();
        });
    });
})(jQuery);


