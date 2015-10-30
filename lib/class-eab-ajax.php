<?php

/**
 * Manage all AJAX actions
 */
class Eab_Ajax {

	public function __construct() {
		add_action('wp_ajax_nopriv_eab_paypal_ipn', array($this, 'process_paypal_ipn'));
		add_action('wp_ajax_eab_paypal_ipn', array($this, 'process_paypal_ipn'));
		add_action('wp_ajax_nopriv_eab_list_rsvps', array($this, 'process_list_rsvps'));
		add_action('wp_ajax_eab_list_rsvps', array($this, 'process_list_rsvps'));

		// End API login & form section
		add_action('wp_ajax_eab_cancel_attendance', array($this, 'handle_attendance_cancel'));
		add_action('wp_ajax_eab_delete_attendance', array($this, 'handle_attendance_delete'));
		add_action('wp_ajax_eab_add_attendance', array($this, 'handle_attendance_add'));
	}

	function process_paypal_ipn() {
		$req = 'cmd=_notify-validate';

		$request = $_REQUEST;

		$post_values = "";
		$cart = array();
		foreach ($request as $key => $value) {
			$value = urlencode(stripslashes($value));
			$req .= "&$key=$value";
			$post_values .= " $key : $value\n";
		}
		$pay_to_email = $request['receiver_email'];
		$pay_from_email = $request['payer_email'];
		$transaction_id = $request['txn_id'];

		$status = $request['payment_status'];
		$amount = $request['mc_gross'];
		$ticket_count = $request['quantity']; // Ticket count is the number of paid for tickets
		$currency = $request['mc_currency'];
		$test_ipn = $request['test_ipn'];
		$event_id = $request['item_number'];
		$booking_id = (int)$request['booking_id'];
		$blog_id = (int)$request['blog_id'];

		if (is_multisite()) switch_to_blog($blog_id);
		$eab_options = get_option('incsub_event_default');

		$header = "";
		// post back to PayPal system to validate
		$header .= "POST /cgi-bin/webscr HTTP/1.1\r\n";
		// Sandbox host: http://stackoverflow.com/questions/17477815/receiving-error-invalid-host-header-from-paypal-ipn
		if ((int)@$eab_options['paypal_sandbox'] == 1) $header .= "Host: www.sandbox.paypal.com\r\n";
		else $header .= "Host: www.paypal.com\r\n";

		// End host
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Connection: Close\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

		if ((int)@$eab_options['paypal_sandbox'] == 1) {
			$fp = fsockopen ('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
		} else {
			$fp = fsockopen ('ssl://www.paypal.com', 443, $errno, $errstr, 30);
		}

		define( 'EAB_PROCESSING_PAYPAL_IPN', true );

		$booking_obj = Eab_EventModel::get_booking($booking_id);

		if (!$booking_obj || !$booking_obj->id) {
			header('HTTP/1.0 404 Not Found');
			header('Content-type: text/plain; charset=UTF-8');
			print 'Booking not found';
			exit(0);
		}

		if ($booking_obj->event_id != $event_id) {
			header('HTTP/1.0 404 Not Found');
			header('Content-type: text/plain; charset=UTF-8');
			print 'Fake event id. REF: PP0';
			exit(0);
		}

		if (@$eab_options['currency'] != $currency) {
			header('HTTP/1.0 400 Bad Request');
			header('Content-type: text/plain; charset=UTF-8');
			print 'We were not expecting you. REF: PP1';
			exit(0);
		}

		if ($amount != $ticket_count * apply_filters('eab-payment-event_price-for_user', get_post_meta($event_id, 'incsub_event_fee', true), $event_id, $booking_obj->user_id)) {
			header('HTTP/1.0 400 Bad Request');
			header('Content-type: text/plain; charset=UTF-8');
			print 'We were not expecting you. REF: PP2';
			exit(0);
		}

		if (!$ticket_count) {
			header('HTTP/1.0 400 Bad Request');
			header('Content-type: text/plain; charset=UTF-8');
			print 'Cheapskate. REF: PP2';
			exit(0);
		}

		if (strtolower($pay_to_email) != strtolower(@$eab_options['paypal_email'])) {
			header('HTTP/1.0 400 Bad Request');
			header('Content-type: text/plain; charset=UTF-8');
			print 'We were not expecting you. REF: PP3';
			exit(0);
		}

		if (!$fp) {
			header('HTTP/1.0 400 Bad Request');
			header('Content-type: text/plain; charset=UTF-8');
			print 'We were not expecting you. REF: PP4';
			exit(0);
		} else {
			fputs ($fp, $header . $req);
			while (!feof($fp)) {
				$res = trim(fgets ($fp, 1024));
				if (strcmp ($res, "VERIFIED") == 0) break;
				if (strcmp ($res, "INVALID") == 0) break;
			}
			if (strcmp ($res, "VERIFIED") == 0) {
				if ($test_ipn == 1) {
					if ((int)@$eab_options['paypal_sandbox'] == 1) {
						// Sandbox, it's allowed so do stuff
						Eab_EventModel::update_booking_meta($booking_obj->id, 'booking_transaction_key', $transaction_id);
						Eab_EventModel::update_booking_meta($booking_obj->id, 'booking_ticket_count', $ticket_count);
						do_action('eab-ipn-event_paid', $event_id, $amount, $booking_obj->id);
					} else {
						// Sandbox, not allowed, bail out
						header('HTTP/1.0 400 Bad Request');
						header('Content-type: text/plain; charset=UTF-8');
						print 'We were not expecting you. REF: PP1';
						exit(0);
					}
				} else {
					// Paid
					Eab_EventModel::update_booking_meta($booking_obj->id, 'booking_transaction_key', $transaction_id);
					Eab_EventModel::update_booking_meta($booking_obj->id, 'booking_ticket_count', $ticket_count);
					do_action('eab-ipn-event_paid', $event_id, $amount, $booking_obj->id);
				}
				header('HTTP/1.0 200 OK');
				header('Content-type: text/plain; charset=UTF-8');
				print 'Success';
				exit(0);
			} else if (strcmp ($res, "INVALID") == 0) {
				$message = "Invalid PayPal IPN $transaction_id";
			}
			fclose ($fp);
		}
		if (is_multisite()) restore_current_blog();
		header('HTTP/1.0 200 OK');
		header('Content-type: text/plain; charset=UTF-8');
		print 'Thank you very much for letting us know. REF: '.$message;
		exit(0);
	}

	function process_list_rsvps() {
		global $post;

		$post = get_post($_REQUEST['pid']);
		echo Eab_Template::get_rsvps($post);

		exit(0);
	}

	function handle_attendance_cancel () {
		$eab = events_and_bookings();
		$user_id = (int)$_POST['user_id'];
		$post_id = (int)$_POST['post_id'];

		$post = get_post($post_id);
		$event = new Eab_EventModel($post);
		$event->cancel_attendance($user_id);
		echo $eab->meta_box_part_bookings($post);
		die;
	}

	function handle_attendance_delete () {
		$eab = events_and_bookings();
		$user_id = (int)$_POST['user_id'];
		$post_id = (int)$_POST['post_id'];

		$post = get_post($post_id);
		$event = new Eab_EventModel($post);
		$event->delete_attendance($user_id);
		echo $eab->meta_box_part_bookings($post);
		die;
	}

	function handle_attendance_add () {
		$eab = events_and_bookings();
		$data = stripslashes_deep($_POST);
		$email = $data['user'];
		$status = $data['status'];
		$post_id = (int)$data['post_id'];
		$allowed = array(Eab_EventModel::BOOKING_YES, Eab_EventModel::BOOKING_NO, Eab_EventModel::BOOKING_MAYBE);

		$post = get_post($post_id);
		if (is_email($email) && $post_id && in_array($status, $allowed)) {
			$user = get_user_by('email', $email);
			if ($user && !empty($user->ID)) {
				$event = new Eab_EventModel($post);
				$event->add_attendance($user->ID, $status);
			}
		}
		echo $eab->meta_box_part_bookings($post);
		die;
	}
}

new Eab_Ajax();