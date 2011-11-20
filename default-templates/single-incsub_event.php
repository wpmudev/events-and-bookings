<?php
global $blog_id, $wp_query, $booking;
get_header( 'event' );
?>
<div id="primary" class="eab-primary-event">
    <div id="content">
        <div class="padder">
            <div id="eab-page-wrapper">
                
                <?php
                the_eab_error_notice();
                the_post();
                
                $start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
                ?>
                
                <div id="event-bread-crumbs">
                    <a href="<?php echo event_link('event_or_calendar'); ?>" class="parent"><?php _e("Events", Booking::$_translation_domain); ?></a> &gt;
                    <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y', strtotime("{$wp_query->query_vars['event_year']}-01-01")).'/'); ?>" class="parent"><?php echo date_i18n('Y', strtotime("{$wp_query->query_vars['event_year']}-01-01")); ?></a> &gt;
                    <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y/m', strtotime("{$wp_query->query_vars['event_year']}-{$wp_query->query_vars['event_monthnum']}-01")).'/'); ?>" class="parent"><?php echo date_i18n('F', strtotime("{$wp_query->query_vars['event_year']}-{$wp_query->query_vars['event_monthnum']}-01")); ?></a> &gt;
                    <span class="current"><?php the_title(); ?></span>
                </div>
                
                <div id="single-event">
                    <h1><?php the_title(); ?></h1>
                    
                    <div id="event-rsvp">
                        <?php if (!has_bookings()) {?>
                        <div id="event-first-booking">
                            <?php _e("Be the first to RSVP", Booking::$_translation_domain); ?>
                        </div>
                        <?php } ?>
                        <?php event_rsvp_form(); ?>
                    </div>
                    
                    <div id="event-details">
                        <?php event_details(); ?>
                    </div>
                    
                    <?php the_content(); ?>
                    
                    <?php comments_template( '', true ); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php get_sidebar('event'); ?>
<?php get_footer('event'); ?>
