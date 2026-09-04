<?php
global $layout, $layout_id;

$row_index = $layout_id;

$content_layout = $layout['faq'] ?? [];        
$faq_title = $content_layout['faq_main_title'] ?? '';  
$faq_description = $content_layout['faq_main_description'] ?? ''; 
$faq_items = $content_layout['faq_items'] ?? [];  
?>

<section class="faq-section">
    <div class="faq-header text-center">
            <h1 class="faq-title"><?php echo esc_html($faq_title); ?></h1>
            <p class="faq-description"><?php echo esc_html($faq_description); ?></p>
        </div>
    <div class="inner-container">
        <div class="faq-container">

        <!-- Accordion -->
        <div class="accordion" id="faqAccordion-<?php echo $row_index; ?>">
            <?php if(!empty($faq_items)) : ?>
                <?php foreach($faq_items as $i => $item): ?>
                    <?php
                        $collapse_id = 'faq' . $row_index . '-' . ($i+1);
                        $heading_id = 'faqHeading' . $row_index . '-' . ($i+1);
                        $show = ($i === 0) ? 'show' : '';
                        $collapsed = ($i === 0) ? '' : 'collapsed';

                        $question = $item['faq_question'] ?? '';
                        $answer = $item['faq_answer'] ?? '';
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                            <a class="accordion-button <?php echo $collapsed; ?> faq-question"
                                data-bs-toggle="collapse"
                                href="#<?php echo $collapse_id; ?>"
                                role="button"
                                aria-expanded="<?php echo ($i === 0) ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo $collapse_id; ?>">
                                <?php echo esc_html($question); ?>
                            </a>

                        </h2>
                        <div id="<?php echo $collapse_id; ?>" 
                             class="accordion-collapse collapse <?php echo $show; ?>" 
                             aria-labelledby="<?php echo $heading_id; ?>" 
                             data-bs-parent="#faqAccordion-<?php echo $row_index; ?>">
                            <div class="accordion-body faq-answer">
                                <?php echo wp_kses_post($answer); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>

    </div>
</section>
