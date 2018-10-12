<?php

/*
  Plugin Name: RSVP Close Period
  Description: Allows you to close RSVP bookings x hours prior to the event start time, preventing new RSVP's during that period.
  Plugin URI: http://premium.wpmudev.org/project/events-and-booking
  Version: 1.0
  Author: WPMU DEV
  AddonType: Events
 */

/*
  Detail: Your events will be shown in archives list, but visitors won't be able to RSVP.
 */

class Eab_Events_RSVPClosePeriod {

    private function __construct() {
	
    }

    public static function serve() {
	$me = new Eab_Events_RSVPClosePeriod;
	$me->_add_hooks();
    }

    private function _add_hooks() {
	//add_action('admin_notices', array($this, 'show_nags'));
	add_action( 'eab_scheduled_jobs', array( $this, 'archive_events_period' ), 99 );

	add_filter( 'eab-event_meta-event_meta_box-after', array( $this, 'add_close_period_meta_box' ) );
	add_action( 'eab-event_meta-save_meta', array( $this, 'save_close_period_meta' ) );
	add_action( 'eab-events-recurrent_event_child-save_meta', array( $this, 'save_close_period_meta' ) );

	add_action( 'admin_print_scripts-post.php', array( $this, 'enqueue_admin_close_period_dependencies' ) );
	add_action( 'admin_print_scripts-post-new.php', array( $this, 'enqueue_admin_close_period_dependencies' ) );
	add_action( 'eab-javascript-enqueue_scripts', array( $this, 'enqueue_public_close_period_dependencies' ) );

	// Front page editor integration
	add_filter( 'eab-events-fpe-add_meta', array( $this, 'add_fpe_close_period_meta_box' ), 10, 2 );
	add_action( 'eab-events-fpe-enqueue_dependencies', array( $this, 'enqueue_fpe_close_period_dependencies' ), 10, 2 );
	add_action( 'eab-events-fpe-save_meta', array( $this, 'save_fpe_close_period_meta' ), 10, 2 );

	//Prevent the attendion in the hook rather than just use the form
	add_action( 'incsub_event_booking', array( $this, 'validate_rsvp_attending_submission' ), 10, 3 );
    }

    function add_close_period_meta_box( $box ) {
	global $post;

	$period = round( get_post_meta( $post->ID, 'eab_close_period', true ), 2 );
	$period_str = $period ? $period : '';
	$nolimit = $period ? '' : 'checked="checked"';

	$ret = '';
	$ret .= '<div class="eab_meta_box">';
	$ret .= '<div class="misc-eab-section" >';
	$ret .= '<div class="eab_meta_column_box top"><label for="eab_event_close_period">' .
		__( 'Event RSVP closing period', Eab_EventsHub::TEXT_DOMAIN ) .
		'</label></div>';

	$ret .= '<label for="eab_event_close_period">' . __( 'Hours', Eab_EventsHub::TEXT_DOMAIN ) . '</label>';
	$ret .= ' <input type="text" name="eab_ecp_close_period" id="eab_event_close_period" size="3" value="' . $period_str . '" /> ';
	$ret .= '<br /><label for="eab_event_close_period-noclose">' . __( 'or check for no closing period', Eab_EventsHub::TEXT_DOMAIN ) . '</label>';
	$ret .= ' <input type="checkbox" name="eab_ecp_close_period" id="eab_event_close_period-noclose" ' . $nolimit . ' value="0" /> ';

	$ret .= '</div>';
	$ret .= '</div>';

	return $box . $ret;
    }

    function add_fpe_close_period_meta_box( $box, $event ) {
	$period = round( get_post_meta( $event->get_id(), 'eab_close_period', true ), 2 );
	$period_str = $period ? $period : '';
	$nolimit = $period ? '' : 'checked="checked"';

	$ret .= '<div class="eab-events-fpe-close_period_meta_box">';

	$ret .= __( 'Enter the RSVP closing period for this event (in hours)', Eab_EventsHub::TEXT_DOMAIN );
	$ret .= ' <input type="text" name="eab_ecp_close_period" id="eab_event_close_period" size="3" value="' . $period_str . '" /><br /> ';
	$ret .= __( 'or check for no closing period', Eab_EventsHub::TEXT_DOMAIN );
	$ret .= ' <input type="checkbox" name="eab_ecp_close_period" id="eab_event_close_period-noclose" ' . $nolimit . ' value="0" /> ';

	$ret .= '</div>';

	return $box . $ret;
    }

