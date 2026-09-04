<?php
global $layout, $layout_id;
$row_index      = $layout_id;
$content_layout = $layout['quotes_layout'] ?? [];
$image       = $content_layout['image'] ?? '';
$title       = $content_layout['title'] ?? '';
?>

<?php
?>
<section class="quotes-section">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6 order-2 order-md-1">
                <?php if($image): ?>
                    <img src="<?php echo $image['url'] ; ?>" alt="<?php echo esc_attr($title); ?>"  class="img-fluid" >
                <?php endif; ?>
            </div>
            <div class="col-md-6 order-1 order-md-2">
                    <?php if($title): ?>
                        <h2 class="quote">
                            <?php echo esc_html($title); ?>
                        </h2>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>