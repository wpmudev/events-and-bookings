<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
get_header();
?>
       
        
<?php
	the_post();
	$start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
?>
	<div id="primary">
		<div id="content" role="main">
			
<div id="wpmudevevents-wrapper">
	<div id="wpmudevents-single">
		<div class="wpmudevevents-header">
			<h2><?php the_title(); ?></h2><br />
			<div class="wpmudevevents-contentmeta" style="clear:both">
				<?php event_details(); ?>
			</div>
		</div>
		<?php the_eab_error_notice(); ?>
		<div id="wpmudevevents-left">	
			<div id="wpmudevevents-tickets" class="wpmudevevents-box">
				<?php
                    	$booking_id = get_booking_id($post->ID, $current_user->ID);
                    
                    	if (
                    		$booking_id && 
                    		in_array(get_booking_status($booking_id), array('yes', 'maybe')) &&
                        	get_post_meta($post->ID, 'incsub_event_paid', true) && 
                        	!get_booking_paid($booking_id)) { 
                    ?>
					<div id="wpmudevevents-payment">
						<a href="" id="wpmudevevents-notpaid-submit">You haven't paid for this event</a>
					</div>
					<?php eab_payment_forms(); ?>
					<?php } ?>
				<!--
				<div class="wpmudevevents-boxheader">
					<h3>Tickets :</h3>
				</div>
				<div class="wpmudevevents-boxinner">
					<!--
					<table class="wpmudevevents-tickets">
						<tr>
							<th>Ticket Type</th>
							<th>Price</th>
							<th>Quantity</th>
						</tr>
						<tr>
							<td><?php echo (get_post_meta($post->ID, 'incsub_event_paid', true) ? __('Premium', Booking::$_translation_domain) : __('Free', Booking::$_translation_domain));?></td>
							<td><?php
								echo $booking->_options['default']['currency'] . ' ' . number_format((float)get_post_meta($post->ID, 'incsub_event_fee', true), 2)
							?></td>
							<td>Picker?</td>
						</tr>
					</table>
					<div class="wpmudevevents-buttons">
						<a href="" class="wpmudevevents-book">Book</a>
					</div>
					<?php
                    	$booking_id = get_booking_id($post->ID, $current_user->ID);
                    
                    	if (
                    		$booking_id && 
                    		in_array(get_booking_status($booking_id), array('yes', 'maybe')) &&
                        	get_post_meta($post->ID, 'incsub_event_paid', true) && 
                        	!get_booking_paid($booking_id)) { 
                    ?>
					<div id="wpmudevevents-payment">
						<a href="" id="wpmudevevents-notpaid-submit">You haven't paid for this event</a>
					</div>
					<?php eab_payment_forms(); ?>
					<?php } ?>
				</div>
				-->
			</div>
			<div id="wpmudevevents-content" class="wpmudevevents-box">
				<div class="wpmudevevents-boxheader">
					<h3>About this event :</h3>
				</div>
					<div class="wpmudevevents-boxinner">
					<?php 
						add_filter('agm_google_maps-options', 'eab_autoshow_map_off', 99);
						the_content();
						remove_filter('agm_google_maps-options', 'eab_autoshow_map_off');
					?>
					</div>
					<div><?php event_display_rsvps_inline(); ?></div>
			</div>
		</div>
		<div id="wpmudevevents-right">
			<div id="wpmudevevents-attending" class="wpmudevevents-box">
				<?php event_rsvp_form(); ?>
			</div>
			<?php if (event_has_map(get_the_ID())) { ?>
			<div id="wpmudevevents-googlemap" class="wpmudevevents-box">
				<div class="wpmudevevents-boxheader">
					<h3>Google Map</h3>
				</div>
					<div class="wpmudevevents-boxinner">
					<?php echo get_event_venue_map(get_the_ID(), array('width'=>'99%')); ?>
					</div>
			</div>
			<?php } ?>
			<div id="wpmudevevents-host" class="wpmudevevents-box">
				<div class="wpmudevevents-boxheader">
				<h3>Your host : <?php the_author_meta('display_name'); ?></h3>
				</div>
					<div class="wpmudevevents-boxinner">
					<p>
						<?php the_author_meta('description'); ?>
					</p>
					</div>
			</div>
		</div>
	</div>
</div>

<div style="clear:both"><?php comments_template( '', true ); ?></div>

		</div>
	</div>
        
        
<?php get_footer('event'); ?>