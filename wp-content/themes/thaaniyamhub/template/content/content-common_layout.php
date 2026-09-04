<?php

global $layout, $layout_id;
$row_index = $layout_id;
$content_layout = $layout['common_layout'];

$background_option	=	$content_layout['background_option'];
$background_type	=	$background_option['background_type'];
$background_image 	= 	$background_option['image'];
$background_color 	= 	$background_option['color'];

$section_type		=	$content_layout['section_type'];
$display_option		=	$content_layout['display_option'];
$title				=	$content_layout['title'];
$subtitle     		=	$content_layout['subtitle'];
$description		=	$content_layout['description'];
$image				=	$content_layout['image'];
$video				=	$content_layout['video'];
$common_repeater	=	$content_layout['common_repeater'];
$about_repeater		=	$content_layout['about_repeater'];
$list_itmes      	=	$content_layout['list_itmes'];
$button				=	$content_layout['button'];
$bottom_description	=	$content_layout['bottom_description'];
$media_align	=	$content_layout['media_align'];
$content_column = $content_layout['content_column'];

$video_overlay_title	=	$content_layout['video_overlay_title'];
$video_overlay_subtitle		=	$content_layout['video_overlay_subtitle'];
$video_overlay_media		=	$content_layout['video_overlay_media'];

if ($background_type == "color") {
	$bg = 'background:' . $background_color;
} else {
	$bg = 'background-image:url(' . $background_image . ')';
}

switch ($content_column) {
    case '1_column':
        $content_column = "cols col-12 col-md-1";
        break;

    case '2_column':
        $content_column = "cols col-12 col-md-2";
        break;

    case '3_column':
        $content_column = "cols col-12 col-md-3";
        break;

    case '4_column':
        $content_column = "cols col-12 col-md-4";
        break;

    case '5_column':
        $content_column = "cols col-12 col-md-5";
        break;

    case '6_column':
        $content_column = "cols col-12 col-md-6";
        break;

    case '7_column':
        $content_column = "cols col-12 col-md-7";
        break;

    case '8_column':

        $content_column = "cols col-12 col-md-8";
        break;

    case '9_column':

        $content_column = "cols col-12 col-md-9";
        break;

    case '10_column':

        $content_column = "cols col-12 col-md-10";
        break;

    case '11_column':

        $content_column = "cols col-12 col-md-11";
        break;

    case '12_column':

        $content_column = "cols col-12 col-md-12";
        break;

    default:

        $content_column = "cols col-12 col-md-12 ";
        break;
}

?>


<?php if ($section_type == 'about') { ?>
<section class="curated-millet-sec" style="<?php echo esc_attr($bg); ?>">
    <div class="inner-container">
        <!-- Left Corner Image -->
        <div class="corner-image-left">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/corner-millets.png" decoding="async" class="img-fluid" alt="Left Corner Image">
        </div>
        <div class="row">
            <!-- Left Section with Video -->
            <div class="col-md-7">
                <div class="video-container">
                    <?php if (!empty($video) && isset($video['url'])): ?>
                        <video class="main-video" autoplay loop muted>
                            <source src="<?php echo esc_url($video['url']); ?>" type="video/mp4"/>
                        </video>
                    <?php endif; ?>
                    <div class="video-overlay"></div>
                    <div class="text-overlay">
                        <?php if (!empty($video_overlay_title)): ?>
                            <h2><?php echo esc_html($video_overlay_title); ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($video_overlay_subtitle)): ?>
                            <p><?php echo esc_html($video_overlay_subtitle); ?></p>
                        <?php endif; ?>
                    </div>                    
                    <div class="video-overlay-media">
                        <img src="<?php echo $video_overlay_media['url']; ?>" decoding="async" class="img-fluid" alt="<?php echo $video_overlay_media['title']; ?>"/>
                    </div>
                </div>
            </div>
            <!-- Right Section with Info -->
            <div class="col-md-5">
                <div class="video-curated-millet-info">
                    <?php if (!empty($title)): ?>
                        <h3><?php echo esc_html($title); ?></h3>
                    <?php endif; ?>

                    <?php if (!empty($description)): ?>
                        <?php echo $description; ?>
                    <?php endif; ?>

                    <?php if (in_array('about_repeater', $display_option) && is_array($about_repeater) && !empty($about_repeater)): ?>
                        <ul>
                            <?php foreach ($about_repeater as $item): ?>
                                <li> <img src="<?php echo $item['image']['url']; ?>" class="img-fluid" decoding="async" alt="<?php echo $item['image']['title']; ?>"/><?php echo $item['description'] ; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($button['url']) && !empty($button['title'])): ?>
                        <a href="<?php echo esc_url($button['url']); ?>" class="btn-secondary">
                            <?php echo esc_html($button['title']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div> 
        <!-- Right Corner Image -->
        <div class="corner-image-right">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/freshly-harvested-millet.png" decoding="async" class="img-fluid" alt="Right Corner Image">
        </div>
    </div>
</section>
<?php } ?>

<?php if ($section_type == 'millets_fit') { ?>
    <section class="millets-fit-section" style="<?php echo esc_attr($bg); ?>">
        <div class="inner-container">
            <div class="millets-fit-grid">

            <!-- LEFT CONTENT -->
            <div class="millets-fit-content">
                <?php if (!empty($title)): ?>
                    <h2><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if (!empty($description)): ?>
                    <?php echo $description; ?>
                <?php endif; ?>                
                <?php if (!empty($button['url']) && !empty($button['title'])): ?>
                    <a href="<?php echo esc_url($button['url']); ?>" class="millets-fit-btn">  <?php echo esc_html($button['title']); ?> </a>
                <?php endif; ?>
            </div>

            <!-- IMAGE PANELS -->
            <div class="millets-fit-images">
                <img src="<?php echo $image['url']; ?>" decoding="async" class="img-fluid" alt="<?php echo $image['title']; ?>">
            </div>

            </div>
        </div>
    </section>
<?php } ?>
<?php if ($section_type == 'partner_brands') { ?>
<section class="partner-brands">
    <div class="inner-container">
        <div class="row partner-flex">
            <div class="col-md-5">
                <div class="partner-left">
                    <?php if (!empty($title)): ?>
                        <h4><?php echo esc_html($title); ?></h4>
                    <?php endif; ?>
                    <?php if (!empty($description)): ?>
                        <?php echo $description; ?>
                    <?php endif; ?>                
                    <?php if (!empty($button['url']) && !empty($button['title'])): ?>
                        <a href="<?php echo esc_url($button['url']); ?>" class="btn-primary">  <?php echo esc_html($button['title']); ?> </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-7">
                <div class="partner-slider">
                    <?php if (in_array('about_repeater', $display_option) && is_array($about_repeater) && !empty($about_repeater)): ?>
                        <?php foreach ($about_repeater as $item): ?>
                            <div>
                                <img src="<?php echo $item['image']['url']; ?>" decoding="async" class="img-fluid" alt="<?php echo $item['image']['title']; ?>">
                            </div> 
                        <?php endforeach; ?>
                    <?php endif; ?>      
                </div>
            </div>
        </div>
    </div>
</section>
<?php } ?>