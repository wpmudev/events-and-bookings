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

function event_rsvp_form($echo = true) {
    global $post, $wpdb, $current_user;
    
    $content = '';
    $content .= '<div class="eab_booking_form">';    
    
    if (accepting_bookings()) {
        if (is_user_logged_in()) {
            $booking_id = get_booking_id($post->ID, $current_user->ID);
            $booking_status = get_booking_status($booking_id);
            $content .= '<form action="" method="post" id="eab_booking_form">';
            $content .= '<input type="hidden" name="event_id" value="'.$post->ID.'" />';
            $content .= '<input type="hidden" name="user_id" value="'.$booking_id.'" />';
            $content .= '<input class="'.(($booking_id && $booking_status == 'yes')?'current':'').'" type="submit" name="action_yes" value="'.__('I\'m attending', Booking::$_translation_domain).'" />';
            $content .= '<input class="'.(($booking_id && $booking_status == 'maybe')?'current':'').'" type="submit" name="action_maybe" value="'.__('Maybe', Booking::$_translation_domain).'" />';
            $content .= '<input class="'.(($booking_id && $booking_status == 'no')?'current':'').'" type="submit" name="action_no" value="'.__('No', Booking::$_translation_domain).'" />';
            $content .= '</form>';
        } else {
	    $content .= '<a href="'.wp_login_url(get_permalink()).'" >'.__('I\'m Attending', Booking::$_translation_domain).'</a>';
            $content .= '<a href="'.wp_login_url(get_permalink()).'" >'.__('Maybe', Booking::$_translation_domain).'</a>';
            $content .= '<a href="'.wp_login_url(get_permalink()).'" >'.__('No', Booking::$_translation_domain).'</a>';
        }
    }
    
    $content .= '</div>';
    
    if ($echo) {
        echo $content;
    }
    
    return $content;
}

function get_booking_id($event_id, $user_id) {
    global $wpdb;
    
    return intval($wpdb->get_var($wpdb->prepare("SELECT id FROM ".Booking::tablename('bookings')." WHERE event_id = %d AND user_id = %d;", $event_id, $user_id)));
}

function get_booking($booking_id) {
    global $wpdb;
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM ".Booking::tablename('bookings')." WHERE id = %d;", $booking_id));
}

function get_booking_status($booking_id) {
    global $wpdb;
    
    return $wpdb->get_var($wpdb->prepare("SELECT status FROM ".Booking::tablename('bookings')." WHERE id = %d;", $booking_id));
}

function get_booking_paid($booking_id) {
    
    return get_booking_meta($booking_id, 'booking_transaction_key');
}

function get_booking_meta($booking_id, $meta_key, $default = false) {
    global $wpdb;
    
    $meta_value = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM ".Booking::tablename('bookings_meta')." WHERE booking_id = %d AND meta_key = %s;", $booking_id, $meta_key));
    
    if (!$meta_value) {
	$meta_value = $default;
    }
    return $meta_value;
}

function update_booking_meta($booking_id, $meta_key, $meta_value) {
    global $wpdb;
    
    $meta_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM ".Booking::tablename('bookings_meta')." WHERE booking_id = %d AND meta_key = %s;", $booking_id, $meta_key));
    
    if (!$meta_id) {
	return $wpdb->query($wpdb->prepare("INSERT INTO ".Booking::tablename('bookings_meta')." VALUES (null, %d, %s, %s);", $booking_id, $meta_key, $meta_value));
    } else {
	return $wpdb->query($wpdb->prepare("UPDATE ".Booking::tablename('bookings_meta')." SET meta_value = %s WHERE id = %d;", $meta_value, $meta_id));
    }
}

