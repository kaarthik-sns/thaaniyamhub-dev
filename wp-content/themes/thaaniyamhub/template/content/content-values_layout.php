<?php
global $layout, $layout_id;
$row_index      = $layout_id;
$content_layout = $layout['values_layout'] ?? [];

$section_title       = $content_layout['section_title'] ?? '';
$section_description = $content_layout['section_description'] ?? '';
$values_items        = $content_layout['values_items'] ?? [];
$section_image       = $content_layout['section_image'] ?? '';
?>

<section class="values-section">
    <div class="inner-container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <?php if ( $section_title ): ?>
                    <h2 class="about-main-title">
                        <?php echo esc_html( $section_title ); ?>
                    </h2>
                <?php endif; ?>
                <?php if ( $section_description ): ?>
                    <p class="about-para values-main-desc">
                        <?php echo esc_html( $section_description ); ?>
                    </p>
                <?php endif; ?>
                <?php if ( ! empty($values_items) ): ?>
                    <div class="row values-list">
                        <?php foreach ( $values_items as $item ): 
                            $icon = $item['icon'] ?? '';
                        ?>
                            <div class="col-md-6 values-item">
                                <div class="values-container">
                                    <?php if ( $icon ): ?>
                                        <img src="<?php echo esc_url($icon['url']); ?>" alt="<?php echo esc_attr($title); ?>" class="img-fluid values-icon">
                                    <?php endif; ?>

                                    <div class="values-text">
                                        <?php if ( !empty($item['title']) ): ?>
                                            <h4 class="values-title"><?php echo esc_html($item['title']); ?></h4>
                                        <?php endif; ?>

                                        <?php if ( !empty($item['description']) ): ?>
                                            <p class="about-para"><?php echo esc_html($item['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="values-image-col">
                    <?php if ( $section_image ): ?>
                        <img src="<?php echo esc_url($section_image['url']); ?>" alt="<?php echo esc_attr($title); ?>" class="values-image img-fluid">
                    <?php endif; ?>
                 </div>
            </div>

        </div>
    </div>
</section>
