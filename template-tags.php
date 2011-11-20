<?php

function the_event_pagination( $query = null ) {
    if ( is_null( $query ) )
	$query = $GLOBALS['wp_query'];
    
    if ( $query->max_num_pages <= 1 )
	return;
    
    $current_page = max( 1, $query->get( 'paged' ) );
    $total_pages = $query->max_num_pages;
    
    $padding = 2;
    $range_start = max( 1, $current_page - $padding );
    $range_finish = min( $total_pages, $current_page + $padding );

    echo '<div class="event-pagination">';

    if ( $current_page > 1 )
	_event_single_page_link( $query, $current_page - 1, __( 'prev', 'eab' ), 'prev' );

    if ( $range_start > 1 )
	_event_single_page_link( $query, 1 );

    if ( $range_start > $padding )
	echo '<span class="dots">...</span>';

    foreach ( range( $range_start, $range_finish ) as $num ) {
	if ( $num == $current_page )
	    echo _event_html( 'span', array( 'class' => 'current' ), number_format_i18n( $num ) );
	else
	    _event_single_page_link( $query, $num );
    }

    if ( $range_finish + $padding <= $total_pages )
	echo '<span class="dots">...</span>';

    if ( $range_finish < $total_pages )
	_event_single_page_link( $query, $total_pages );

    if ( $current_page < $total_pages )
	_event_single_page_link( $query, $current_page + 1, __( 'next', 'eab' ), 'next' );

    echo '</div>';
}

function the_event_link( $question_id = 0 ) {
    if ( !$event_id )
    	$event_id = get_the_ID();

    echo get_event_link( $event_id );
}

function get_event_link( $question_id ) {
	return _event_html( 'a', array( 'class' => 'question-link', 'href' => event_get_url( 'single', $question_id ) ), get_the_title( $question_id ) );
}

