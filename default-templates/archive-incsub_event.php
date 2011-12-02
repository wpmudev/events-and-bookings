<?php
global $booking;
get_header( 'event' );
?>
    <div id="primary">
        <div id="content" role="main">
            <div id="wpmudevevents-wrapper">
                <h2><?php _e('Events', Booking::$_translation_domain); ?></h2>
                <hr/>
                <?php if ( !have_posts() ) : ?>
                    <p><?php $event_ptype = get_post_type_object( 'incsub_event' ); echo $event_ptype->labels->not_found; ?></p>
                <?php else: ?>
                    <div class="wpmudevevents-list">
                    <?php while ( have_posts() ) : the_post(); ?>
                        <div class="event">
                            <!-- div class="event-digest">
                                <?php if (event_booking_count('yes') > 0) { ?>
                                <div class="event-digest-widget event-status-yes">
                                    <div class="booking-count"><?php echo event_booking_count('yes'); ?></div>
                                    <?php _e('attending', Booking::$_translation_domain); ?>
                                </div>
                                <?php } ?>
                                <?php if (event_booking_count('maybe') > 0) { ?>
                                <div class="event-digest-widget event-status-maybe">
                                    <div class="booking-count"><?php echo event_booking_count('maybe'); ?></div>
                                    <?php _e('may be', Booking::$_translation_domain); ?>
                                </div>
                                <?php } ?>
                            </div -->
                            <div class="wpmudevevents-header">
                                <h3><?php the_event_link(); ?></h3>
                                <a href="<?php the_permalink(); ?>" class="wpmudevevents-viewevent"><?php _e('View event', Booking::$_translation_domain); ?></a>
                            </div>
                            <?php
                                event_details(true, true);
                            ?>
                            <?php
                            event_rsvp_form();
                            ?>
                            <hr />
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
