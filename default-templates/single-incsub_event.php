<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
get_header( 'event' );
?>
<div id="primary" class="eab-primary-event">
    <div id="content">
        <div class="padder">
            <div id="wpmudevevents-wrapper">
		<div id="wpmudevents-single">
                
                    <?php
                    the_post();
                    
                    $start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
                    ?>
                
                    <div id="event-bread-crumbs">
                        <?php event_breadcrumbs(); ?>
                    </div>
                    
                    <?php the_eab_error_notice(); ?>
                
                    <div class="wpmudevevents-header">
                        <h1><?php the_title(); ?></h1>
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
			You haven't paid for this event
                        <?php eab_payment_forms(); ?>
		    </div>
                    <?php } ?>
                    
                    <div class="wpmudevevents-content">
			<div id="wpmudevevents-contentheader">
                            <h3>About this event :</h3>
                            
			    <div id="wpmudevevents-user">Created by admin</div>
			</div>
                        
                        <hr />
			<div class="wpmudevevents-contentmeta">
			    <div class="wpmudevevents-date">12th January 2011</div>
			    <div class="wpmudevevents-location">The old barn pub</div>
			    <div class="wpmudevevents-price">£20.00</div>
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
</div>
<?php get_sidebar('event'); ?>
<?php get_footer('event'); ?>
