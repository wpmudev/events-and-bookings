<?php

class Eab_Template {
	
	public static function get_archive_content ($post, $content=false) {
		
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		if ('incsub_event' != $event->get_type()) return $content;
		
		$start_day = date_i18n('m', $event->get_start_timestamp());

		$network = $event->from_network();
		$link = $network 
			? get_blog_permalink($network, $event->get_id())
			: get_permalink($event->get_id())
		;
		
		$new_content  = '';
		
		$new_content .= '<div class="event ' . self::get_status_class($event) . '" itemscope itemtype="http://schema.org/Event">';

		if( !empty( $content['with_thumbnail'] ) && ($content['with_thumbnail'] == 'yes' || true == $content['with_thumbnail']) ) {
			$new_content .= '<div class="event_sc_thumb">';
			$new_content .= get_the_post_thumbnail( $event->get_id(), $content['thumbnail_size'] );
			$new_content .= '</div>';
		}

		$new_content .= '<meta itemprop="name" content="' . esc_attr($event->get_title()) . '" />';
		$new_content .= '<a href="' . $link . '" class="wpmudevevents-viewevent">' .
			__('View event', Eab_EventsHub::TEXT_DOMAIN) . 
		'</a>';
		$new_content .= apply_filters('eab-template-archive_after_view_link', '', $event);
		$new_content .= '<div style="clear: both;"></div>';
		$new_content .= '<hr />';
		$new_content .= self::get_event_details($event);
		$new_content .= self::get_rsvp_form($event);
		$new_content .= '</div>';
		$new_content .= '<div style="clear:both"></div>';
		
		return $new_content;
	}
	
	public static function get_single_content ($post, $content=false) {
		global $current_user;
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
		if ('incsub_event' != $event->get_type()) return $content;
		
		$start_day = date_i18n('m', $event->get_start_timestamp());
		    
		$new_content  = '';
		$new_content .= '<div class="event ' . self::get_status_class($event) . '" id="wpmudevevents-wrapper" itemscope itemtype="http://schema.org/Event"><div id="wpmudevents-single">';
		$new_content .= '<meta itemprop="name" content="' . esc_attr($event->get_title()) . '" />';
		
		$new_content .= self::get_error_notice();
		
		// Added by Hakan
		$show_pay_note = $event->is_premium() && $event->user_is_coming() && !$event->user_paid();
		$show_pay_note = apply_filters('eab-event-show_pay_note', $show_pay_note, $event->get_id () );

		$paypal_processing = false;
		if ( isset( $_GET['paypal_processing'] ) ) {
			if ( $_GET['paypal_processing'] == 1 ) {
				// Will show message if user has just returned from a paypal payment as it takes around 10 seconds for the payment to register in the database.
				$paypal_processing = true;
			}
		}
		if ( $show_pay_note && ! $paypal_processing ) {
			$new_content .= '<div id="wpmudevevents-payment">';
			$new_content .= __( 'You haven\'t paid for this event', Eab_EventsHub::TEXT_DOMAIN ) . ' ';
			$new_content .= self::get_payment_forms($event);
			$new_content .= '</div>';
		} elseif ( $event->is_premium() && $event->user_paid() ) {
                        $new_content .= '<div id="wpmudevevents-payment">';
			$new_content .= __( 'You already paid for this event', Eab_EventsHub::TEXT_DOMAIN );
                        $new_content .= '</div>';
		} elseif ( $paypal_processing ) {
			$new_content .= '<div id="wpmudevevents-payment">';
			$new_content .= __( 'Your payment is being processed. This may take a few minutes to show here.', Eab_EventsHub::TEXT_DOMAIN ) . ' ';
			$new_content .= '</div>';
		}
		
		// Added by Hakan
		//$new_content = apply_filters('eab-event-after_payment_forms', $new_content, $event->get_id()); // Moved this to self::get_payment_forms()
	
		$new_content .= '<div class="eab-needtomove"><div id="event-bread-crumbs" >' . self::get_breadcrumbs($event) . '</div></div>';
		
		$new_content .= '<div id="wpmudevevents-header">';
		$new_content .= self::get_rsvp_form($event);
		$new_content .= self::get_inline_rsvps($event);
		$new_content .= '</div>';
		
		$new_content .= '<hr/>';
		
		$new_content .= '<div class="wpmudevevents-content">';
		
		$new_content .= '<div id="wpmudevevents-contentheader">';
		$new_content .= '<h3>' . __('About this event:', Eab_EventsHub::TEXT_DOMAIN) . '</h3>';
		$new_content .= '<div id="wpmudevevents-user">'. __('Created by ', Eab_EventsHub::TEXT_DOMAIN) . self::get_event_author_link($event) . '</div>';
		$new_content .= '</div>';
		
		$new_content .= '<hr/>';
		
		$new_content .= '<div id="wpmudevevents-contentmeta">' . self::get_event_details($event) . '<div style="clear: both;"></div></div>';
		$new_content .= '<div id="wpmudevevents-contentbody" itemprop="description">' . ($content ? $content : $event->get_content()) . '</div>';			
		
		if ($event->has_venue_map()) {
			$new_content .= '<div id="wpmudevevents-map">' . $event->get_venue_location(Eab_EventModel::VENUE_AS_MAP) . '</div>';
		}
		$new_content .= '</div>';
		$new_content .= apply_filters('eab-events-after_single_event', '', $event);
		$new_content .= '</div></div>';
		return $new_content;		
	}

	public static function get_inline_rsvps ($post) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		$data = Eab_Options::get_instance();
		
