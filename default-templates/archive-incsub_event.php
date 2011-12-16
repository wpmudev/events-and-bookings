<?php
global $booking, $wpdb, $wp_query;
get_header( 'event' );
?>
            <div id="wpmudevevents-wrapper">
                <h2><?php _e('Events', Booking::$_translation_domain); ?></h2>
                <hr/>
                <?php if ( !have_posts() ) : ?>
                    <p><?php $event_ptype = get_post_type_object( 'incsub_event' ); echo $event_ptype->labels->not_found; ?></p>
                <?php else: ?>
                    <div class="wpmudevevents-list">
                    <?php
                        $end = date('Y-m-d',strtotime('-1 second',strtotime('+1 year',strtotime($wp_query->query_vars['year'].'-01-01'))));
                        /*$querystr = "SELECT $wpdb->posts.* FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id 
                        AND $wpdb->postmeta.meta_key = 'incsub_event_start' 
                        AND DATE($wpdb->postmeta.meta_value) >= DATE('{$wp_query->query_vars['year']}-01-01')
                        AND DATE($wpdb->postmeta.meta_value) <= DATE('{$end}')
                        AND $wpdb->posts.post_status = 'publish' 
                        AND $wpdb->posts.post_type = 'incsub_event'
                        AND $wpdb->posts.post_date < NOW()
                        ORDER BY $wpdb->postmeta.meta_value ASC";
                        
                        $pageposts = $wpdb->get_results($querystr, OBJECT);*/
                        
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
<?php get_sidebar( 'event' ); ?>
<?php get_footer( 'event' ); ?>
