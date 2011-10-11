<?php get_header( 'event' ); ?>

<div id="content">
    <div class="padder">
        <div id="eab-page-wrapper">
            <h2><?php _e('Events', 'eab'); ?></h2>
            
            <?php if ( !have_posts() ) : ?>
            <p><?php $event_ptype = get_post_type_object( 'incsub_event' ); echo $event_ptype->labels->not_found; ?></p>
            <?php else: ?>
            <div id="event-list">
            <?php while ( have_posts() ) : the_post(); ?>
                    <div class="event">
                            <div class="event-summary">
                                    <h3><?php the_event_link(); ?></h3>
                            </div>
                    </div>
            <?php endwhile; ?>
            </div>
            
            <?php the_event_pagination(); ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<?php get_sidebar( 'event' ); ?>

<?php get_footer( 'event' ); ?>
