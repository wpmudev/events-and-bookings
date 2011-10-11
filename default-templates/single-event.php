<?php get_header( 'event' ); ?>
<div id="content">
    <div class="padder">
        <div id="eab-page-wrapper">
        
            <?php the_post(); ?>
            
            <div id="single-event">
                <h1><?php the_title(); ?></h1>
                <?php the_content(); ?>
            </div>
        
        </div>
    </div>
</div>
<?php get_sidebar('event'); ?>
<?php get_footer('event'); ?>
