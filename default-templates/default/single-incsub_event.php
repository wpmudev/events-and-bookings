<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
get_header( );
?>
	<div id="primary">
		<div id="content" role="main">
            <div id="wpmudevevents-wrapper">
		<div id="wpmudevents-single">
                
                    <?php
                    the_post();
                    
                    $start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
                    ?>
                    
                    <div class="wpmudevevents-header">
                        <h2><?php the_title(); ?></h2>
                        <div class="eab-needtomove"><div id="event-bread-crumbs" ><?php event_breadcrumbs(); ?></div></div>
                        <?php
                        event_rsvp_form();
						event_display_rsvps_inline();
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
                            
			    <div id="wpmudevevents-user"><?php _e('Created by ', Booking::$_translation_domain); ?><?php the_author_link();?></div>
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