function the_eab_error_notice($echo = true) {
    if ( !isset( $_GET['eab_success_msg'] ) && !isset( $_GET['eab_error_msg'] ) )
	return;
    $content = '';
    if (isset($_GET['eab_success_msg'])) {
	$content .= '<div id="eab-success-notice" class="message success">'. __($_GET['eab_success_msg'], Booking::$_translation_domain).'</div>';
    }
    
    if (isset($_GET['eab_error_msg'])) {
	$content .= '<div id="eab-error-notice" class="message error">'.__($_GET['eab_error_msg'], Booking::$_translation_domain).'</div>';
    }
    
    if ($echo) {
	echo $content;
    }
    
    return $content;
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
    $statuses = array('yes' => 'Attending', 'maybe' => 'Maybe', 'no' => 'No');
    
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
    global $post, $event_variation, $booking;
    
    if (!$archive) {
        $content .= '<h3>' . __('Event Details', Booking::$_translation_domain) . '</h3>';
    }
    $content .= '<ul>';
    
    $meta = get_post_custom($post->ID);
    $maybe_single = false;
    
    if (!isset($event_variation) || !isset($event_variation[$post->ID])) {
	$maybe_single = true;
	$event_variation[$post->ID] = 0;
    }
    
    if ($maybe_single) {
	$content .= '<li><b>' . __('Time', Booking::$_translation_domain) . '</b>: ';
	$_dc = 0;
	foreach ($meta['incsub_event_start'] as $_variation => $_start) {
	    if (date_i18n(get_option('date_format'), strtotime($meta['incsub_event_start'][$_variation])) ==
		date_i18n(get_option('date_format'), strtotime($meta['incsub_event_end'][$_variation]))) {
		$end_date = '';
	    } else {
		$end_date = date_i18n(get_option('date_format'), strtotime($meta['incsub_event_end'][$_variation])) . ' ';
	    }
	    if ($_dc > 0) {
		$content .= 'and ';
	    }
	    $content .= __('On', Booking::$_translation_domain) . ' ' . date_i18n(get_option('date_format'), strtotime($meta['incsub_event_start'][$_variation])) . ' ' . __('from', Booking::$_translation_domain) . ' ' . date_i18n(get_option('time_format'), strtotime($meta['incsub_event_start'][$_variation])) . ' ' . __('to', Booking::$_translation_domain) . ' ' . $end_date . date_i18n(get_option('time_format'), strtotime($meta['incsub_event_end'][$_variation])) . ' <br/>';
	    $_dc++;
	}
	$content .= '</li>';
    } else {
	if (date_i18n(get_option('date_format'), strtotime($meta['incsub_event_start'][$event_variation[$post->ID]])) ==
	    date_i18n(get_option('date_format'), strtotime($meta['incsub_event_end'][$event_variation[$post->ID]]))) {
	    $end_date = '';
	} else {
	    $end_date = date_i18n(get_option('date_format'), strtotime($meta['incsub_event_end'][$event_variation[$post->ID]])) . ' ';
	}
	$content .= '<li><b>' . __('Time', Booking::$_translation_domain) . '</b>: ' . __('On', Booking::$_translation_domain) . ' ' . date_i18n(get_option('date_format'), strtotime($meta['incsub_event_start'][$event_variation[$post->ID]])) . ' ' . __('from', Booking::$_translation_domain) . ' ' . date_i18n(get_option('time_format'), strtotime($meta['incsub_event_start'][$event_variation[$post->ID]])) . ' ' . __('to', Booking::$_translation_domain) . ' ' . $end_date . date_i18n(get_option('time_format'), strtotime($meta['incsub_event_end'][$event_variation[$post->ID]])) . '</li>';
    }
    $content .= '<li><b>' . __('Location', Booking::$_translation_domain) . '</b>: ' . get_post_meta($post->ID, 'incsub_event_venue', true) . '</li>';
    if (get_post_meta($post->ID, 'incsub_event_paid', true) == 1) {
	$content .= '<li><b>' . __('Fee', Booking::$_translation_domain) . '</b>: ' . $booking->_options['default']['currency'].' '. number_format(get_post_meta($post->ID, 'incsub_event_fee', true), 2) . '</li>';
    }
    $content .= '<li><b>' . __('Created By', Booking::$_translation_domain) . '</b>: <a href="'.get_the_author_link().'" title="'.get_the_author().'">' . get_the_author() . '</a></li>';
    $content .= '</ul>';
    
    if ($echo) {
        echo $content;
    }
    
    return $content;
}

function eab_payment_forms($echo = true) {
    global $booking, $post;
    
    $content = '';
    
    $content .= '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">';
    $content .= '<input type="hidden" name="business" value="'.$booking->_options['default']['paypal_email'].'" />';
    $content .= '<input type="hidden" name="item_name" value="'.get_the_title().'" />';
    $content .= '<input type="hidden" name="item_number" value="'.$post->ID.'" />';
    $content .= '<input type="hidden" name="booking_id" value="'.$booking_id.'" />';
    $content .= '<input type="hidden" name="notify_url" value="'.admin_url('admin-ajax.php?action=eab_paypal_ipn').'" />';
    $content .= '<input type="hidden" name="amount" value="'.get_post_meta($post->ID, 'incsub_event_fee', true).'" />';
    $content .= '<input type="hidden" name="return" value="'.get_permalink().'" />';
    $content .= '<input type="hidden" name="currency_code" value="'.$booking->_options['default']['currency'].'">';
    $content .= '<input type="hidden" name="cmd" value="_xclick" />';
    $content .= '<input type="image" name="submit" border="0" src="https://www.paypal.com/en_US/i/btn/btn_paynow_SM.gif" alt="PayPal - The safer, easier way to pay online" />';
    $content .= '<img alt="" border="0" width="1" height="1" src="https://www.paypal.com/en_US/i/scr/pixel.gif" />';
    $content .= '</form>';
    
    if ($echo) {
        echo $content;
    }
    
    return $content;
}

function event_breadcrumbs($echo = true) {
    global $wp_query;
    
    $content = '';
    
    $content .= '<a href="'.event_link('event_or_calendar').'" class="parent">'.__("Events", Booking::$_translation_domain).'</a> &gt; ';
    $content .= '<a href="'.get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y', strtotime("{$wp_query->query_vars['event_year']}-01-01")).'/').'" class="parent">'.date_i18n('Y', strtotime("{$wp_query->query_vars['event_year']}-01-01")).'</a> &gt; ';
    $content .= '<a href="'.get_site_url($blog_id, $booking->_options['default']['slug'].'/'.date('Y/m', strtotime("{$wp_query->query_vars['event_year']}-{$wp_query->query_vars['event_monthnum']}-01")).'/').'" class="parent">'.date_i18n('F', strtotime("{$wp_query->query_vars['event_year']}-{$wp_query->query_vars['event_monthnum']}-01")).'</a> &gt; ';
    $content .= '<span class="current">'.get_the_title().'</span>';
    
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
