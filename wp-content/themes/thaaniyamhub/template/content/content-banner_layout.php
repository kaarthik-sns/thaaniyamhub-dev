<?php
	global $layout, $layout_id;
	$row_index = $layout_id;
	$content_layout=$layout['banner_layout'];
    $slider_repeater=$content_layout['slider_repeater'];
	$background_option=$content_layout['background_option'];
	$background_type=$background_option['background_type'];
	$background_image = $background_option['image'];
	$background_color = $background_option['color'];
	$background_video = $background_option['video'];	
	$title=$content_layout['title'];
	$sub_title=$content_layout['sub_title'];
	$description=$content_layout['description'];
	$page_type=$content_layout['page_type'];
	$image=$content_layout['image'];
	 if($background_type == "color" ) { 
		 $bg = 'background:'.$background_color;
	 }else if($background_type == "video" ) {
		$bg =  $background_video['url'];
	 }
	 else {
		$bg = 'background-image:url('.$background_image.')';
	 }
	$button=$content_layout['button'];

     
 ?>
 
<?php if($page_type == 'home') { ?>
    <?php if (!empty($slider_repeater) && is_array($slider_repeater)) { ?>
    <section class="main-banner-sec" id="banner-<?php echo $row_index; ?>">
        <div class="inner-container">

            <?php $slide_count = count($slider_repeater); ?>

            <div id="carouselExampleIndicators-<?php echo $row_index; ?>"
                class="carousel slide"
                data-bs-ride="carousel"
                data-bs-interval="5000">

                <!-- SLIDES -->
                <div class="carousel-inner">
                    <?php
                    $firstIteration = true;
                    foreach ($slider_repeater as $val) {

                    if (!is_array($val)) continue;

                    $title        = $val['title'] ?? '';
                    $description  = $val['description'] ?? '';
                    $button       = $val['button'] ?? [];
                    $slider_image = $val['image'] ?? [];
                    $bg_image     = $val['bg'] ?? [];
                    ?>
                    <div class="carousel-item <?php echo $firstIteration ? 'active' : ''; ?>"
                        style="background-image: url('<?php echo esc_url($bg_image['url'] ?? ''); ?>');">

                        <div class="row banner-slide align-items-center">

                        <div class="col-md-7 banner-height">
                            <div class="carousel-content">
                            <h1><?php echo $title; ?></h1>
                            <p><?php echo $description; ?></p>

                            <?php if (!empty($button['url'])) { ?>
                                <div class="carousel-button">
                                <a href="<?php echo esc_url($button['url']); ?>" class="banner-btn"> <?php echo esc_html($button['title'] ?? ''); ?> </a>
                                </div>
                            <?php } ?>
                            </div>
                        </div>

                        <div class="col-md-5 p-0 banner-height">
                            <div class="carousel-media">
                                <?php if (!empty($slider_image['url'])) { ?>
                                <img class="slider-img" src="<?php echo esc_url($slider_image['url']); ?>" alt="<?php echo esc_attr($slider_image['title'] ?? ''); ?>">
                                <?php } ?>
                            </div>
                        </div>

                        </div>
                    </div>
                    <?php
                    $firstIteration = false;
                    }
                    ?>
                </div>

                <!-- DYNAMIC DOTS -->
                <?php if ($slide_count > 1) { ?>
                    <div class="carousel-indicators">
                        <?php for ($i = 0; $i < $slide_count; $i++) { ?>
                        <button
                            type="button"
                            data-bs-target="#carouselExampleIndicators-<?php echo $row_index; ?>"
                            data-bs-slide-to="<?php echo $i; ?>"
                            class="<?php echo ($i === 0) ? 'active' : ''; ?>"
                            aria-current="<?php echo ($i === 0) ? 'true' : 'false'; ?>"
                            aria-label="Slide <?php echo $i + 1; ?>">
                        </button>
                        <?php } ?>
                    </div>
                <?php } ?>

                <!-- MOBILE ARROWS -->
<a class="carousel-control-prev mobile-arrow"
   data-bs-target="#carouselExampleIndicators-<?php echo $row_index; ?>"
   data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
</a>

<a class="carousel-control-next mobile-arrow"
   data-bs-target="#carouselExampleIndicators-<?php echo $row_index; ?>"
   data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
</a>

            </div>
        </div>
    </section>
    <?php } ?>
<?php } ?>

<?php if($page_type == 'inner') { ?>
  <section class="inner-banner-sec"style="<?php echo $bg;?>">
    <div class="inner-container">
        <div class="row">
            <div class="col-md-12">
                <div class="inner-banner-content">
                    <h1><?php echo $title; ?></h1>
                    <p><?php echo $sub_title; ?></p>
                    <p><?php echo $description; ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php } ?>
<?php if($page_type == 'common_banner') { ?>
<section class="common-banner-sec" style="<?php echo $bg;?>">
    <div class="inner-container">
        <div class="row">
            <div class="col-md-12">
                <div class="common-banner-content">
                    <h1 class="about-banner-main-title"><?php echo $title; ?></h1>
                    <p><?php echo $sub_title; ?></p>
                    <p><?php echo $description; ?></p>
                </div>
            </div>            
        </div>
    </div>
</section>
<?php } ?>

