<?php
global $layout, $layout_id;
$row_index      = $layout_id;
$content_layout = $layout['contact_layout'];
$description = $content_layout['description'];
$title = $content_layout['title'];
$support_title = $content_layout['support_title'];
$support_mail = $content_layout['support_mail'];
$phone_description = $content_layout['phone_description'];
$email_description = $content_layout['email_description'];
$address_description = $content_layout['address_description'];
$short_code		=	$content_layout['short_code'];

?>

<section class="contact-page-sec">
  <div class="inner-container">
    <div class="row">
      <div class="contact-info">        
        <div class="info-item">
          <div class="icon"><img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/baseline-phone.png" alt="phone" class="img-fluid"/></div>
			<a href="tel:<?php echo  get_theme_mod( 'phone_number' );?>"><?php echo  get_theme_mod( 'phone_number' );?></a>
            <p><?php echo $phone_description; ?></p>
        </div>
        <div class="info-item">
          <div class="icon"><img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/symbols_mail.png" alt="email" class="img-fluid"/></div>
			  <a href="mailto:<?php echo  get_theme_mod( 'email_address' );?>"><?php echo  get_theme_mod( 'email_address' );?></a>
			  <p><?php echo $email_description; ?></p>
        </div>
        <div class="info-item">
			<div class="icon"><img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/location.png" alt="Address" class="img-fluid"/></div>
			<span><?php echo  get_theme_mod( 'address' );?></span>
			  <p><?php echo $address_description; ?></p>
        </div>
      </div>
    </div>

    <div class="row contact-content">
      <div class="col-md-5">
        <div class="info-block">
          <h3><?php echo $title; ?></h3>
          <p><?php echo $description; ?></p>
		  <p><?php echo $support_title; ?></p>
		   <div class="info-item supoort">
			  <a href="mailto:<?php echo  get_theme_mod( 'support_email' );?>"><?php echo  get_theme_mod( 'support_email' );?></a>
			</div>
        </div>
        
      </div>
      <div class="col-md-7">
        <div class="contact-form">
          <?php echo do_shortcode($short_code); ?>
        </div>
      </div>
    </div>
  </div>
</section>
