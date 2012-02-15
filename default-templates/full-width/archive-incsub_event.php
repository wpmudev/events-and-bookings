<?php
global $booking, $wpdb, $wp_query;
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
                    <?php
                        $end = date('Y-m-d',strtotime('-1 second',strtotime('+1 year',strtotime($wp_query->query_vars['year'].'-01-01'))));
                        $args = array_merge($wp_query->query, array('suppress_filters' => false, 'meta_key' => 'incsub_event_start'));
                        query_posts( $args );
                    ?>
                    <?php while ( have_posts() ) : the_post(); ?>
                        <div class="event">
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
<?php get_footer( 'event' ); ?>
