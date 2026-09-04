<?php
	global $layout, $layout_id;
	$row_index = $layout_id;
	$content_layout=$layout['privacy_policy'];
    $detail_content=$content_layout['detail_content'];
?>

<section class="privacy-sec">
    <div class="inner-container">
        <div class="row">
            <div class="privacy-policy-options">
                <?php 
                if(!empty($detail_content)) {
					$i = "1";
                foreach($detail_content as $detail_content_val) { 
                    $display_option = $detail_content_val['display_option'];
                    $title = $detail_content_val['title'];
                    $subtitle = $detail_content_val['subtitle'];
                    $description = $detail_content_val['description'];
                    $repeater_points = $detail_content_val['repeater_points'];
                    $repeater_question_answer = $detail_content_val['repeater_question_answer'];
                    $bottom_desc = $detail_content_val['bottom_desc'];
                    $bg_color = $detail_content_val['bg_color'];


                ?>
                    <article id="expect-during-privacy" class="service-list-privacy">
                        <div class="inner-container">
                        <div class="row">
                            <div class="privacy-policy-section">
                                <?php if(in_array("title", $display_option) && $title!="" ){ ?>
                                <h3 class="privacy-policy-title header-title<?php echo $i; ?>"><?php echo $title; ?></h3>
                                <?php } ?>
                                <?php if(in_array("subtitle", $display_option) && $subtitle!="" ){ ?>
                                <h6 class="privacy-policy-subtitle"><?php echo $subtitle; ?></h6>
                                <?php } ?>
                                <?php 
                                    if (in_array("description", $display_option) && $description != "") { 
                                    ?>
                                    <div class="privacy-policy-description">
                                        <?php echo wpautop($description); ?>
                                    </div>
                                    <?php 
                                    } 
                                    ?>
                                <?php if (in_array("repeater_points", $display_option)) { ?>
                                    <ul class="privacy-single">
                                        <?php foreach($repeater_points as $vals){ 
                                            $description = $vals['list_desc'];
                                        ?>
                                            <li class="privacy-policy-desc"><?php echo $description; ?></li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?> 
                                
                                <?php if (in_array("repeater_question_ans", $display_option)) { ?>
                                    <div class="privacy-policy-single">
                                    <?php 
                                    foreach($repeater_question_answer as $val) { 
                                        $repeater_heading = $val['heading'];
                                        $description = $val['description'];
                                    ?>
                                        <h6><?php echo $repeater_heading; ?></h6>
                                        <p class="privacy-policy-description"><?php echo $description; ?></p>
                                    <?php 
                                    } 
                                    ?>
                                    </div>
                                <?php } ?>
                                <?php if (in_array("bottom_desc", $display_option) && $bottom_desc!="" ){ ?>
                                        <div class="privacy-policy-description">
                                            <?php echo wpautop($bottom_desc); ?>
                                        </div>
                                <?php } ?>
                
                                </div>
                        </div>
                        </div>
                    </article>
                <?php $i++; } }?>
            </div>

        </div>
    </div>
</section>


