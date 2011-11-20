<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
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
                    
                    <?php
                    $booking_id = get_booking_id($post->ID, $current_user->ID);
                    
                    if ($booking_id && in_array(get_booking_status($booking_id), array('yes', 'maybe')) &&
                              !get_booking_paid($booking_id)) { ?>
                    <div class="event-notice">
                        <b><?php _e('You haven\'t paid for this event.', Booking::$_translation_domain); ?></b>
                        
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                            <input type="hidden" name="business" value="<?php print $booking->_options['default']['paypal_email']; ?>" />
                            <input type="hidden" name="item_name" value="<?php print get_the_title(); ?>" />  
                            <input type="hidden" name="item_number" value="<?php print $post->ID; ?>" />
                            <input type="hidden" name="booking_id" value="<?php print $booking_id; ?>" />
                            <input type="hidden" name="notify_url" value="<?php print admin_url('admin-ajax.php?action=eab_paypal_ipn'); ?>" />
                            <input type="hidden" name="amount" value="<?php print get_post_meta($post->ID, 'incsub_event_fee', true); ?>" />  
                            <input type="hidden" name="return" value="<?php print get_permalink(); ?>" />
                            <input type="hidden" name="currency_code" value="<?php print $booking->_options['default']['currency']; ?>">
                            <input type="hidden" name="cmd" value="_xclick" />
                            <input type="image" name="submit" border="0" src="https://www.paypal.com/en_US/i/btn/btn_paynow_SM.gif" alt="PayPal - The safer, easier way to pay online" />  
                            <img alt="" border="0" width="1" height="1" src="https://www.paypal.com/en_US/i/scr/pixel.gif" />  
                        </form> 
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