    private function _save_meta( $post_id, $request ) {
	if ( !isset( $request[ 'eab_ecp_close_period' ] ) ) {
	    return false;
	}

	$period = round( ( float ) $request[ 'eab_ecp_close_period' ], 2 );

	update_post_meta( $post_id, 'eab_close_period', $period );
    }

    function save_close_period_meta( $post_id ) {
	$this->_save_meta( $post_id, $_POST );
    }

    function save_fpe_close_period_meta( $post_id, $request ) {
	$this->_save_meta( $post_id, $request );
    }

    function enqueue_fpe_close_period_dependencies() {
	wp_enqueue_script( 'eab-buddypress-close_period-fpe', plugins_url( basename( EAB_PLUGIN_DIR ) . "/js/eab-buddypress-close_period-fpe.js" ), array( 'jquery' ) );
    }

    function enqueue_admin_close_period_dependencies() {
	wp_enqueue_script( 'eab-buddypress-close_period-admin', plugins_url( basename( EAB_PLUGIN_DIR ) . "/js/eab-buddypress-close_period-admin.js" ), array( 'jquery' ) );
    }

    function enqueue_public_close_period_dependencies() {
	wp_enqueue_script( 'eab-buddypress-close_period-public', plugins_url( basename( EAB_PLUGIN_DIR ) . "/js/eab-buddypress-close_period-public.js" ), array( 'jquery' ) );
    }

    private function _get_rsvp_closed_message( $post_id = false ) {
	$message = apply_filters( 'eab-rsvps-event_closed_period-message', __( 'Sorry, RSVP bookings are no longer available for this event.', Eab_EventsHub::TEXT_DOMAIN ), $post_id );
	if ( $post_id ) {
	    $login_url_n = apply_filters( 'eab-rsvps-rsvp_login_page-no', wp_login_url( get_permalink( $post_id ) ) . '&eab=n' );
	    if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		$event = new Eab_EventModel( $post_id );
		$is_coming = $event->user_is_coming( false, $user_id );
		$cancel = '<input class="current wpmudevevents-no-submit" type="submit" name="action_no" value="' .
			__( 'Cancel', Eab_EventsHub::TEXT_DOMAIN ) .
			'" ' .
			( $is_coming ? '' : 'style="display:none"' ) .
			' />';
		if ( $is_coming ) {
		    $cancel .= '<input type="hidden" name="user_id" value="' . get_current_user_id() . '" />';
		}
	    } else {
		$cancel = '<a class="wpmudevevents-no-submit" href="' . $login_url_n . '" >' . __( 'Cancel', Eab_EventsHub::TEXT_DOMAIN ) . '</a>';
	    }
	    $message .= '<div class="wpmudevevents-buttons">' .
		    '<form action="' . get_permalink( $post_id ) . '" method="post" >' .
		    '<input type="hidden" name="event_id" value="' . $post_id . '" />' .
		    $cancel .
		    '</form>' .
		    '</div>';
	}

	return '<div class="wpmudevevents-event_rsvp_closed_period">' .
		$message .
		'</div>';
    }

    function validate_rsvp_attending_submission( $event_id, $user_id, $booking_action ) {
	if ( isset( $_POST[ 'action_yes' ] ) ) {
	    $period = round( (float) get_post_meta( $event_id, 'eab_close_period', true ), 2 );
	    if ( $period > 0 ) {
		$start = $this->_get_event_start_time( $event_id );
		$current_time = strtotime( current_time( 'mysql' ) );

		$diff = round( ( $start - $current_time ) / 3600, 2 );

		if ( $diff <= $period ) {
		    wp_redirect( '?eab_error_msg=' . urlencode( __( 'Sorry, RSVP bookings are now closed for this event.', Eab_EventsHub::TEXT_DOMAIN ) ) );
		    exit;
		}
	    }
	}
    }

    private function _get_event_start_time( $event_id, $exclude_user = false ) {
	global $wpdb;
	$event_id = (int) $event_id;

	$start = $wpdb->get_var( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'incsub_event_start' AND post_id = $event_id" );

	return strtotime($start);
    }

}

Eab_Events_RSVPClosePeriod::serve();
