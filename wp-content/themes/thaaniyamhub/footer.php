<footer class="main-footer">
  <div class="inner-container">
    <div class="row footer-inner">

      <div class="col-list footer-logo-mdia">
        <div class="footer-logo">
          <?php
          $footer_logo_url = get_theme_mod('footer_logo');
          if (empty($footer_logo_url)) {
              $footer_logo_url = get_stylesheet_directory_uri() . '/assets/images/thaaniyam-logo.png';
          }
          ?>
          <img src="<?php echo esc_url($footer_logo_url); ?>" alt="<?php bloginfo('name'); ?>">
        </div>
        <div class="footer-copyright">
          <p><?php echo get_theme_mod('copyright_text'); ?></p>
        </div>
      </div>

      <div class="col-list footer-menu-list">
        <div class="d-block footer-contact-link">
          <a class="con-heading con-loc-heading">Shop</a>
        </div>
        <ul>
          <?php
          wp_nav_menu(
            array(
              'container' => 'ul',
              'items_wrap' => '%3$s',
              'theme_location' => 'first-footer-menu'

            )
          );
          ?>
        </ul>
      </div>

      <div class="col-list footer-menu-list">
        <div class="d-block footer-contact-link">
          <a class="con-heading con-loc-heading">Learn</a>
        </div>
        <ul>
          <?php
          wp_nav_menu(
            array(
              'container' => 'ul',
              'items_wrap' => '%3$s',
              'theme_location' => 'second-footer-menu'

            )
          );
          ?>
        </ul>
      </div>

      <!-- <div class="col-list footer-menu-list">
                <div class="d-block footer-contact-link">
                    <a class="con-heading con-loc-heading">Company</a>
                </div>
                <ul>
                    <?php
                    wp_nav_menu(
                      array(
                        'container' => 'ul',
                        'items_wrap' => '%3$s',
                        'theme_location' => 'third-footer-menu'

                      )
                    );
                    ?>
                </ul>
            </div> -->

      <div class="col-list footer-menu-list">
        <div class="d-block footer-contact-link">
          <a class="con-heading con-loc-heading">Support</a>
        </div>
        <ul>
          <?php
          wp_nav_menu(
            array(
              'container' => 'ul',
              'items_wrap' => '%3$s',
              'theme_location' => 'fourth-footer-menu'

            )
          );
          ?>
        </ul>
      </div>

      <div class="col-list footer-menu-list">
        <div class="d-block footer-contact-link">
          <a class="con-heading con-loc-heading">Legal</a>
        </div>
        <ul>
          <?php
          wp_nav_menu(
            array(
              'container' => 'ul',
              'items_wrap' => '%3$s',
              'theme_location' => 'fifth-footer-menu'

            )
          );
          ?>
        </ul>
      </div>

		<div class="col-list footer-menu-list footer-menu-last">
			<div class="d-block footer-contact-link">
				<a class="con-heading con-loc-heading">Connect With Us</a>
			</div>

			<ul>
				<?php if(get_theme_mod('social_link_1')) : ?>
					<li>
						<a href="<?php echo esc_url(get_theme_mod('social_link_1')); ?>" target="_blank">
							<i class="fa-brands fa-facebook"></i> Facebook
						</a>
					</li>
				<?php endif; ?>

				<?php if(get_theme_mod('social_link_3')) : ?>
					<li>
						<a href="<?php echo esc_url(get_theme_mod('social_link_3')); ?>" target="_blank">
							<i class="fa-brands fa-instagram"></i> Instagram
						</a>
					</li>
				<?php endif; ?>
				
				<?php if(get_theme_mod('social_link_2')) : ?>
					<li>
						<a href="<?php echo esc_url(get_theme_mod('social_link_2')); ?>" target="_blank">
							<i class="fa-brands fa-x-twitter"></i> Twitter
						</a>
					</li>
				<?php endif; ?>

				<?php if(get_theme_mod('social_link_4')) : ?>
					<li>
						<a href="<?php echo esc_url(get_theme_mod('social_link_4')); ?>" target="_blank">
							<i class="fa-brands fa-linkedin"></i> LinkedIn
						</a>
					</li>
				<?php endif; ?>

				<?php if(get_theme_mod('social_link_5')) : ?>
					<li>
						<a href="<?php echo esc_url(get_theme_mod('social_link_5')); ?>" target="_blank">
							<i class="fa-brands fa-google"></i> Google Business
						</a>
					</li>
				<?php endif; ?>
				
				<?php if(get_theme_mod('social_link_6')) : ?>
					<li>
						<a href="<?php echo esc_url(get_theme_mod('social_link_6')); ?>" target="_blank">
							<i class="fa-brands fa-whatsapp"></i> WhatsApp
						</a>
					</li>
				<?php endif; ?>
			</ul>
		</div>	

    </div>
  </div>
</footer>
<!-- jQuery library -->
<?php wp_footer(); ?>
<script>
  jQuery(document).ready(function ($) {
    // Toggle submenu on dropdown icon click
    $('.dropdown-toggle-icon').on('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var parentLi = $(this).closest('li');

      if (parentLi.hasClass('menu-open')) {
        parentLi.removeClass('menu-open').find('li').removeClass('menu-open');
        parentLi.find('.sub-menu, .sub-sub-menu').slideUp();
      } else {
        parentLi.siblings().removeClass('menu-open').find('.sub-menu, .sub-sub-menu').slideUp();
        parentLi.siblings().find('li').removeClass('menu-open');
        parentLi.addClass('menu-open').children('.sub-menu, .sub-sub-menu').slideDown();
      }
    });
    $(document).on('click', function (e) {
      if (!$(e.target).closest('.navbar-nav').length) {
        $('.menu-item.menu-open').removeClass('menu-open');
        $('.sub-menu, .sub-sub-menu').slideUp();
      }
    });
  });
