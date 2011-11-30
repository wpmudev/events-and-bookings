<?php
global $booking;
get_header( 'event' );
?>
    <div id="content">
        <div class="padder">
            <div id="eab-archive-wrapper">
                <div id="eab-archive-view">
                    <?php _e("View", Booking::$_translation_domain); ?>:
                    <?php if (is_date()) { ?>
                    <a href="<?php echo event_link('event');?>" ><?php _e("Agenda", Booking::$_translation_domain); ?></a> |
                    <span class="current"><?php _e("Calendar", Booking::$_translation_domain); ?></span>
                    <?php } else { ?>
                    <span class="current"><?php _e("Agenda", Booking::$_translation_domain); ?></span> |
                    <a href="<?php echo event_link('calendar');?>" ><?php _e("Calendar", Booking::$_translation_domain); ?></a>
                    <?php } ?>
                </div>
                <?php
                if (is_year()) {
                    global $post, $wpdb, $wp_query, $event_variation;
                    
                    $end = date('Y-m-d',strtotime('-1 second',strtotime('+1 year',strtotime($wp_query->query_vars['year'].'-01-01'))));
                    $querystr = "SELECT $wpdb->posts.* FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id 
                    AND $wpdb->postmeta.meta_key = 'incsub_event_start' 
                    AND DATE($wpdb->postmeta.meta_value) >= DATE('{$wp_query->query_vars['year']}-01-01')
                    AND DATE($wpdb->postmeta.meta_value) <= DATE('{$end}')
                    AND $wpdb->posts.post_status = 'publish' 
                    AND $wpdb->posts.post_type = 'incsub_event'
                    AND $wpdb->posts.post_date < NOW()
                    ORDER BY $wpdb->postmeta.meta_value ASC";
                    
                    $pageposts = $wpdb->get_results($querystr, OBJECT);
                    $event_variation = array();
                ?>
                    <div id="event-bread-crumbs">
                        <a href="<?php echo event_link('event_or_calendar'); ?>" class="parent"><?php _e("Events", Booking::$_translation_domain); ?></a> &gt;
                        <span class="current"><?php echo date_i18n('Y', strtotime($wp_query->query_vars['year'].'-01-01')); ?></span>
                    </div>
                    <h2><?php printf(__('Events in %s', 'eab'), date_i18n('Y', strtotime($wp_query->query_vars['year'].'-01-01'))); ?></h2>
                    
                    <?php if ( is_array($pageposts) && count($pageposts) > 0 ) : ?>
                    <div id="event-list">
                    <?php
                        $month = "";
                        foreach ($pageposts as $post) {
                            setup_postdata($post);
                            if (isset($event_variation[$post->ID])) {
                                $event_variation[$post->ID]++;
                            } else {
                                $event_variation[$post->ID] = 0;
                            }
                            $meta = get_post_meta($post->ID, 'incsub_event_start', false);
                            $new_month = date_i18n('F', strtotime($meta[$event_variation[$post->ID]]));
                            if ($month != $new_month) {
                                $month = $new_month;
                                ?>
                                <h3><?php echo $month; ?></h3>
                                <?php
                            }
                    ?>
                        <div class="event">
                            <div class="event-digest">
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
                            </div>
                            <div class="event-summary">
                                <h4><?php the_event_link(); ?></h4>
                                <?php
                                event_details(true, true);
                                ?>
                            </div>
                        </div>
                    <?php
                        }
                    ?>
                    </div>
                    <?php else: ?>
                    <p><?php $event_ptype = get_post_type_object( 'incsub_event' ); echo $event_ptype->labels->not_found; ?></p>
                    <?php endif; ?>
                    <div class="event-pagination">
                        <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.(date('Y', strtotime($wp_query->query_vars['year'].'-01-01'))-1).'/'); ?>"><?php _e( 'prev', 'eab' ); ?></a>
                        <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.(date('Y', strtotime($wp_query->query_vars['year'].'-01-01'))+1).'/'); ?>"><?php _e( 'next', 'eab' ); ?></a>
                    </div>
                <?php
                } else if (is_month()) {
                    global $post, $wpdb, $wp_query, $event_variation;
                    
                    $end = date('Y-m-d',strtotime('-1 second',strtotime('+1 month',strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))));
                    $querystr = "SELECT $wpdb->posts.* FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id 
                    AND $wpdb->postmeta.meta_key = 'incsub_event_start' 
                    AND DATE($wpdb->postmeta.meta_value) >= DATE('{$wp_query->query_vars['year']}-{$wp_query->query_vars['monthnum']}-01')
                    AND DATE($wpdb->postmeta.meta_value) <= DATE('{$end}')
                    AND $wpdb->posts.post_status = 'publish' 
                    AND $wpdb->posts.post_type = 'incsub_event'
                    AND $wpdb->posts.post_date < NOW()
                    ORDER BY $wpdb->postmeta.meta_value ASC";
                    
                    $pageposts = $wpdb->get_results($querystr, OBJECT);
                    $event_variation = array();
                ?>
                    <div id="event-bread-crumbs">
                        <a href="<?php echo event_link('event_or_calendar'); ?>" class="parent"><?php _e("Events", Booking::$_translation_domain); ?></a> &gt;
                        <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01')).'/'); ?>" class="parent"><?php echo date_i18n('Y', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01')); ?></a> &gt;
                        <span class="current"><?php echo date_i18n('F', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01')); ?></span>
                    </div>
                    <h2><?php printf(__('Events in %s, %s', 'eab'), date_i18n('F', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01')), date_i18n('Y', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))); ?></h2>
                    <?php if ( is_array($pageposts) && count($pageposts) > 0 ) : ?>
                    <div id="eab-calendar-month">
                        <?php
                        $week_start = get_option('start_of_week');
                        for ($c=$week_start; $c<$week_start+7; $c++) { ?>
                        <div class="eab-header eab-header-<?php echo $c; ?>"><?php echo date_i18n('D', 1319328000+($c*3600*24)); ?></div>
                        <?php } ?>
                        <div class="clear"></div>
                        <div class="eab-premonth" >
                    <?php
                        $day = 0;
                        foreach ($pageposts as $post) {
                            setup_postdata($post);
                            if (isset($event_variation[$post->ID])) {
                                $event_variation[$post->ID]++;
                            } else {
                                $event_variation[$post->ID] = 0;
                            }
                            $meta = get_post_meta($post->ID, 'incsub_event_start', false);
                            $new_day = date_i18n('d', strtotime($meta[$event_variation[$post->ID]]));
                            
                            if ($new_day != $day) {
                                if (($new_day-$day) > 1) {
                                    for ($c=$day+1; $c<$new_day; $c++) {
                                        ?>
                                        </div>
                                        <div class="eab-day" id="eab-day-<?php echo intval($c); ?>">
                                            <div class="eab-day-text"><?php echo intval($c); ?></div>
                                        <?php
                                    }
                                }
                                
                                $day = $new_day;
                            ?>
                                </div>
                                <div class="eab-day" id="eab-day-<?php echo $day; ?>">
                                    <div class="eab-day-text"><?php echo $day; ?></div>
                            <?php
                            }
                    ?>
                        <div class="eab-calendar-event">
                            <div class="eab-event-summary">
                                <?php the_event_link(); ?>
                            </div>
                        </div>
                    <?php
                        }
                        
                        $new_day = date('d',strtotime('-1 second',strtotime('+1 month',strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))));
                        if ($new_day != $day) {
                            if (($new_day-$day) > 1) {
                                for ($c=$day+1; $c<$new_day; $c++) {
                                ?>
                                    </div>
                                    <div class="eab-day" id="eab-day-<?php echo $c; ?>">
                                        <div class="eab-day-text"><?php echo $c; ?></div>
                                <?php
                                }
                            }
                                
                            $day = $new_day;
                            ?>
                            </div>
                            <div class="eab-day" id="eab-day-<?php echo $day; ?>">
                                <div class="eab-day-text"><?php echo $c; ?></div>
                            <?php
                        }
                    ?>
                        </div>
                        <div class="eab-postmonth">
                        </div>
                        <div class="clear"></div>
                    </div>
                    <style type="text/css">
                        div.eab-premonth {
                            width: <?php echo 12*(date('w', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))-$week_start); ?>%;
                            padding: 0 <?php echo 3*(date('w', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))-$week_start); ?>px;
                            margin: 0 <?php echo 2*(date('w', strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))-$week_start); ?>px;
                        }
                    </style>
                    <?php else: ?>
                    <p><?php $event_ptype = get_post_type_object( 'incsub_event' ); echo $event_ptype->labels->not_found; ?></p>
                    <?php endif; ?>
                    
                    <div class="event-pagination">
                        <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y/m', (strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01')-10)).'/'); ?>"><?php _e( 'prev', 'eab' ); ?></a>
                        <a href="<?php echo get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y/m', (strtotime('+1 month',strtotime($wp_query->query_vars['year'].'-'.$wp_query->query_vars['monthnum'].'-01'))+10)).'/'); ?>"><?php _e( 'next', 'eab' ); ?></a>
                    </div>
                <?php
                } else {
                ?>
                    <h2><?php _e('Events', 'eab'); ?></h2>
                    
                    <?php if ( !have_posts() ) : ?>
                    <p><?php $event_ptype = get_post_type_object( 'incsub_event' ); echo $event_ptype->labels->not_found; ?></p>
                    <?php else: ?>
                    <div id="event-list">
                    <?php while ( have_posts() ) : the_post(); ?>
                        <div class="event">
                            <div class="event-digest">
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
                            </div>
                            <div class="event-summary">
                                <h3><?php the_event_link(); ?></h3>
                                <?php
                                event_details(true, true);
                                ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                    <?php the_event_pagination(); ?>
                    <?php endif; ?>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
<?php get_sidebar( 'event' ); ?>
<?php get_footer( 'event' ); ?>
