<?php
global $layout, $layout_id;
$row_index      = $layout_id;
$content_layout  = $layout['vision_mission_layout'] ?? [];
$layout_blocks   = $content_layout['vision_mission_blocks'] ?? [];

if ( !empty($layout_blocks) && count($layout_blocks) === 2 ): ?>
<section class="vision-mission-section">
    <div class="container-fluid p-0">
        <div class="row m-0 no-gutters">

            <?php foreach ( $layout_blocks as $block ):

                $title       = $block['title'] ?? '';
                $description = $block['description'] ?? '';
                $bg_color    = $block['background_color'] ?? '';
            ?>
            <div class="col-md-6 p-0"  style="background-color: <?php echo esc_attr($bg_color); ?>;"> 
                    <div class="vision-mission-container">
                            <?php if ($title ): ?>
                                <h2 class="about-main-title vision-mission-title"><?php echo esc_html($title); ?></h2>
                            <?php endif; ?>

                            <?php if ($description ): ?>
                                <p class="about-para vision-mission-para"> <?php echo wp_kses_post($description); ?></p>
                            <?php endif; ?>
                    </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