function _event_html( $tag ) {
    $args = func_get_args();

    $tag = array_shift( $args );

    if ( is_array( $args[0] ) ) {
    	$closing = $tag;
    	$attributes = array_shift( $args );
    	foreach ( $attributes as $key => $value ) {
    		$tag .= ' ' . $key . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
    	}
    } else {
    	list( $closing ) = explode( ' ', $tag, 2 );
    }

    if ( in_array( $closing, array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta' ) ) ) {
    	return "<{$tag} />";
    }

    $content = implode( '', $args );

    return "<{$tag}>{$content}</{$closing}>";
}

function event_get_url( $type, $id = 0 ) {
    $base = get_post_type_archive_link( 'event' );

    switch ( $type ) {
	case 'single':
	    $result = get_permalink( $id );
	    break;
	case 'archive':
	    $result = get_post_type_archive_link( 'event' );
	    break;
	default:
	    return '';
    }

    return apply_filters( 'qa_get_url', $result, $type, $id );
}

function _event_single_page_link( $query, $num, $title = '', $class = '' ) {
    if ( !$title )
	$title = number_format_i18n( $num );

    $args = array( 'href' => get_pagenum_link( $num ) );

    if ( $class )
	$args['class'] = $class;

    echo _event_html( 'a', $args, $title );
}

function event_rsvp_form() {
    global $post, $wpdb, $current_user;
    ?>
    <style type="text/css">
        input.current { font-weight: bold; }
    </style>
    <div class="eab_booking_form">
        <?php
        if (accepting_bookings()) {
            if (is_user_logged_in()) {
                $booking = intval($wpdb->get_var($wpdb->prepare("SELECT id FROM ".Booking::tablename('bookings')." WHERE event_id = %d AND user_id = %d;", $post->ID, $current_user->ID)));
                $booking_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM ".Booking::tablename('bookings')." WHERE event_id = %d AND user_id = %d;", $post->ID, $current_user->ID));
            ?>
                <form action="" method="post" id="eab_booking_form">
                    <input type="hidden" name="event_id" value="<?php print $post->ID; ?>" />
                    <input type="hidden" name="user_id" value="<?php print $booking; ?>" />
                    <input class="<?php echo ($booking && $booking_status == 'yes')?'current':''; ?>" type="submit" name="action_yes" value="<?php _e('I\'m attending', Booking::$_translation_domain); ?>" />
                    <input class="<?php echo ($booking && $booking_status == 'maybe')?'current':''; ?>" type="submit" name="action_maybe" value="<?php _e('May be', Booking::$_translation_domain); ?>" />
                    <input class="<?php echo ($booking && $booking_status == 'no')?'current':''; ?>" type="submit" name="action_no" value="<?php _e('No', Booking::$_translation_domain); ?>" />
                </form>
            <?php
            } else {
            ?>
                <a href="<?php print wp_login_url(get_permalink()); ?>" ><?php _e('I\'m Attending', Booking::$_translation_domain); ?></a>
                <a href="<?php print wp_login_url(get_permalink()); ?>" ><?php _e('Maybe', Booking::$_translation_domain); ?></a>
                <a href="<?php print wp_login_url(get_permalink()); ?>" ><?php _e('No', Booking::$_translation_domain); ?></a>
            <?php
            }
        }
        ?>
    </div>
<?php
}

function the_eab_error_notice() {
    if ( !isset( $_GET['eab_msg'] ) )
	return;
?>
    <div id="eab-error-notice">
	<?php _e($_GET['eab_msg'], Booking::$_translation_domain); ?>
    </div>
<?php
}

function has_bookings($coming = true) {
    global $wpdb, $post;
    
    if ($coming) {
        $bookings = $wpdb->get_results($wpdb->prepare("SELECT id FROM ".Booking::tablename('bookings')." WHERE event_id = %d AND status != 'no';", $post->ID));
    } else {
        $bookings = $wpdb->get_results($wpdb->prepare("SELECT id FROM ".Booking::tablename('bookings')." WHERE event_id = %d;", $post->ID));
    }
    
    return ($bookings);
}

function event_bookings($status = 'yes', $echo = true, $admin = false) {
    global $wpdb, $post, $eab_user_logins;
    $statuses = array('yes' => 'Attending', 'maybe' => 'May be', 'no' => 'No');
    
    $status_name = $statuses[$status];
    
    $content = '';
    
    $bookings = $wpdb->get_results($wpdb->prepare("SELECT user_id FROM ".Booking::tablename('bookings')." WHERE event_id = %d AND status = %s;", $post->ID, $status));
    
    if (count($bookings) > 0) {
        $content .= '<h4>'. __($status_name, Booking::$_translation_domain). '</h4>';
        $content .= '<ul class="eab-guest-list">';
	$eab_user_logins[$status] = array();
        foreach ($bookings as $booking) {
            $user_data = get_userdata( $booking->user_id );
	    $eab_user_logins[$status][] = $user_data->user_login;
            
            $content .= '<li>';
            if ($admin) {
                $content .= '<span>';
		$content .= '<a href="user-edit.php?user_id='.$booking->user_id .'" title="'.$user_data->display_name.'">';
                $content .= $user_data->display_name;
                $content .= '</a>';
                $content .= '</span>';
            } else {
                $content .= '<a href="'.get_author_posts_url( $booking->user_id ).'" title="'.$user_data->display_name.'">';
                $content .= get_avatar( $booking->user_id, 32 );
                $content .= '</a>';
            }
            $content .= '</li>';
        }
        $content .= '</ul>';
	$content .= '<div class="clear"></div>';
    }
    
    if ($echo) {
        echo $content;
    }
    
    return $content;
}

function accepting_bookings() {
    global $post;
    
    if (get_post_meta($post->ID, 'incsub_event_status', true) == 'open') {
        return true;
    }
    return false;
}

function event_details($echo = true, $archive = false) {
    global $post, $event_variation;
    
    if (!$archive) {
        $content .= '<h3>' . __('Event Details', Booking::$_translation_domain) . '</h3>';
    }
    $content .= '<ul>';
    
    $meta = get_post_custom($post->ID);
    
    if (!isset($event_variation) || !isset($event_variation[$post->ID])) {
	$event_variation[$post->ID] = 0;
    }
    if (date_i18n(get_option('date_format'), strtotime($meta['incsub_event_start'][$event_variation[$post->ID]])) ==
        date_i18n(get_option('date_format'), strtotime($meta['incsub_event_end'][$event_variation[$post->ID]]))) {
        $end_date = '';
    } else {
        $end_date = date_i18n(get_option('date_format'), strtotime($meta['incsub_event_end'][$event_variation[$post->ID]])) . ' ';
    }
    
    $content .= '<li><b>' . __('Time', Booking::$_translation_domain) . '</b>: ' . __('On', Booking::$_translation_domain) . ' ' . date_i18n(get_option('date_format'), strtotime($meta['incsub_event_start'][$event_variation[$post->ID]])) . ' ' . __('from', Booking::$_translation_domain) . ' ' . date_i18n(get_option('time_format'), strtotime($meta['incsub_event_start'][$event_variation[$post->ID]])) . ' ' . __('to', Booking::$_translation_domain) . ' ' . $end_date . date_i18n(get_option('time_format'), strtotime($meta['incsub_event_end'][$event_variation[$post->ID]])) . '</li>';
    $content .= '<li><b>' . __('Location', Booking::$_translation_domain) . '</b>: ' . get_post_meta($post->ID, 'incsub_event_venue', true) . '</li>';
    $content .= '<li><b>' . __('Created By', Booking::$_translation_domain) . '</b>: <a href="'.get_the_author_link().'" title="'.get_the_author().'">' . get_the_author() . '</a></li>';
    $content .= '</ul>';
    
    if ($echo) {
        echo $content;
    }
    
    return $content;
}

function event_booking_count($status) {
    global $wpdb, $post;
    
    $booking_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(user_id) FROM ".Booking::tablename('bookings')." WHERE event_id = %d AND status = %s;", $post->ID, $status));

    return $booking_count;
}

function event_link($page) {
    global $blog_id, $booking;
    switch ($page) {
	case 'calendar':
	    return apply_filters('event_link', get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y/m').'/'), $page);
	case 'event':
	    return apply_filters('event_link', get_site_url($blog_id, $booking->_options['default']['slug'].'/'), $page);
	default:
	    if (isset($_COOKIE['eab_default_view']) && $_COOKIE['eab_default_view'] == 'calendar') {
		return event_link('calendar');
	    } else {
		return event_link('event');
	    }
    }
}