</script>

<script>
  jQuery(document).ready(function ($) {
    var header = $(".top-header");
    var headerOffset = header.offset().top;

    $(window).scroll(function () {
      if ($(window).scrollTop() > headerOffset) {
        header.addClass("fixed");
      } else {
        header.removeClass("fixed");
      }
    });
  });
</script>

<!-- Slick Slider JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"></script>
<script>
  jQuery(document).ready(function ($) {
    $('.collection-slider').slick({
      infinite: false,
      slidesToShow: 5,
      slidesToScroll: 1,
      autoplay: false,
      arrows: true,
      prevArrow: '<button type="button" class="slick-prev"><i class="fas fa-chevron-left"></i></button>',
      nextArrow: '<button type="button" class="slick-next"><i class="fas fa-chevron-right"></i></button>',
      responsive: [
        {
          breakpoint: 1440,
          settings: { slidesToShow: 4 }
        },
        {
          breakpoint: 768,
          settings: { slidesToShow: 2 }
        },
        {
          breakpoint: 480,
          settings: { slidesToShow: 1 }
        }
      ]
    });
  });
</script>

<script>
  jQuery(document).ready(function ($) {
    $('.frequent-order-products').slick({
      slidesToShow: 4,
      slidesToScroll: 1,
      infinite: false,
      arrows: true,
      dots: false,

      prevArrow: `
        <button class="slick-prev custom-arrow">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
      `,
      nextArrow: `
        <button class="slick-next custom-arrow">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      `,
      responsive: [
        { breakpoint: 1024, settings: { slidesToShow: 4 } },
        { breakpoint: 768, settings: { slidesToShow: 2 } },
        { breakpoint: 480, settings: { slidesToShow: 1 } }
      ]
    });
  });
</script>


<script>
  jQuery(document).ready(function ($) {
    $('.millet-type').slick({
      slidesToShow: 5,
      slidesToScroll: 1,
      infinite: false,
      dots: false,

      prevArrow: `
        <button class="slick-prev custom-arrow">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
      `,
      nextArrow: `
        <button class="slick-next custom-arrow">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      `,

      responsive: [
        {
          breakpoint: 1024,
          settings: { slidesToShow: 4 }
        },
        {
          breakpoint: 768,
          settings: { slidesToShow: 2 }
        },
        {
          breakpoint: 480,
          settings: { slidesToShow: 1 }
        }
      ]
    });
  });
</script>
<script>
  jQuery(document).ready(function ($) {
    $('.combo-wrapper').slick({
      slidesToShow: 4,
      slidesToScroll: 1,
      arrows: true,
      dots: false,
      infinite: false,
      speed: 500,
      prevArrow: '<button class="slick-prev custom-arrow"><i class="fa-solid fa-chevron-left"></i></button>',
      nextArrow: '<button class="slick-next custom-arrow"><i class="fa-solid fa-chevron-right"></i></button>',
      responsive: [
        { breakpoint: 1200, settings: { slidesToShow: 3 } },
        { breakpoint: 992, settings: { slidesToShow: 3 } },
        { breakpoint: 576, settings: { slidesToShow: 1 } }
      ]
    });
  });
</script>

<script>
  jQuery(document).ready(function ($) {
    $('.partner-slider').slick({
      slidesToShow: 4,
      slidesToScroll: 1,
      autoplay: true,
      autoplaySpeed: 2000,
      arrows: true,
      prevArrow: `
          <button class="slick-prev custom-arrow">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
        `,
      nextArrow: `
          <button class="slick-next custom-arrow">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        `,
      dots: false,
      infinite: true,
      responsive: [
        {
          breakpoint: 1500,
          settings: {
            slidesToShow: 3
          }
        },
        {
          breakpoint: 1000,
          settings: {
            slidesToShow: 2
          }
        },

        {
          breakpoint: 576,
          settings: {
            slidesToShow: 2
          }
        }
      ]
    });
  });
</script>

<script>
  jQuery(function ($) {

    // After product added to cart
    $(document.body).on('added_to_cart', function (e, fragments, cart_hash, button) {

      // Do nothing on single product page
      if ($('body').hasClass('single-product')) return;

      // Do not hide the custom hover add to cart icon
      if ($(button).hasClass('custom_hover_add_to_cart')) return;

      // Hide Add to Cart
      $(button).hide();

      // Show View Cart
      $(button).siblings('.added_to_cart').show();
    });

  });
</script>
<script>
  jQuery(function ($) {

    setTimeout(function () {
      $('.woocommerce-notices-wrapper').fadeOut(400);
    }, 3000); // 2.5 seconds

  });
</script>
<script>
  jQuery(function ($) {

    // This event fires when WooCommerce adds a product
    $(document.body).on('added_to_cart', function () {
      console.log('Product added to cart (event fired)');
    });

  });
</script>


</body>

</html>