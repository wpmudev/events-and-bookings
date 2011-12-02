<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
get_header( 'event' );
?>
<div id="primary" class="eab-primary-event">
    <div id="content" role="main">
            <div id="wpmudevevents-wrapper">
		<div id="wpmudevents-single">
                
                    <?php
                    the_post();
                    
                    $start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
                    ?>
                    
                    <div class="wpmudevevents-header">
                        <h2><?php the_title(); ?></h2>
                        <?php
                        event_rsvp_form();
                        ?>
                    </div>
                    
                    <hr />
                    <?php
                    $booking_id = get_booking_id($post->ID, $current_user->ID);
                    
                    if ($booking_id && in_array(get_booking_status($booking_id), array('yes', 'maybe')) &&
                        get_post_meta($post->ID, 'incsub_event_paid', true) && !get_booking_paid($booking_id)) { ?>
		    <div id="wpmudevevents-payment">
			<?php _e('You haven\'t paid for this event', Booking::$_translation_domain); ?>
                        <?php eab_payment_forms(); ?>
		    </div>
                    <?php } ?>
                    
                    <?php the_eab_error_notice(); ?>
                    
                    <div class="wpmudevevents-content">
			<div id="wpmudevevents-contentheader">
                            <h3><?php _e('About this event:', Booking::$_translation_domain); ?></h3>
                            
			    <div id="wpmudevevents-user">Created by <a href="<?php the_author_link(); ?>" title="<?php the_author(); ?>"><?php the_author(); ?></a></div>
			</div>
                        
                        <hr />
			<div class="wpmudevevents-contentmeta">
                            <?php event_details(); ?>
			</div>
			<div id="wpmudevevents-contentbody">
			    <?php the_content(); ?>
                        </div>
                        <?php comments_template( '', true ); ?>
                    </div>
                </div>
        </div>
    </div>
</div>
<?php get_footer('event'); ?>
