<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
get_header( 'event' );
?>
<div id="primary" class="eab-primary-event">
    <div id="content">
        <div class="padder">
            <div id="eab-page-wrapper">
                
                <?php
                the_post();
                
                $start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
                ?>
                
                <div id="event-bread-crumbs">
                    <?php event_breadcrumbs(); ?>
                </div>
                
                <div id="single-event">
                    <h1><?php the_title(); ?></h1>
                    
                    <div id="event-rsvp">
                        <?php the_eab_error_notice(); ?>
                        <?php if (!has_bookings()) {?>
                        <div id="event-first-booking">
                            <?php _e("Be the first to RSVP", Booking::$_translation_domain); ?>
                        </div>
                        <?php } ?>
                        
                        <?php
                        event_rsvp_form();
                        ?>
                    </div>
                    
                    <?php
                    $booking_id = get_booking_id($post->ID, $current_user->ID);
                    
                    if ($booking_id && in_array(get_booking_status($booking_id), array('yes', 'maybe')) &&
                        get_post_meta($post->ID, 'incsub_event_paid', true) && !get_booking_paid($booking_id)) { ?>
                    <div class="event-notice">
                        <b><?php _e('You haven\'t paid for this event.', Booking::$_translation_domain); ?></b>
                        
                        <?php eab_payment_forms(); ?>
                        
                    </div>
                    <?php } ?>
                    
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
