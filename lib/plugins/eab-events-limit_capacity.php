<?php

/*
Plugin Name: Limited capacity Events
Description: Allows you to limit the number of attendees for each of your events.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0.2
Author: WPMU DEV
AddonType: Events
*/

class Eab_Addon_LimitCapacity {

	private $_data;

	private function __construct() {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve() {
		$me = new Eab_Addon_LimitCapacity;
		$me->_add_hooks();
	}

	private function _add_hooks() {
		add_action( 'eab-settings-after_api_settings', array( $this, 'show_settings' ) );
		add_filter( 'eab-settings-before_save', array( $this, 'save_settings' ) );

		add_filter( 'eab-event_meta-event_meta_box-after', array( $this, 'add_capacity_meta_box' ) );
		add_action( 'eab-event_meta-save_meta', array( $this, 'save_capacity_meta' ) );
		add_action( 'eab-events-recurrent_event_child-save_meta', array( $this, 'save_capacity_meta' ) );

		add_action( 'admin_print_scripts-post.php', array( $this, 'enqueue_admin_dependencies' ) );
		add_action( 'admin_print_scripts-post-new.php', array( $this, 'enqueue_admin_dependencies' ) );
		add_action( 'eab-javascript-enqueue_scripts', array( $this, 'enqueue_public_dependencies' ) );

		add_filter( 'eab-rsvps-rsvp_form', array( $this, 'handle_rsvp_form' ), 10, 2 );
		add_filter( 'eab-event-payment_forms', array( $this, 'show_remaining_tickets' ), 10, 2 );
		add_filter( 'eab-payment-paypal_tickets-extra_attributes', array( $this, 'handle_paypal_tickets' ), 10, 3 );

		// Front page editor integration
		add_filter( 'eab-events-fpe-add_meta', array( $this, 'add_fpe_meta_box' ), 10, 2 );
		add_action( 'eab-events-fpe-enqueue_dependencies', array( $this, 'enqueue_fpe_dependencies' ), 10, 2 );
		add_action( 'eab-events-fpe-save_meta', array( $this, 'save_fpe_meta' ), 10, 2 );

		// Attendance data juggling
		add_filter( '_eab-capacity-internal-attendance', array( $this, 'get_remaining_capacity' ), 10, 2 );

		// MarketPress integration
		add_action( 'eab-mp-variation-meta', array( $this, 'add_mp_inventory' ), 10, 5 );
		add_action( 'eab-mp-variation-thrash', array( $this, 'thrash_mp_inventory' ), 10, 2 );

		//Prevent the attendion in the hook rather than just use the form
		add_action( 'incsub_event_booking', array( $this, 'validate_attending_submission' ), 10, 3 );
	}

	public function add_mp_inventory( $product_id, $key, $instance_event_id, $parent_event_id, $unset_first ) {
		$capacity = ! empty( $_POST['eab-elc_capacity'] ) && (int) $_POST['eab-elc_capacity']
			? (int) $_POST['eab-elc_capacity']
			: $this->get_remaining_capacity( null, $parent_event_id );
		if ( ! $capacity ) {
			update_post_meta( $product_id, 'mp_track_inventory', 0 ); // Stop tracking inventory changes
			return false;
		}

		$inventory       = get_post_meta( $product_id, 'mp_inventory', true );
		$inventory[$key] = $capacity;

		if ( $unset_first && empty( $inventory[0] ) ) {
			unset( $inventory[0] );
			$inventory = array_values( $inventory );
		}

		update_post_meta( $product_id, 'mp_track_inventory', 1 ); // Do track inventory changes

		return update_post_meta( $product_id, 'mp_inventory', $inventory );
	}

	public function thrash_mp_inventory( $event_id, $product_id ) {
		delete_post_meta( $product_id, 'mp_inventory' );
		add_post_meta( $product_id, 'mp_inventory', array( 0 ) );
	}

	private function _get_event_total_attendance( $event_id, $exclude_user = false ) {
		global $wpdb;
		$event_id     = (int) $event_id;
		$exclude_user = (int) $exclude_user;
		$exclusion    = '';
		if ( $exclude_user ) {
			$exclusion = sprintf( 'AND user_id<>%d', $exclude_user );
		}
		$meta = $wpdb->get_col( "SELECT id FROM " . Eab_EventsHub::tablename( Eab_EventsHub::BOOKING_TABLE ) . " WHERE event_id={$event_id} AND status='" . Eab_EventModel::BOOKING_YES . "' {$exclusion}" );
		if ( ! $meta ) {
			return 0;
		}

		$booked             = join( ',', $meta );
		$multiples_this_far = $wpdb->get_col( "SELECT meta_value FROM " . Eab_EventsHub::tablename( Eab_EventsHub::BOOKING_META_TABLE ) . " WHERE booking_id IN({$booked}) AND meta_key='booking_ticket_count'" );
		if ( ! $multiples_this_far ) {
			return count( $meta );
		}

		$this_far = count( $meta ) - count( $multiples_this_far );
		foreach ( $multiples_this_far as $count ) {
			$this_far += $count;
		}

		return $this_far;
	}

	function handle_paypal_tickets( $atts, $event_id, $booking_id ) {
		$capacity = (int) get_post_meta( $event_id, 'eab_capacity', true );
		if ( ! $capacity ) {
			return $atts;
		} // No capacity set, we're good to show

		$user_id = false;
		if ( is_user_logged_in() ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
		}

		$total = $this->_get_event_total_attendance( $event_id, $user_id );
		$max   = $capacity - $total;

		return "{$atts} max='{$max}'";

	}

	function handle_rsvp_form( $content, $event = false ) {
		$post_id = false;
		if ( $event && $event instanceof Eab_EventModel ) {
			$post_id = $event->get_id();
		} else {
			global $post;
			$post_id = (int) @$post->ID;
		}

		$capacity = (int) get_post_meta( $post_id, 'eab_capacity', true );
		if ( ! $capacity ) {
			return $content;
		} // No capacity set, we're good to show

		$total = $this->_get_event_total_attendance( $post_id );

		global $wpdb, $current_user;
		$users = $wpdb->get_col( "SELECT user_id FROM " . Eab_EventsHub::tablename( Eab_EventsHub::BOOKING_TABLE ) . " WHERE event_id={$post_id} AND status='yes';" );

		if ( $capacity > $total ) {
			$remaining = $this->get_remaining_capacity( 0, $post_id );
			$content .= $this->_data->get_option( 'eab-limit_capacity-show_remaining' )
				? '<span class="eab-limit_capacity-remaining">' . apply_filters( 'eab-rsvps-event_capacity_remaining-message', sprintf( __( '%d seats left', Eab_EventsHub::TEXT_DOMAIN ), $remaining ), $post_id, $remaining ) . '</span>'
				: '';

			return $content;
		}

		return ( in_array( $current_user->id, $users ) ) ? $content : $this->_get_overbooked_message( $post_id );
		/*
		if ($capacity > $total) return $content;
		return (in_array($current_user->id, $users)) ? $content : $this->_get_overbooked_message();
		 */
	}

	function show_remaining_tickets( $content, $event_id ) {
		$capacity = (int) get_post_meta( $event_id, 'eab_capacity', true );
		if ( ! $capacity ) {
			return $content;
		} // No capacity set

		$total = $this->_get_event_total_attendance( $event_id );
		$max   = $capacity - $total;

		return $max
			? '<div class="eab-max_capacity">' . sprintf( __( '%s tickets left', Eab_EventsHub::TEXT_DOMAIN ), $max ) . '</div>' . $content
			: $content . '<div class="eab-max_capacity">' . __( 'No tickets left', Eab_EventsHub::TEXT_DOMAIN ) . '</div>';
	}

	function get_remaining_capacity( $attentance, $event_id ) {
		$capacity = (int) get_post_meta( $event_id, 'eab_capacity', true );
		if ( ! $capacity ) {
			return false;
		} // No capacity set

		$total = $this->_get_event_total_attendance( $event_id );

		return $capacity - $total;
	}

	function add_capacity_meta_box( $box ) {
		global $post;

		$capacity           = (int) get_post_meta( $post->ID, 'eab_capacity', true );
		$capacity_str       = $capacity ? $capacity : "";
		$unlimited_capacity = $capacity ? '' : 'checked="checked"';

		$ret = '';
		$ret .= '<div class="eab_meta_box">';
		$ret .= '<div class="misc-eab-section" >';
		$ret .= '<div class="eab_meta_column_box top"><label for="eab_event_capacity">' .
			__( 'Event capacity', Eab_EventsHub::TEXT_DOMAIN ) .
			'</label></div>';

		$ret .= '<label for="eab_event_capacity">' . __( 'Enter the maximum attendees for this event', Eab_EventsHub::TEXT_DOMAIN ) . '</label>';
		$ret .= ' <input type="text" name="eab-elc_capacity" id="eab_event_capacity" size="3" value="' . $capacity_str . '" /> ';
		$ret .= '<label for="eab_event_capacity-unlimited">' . __( 'or check for unlimited', Eab_EventsHub::TEXT_DOMAIN ) . '</label>';
		$ret .= ' <input type="checkbox" name="eab-elc_capacity" id="eab_event_capacity-unlimited" ' . $unlimited_capacity . ' value="0" /> ';

		$ret .= '</div>';
		$ret .= '</div>';

		return $box . $ret;
	}

	function add_fpe_meta_box( $box, $event ) {
		$capacity           = (int) get_post_meta( $event->get_id(), 'eab_capacity', true );
		$capacity_str       = $capacity ? $capacity : "";
		$unlimited_capacity = $capacity ? '' : 'checked="checked"';

		$ret .= '<div class="eab-events-fpe-meta_box">';

		$ret .= __( 'Enter the maximum attendees for this event', Eab_EventsHub::TEXT_DOMAIN );
		$ret .= ' <input type="text" name="eab-elc_capacity" id="eab_event_capacity" size="3" value="' . $capacity_str . '" /> ';
		$ret .= __( 'or check for unlimited', Eab_EventsHub::TEXT_DOMAIN );
		$ret .= ' <input type="checkbox" name="eab-elc_capacity" id="eab_event_capacity-unlimited" ' . $unlimited_capacity . ' value="0" /> ';

		$ret .= '</div>';

		return $box . $ret;
	}

	private function _save_meta( $post_id, $request ) {
		if ( ! isset( $request['eab-elc_capacity'] ) ) {
			return false;
		}

		$capacity = (int) $request['eab-elc_capacity'];
		//if (!$capacity) return false;

		update_post_meta( $post_id, 'eab_capacity', $capacity );
	}

	function save_capacity_meta( $post_id ) {
		$this->_save_meta( $post_id, $_POST );
	}

	function save_fpe_meta( $post_id, $request ) {
		$this->_save_meta( $post_id, $request );
	}


	function enqueue_fpe_dependencies() {
		wp_enqueue_script( 'eab-buddypress-limit_capacity-fpe', plugins_url( basename( EAB_PLUGIN_DIR ) . "/js/eab-buddypress-limit_capacity-fpe.js" ), array( 'jquery' ) );
	}

	function enqueue_admin_dependencies() {
		wp_enqueue_script( 'eab-buddypress-limit_capacity-admin', plugins_url( basename( EAB_PLUGIN_DIR ) . "/js/eab-buddypress-limit_capacity-admin.js" ), array( 'jquery' ) );
	}

	function enqueue_public_dependencies() {
		wp_enqueue_script( 'eab-buddypress-limit_capacity-public', plugins_url( basename( EAB_PLUGIN_DIR ) . "/js/eab-buddypress-limit_capacity-public.js" ), array( 'jquery' ) );
	}

	private function _get_overbooked_message( $post_id = false ) {
		$message = apply_filters( 'eab-rsvps-event_capacity_reached-message', __( 'Sorry, the event sold out.', Eab_EventsHub::TEXT_DOMAIN ), $post_id );
		if ( $post_id && $this->_data->get_option( 'eab-limit_capacity-show_cancel' ) ) {
			$login_url_n = apply_filters( 'eab-rsvps-rsvp_login_page-no', wp_login_url( get_permalink( $post_id ) ) . '&eab=n' );
			if ( is_user_logged_in() ) {
				$user_id   = get_current_user_id();
				$event     = new Eab_EventModel( $post_id );
				$is_coming = $event->user_is_coming( false, $user_id );
				$cancel    = '<input class="current wpmudevevents-no-submit" type="submit" name="action_no" value="' .
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

		return '<div class="wpmudevevents-event_reached_capacity">' .
		$message .
		'</div>';
	}

	function save_settings( $options ) {
		$options['eab-limit_capacity-show_remaining'] = @$_POST['event_default']['eab-limit_capacity-show_remaining'];
		$options['eab-limit_capacity-show_cancel']    = @$_POST['event_default']['eab-limit_capacity-show_cancel'];

		return $options;
	}

	function show_settings() {
		$checked_remaining = $this->_data->get_option( 'eab-limit_capacity-show_remaining' ) ? 'checked="checked"' : '';
		$checked_cancel    = $this->_data->get_option( 'eab-limit_capacity-show_cancel' ) ? 'checked="checked"' : '';
		?>
		<div id="eab-settings-limit_capacity" class="eab-metabox postbox">
			<h3 class="eab-hndle"><?php _e( 'Limited capacity events settings', Eab_EventsHub::TEXT_DOMAIN ); ?></h3>

			<div class="eab-inside">
				<div class="eab-settings-settings_item">
					<label for="eab-limit_capacity-show_remaining"><?php _e( 'Show remaining seats', Eab_EventsHub::TEXT_DOMAIN ); ?>?</label>
					<input type="checkbox" id="eab-limit_capacity-show_remaining" name="event_default[eab-limit_capacity-show_remaining]" value="1" <?php print $checked_remaining; ?> />
				</div>
				<div class="eab-settings-settings_item">
					<label for="eab-limit_capacity-show_cancel"><?php _e( 'Show cancel link for logged out users when the event is sold out', Eab_EventsHub::TEXT_DOMAIN ); ?>?</label>
					<input type="checkbox" id="eab-limit_capacity-show_cancel" name="event_default[eab-limit_capacity-show_cancel]" value="1" <?php print $checked_cancel; ?> />
				</div>
			</div>
		</div>
	<?php
	}

	function validate_attending_submission( $event_id, $user_id, $booking_action ) {
		if ( isset( $_POST['action_yes'] ) ) {
			$capacity = (int) get_post_meta( $event_id, 'eab_capacity', true );
			if ( $capacity > 0 ) {
				$total = $this->_get_event_total_attendance( $event_id );

				if ( $total >= $capacity ) {
					//reach the limit
					wp_redirect( '?eab_error_msg=' . urlencode( __( 'Sorry, the event has reached it\'s max capacity!!', Eab_EventsHub::TEXT_DOMAIN ) ) );
					exit;
				}
			}
		}
	}
}

Eab_Addon_LimitCapacity::serve();

function eab_capacity_remaining( $event_id = false ) {
	if ( ! $event_id ) {
		global $post;
		$event_id = $post->ID;
	}

	return apply_filters( '_eab-capacity-internal-attendance', 0, $event_id );
}