		$content = '';
		if ($event->has_bookings() && $data->get_option('display_attendees') == 1) {
			$content .= '<div id="wpmudevevents-rsvps">';
			$content .= '<a href="' . 
				admin_url('admin-ajax.php?action=eab_list_rsvps&pid=' . $event->get_id()) . 
				'" id="wpmudevevents-load-rsvps" class="hide-if-no-js wpmudevevents-viewrsvps wpmudevevents-loadrsvps">' .
					apply_filters( 'eab_show_rsvp_text', __('See who has RSVPed', Eab_EventsHub::TEXT_DOMAIN) ) .
			'</a>';
			$content .= '&nbsp;';
			$content .= '<a href="#" id="wpmudevevents-hide-rsvps" class="hide-if-no-js wpmudevevents-viewrsvps wpmudevevents-hidersvps">' .
				apply_filters( 'eab_hide_rsvp_text', __('Hide who has RSVPed', Eab_EventsHub::TEXT_DOMAIN) ) .
			'</a>';
			$content .= '</div>';
			$content .= '<div id="wpmudevevents-rsvps-response"></div>';
		}
		
		return $content;
	}
	
	public static function get_rsvps ($post) {
		$data = Eab_Options::get_instance();
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		$content = '';
		if ($data->get_option('display_attendees') == 1) {
			$content .= '<div class="wpmudevevents-attendees">';
			$content .= '	<div id="event-bookings">';
			$content .= '		<div id="event-booking-yes">';
			$content .= self::get_bookings(Eab_EventModel::BOOKING_YES, $event);
			$content .= '		</div>';
			$content .= '		<div class="clear"></div>';
			$content .= '		<div id="event-booking-maybe">';
			$content .= self::get_bookings(Eab_EventModel::BOOKING_MAYBE, $event);
			$content .= '		</div>';
			$content .= '		<div id="event-booking-no">';
			$content .= self::get_bookings(Eab_EventModel::BOOKING_NO, $event);
			$content .= '		</div>';
			$content .= '	</div>';
			$content .= '</div>';
		}
		return $content;
	}

	public static function get_bookings ($status, $post) {
		global $wpdb;
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
		$statuses = array(
			Eab_EventModel::BOOKING_YES => __('Attending', Eab_EventsHub::TEXT_DOMAIN), 
			Eab_EventModel::BOOKING_MAYBE => __('Maybe', Eab_EventsHub::TEXT_DOMAIN), 
			Eab_EventModel::BOOKING_NO => __('No', Eab_EventsHub::TEXT_DOMAIN)
		);
		if (!in_array($status, array_keys($statuses))) return false; // Unknown status
		$status_name = $statuses[$status];
		
		$bookings = $wpdb->get_results($wpdb->prepare("SELECT user_id FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s ORDER BY timestamp;", $event->get_id(), $status));
		if (!count($bookings)) return false;
		
		$content = '';		
		$content .= '<h4>'. $status_name . '</h4>';
		$content .= '<ul class="eab-guest-list">';
		
		foreach ($bookings as $booking) {
			$user_data = get_userdata($booking->user_id);
			$url = defined('BP_VERSION') 
				? bp_core_get_user_domain($booking->user_id) : 
				get_author_posts_url($booking->user_id)
			;
			
			$avatar = '<a href="' . $url . '" title="' . esc_attr($user_data->display_name) . '">' .
				get_avatar($booking->user_id, 32) .
			'</a>';
			$avatar = apply_filters('eab-guest_list-guest_avatar', 
				apply_filters("eab-guest_list-status_{$status}-guest_avatar", $avatar, $booking->user_id, $user_data, $event),
				$booking->user_id, $user_data, $event
			);
			
			$content .= "<li>{$avatar}</li>";
		}
		
		$content .= '</ul>';
		$content .= '<div class="clear"></div>';
		
		return $content;	
	}

	public static function get_user_events ($status, $user_id) {
		global $wpdb;
		
		$statuses = array(
			Eab_EventModel::BOOKING_YES => __('Attending', Eab_EventsHub::TEXT_DOMAIN), 
			Eab_EventModel::BOOKING_MAYBE => __('Maybe', Eab_EventsHub::TEXT_DOMAIN), 
			Eab_EventModel::BOOKING_NO => __('No', Eab_EventsHub::TEXT_DOMAIN)
		);
		if (!in_array($status, array_keys($statuses))) return false; // Unknown status
		$status_name = $statuses[$status];
		
		$bookings = $wpdb->get_col($wpdb->prepare("SELECT event_id FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE user_id = %d AND status = %s ORDER BY timestamp;", $user_id, $status));
		if (!count($bookings)) return false;
		
		$ret = '<div class="wpmudevevents-user_bookings wpmudevevents-user_bookings-' . $status . '">';
		foreach ($bookings as $event_id) {
			$event = new Eab_EventModel(get_post($event_id));
			$id_check = $event->get_id();
			if (apply_filters('eab-event-user_events-exclude_event', empty($id_check), $event)) continue;
			$ret .= '<h4>' . self::get_event_link($event) . '</h4>';
			$ret .= '<div class="wpmudevevents-event-meta">' . 
				apply_filters('eab-event-user_events-before_meta', '', $event, $status) .
				self::get_event_dates($event) .
				'<br />' .
				$event->get_venue_location() . 
			'</div>';
		}
		$ret .= '</div>';
		return $ret;
	}

	public static function get_user_organized_events ($user_id) {
		$events = Eab_CollectionFactory::get_user_organized_events($user_id);

		$ret = '<div class="wpmudevevents-user_bookings wpmudevevents-events-user_organized">';
		foreach ($events as $event) {
			if ($event->is_recurring()) continue;
			$ret .= '<h4>' . self::get_event_link($event) . '</h4>';
			$ret .= '<div class="wpmudevevents-event-meta">' . 
				self::get_event_dates($event) .
				'<br />' .
				$event->get_venue_location() . 
			'</div>';
		}
		$ret .= '</div>';
		return $ret;
	}

	public static function get_admin_attendance_addition_form ($event, $statuses) {
		if (!method_exists($event, 'get_id') || !is_array($statuses)) return false;
		if ($event->is_premium()) return ''; // Won't deal with the paid events
		if ($event->is_recurring() && !$event->is_recurring_child()) return ''; // Won't deal with the recurring hub events

		$content = '';

		$content = '<div class="eab-add_attendance-container">';

		//$content .= '<div id="eab-bookings-response"></div>';
                
                $content .= '<fieldset class="eab-add_attendance">';
		$content .= '<legend>' . __('Add user', Eab_EventsHub::TEXT_DOMAIN) . '</legend>';
		
		$content .= '<label>' . __('User email', Eab_EventsHub::TEXT_DOMAIN) . '</label>&nbsp;';
		$content .= '<input type="hidden" class="eab-attendance-event_id" value="' . (int)$event->get_id() . '" />';
		$content .= '<input type="email" class="eab-attendance-email" />';
		$content .= '<select class="eab-attendance-status">';
		foreach ($statuses as $status => $label) {
			$content .= '<option value="' . esc_attr($status) . '">' . esc_html($label) . '</option>';
		}
		$content .= '</select>';
		$content .= '<input type="button" class="button" value="' . esc_attr(__('Add', Eab_EventsHub::TEXT_DOMAIN)) . '" />';
		$content .= '</fieldset>';
		
		$content .= '</div>';
		
		return $content;
	}

	public static function get_admin_bookings ($status, $post) {
		global $wpdb;
		if (!current_user_can('edit_posts')) return false; // Basic sanity check
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
		$statuses = self::get_rsvp_status_list();
		if (!in_array($status, array_keys($statuses))) return false; // Unknown status
		$status_name = $statuses[$status];

		//$content = Eab_Template::get_admin_attendance_addition_form($event, $statuses); // Moved to actual bookings areas

                $content = '';
		$content .= '<h4>'. __($status_name, Eab_EventsHub::TEXT_DOMAIN). '</h4>';
		$content .= '<ul class="eab-guest-list">';

		$all_events = array($event);
		if ($event->is_recurring()) $all_events = Eab_CollectionFactory::get_all_recurring_children_events($event);
		$all_event_ids = array();
		foreach ($all_events as $e) { $all_event_ids[] = $e->get_id(); }
		$all_event_ids = array_filter(array_map('intval', $all_event_ids));

		$bookings = $wpdb->get_results($wpdb->prepare("SELECT id,user_id,event_id FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id IN(" . join(',', $all_event_ids) . ") AND status = %s ORDER BY timestamp;", $status));
		if (!count($bookings)) return false;

		foreach ($bookings as $booking) {
			$user_data = get_userdata($booking->user_id);
			if ($event->get_id() !== $booking->event_id) $event = new Eab_EventModel(get_post($booking->event_id));
			$content .= '<li>';
			$content .= '<div class="eab-guest">';
			$content .= '<a href="user-edit.php?user_id='.$booking->user_id .'" title="'.$user_data->display_name.'">' .
				get_avatar( $booking->user_id, 32 ) .
				'<br />' .
				apply_filters('eab-guest_list-admin-guest_name', $user_data->display_name, $booking->user_id, $user_data) .
			'</a>';
			if ($event->is_premium()) {
				if ($event->user_paid($booking->user_id)) {
					$ticket_count = $event->get_booking_meta($booking->id, 'booking_ticket_count');
					$ticket_count = $ticket_count ? $ticket_count : 1;
					$payment_status = '' .
						'<span class="eab-guest-payment_info-paid">' . 
							__('Paid', Eab_EventsHub::TEXT_DOMAIN) . 
						'</span>' .
						'&nbsp;' .
						sprintf(__('(%s tickets)', Eab_EventsHub::TEXT_DOMAIN), $ticket_count) .
					''; 
				} else {
					$payment_status = '<span class="eab-guest-payment_info-not_paid">' . __('Not paid', Eab_EventsHub::TEXT_DOMAIN) . '</span>';
				}
				// Added by Hakan
				$payment_status = apply_filters('eab-event-payment_status', $payment_status, $booking->user_id, $event); 
				$content .= "<div class='eab-guest-payment_info'>{$payment_status}</div>";
			}
			if (in_array($status, array(Eab_EventModel::BOOKING_YES, Eab_EventModel::BOOKING_MAYBE))) {
				$content .= '<div class="eab-guest-actions"><a href="#cancel-attendance" class="eab-guest-cancel_attendance" data-eab-user_id="' . $booking->user_id . '" data-eab-event_id="' . $event->get_id() . '">' .
					__('Cancel attendance', Eab_EventsHub::TEXT_DOMAIN) .
				'</a></div>';
			}
			$content .= '<div class="eab-guest-actions"><a href="#delete-attendance" class="eab-guest-delete_attendance" data-eab-user_id="' . $booking->user_id . '" data-eab-event_id="' . $event->get_id() . '">' .
				__('Delete attendance entirely', Eab_EventsHub::TEXT_DOMAIN) .
			'</a></div>';

			$list_event_date = date( 'Y-m-d h:i a', strtotime( get_post_meta( $booking->event_id, 'incsub_event_start', true ) ) );

			if ( ! empty( $list_event_date ) && 'recurrent' === get_post_status( $booking->event_id ) ) {
				$content .= '<span class="eab-event-recurring-date-information">' . $list_event_date . '</span>';
			}
			$content = apply_filters('eab-event-booking_metabox_content', $content, $booking->user_id);
			$content .= '</div>'; // .eab-guest
			$content .= '</li>';
		}
		$content .= '</ul>';
		$content .= '<div class="clear"></div>';
		
		return $content;
	}

	public static function get_event_author_link ($post) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		$user_id = $event->get_author();
		$url = get_the_author_meta('url', $user_id);
		$author = get_the_author_meta('display_name', $user_id);
		return $url 
			? '<a href="' . $url . '" title="' . 
				esc_attr(sprintf(__("Visit %s&#8217;s website"), $author)) . 
				'" rel="external">' . 
					$author . 
			'</a>'
			: $author
		;
	}
	
	public static function get_breadcrumbs ($post) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		$start = $event->get_start_timestamp();
		$content = '';
		
		$content .= '<a href="' . self::get_root_url() . '/" class="parent">' .
			__("Events", Eab_EventsHub::TEXT_DOMAIN) .
		'</a> &gt; ';
		$content .= '<a href="' . self::get_archive_url($start, false) . '" class="parent">' .
				date('Y', $start) .
		'</a> &gt; ';
		$content .= '<a href="' . self::get_archive_url($start, true) . '" class="parent">' . 
				date_i18n('F', $start) .
		'</a> &gt; ';
		$content .= '<span class="current">' . $event->get_title() . '</span>';
		
		return $content;
	}
	
	public static function get_payment_forms ($post ) {
		global $blog_id, $current_user;
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);

		$booking_id = $event->get_user_booking_id();
		$data = Eab_Options::get_instance();
		
		$content = '';
		
		if( trim( $data->get_option('paypal_email') ) != '' ) {
		
			$content .= $data->get_option('paypal_sandbox') 
				? '<form action="https://www.sandbox.paypal.com/cgi-bin/webscr" method="post">'
				: '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">'
			;
			$content .= '<input type="hidden" name="business" value="' . $data->get_option('paypal_email') . '" />';
			$content .= '<input type="hidden" name="item_name" value="' . esc_attr($event->get_title()) . '" />';
			$content .= '<input type="hidden" name="item_number" value="' . $event->get_id() . '" />';
			$content .= '<input type="hidden" name="notify_url" value="' . 
				admin_url('admin-ajax.php?action=eab_paypal_ipn&blog_id=' . $blog_id . '&booking_id=' . $booking_id) .
			'" />';
			$content .= '<input type="hidden" name="amount" value="' . $event->get_price()  .'" />';
			$content .= '<input type="hidden" name="return" value="' . get_permalink($event->get_id()) . '?paypal_processing=1" />';
			$content .= '<input type="hidden" name="currency_code" value="' . $data->get_option('currency') . '">';
			$content .= '<input type="hidden" name="cmd" value="_xclick" />';
			
			// Add multiple tickets
			$extra_attributes = '';
			$extra_attributes = apply_filters('eab-payment-paypal_tickets-extra_attributes', $extra_attributes, $event->get_id(), $booking_id);
			$content .= '' .// '<a href="#buy-tickets" class="eab-buy_tickets-trigger" style="display:none">' . __('Buy tickets', Eab_EventsHub::TEXT_DOMAIN) . '</a>' . 
				sprintf(
					//'<p class="eab-buy_tickets-target">' . __('I want to buy %s ticket(s)', Eab_EventsHub::TEXT_DOMAIN) . '</p>', 
					'<p>' . __('I want to buy %s ticket(s)', Eab_EventsHub::TEXT_DOMAIN) . '</p>', 
					'<input type="number" size="2" name="quantity" value="1" min="1" ' . $extra_attributes . ' />'
				)
			;
			
			$content .= '<input type="image" name="submit" border="0" src="https://www.paypal.com/en_US/i/btn/btn_paynow_SM.gif" alt="PayPal - The safer, easier way to pay online" />';
			$content .= '<img alt="" border="0" width="1" height="1" src="https://www.paypal.com/en_US/i/scr/pixel.gif" />';
			$content .= '</form>';
		
		}

		// Moved this here so manual payments get propagated to templates.
		$content = apply_filters('eab-event-after_payment_forms', $content, $event->get_id());

		// Added by Hakan
		$content = apply_filters('eab-event-payment_forms', $content, $event->get_id());
		
		return $content;
	}

	static public function get_root_url () {
		global $blog_id;
		$data = Eab_Options::get_instance();
		return get_home_url($blog_id, $data->get_option('slug'));
	}

	public static function get_archive_url ($timestamp=false, $full=false) {
		global $blog_id;
		$data = Eab_Options::get_instance();
		$timestamp = $timestamp ? $timestamp : eab_current_time();
		$format = $full ? 'Y/m' : 'Y';
		return get_home_url(
			$blog_id, 
			$data->get_option('slug') . '/' . date($format, $timestamp) . '/'
		);
	} 

	public static function get_archive_url_next ($timestamp=false, $full=false) {
		//return self::get_archive_url($timestamp + (32*86400), $full);
		return self::get_archive_url(strtotime("next month", $timestamp), $full);
	} 
	public static function get_archive_url_next_year ($timestamp=false, $full=false) {
		//return self::get_archive_url($timestamp + (366*86400), $full);
		return self::get_archive_url(strtotime("next year", $timestamp), $full);
	} 
	public static function get_archive_url_prev ($timestamp=false, $full=false) {
		//return self::get_archive_url($timestamp - (28*86400), $full);
		return self::get_archive_url(strtotime("previous month", $timestamp), $full);
	} 
	public static function get_archive_url_prev_year ($timestamp=false, $full=false) {
		//return self::get_archive_url($timestamp - (366*86400), $full);
		return self::get_archive_url(strtotime("previous year", $timestamp), $full);
	} 

	public static function get_event_link ($post) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		return '<a class="wpmudevevents-event_link" href="' . get_permalink($event->get_id()) . '">' . $event->get_title() . '</a>';
	}

	public static function get_error_notice() {
		if (!isset( $_GET['eab_success_msg'] ) && !isset( $_GET['eab_error_msg'] )) return;
		
		$legacy_redirects = apply_filters(
			'eab-rsvps-status_messages-legacy_redirects',
			(defined('EAB_RSVPS_LEGACY_REDIRECTS') && EAB_RSVPS_LEGACY_REDIRECTS)
		);

		$content = '';
		if (isset($_GET['eab_success_msg'])) {
			$status = stripslashes($_GET['eab_success_msg']);
			$message = $legacy_redirects
				? apply_filters('eab-rsvps-status_messages-legacy_message', $status)
				: self::get_success_message($status)
			;
			if ($message) $content = '<div id="eab-success-notice" class="message success">' . esc_html($message) . '</div>';
		}
		
		$content .= isset($_GET['eab_error_msg'])
		 	? '<div id="eab-error-notice" class="message error">'.esc_html(stripslashes($_GET['eab_error_msg'])).'</div>'
		 	: ''
		 ;	
		return $content;
	}
	
	public static function get_rsvp_form ($post) {
		global $current_user;
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		    
		$content = '';
		$content .= '<div class="wpmudevevents-buttons">';
		
		if ($event->is_open()) {
			if (is_user_logged_in()) {
				$booking_id = $event->get_user_booking_id();
				$booking_status = $event->get_user_booking_status();
				$default_class = $booking_status ? 'ncurrent' : '';

				$content .= '<form action="' . get_permalink($event->get_id()) . '" method="post" id="eab_booking_form">';
				$content .= '<input type="hidden" name="event_id" value="' . $event->get_id() . '" />';
				$content .= '<input type="hidden" name="user_id" value="' . $booking_id . '" />';
				$content .= apply_filters('eab-rsvps-button-no',
					'<input class="' .
						(($booking_id && $booking_status == 'no') ? 'current wpmudevevents-no-submit' : 'wpmudevevents-no-submit ' . $default_class) .
						'" type="submit" name="action_no" value="' . __('No', Eab_EventsHub::TEXT_DOMAIN) .
					'" '.(($booking_id && $booking_status == 'no') ? 'disabled="disabled"' : '').' />',
					$event->get_id()
				);
				$content .= apply_filters('eab-rsvps-button-maybe',
					'<input class="' . (($booking_id && $booking_status == 'maybe') ? 'current wpmudevevents-maybe-submit' : 'wpmudevevents-maybe-submit ' . $default_class) .
						'" type="submit" name="action_maybe" value="' . __('Maybe', Eab_EventsHub::TEXT_DOMAIN) . 
					'" '.(($booking_id && $booking_status == 'maybe') ? 'disabled="disabled"' : '').' />',
					$event->get_id()
				);
				$content .= apply_filters('eab-rsvps-button-yes',
					'<input class="' . (($booking_id && $booking_status == 'yes') ? 'current wpmudevevents-yes-submit' : 'wpmudevevents-yes-submit ' . $default_class) .
						'" type="submit" name="action_yes" value="' . __('I\'m attending', Eab_EventsHub::TEXT_DOMAIN) .
					'" '.(($booking_id && $booking_status == 'yes') ? 'disabled="disabled"' : '').'/>',
					$event->get_id()
				);
				$content .= '</form>';
			} else {
				$login_url_y = apply_filters('eab-rsvps-rsvp_login_page-yes', wp_login_url(get_permalink($event->get_id())) . '&eab=y');
				$login_url_m = apply_filters('eab-rsvps-rsvp_login_page-maybe', wp_login_url(get_permalink($event->get_id())) . '&eab=m');
				$login_url_n = apply_filters('eab-rsvps-rsvp_login_page-no', wp_login_url(get_permalink($event->get_id())) . '&eab=n');
				$content .= '<input type="hidden" name="event_id" value="' . $event->get_id() . '" />';
				$content .= apply_filters('eab-rsvps-button-no',
					'<a class="wpmudevevents-no-submit" href="' .
						$login_url_n .
					'" >'.__('No', Eab_EventsHub::TEXT_DOMAIN).'</a>',
					$event->get_id()
				);
				$content .= apply_filters('eab-rsvps-button-maybe',
					'<a class="wpmudevevents-maybe-submit" href="' .
						$login_url_m .
					'" >'.__('Maybe', Eab_EventsHub::TEXT_DOMAIN).'</a>',
					$event->get_id()
				);
				$content .= apply_filters('eab-rsvps-button-yes',
					'<a class="wpmudevevents-yes-submit" href="' .
						$login_url_y .
					'" >'.__('I\'m Attending', Eab_EventsHub::TEXT_DOMAIN).'</a>',
					$event->get_id()
				);
			}
		}
		
		$content .= '</div>';
	
		$content = apply_filters('eab-rsvps-rsvp_form', $content, $event);

		return $content;
	}
	
	public static function get_event_details ($post) {
		$content = '';
		$data = Eab_Options::get_instance();
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
		$content .= '<div class="wpmudevevents-date">' . self::get_event_dates($event) . '</div>';

		if ($event->has_venue()) {
			$venue = $event->get_venue_location(Eab_EventModel::VENUE_AS_ADDRESS);
			$content .= "<div class='wpmudevevents-location' itemprop='location' itemscope itemtype='http://schema.org/Place'>
                            <span itemprop='name'>{$venue}</span>
                            <span itemprop='address' itemscope itemtype='http://schema.org/PostalAddress'></span>
                        </div>";
                            
		}
		if ($event->is_premium()) {
			$price = $event->get_price();
			$currency = $data->get_option('currency');
			$amount = is_numeric($price) ? number_format($price, 2) : $price;
			$content .= apply_filters('eab-events-event_details-price', "<div class='wpmudevevents-price'>{$currency} {$amount}</div>", $event->get_id());
		}
		$data = apply_filters('eab-events-after_event_details', '', $event);
		if ($data) {
			$content .= '<div class="wpmudevevents-additional_details">' . $data . '</div>';
		}
		
		return $content;
	}

	public static function get_event_dates ($post) {
		$content = '';
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
		$start_dates = $event->get_start_dates();
		if (!$start_dates) return $content;
		foreach ($start_dates as $key => $start) {
			$start = $event->get_start_timestamp($key);
			$end = $event->get_end_timestamp($key);		
			
			$end_date_str = (date('Y-m-d', $start) != date('Y-m-d', $end)) 
				? date_i18n(get_option('date_format'), $end) : ''
			;
			
			$content .= $key ? __(' and ', Eab_EventsHub::TEXT_DOMAIN) : '';

			// Differentiate start/end date equality
			if ($end_date_str) {
				// Start and end day stamps differ
				$start_string = $event->has_no_start_time($key)
					? sprintf(__('On <span class="wpmudevevents-date_format-start"><var class="eab-date_format-date">%s</var></span>', Eab_EventsHub::TEXT_DOMAIN), date_i18n(get_option('date_format'), $start))
					: sprintf(__('On <var class="eab-date_format-date">%s</var> <span class="wpmudevevents-date_format-start">from <var class="eab-date_format-time">%s</var></span>', Eab_EventsHub::TEXT_DOMAIN), date_i18n(get_option('date_format'), $start), date_i18n(get_option('time_format'), $start))
				;
				$end_string = $event->has_no_end_time($key)
					? sprintf(__('<span class="wpmudevevents-date_format-end">to %s</span><br />', Eab_EventsHub::TEXT_DOMAIN), '<span class="wpmudevevents-date_format-end_date"><var class="eab-date_format-date">' . $end_date_str . '</var></span>')
					: sprintf(__('<span class="wpmudevevents-date_format-end">to %s</span><br />', Eab_EventsHub::TEXT_DOMAIN), '<span class="wpmudevevents-date_format-end_date"><var class="eab-date_format-date">' . $end_date_str . '</var></span> <span class="wpmudevevents-date_format-end_time"><var class="eab-date_format-time">' . date_i18n(get_option('time_format'), $end) . '</var></span>')
				;
			} else {
				// The start and end day stamps do NOT differ
				if (eab_current_time() > $start) {
					// In the past
					$start_string = $event->has_no_start_time($key)
						? sprintf(__('Took place on <span class="wpmudevevents-date_format-start"><var class="eab-date_format-date">%s</var></span>', Eab_EventsHub::TEXT_DOMAIN), date_i18n(get_option('date_format'), $start))
						: sprintf(__('Took place on <var class="eab-date_format-date">%s</var> <span class="wpmudevevents-date_format-start">from <var class="eab-date_format-time">%s</var></span>', Eab_EventsHub::TEXT_DOMAIN), date_i18n(get_option('date_format'), $start), date_i18n(get_option('time_format'), $start))
					;
				} else {
					// Now, or in the future
					$start_string = $event->has_no_start_time($key)
						? sprintf(__('Takes place on <span class="wpmudevevents-date_format-start"><var class="eab-date_format-date">%s</var></span>', Eab_EventsHub::TEXT_DOMAIN), date_i18n(get_option('date_format'), $start))
						: sprintf(__('Takes place on <var class="eab-date_format-date">%s</var> <span class="wpmudevevents-date_format-start">from <var class="eab-date_format-time">%s</var></span>', Eab_EventsHub::TEXT_DOMAIN), date_i18n(get_option('date_format'), $start), date_i18n(get_option('time_format'), $start))
					;
				}
				$end_string = $event->has_no_end_time($key)
					? ''
					: sprintf(__('<span class="wpmudevevents-date_format-end">to %s</span><br />', Eab_EventsHub::TEXT_DOMAIN), '<span class="wpmudevevents-date_format-end_date"><var class="eab-date_format-date">' . $end_date_str . '</var></span> <span class="wpmudevevents-date_format-end_time"><var class="eab-date_format-time">' . date_i18n(get_option('time_format'), $end) . '</var></span>')
				;
			}
			// Why, thank you `date_i18n` for working so well with properly parsing 'O' argument when offsets are set in UTC values... >.<
			$gmt_offset = (float)get_option('gmt_offset');
			$hour_tz = sprintf('%02d', abs((int)$gmt_offset));
			$minute_offset = (abs($gmt_offset) - abs((int)$gmt_offset)) * 60;
			$min_tz = sprintf('%02d', $minute_offset);
			$timezone = ($gmt_offset > 0 ? '+' : '-') . $hour_tz . $min_tz;

			$time_date_start = esc_attr(date_i18n("Y-m-d\TH:i:s", $start)) . $timezone;
			$time_date_end = esc_attr(date_i18n("Y-m-d\TH:i:s", $end)) . $timezone;
			$content .= apply_filters('eab-events-event_date_string', "<time itemprop='startDate' datetime='{$time_date_start}'>{$start_string}</time> <time itemprop='endDate' datetime='{$time_date_end}'>{$end_string}</time>", $event->get_id(), $start, $end);
			/*
			$content .= apply_filters('eab-events-event_date_string', sprintf(
				__('On %s <span class="wpmudevevents-date_format-start">from %s</span> <span class="wpmudevevents-date_format-end">to %s</span><br />', Eab_EventsHub::TEXT_DOMAIN),
				'<span class="wpmudevevents-date_format-start_date">' . date_i18n(get_option('date_format'), $start) . '</span>',
				'<span class="wpmudevevents-date_format-start_time">' . date_i18n(get_option('time_format'), $start) . '</span>',
				'<span class="wpmudevevents-date_format-end_date">' . $end_date_str . '</span> <span class="wpmudevevents-date_format-end_time">' . date_i18n(get_option('time_format'), $end) . '</span>'
			), $event->get_id(), $start, $end);
			*/
		}
		return $content;
	}

	public static function get_rsvp_status_list () {
		return array(
			Eab_EventModel::BOOKING_YES => __('Attending', Eab_EventsHub::TEXT_DOMAIN), 
			Eab_EventModel::BOOKING_MAYBE => __('Maybe', Eab_EventsHub::TEXT_DOMAIN), 
			Eab_EventModel::BOOKING_NO => __('No', Eab_EventsHub::TEXT_DOMAIN)
		);
	}

	public static function get_status_class ($post) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		$status = $event->get_status();
		return apply_filters('eab-render-css_classes', sanitize_html_class($status), $event->get_id());
	}

	public static function get_success_message_code ($status=false) {
		$status = $status ? $status : Eab_EventModel::BOOKING_YES;

		$legacy_redirects = apply_filters(
			'eab-rsvps-status_messages-legacy_redirects',
			(defined('EAB_RSVPS_LEGACY_REDIRECTS') && EAB_RSVPS_LEGACY_REDIRECTS)
		);

		$value = $legacy_redirects
			? self::get_success_message($status)
			: $status
		;
		return urlencode($value);
	}	

	public static function get_success_message ($status=false) {
		$status = $status ? $status : Eab_EventModel::BOOKING_YES;

		$map = apply_filters('eab-rsvps-status_messages-map', array(
			Eab_EventModel::BOOKING_YES => __("Excellent! We've got you marked as coming and we'll see you there!", Eab_EventsHub::TEXT_DOMAIN),
			Eab_EventModel::BOOKING_MAYBE => __("Thanks for letting us know. Hopefully you'll be able to make it!", Eab_EventsHub::TEXT_DOMAIN),
			Eab_EventModel::BOOKING_NO => __("That's too bad you won't be able to make it", Eab_EventsHub::TEXT_DOMAIN),
		));
		return isset($map[$status])
			? $map[$status]
			: apply_filters('eab-rsvps-status_messages-fallback_message', false)
		;
	}

	public static function get_pagination ($permalink, $total, $current) {
		$pagination = paginate_links(array(
			'base' => "{$permalink}%_%",
			'total' => $total,
			'current' => $current,
		));
		return "<div class='eab-pagination'>{$pagination}</div>";
	}

	public static function get_shortcode_paging ($events_query, $args) {
		global $post;

		$current = $args['page'];
		$total = $events_query->max_num_pages;
		return eab_call_template('get_pagination', get_permalink($post->ID), $total, $current);
	}

	public static function get_shortcode_archive_output ($events, $args) {
		$out = '<section class="eab-events-archive ' . $args['class'] . '">';
		foreach ($events as $event) {
			$event = $event instanceof Eab_EventModel ? $event : new Eab_EventModel($event);
			$out .= '<article class="eab-event ' . eab_call_template('get_status_class', $event) . '" id="eab-event-' . $event->get_id() . '">' .
				'<h4>' . $event->get_title() . '</h4>' .
				'<div class="eab-event-body">' .
					eab_call_template('get_archive_content', $event, $args) .
				'</div>' .
			'</article>';
		}
		$out .= '</section>';
		return $out;
	}

	public static function get_shortcode_calendar_output ($events, $args) {
		if (!class_exists('Eab_CalendarTable_EventShortcodeCalendar')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_calendar_helper.php';
		$renderer = new Eab_CalendarTable_EventShortcodeCalendar($events);

		$renderer->set_class($args['class']);
		$renderer->set_footer($args['footer']);
		$renderer->set_scripts(!$args['override_scripts']);
		$renderer->set_navigation($args['navigation']);
		$renderer->set_track($args['track']);
		$renderer->set_title_format($args['title_format']);
		$renderer->set_short_title_format($args['short_title_format']);
		$renderer->set_long_date_format($args['long_date_format']);
		$renderer->set_thumbnail($args);
		$renderer->set_excerpt($args);

		return '<section class="wpmudevevents-list">' . $renderer->get_month_calendar($args['date']) . '</section>';
	}

	public static function get_shortcode_events_map_marker_body_output ($event, $args) {
		$class_pfx = !empty($args['class']) ? $args['class'] : 'eab-events_map';
		$content = '';
		if ($args['show_date']) $content .= eab_call_template('get_event_dates', $event);
		if ($args['show_excerpt']) $content .= '<div class="eab-event-excerpt">' . $event->get_excerpt_or_fallback($args['excerpt_length']) . '</div>';
		return "<div class='{$class_pfx}-venue'>" .
			'<a href="' . get_permalink($event->get_id()) . '">' .
				$event->get_venue_location() .
			'</a>' .
		"</div><div class='{$class_pfx}-dates'>" .
			$content .
		'</div>';
	}

	public static function get_shortcode_single_output ($event, $args) {
		return '<div class="eab-event ' . $args['class'] . '">' .
			'<h4>' . $event->get_title() . '</h4>' .
			'<div class="eab-event-body">' .
				eab_call_template('get_single_content', $event) .
			'</div>' .
		'</div>';
	}

/* ----- Utility methods ----- */

	public static function util_strlen ($str) {
		return preg_match_all ('/./u', $str, $m);
	}

	public static function util_substr ($str, $start=0, $length=false) {
		if (!$str) return false;

		if (!is_numeric($start)) $start = 0;

		$strlen = self::util_strlen($str);
		$max = $strlen - $start;
		$length = $length 
			? ($length > $max ? $max : $length)
			: $strlen
		;
		
		return $start
			? preg_replace ('/^(.{'.$length.'}).*$/mu', '\1', $str)
			: preg_replace ('/^.{'.$start.'}(.{'.$length.'}).*$/mu', '\1', $str)
		;
	}

	public static function util_safe_substr ($str, $start=0, $length=false) {
		return self::util_substr(wp_strip_all_tags($str), $start, $length);
	}
	
	public static function util_words_limit ($str, $count=false, $default_suffix='... ') {
		if (!$count) return $str;
		$str = preg_replace('/\s+/', ' ', $str);
		$words = explode(' ', $str);
		
		return count($words) <= $count
			? $str
			: join(' ', array_slice($words, 0, $count)) . $default_suffix;
		;
	}

	public static function util_locate_template ($template) {
		$path = false;
		if (!$template) return $path;

		$file = basename(trim($template));
		$file = preg_match('/\.php$/', $file) ? $file : "{$file}.php";

		// Check theme dir first
		if (file_exists(get_stylesheet_directory() . '/' . $file)) {
			$path = get_stylesheet_directory() . '/' . $file;
		} else if (file_exists(get_template_directory() . '/' . $file)) {
			$path = get_template_directory() . '/' . $file;
		}
		if ($path) return $path;

		// Check fallback directory
		if (file_exists(EAB_PLUGIN_DIR . "default-templates/{$file}")) return EAB_PLUGIN_DIR . "default-templates/{$file}";
		return false;
	}

	public static function util_get_default_template_style ($template) {
		$style = false;
		if (!$template) return $style;

		$template = basename(trim($template));
		$style_path = file_exists(EAB_PLUGIN_DIR . "default-templates/{$template}/events.css");
		return $style_path ? plugins_url(EAB_PLUGIN_BASENAME . "/default-templates/{$template}/events.css") : false;
	}

	public static function util_apply_shortcode_template ($events, $args) {
		$output = false;
		// Check template output
		if (eab_has_template_method($args['template'])) {
			// Template class method call
			$output = eab_call_template($args['template'], $events, $args);
		} else {
			// Template file
			$template = eab_call_template('util_locate_template', $args['template']);
			if ($template) {
				ob_start();
				include($template);
				$output = ob_get_clean();
			}
		}
		return $output;
	}

	private static function _shortcode_arg_type_map_values ($raw_type, $argument, $tag) {
		$type_map = array(
			'boolean' => array(
				'type' => __('boolean', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"yes" or "no"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="yes"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'integer' => array(
				'type' => __('integer', Eab_EventsHub::TEXT_DOMAIN),
				'value' => 'numeric value',
				'example' => sprintf(__('%s="326"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string' => array(
				'type' => __('string', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"mystring"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="mystring"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:date' => array(
				'type' => __('date', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('date format string', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="2011-11-18"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:date_format' => array(
				'type' => __('date format', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('format string for dates', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="Y-m-d"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:date_strtotime' => array(
				'type' => sprintf(__('<a href="%s" target="_blank">strtotime</a>-compatible string', Eab_EventsHub::TEXT_DOMAIN), 'http://www.php.net/manual/en/datetime.formats.relative.php'),
				'value' => __('strtotime-compatible expression', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf('%s="+1 month"', $argument),
			),
			'string:or_integer' => array(
				'type' => __('string or integer', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"some_text" or "212"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="some_text"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:sort' => array(
				'type' => __('ordering keyword', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"ASC" or "DESC"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="ASC"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:id_list' => array(
				'type' => __('list of comma-separated IDs', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"52,26,18"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="52,26,18"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:list' => array(
				'type' => __('list of comma-separated strings', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"foo,bar,baz"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="foo,bar,baz"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
			'string:url' => array(
				'type' => __('valid URL', Eab_EventsHub::TEXT_DOMAIN),
				'value' => __('"http://example.com/something"', Eab_EventsHub::TEXT_DOMAIN),
				'example' => sprintf(__('%s="http://example.com/something"', Eab_EventsHub::TEXT_DOMAIN), $argument),
			),
		);

		$type = !empty($type_map[$raw_type])
			? $type_map[$raw_type]
			: false
		;
		$title = sprintf(__("%s, e.g. [%s ... %s]", Eab_EventsHub::TEXT_DOMAIN), $type['value'], $tag, $type['example']);
		return array(
			'type' => $type['type'],
			'title' => $title,
		);
	}

	public static function util_shortcode_argument_type_string_info ($raw_type, $argument, $tag, $tips=false) {
		if (!is_object($tips)) return Eab_Template::util_shortcode_argument_type_string($raw_type, $argument, $tag);
		$resolved = Eab_Template::_shortcode_arg_type_map_values($raw_type, $argument, $tag);
		if (empty($resolved['type']) || empty($resolved['title'])) return "<code>({$raw_type})</code>";

		return "<span>({$resolved['type']})</span>&nbsp;" . $tips->add_tip($resolved['title']);
	}

	public static function util_shortcode_argument_type_string ($raw_type, $argument, $tag) {
		$resolved = Eab_Template::_shortcode_arg_type_map_values($raw_type, $argument, $tag);
		if (empty($resolved['type']) || empty($resolved['title'])) return "<code>({$raw_type})</code>";
		$title = 'title="' . esc_attr($resolved['title']) . '"';
		return "<abbr {$title}>({$resolved['type']})</abbr>";
	}
	
}