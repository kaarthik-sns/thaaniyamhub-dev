<?php get_header(); ?>
<?php
    if ( have_posts() ) {
     
    while ( have_posts() ) { 
    the_post();
  
    $layout_page=get_field('layouts',get_the_ID());
   
    $ly_cnt=0;
    if(!empty($layout_page)){
       
        foreach($layout_page as $layout){
                                    
            $ly_cnt++;
            global $layout, $layout_id;                                    
            $layout_id=$ly_cnt."-".get_the_ID();
            if(!empty($layout)){  
                get_template_part( 'template/content/content', $layout['layout_type'] );
            }else{
                $layout="";
            }
			
        }
    }
	}
	}
 ?>


<?php get_footer(); ?>