<?php
global $layout, $layout_id;
$row_index      = $layout_id;
$content_layout = $layout['about_layout'] ?? [];

$title       = $content_layout['title'] ?? '';
$description = $content_layout['description'] ?? '';
$image       = $content_layout['image'] ?? '';
?>

<section class="about-thaniyam-story">
    <div class="inner-container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <?php if($title): ?>
                    <h2 class="about-main-title"><?php echo $title; ?></h2>
                <?php endif; ?>
                
                <?php if($description): ?>
                    <?php 
                        // Generate paragraphs
                        $desc_paragraphs = wpautop($description);
                        
                        // Add class to every <p>
                        $desc_paragraphs = str_replace('<p>', '<p class="about-para">', $desc_paragraphs);

                        echo $desc_paragraphs;
                    ?>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if($image): 
                    // Check if ACF image is array or URL
                    $img_url = is_array($image) ? esc_url($image['url']) : esc_url($image);
                ?>
                    <img src="<?php echo $img_url; ?>" alt="<?php echo esc_attr($title); ?>" class="img-fluid">
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

