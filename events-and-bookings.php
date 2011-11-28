<?php
/*
 Plugin Name: Events and Booking
 Plugin URI: http://premium.wpmudev.org/project/events-and-booking
 Description: 
 Author: S H Mohanjith (Incsub)
 WDP ID: 
 Version: 1.0.0
 Author URI: http://premium.wpmudev.org
*/
/**
 * @global      object  $booking   Convenient access to the booking object
 */
global $booking;

/**
 * Booking object (PHP4 compatible)
 * 
 * Allow your readers to register for events you organize
 * 
 * @since 1.0.0
 * @author S H Mohanjith <moha@mohanjith.net>
 */
class Booking {
    
    /**
     * @todo Update version number for new releases
     * 
     * @var		string	$_current_version	Current version
     */
    var $_current_version = '1.0.0';
    
    /**
     * @static		string	$_translation_domain	Translation domain
     */
    static $_translation_domain = 'eab';
    
    /**
     * @var		array	$_options		Consolidated options
     */
    var $_options = array();
    
    /**
     * Get the table name with prefixes
     * 
     * @global	object	$wpdb
     * @param	string	$table	Table name
     * @return	string			Table name complete with prefixes
     */
    function tablename($table) {
	global $wpdb;
    	// We use a single table for all events accross the network
	return $wpdb->prefix.'eab_'.$table;
    }
    
    /**
     * Initializing object
     * 
     * Plugin register actions, filters and hooks. 
     */
    function Booking() {
	global $wpdb;
	
	// Activation deactivation hooks
	register_activation_hook(__FILE__, array(&$this, 'install'));
	register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));
	
	// Actions
	add_action('init', array(&$this, 'init'), 0);
	
	add_action('admin_menu', array(&$this, 'admin_menu'));
	add_action('option_rewrite_rules', array(&$this, 'check_rewrite_rules'));
	
	add_action('wp_print_styles', array(&$this, 'wp_print_styles'));
	add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));
	
	add_action( 'manage_incsub_event_posts_custom_column', array(&$this, 'manage_posts_custom_column') );
	
	add_action('add_meta_boxes_incsub_event', array(&$this, 'meta_boxes') );
	add_action('wp_insert_post', array(&$this, 'save_event_meta'), 10, 2 );
	add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts') );
	add_action('admin_print_styles', array(&$this, 'admin_print_styles') );
	add_action('widgets_init', array(&$this, 'widgets_init'));
	
	add_action('wp_ajax_eab_paypal_ipn', array(&$this, 'process_paypal_ipn'));
	
	add_filter('single_template', array( &$this, 'handle_single_template' ) );
	add_filter('archive_template', array( &$this, 'handle_template' ) );
	add_filter('template_include', array( &$this, 'handle_template' ) );
	
	add_filter('rewrite_rules_array', array(&$this, 'add_rewrite_rules'));
	add_filter('post_type_link', array(&$this, 'post_type_link'), 10, 3);
	
	add_filter('manage_incsub_event_posts_columns', array(&$this, 'manage_posts_columns') );
	add_filter('query_vars', array(&$this, 'query_vars') );
	add_filter('cron_schedules', array(&$this, 'cron_schedules') );
	
	add_filter('views_edit-incsub_event', array(&$this, 'views_list') );
	add_filter('agm_google_maps-post_meta-address', array(&$this, 'agm_google_maps_post_meta_address'));
	add_filter('agm_google_maps-options', array(&$this, 'agm_google_maps_options'));
	
	add_filter('user_has_cap', array(&$this, 'user_has_cap'), 10, 3);
	
	$this->_options['default'] = get_option('incsub_event_default',
	    array('currency' => 'USD', 'slug' => 'events', 'accept_payments' => 1, 'paypal_email' => '', 'paypal_sandbox' => 0));
    }
    
    /**
     * Initialize the plugin
     * 
     * @see		http://codex.wordpress.org/Plugin_API/Action_Reference
     * @see		http://adambrown.info/p/wp_hooks/hook/init
     */
    function init() {
	global $wpdb, $wp_rewrite, $current_user, $blog_id;
	
	if (preg_match('/mu\-plugin/', PLUGINDIR) > 0) {
	    load_muplugin_textdomain($this->_translation_domain, dirname(plugin_basename(__FILE__)).'/languages');
	} else {
	    load_plugin_textdomain($this->_translation_domain, false, dirname(plugin_basename(__FILE__)).'/languages');
	}
	
	$labels = array(
	    'name' => __('Events', $this->_translation_domain),
	    'singular_name' => __('Event', $this->_translation_domain),
	    'add_new' => __('Add Event', $this->_translation_domain),
	    'add_new_item' => __('Add New Event', $this->_translation_domain),
	    'edit_item' => __('Edit Event', $this->_translation_domain),
	    'new_item' => __('New Event', $this->_translation_domain),
	    'view_item' => __('View Event', $this->_translation_domain),
	    'search_items' => __('Search Event', $this->_translation_domain),
	    'not_found' =>  __('No event found', $this->_translation_domain),
	    'not_found_in_trash' => __('No event found in Trash', $this->_translation_domain),
	    'menu_name' => __('Events', $this->_translation_domain)
	);
	
	$supports = array( 'title', 'editor', 'author', 'venue', 'thumbnail', 'comments');
	
	register_post_type( 'incsub_event',
	    array(
		'labels' => $labels,
		'public' => true,
		'show_ui' => true,
		'publicly_queryable' => true,
		'capability_type' => 'event',
		'hierarchical' => false,
		'map_meta_cap' => true,
		'query_var' => true,
		'supports' => $supports,
		'rewrite' => array( 'slug' => $this->_options['default']['slug'], 'with_front' => false ),
		'has_archive' => true,
		'menu_icon' => plugins_url('events-and-bookings/img/small-greyscale.png'),
	    )
	);
	
	register_post_status( 'expire', array(
  		'label'       => __('Expired', $this->_translation_domain),
  		'label_count' => array( __('Expired <span class="count">(%s)</span>', $this->_translation_domain), __('Expired <span class="count">(%s)</span>', $this->_translation_domain) ),
  		'post_type'   => 'incsub_event',
  		'protected'      => true
  	) );
	
	$event_structure = '/'.$this->_options['default']['slug'].'/%event_year%/%event_monthnum%/%incsub_event%';
	
	$wp_rewrite->add_rewrite_tag("%incsub_event%", '(.+?)', "incsub_event=");
	$wp_rewrite->add_rewrite_tag("%event_year%", '([0-9]{4})', "event_year=");
	$wp_rewrite->add_rewrite_tag("%event_monthnum%", '([0-9]{2})', "event_monthnum=");
	$wp_rewrite->add_permastruct('incsub_event', $event_structure, false);
	
	wp_register_script('eab_jquery_ui', plugins_url('events-and-bookings/js/jquery-ui.custom.min.js'), array('jquery'), $this->current_version);
	wp_register_script('eab_admin_js', plugins_url('events-and-bookings/js/eab-admin.js'), array('jquery'), $this->current_version);
	wp_register_script('eab_event_js', plugins_url('events-and-bookings/js/eab-event.js'), array('jquery'), $this->current_version);
	
	wp_register_style('eab_jquery_ui', plugins_url('events-and-bookings/css/smoothness/jquery-ui-1.8.16.custom.css'), null, $this->current_version);
	wp_register_style('eab_admin', plugins_url('events-and-bookings/css/admin.css'), null, $this->current_version);
	
	wp_register_style('eab_front', plugins_url('events-and-bookings/css/front.css'), null, $this->current_version);
	
	if (get_option('eab_expiring_scheduled', false) == false) {
	    wp_schedule_event(time()+10, 'thirtyminutes', 'eab_expire_events');
	    update_option('eab_expiring_scheduled', true);
	}
	
	if (isset($_POST['event_id']) && isset($_POST['user_id'])) {
	    $booking_actions = array('yes' => 'yes', 'maybe' => 'maybe', 'no' => 'no');
	    
	    $event_id = intval($_POST['event_id']);
	    $booking_action = $booking_actions[$_POST['action_yes']];
	    
	    do_action( 'incsub_event_booking', $event_id, $current_user->ID, $booking_action );
	    if (isset($_POST['action_yes'])) {
		$wpdb->query(
		    $wpdb->prepare("INSERT INTO ".Booking::tablename('bookings')." VALUES(null, %d, %d, NOW(), 'yes') ON DUPLICATE KEY UPDATE `status` = 'yes';", $event_id, $current_user->ID)
		);
		// TODO: Add to BP activity stream
		do_action( 'incsub_event_booking_yes', $event_id, $current_user->ID );
		$this->recount_bookings($event_id);
		wp_redirect('?eab_success_msg='.urlencode('Your response recorded'));
		exit();
	    }
	    if (isset($_POST['action_maybe'])) {
		$wpdb->query(
		    $wpdb->prepare("INSERT INTO ".Booking::tablename('bookings')." VALUES(null, %d, %d, NOW(), 'maybe') ON DUPLICATE KEY UPDATE `status` = 'maybe';", $event_id, $current_user->ID)
		);
		// TODO: Add to BP activity stream
		do_action( 'incsub_event_booking_maybe', $event_id, $current_user->ID );
		$this->recount_bookings($event_id);
		wp_redirect('?eab_success_msg='.urlencode('Your response recorded'));
		exit();
	    }
	    if (isset($_POST['action_no'])) {
		$wpdb->query(
		    $wpdb->prepare("INSERT INTO ".Booking::tablename('bookings')." VALUES(null, %d, %d, NOW(), 'no') ON DUPLICATE KEY UPDATE `status` = 'no';", $event_id, $current_user->ID)
		);
		// TODO: Remove from BP activity stream
		do_action( 'incsub_event_booking_no', $event_id, $current_user->ID );
		$this->recount_bookings($event_id);
		wp_redirect('?eab_success_msg='.urlencode('Your response recorded'));
		exit();
	    }
	}
	
	
	if (wp_verify_nonce($_POST['_wpnonce'], 'incsub_event-update-options')) {
	    $this->_options['default']['slug'] = $_POST['event_default']['slug'];
	    $this->_options['default']['accept_payments'] = $_POST['event_default']['accept_payments'];
	    $this->_options['default']['currency'] = $_POST['event_default']['currency'];
	    $this->_options['default']['paypal_email'] = $_POST['event_default']['paypal_email'];
	    update_option('incsub_event_default', $this->_options['default']);
	    wp_redirect('edit.php?post_type=incsub_event&page=eab_settings&incsub_event_settings_saved=1');
	    exit();
	}
    }
    
    function recount_bookings($event_id) {
	global $wpdb;
	
	// Yes
	$yes_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Booking::tablename('bookings')." WHERE `status` = 'yes' AND event_id = %d;", $event_id));
    	update_post_meta($event_id, 'incsub_event_yes_count', $yes_count);
	
	// Maybe
	$maybe_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Booking::tablename('bookings')." WHERE `status` = 'maybe' AND event_id = %d;", $event_id));
    	update_post_meta($event_id, 'incsub_event_maybe_count', $maybe_count);
	update_post_meta($event_id, 'incsub_event_attending_count', $maybe_count+$yes_count);
	
	// No
	$no_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Booking::tablename('bookings')." WHERE `status` = 'no' AND event_id = %d;", $event_id));
	update_post_meta($event_id, 'incsub_event_no_count', $no_count);
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
	
	$header = "";
	// post back to PayPal system to validate
	$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
	$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
	
	if ($this->_options['default']['paypal_sandbox'] == 1) {
	    $fp = fsockopen ('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
	} else {
	    $fp = fsockopen ('ssl://www.paypal.com', 443, $errno, $errstr, 30);
	}
	
	$ip = $ip;
	
	$pay_to_email = $request['receiver_email'];
	$pay_from_email = $request['payer_email'];
	$transaction_id = $request['txn_id'];
	
    	$status = $request['payment_status'];
	$amount = $request['mc_gross'];
	$currency = $request['mc_currency'];
	$test_ipn = $request['test_ipn'];
	$event_id = $request['item_number'];
	
	$booking_id = $request['booking_id'];
	$booking_obj = get_booking($booking_id);
	
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
	
	if ($this->_options['default']['currency'] != $currency) {
	    header('HTTP/1.0 400 Bad Request');
	    header('Content-type: text/plain; charset=UTF-8');
	    print 'We were not expecting you. REF: PP1';
	    exit(0);
	}
	
	if ($amount != get_post_meta($event_id, 'incsub_event_fee', true)) {	    
	    header('HTTP/1.0 400 Bad Request');
	    header('Content-type: text/plain; charset=UTF-8');
	    print 'We were not expecting you. REF: PP2';
    	    exit(0);
	}
	
	if ($pay_to_email != $this->_options['default']['paypal_address']) {
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
		$res = fgets ($fp, 1024);
		
		if (strcmp ($res, "VERIFIED") == 0) {
		    if ($this->status == "Completed") {
			if ($this->test_ipn == 1) {
			    if ($this->_options['default']['paypal_sandbox'] == 1) {
				// Sandbox
			    }
			} else {
			    // Paid
			    update_booking_meta($booking_id, 'booking_transaction_key', $txn_id);
			}
			header('HTTP/1.0 200 OK');
			header('Content-type: text/plain; charset=UTF-8');
			print 'Success';
		        exit(0);
	    	    } else {
			$message = "Corrupted PayPal IPN $txn_id, $count, $receiver_email";
		    }
		} else if (strcmp ($res, "INVALID") == 0) {
		    $message = "Invalid PayPal IPN $txn_id";
		}
	    }
	    fclose ($fp);
    	}
	
	header('HTTP/1.0 200 OK');
	header('Content-type: text/plain; charset=UTF-8');
	print 'Thank you very much for letting us know. REF: '.$message;
	exit(0);
    }
    
    function agm_google_maps_post_meta_address($location) {
	global $post;
	
	if (!$location && $post->post_type == 'incsub_event') {
	    $meta = get_post_custom($post->ID);
	    
	    $venue = '';
	    if (isset($meta["incsub_event_venue"]) && isset($meta["incsub_event_venue"][0])) {
		$venue = stripslashes($meta["incsub_event_venue"][0]);
	    }
	    
	    return $venue;
	}
	return $location;
    }
    
    function agm_google_maps_options($opts) {
	$opts['use_custom_fields'] = 1;
	$opts['custom_fields_options']['associate_map'] = 1;
	$opts['custom_fields_options']['autoshow_map'] = 1;
	return $opts;
    }
    
    function handle_template( $path ) {
	global $wp_query, $post;
	
	if ( 'incsub_event' != $post->post_type )
	    return $path;
	
	$type = reset( explode( '_', current_filter() ) );
	
	$file = basename( $path );
	
	if ( empty( $path ) || "$type.php" == $file ) {
	    // A more specific template was not found, so load the default one
	    $path = EAB_PLUGIN_DIR . "default-templates/$type-incsub_event.php";
	}
	return $path;
    }
    
    function handle_single_template( $path ) {
	global $wp_query, $post;
	
	if ( 'incsub_event' != $post->post_type )
	    return $path;
	
	$type = reset( explode( '_', current_filter() ) );
	
	$file = basename( $path );
	
	if ( empty( $path ) || "$type.php" == $file ) {
	    // A more specific template was not found, so load the default one
	    add_filter('the_content', array(&$this, 'single_content'));
	    if (file_exists(get_stylesheet_directory().'/page.php')) {
		$path = get_stylesheet_directory().'/page.php';
	    } else {
		$path = get_template_directory().'/page.php';
	    }
	}
	return $path;
    }
    
    function single_content($content) {
	global $post, $current_user;
	
	$start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'incsub_event_start', true)));
        
	$new_content  = '';
	$new_content .= '<div class="eab-needtomove"><div id="event-bread-crumbs" >'.event_breadcrumbs(false).'</div></div>';
        
        $new_content .= '<div id="event-rsvp">'.the_eab_error_notice(false);
        if (!has_bookings()) {
	    $new_content .= '<div id="event-first-booking">'.__("Be the first to RSVP", Booking::$_translation_domain).'</div>';
        }
	$new_content .= event_rsvp_form(false);
        
        $new_content .= '</div>';
        
        $booking_id = get_booking_id($post->ID, $current_user->ID);
        
	if ($booking_id && in_array(get_booking_status($booking_id), array('yes', 'maybe')) &&
            get_post_meta($post->ID, 'incsub_event_paid', true) && !get_booking_paid($booking_id)) {
            $new_content .= '<div class="event-notice">';
            $new_content .= '<b>'.__('You haven\'t paid for this event.', Booking::$_translation_domain).'</b>';
            $new_content .= eab_payment_forms(false);
            $new_content .= '</div>';
        }
	
	$new_content .= '<div id="event-details">'.event_details(false).'</div>';
        $new_content .= $content;
	
	return $new_content;
    }
    
    function meta_boxes() {
	global $post, $current_user;
	
	add_meta_box('incsub-event', __('Event Details', $this->_translation_domain), array(&$this, 'event_meta_box'), 'incsub_event', 'normal', 'high');
	add_meta_box('incsub-event-bookings', __('Bookings', $this->_translation_domain), array(&$this, 'bookings_meta_box'), 'incsub_event', 'normal', 'high');
    }
    
    function admin_enqueue_scripts() {
	wp_enqueue_script('eab_jquery_ui');
	wp_enqueue_script('eab_admin_js');
    }
    
    function admin_print_styles() {	
	wp_enqueue_style('eab_jquery_ui');
	wp_enqueue_style('eab_admin');
    }
    
    function wp_print_styles() {
	global $wp_query;
	
	if ($wp_query->query_vars['post_type'] == 'incsub_event') {
	    wp_enqueue_style('eab_front');
	}
    }
    
    function wp_enqueue_scripts() {
	global $wp_query;
	
	if ($wp_query->query_vars['post_type'] == 'incsub_event') {
	    wp_enqueue_script('eab_event_js');
	}
    }
    
    function event_meta_box($echo = true) {
	global $post, $eab_user_logins;
	
	$content = '';
	$content .= $this->where_meta_box(false);
	$content .= $this->status_meta_box(false);
	if ($this->_options['default']['accept_payments']) {
	    $content .= $this->payments_meta_box(false);
	}
	$content .= '<div class="clear"></div>';
	$content .= $this->when_meta_box(false);
	$content .= '<div class="clear"></div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function where_meta_box($echo = true) {
	global $post;
	$meta = get_post_custom($post->ID);
	
	$venue = '';
	if (isset($meta["incsub_event_venue"]) && isset($meta["incsub_event_venue"][0])) {
	    $venue = stripslashes($meta["incsub_event_venue"][0]);
	}
	
	$content  = '';
	
	$content .= '<div class="eab_meta_box">';
	$content .= '<input type="hidden" name="incsub_event_where_meta" value="1" />';
	$content .= '<div class="misc-eab-section" ><label>';
	$content .= __('Where', $this->_translation_domain).':<br/>';
	$content .= '<textarea type="text" name="incsub_event_venue" size="20" >'.$venue.'</textarea>';
	$content .= '</label></div>';
	$content .= '</div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function when_meta_box($echo = true) {
	global $post;
	$meta = get_post_custom($post->ID);
	
	$content = '';
	
	$content .= '<div class="eab_meta_box">';
	$content .= '<div class="eab_meta_column_box">'.__('When', $this->_translation_domain).'</div>';
	
	$content .= '<input type="hidden" name="incsub_event_when_meta" value="1" />';
	
	$start = time();
	$end = time();
	
	$content .= '<div id="eab-add-more-rows">';
	if (isset($meta["incsub_event_start"])) {
	    for ($i=0; $i<count($meta["incsub_event_start"]); $i++) {
		if (isset($meta["incsub_event_start"]) && isset($meta["incsub_event_start"][$i])) {
		    $start = strtotime($meta["incsub_event_start"][$i]);
		}
		
		if (isset($meta["incsub_event_end"]) && isset($meta["incsub_event_end"][$i])) {
		    $end = strtotime($meta["incsub_event_end"][$i]);
		}
		
		$content .= '<div class="eab-section-block">';
		$content .= '<div class="eab-section-heading">'.sprintf(__('Part %d', $this->_translation_domain), $i+1).'</div>';
		$content .= '<div class="misc-eab-section eab-start-section"><label>';
		$content .= __('Start', $this->_translation_domain).':&nbsp;';
		$content .= '<input type="text" name="incsub_event_start[]" id="incsub_event_start_'.$i.'" class="incsub_event_picker" value="'.date('Y-m-d', $start).'" size="7" /> ';
		$content .= '<input type="text" name="incsub_event_start_time[]" id="incsub_event_start_time_'.$i.'" value="'.date('H:i', $start).'" size="3" />';
		$content .= '</label></div>';
		
		$content .= '<div class="misc-eab-section"><label>';
		$content .= __('End', $this->_translation_domain).':&nbsp;&nbsp;';
		$content .= '<input type="text" name="incsub_event_end[]" id="incsub_event_end_'.$i.'" class="incsub_event_picker" value="'.date('Y-m-d', $end).'" size="7" /> ';
		$content .= '<input type="text" name="incsub_event_end_time[]" id="incsub_event_end_time_'.$i.'" class="incsub_event_picker" value="'.date('H:i', $end).'" size="3" />';
		$content .= '</label></div>';
		$content .= '</div>';
	    }
	}
	$content .= '</div>';
	
	$content .= '<div id="eab-add-more-bank">';
	$content .= '<div class="eab-section-block">';
	$content .= '<div class="eab-section-heading">'.__('Part bank', $this->_translation_domain).'</div>';
	$content .= '<div class="misc-eab-section eab-start-section"><label>';
	$content .= __('Start', $this->_translation_domain).':&nbsp;';
	$content .= '<input type="text" name="incsub_event_start_b[]" id="incsub_event_start_bank" class="incsub_event_picker_b" value="" size="7" /> ';
	$content .= '<input type="text" name="incsub_event_start_time_b[]" id="incsub_event_start_time_bank" value="" size="3" />';
	$content .= '</label></div>';
	
	$content .= '<div class="misc-eab-section eab-end-section"><label>';
	$content .= __('End', $this->_translation_domain).':&nbsp;&nbsp;';
	$content .= '<input type="text" name="incsub_event_end_b[]" id="incsub_event_end_bank" class="incsub_event_picker_b" value="" size="7" /> ';
	$content .= '<input type="text" name="incsub_event_end_time_b[]" id="incsub_event_end_time_bank" class="incsub_event_picker" value="" size="3" />';
	$content .= '</label></div></div>';
	$content .= '</div>';
	
	$content .= '<div id="eab-add-more"><input type="button" name="eab-add-more-button" id="eab-add-more-button" value="+"/></div>';
	$content .= '</div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function status_meta_box($echo = true) {
	global $post, $eab_user_logins;
	$meta = get_post_custom($post->ID);
	
	$status = 'open';
	if (isset($meta["incsub_event_status"]) && isset($meta["incsub_event_status"][0])) {
	    $status = stripslashes($meta["incsub_event_status"][0]);
	}
	
	$content  = '';
	
	$content .= '<div class="eab_meta_box">';
	
	$content .= '<input type="hidden" name="incsub_event_status_meta" value="1" />';
	$content .= '<div class="misc-eab-section"><label>';
	$content .= __('Status', $this->_translation_domain).':&nbsp;';
	$content .= '<select name="incsub_event_status" >';
	$content .= '	<option value="open" '.(($status == 'open')?'selected="selected"':'').' >'.__('Open', $this->_translation_domain).'</option>';
	$content .= '	<option value="closed" '.(($status == 'closed')?'selected="selected"':'').' >'.__('Closed', $this->_translation_domain).'</option>';
	$content .= '	<option value="expired" '.(($status == 'expired')?'selected="selected"':'').' >'.__('Expired', $this->_translation_domain).'</option>';
	$content .= '	<option value="archived" '.(($status == 'archived')?'selected="selected"':'').' >'.__('Archived', $this->_translation_domain).'</option>';
	$content .= '</select>';
	$content .= '</label></div>';
	$content .= '<div class="clear"></div>';
	$content .= '</div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function payments_meta_box($echo = true) {
	global $post, $eab_user_logins;
	$meta = get_post_custom($post->ID);
	
	$content  = '';
	$content .= '<div class="eab_meta_box">';
	$content .= '<input type="hidden" name="incsub_event_payments_meta" value="1" />';
	$content .= '<div class="misc-eab-section">';
	$content .= '<label>'.__('Paid Event', $this->_translation_domain).':&nbsp;';
	$content .= '<input type="checkbox" name="incsub_event_paid" id="incsub_event_paid" class="incsub_event_paid" value="1" '.(($meta['incsub_event_paid'][0] == 1)?'checked="checked"':'').'/> ';
	$content .= '</label>';
	$content .= '<label class="incsub_event-fee_row"">'.__('Fee', $this->_translation_domain).':&nbsp;';
	$content .= $this->_options['default']['currency'].'&nbsp;<input type="text" name="incsub_event_fee" id="incsub_event_fee" class="incsub_event_fee" value="'.$meta['incsub_event_fee'][0].'" size="6" /> ';
	$content .= '</label>';
	$content .= '</div>';
	$content .= '</div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function bookings_meta_box($echo = true) {
	global $post, $eab_user_logins;
	$meta = get_post_custom($post->ID);
	
	$content  = '';
	
	$content .= '<input type="hidden" name="incsub_event_bookings_meta" value="1" />';
	$content .= '<div class="bookings-list-left">';
	
	if (has_bookings(false)) {
	    $content .= '<div id="event-booking-yes">';
            $content .= event_bookings('yes', false, true);
            $content .= '</div>';
                
            $content .= '<div id="event-booking-maybe">';
            $content .= event_bookings('maybe', false, true);
            $content .= '</div>';
	    
	    $content .= '<div id="event-booking-no">';
            $content .= event_bookings('no', false, true);
            $content .= '</div>';
	    
	    if (function_exists('messaging_init')) {
		$content .= '<div class="misc-eab-section"><a class="eab_notify_only eab_notify_all" target="_blank" href="'.admin_url('admin.php?page=messaging_new').'">';
		$content .= __("Message All", Booking::$_translation_domain).'</a>';
		
		if (count($eab_user_logins['yes']) > 0) {
		    $content .= '&nbsp;|&nbsp;<a class="eab_notify_only eab_notify_yes" target="_blank" href="'.admin_url('admin.php?page=messaging_new&message_to='.join(',',$eab_user_logins['yes'])).'">';
		    $content .= __("Message Yes", Booking::$_translation_domain).'</a>';
		}
		if (count($eab_user_logins['maybe']) > 0) {
		    $content .= '&nbsp;|&nbsp;<a class="eab_notify_only eab_notify_maybe" target="_blank" href="'.admin_url('admin.php?page=messaging_new&message_to='.join(',',$eab_user_logins['maybe'])).'">';
		    $content .= __("Message Maybe", Booking::$_translation_domain).'</a>';
		}
		if (count($eab_user_logins['no']) > 0) {
		    $content .= '&nbsp;|&nbsp;<a class="eab_notify_only eab_notify_no" target="_blank" href="'.admin_url('admin.php?page=messaging_new&message_to='.join(',',$eab_user_logins['no'])).'">';
		    $content .= __("Message No", Booking::$_translation_domain).'</a>';
		}
		$content .= '</div>';
	    }
        }  else {
            $content .= __('No bookings', $this->_translation_domain);
        }
	$content .= '</div>';
	$content .= '<div class="clear"></div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function save_event_meta($post_id, $post = null) {
	global $wpdb;
	
	//skip quick edit
	if ( defined('DOING_AJAX') )
	    return;
      
	if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_where_meta'] ) ) {
	    $meta = get_post_custom($post_id);
	    
	    update_post_meta($post_id, 'incsub_event_venue', $_POST['incsub_event_venue']);
	    
	    //for any other plugin to hook into
	    do_action( 'incsub_event_save_where_meta', $post_id, $meta );
	}
	
	if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_when_meta'] ) ) {
	    $meta = get_post_custom($post_id);
	    
	    if (isset($_POST['incsub_event_start']) && count($_POST['incsub_event_start']) > 0) {
		foreach ($_POST['incsub_event_start'] as $i => $event_start) {
		    if (isset($meta['incsub_event_start'][$i])) {
			if (!empty($_POST['incsub_event_start'][$i])) {
			    update_post_meta($post_id, 'incsub_event_start', date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_start'][$i]} {$_POST['incsub_event_start_time'][$i]}")), $meta['incsub_event_start'][$i]);
			} else {
			    delete_post_meta($post_id, 'incsub_event_start', $meta['incsub_event_start'][$i]);
			}
		    } else {
			if (!empty($_POST['incsub_event_start'][$i])) {
			    add_post_meta($post_id, 'incsub_event_start', date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_start'][$i]} {$_POST['incsub_event_start_time'][$i]}")));
			}
		    }
		    if (isset($meta['incsub_event_end'][$i])) {
			if (!empty($_POST['incsub_event_end'][$i])) {
			    update_post_meta($post_id, 'incsub_event_end', date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_end'][$i]} {$_POST['incsub_event_end_time'][$i]}")), $meta['incsub_event_end'][$i]);
			} else {
			    delete_post_meta($post_id, 'incsub_event_end', $meta['incsub_event_end'][$i]);
			}
		    } else {
			if (!empty($_POST['incsub_event_end'][$i])) {
			    add_post_meta($post_id, 'incsub_event_end', date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_end'][$i]} {$_POST['incsub_event_end_time'][$i]}")));
			}
		    }
		}
	    }
	    
	    //for any other plugin to hook into
	    do_action( 'incsub_event_save_when_meta', $post_id, $meta );
	}
	
	if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_status_meta'] ) ) {
	    $meta = get_post_custom($post_id);
	    
	    update_post_meta($post_id, 'incsub_event_status', $_POST['incsub_event_status']);
	    
	    //for any other plugin to hook into
	    do_action( 'incsub_event_save_status_meta', $post_id, $meta );
	}
	
	if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_payments_meta'] ) ) {
	    $meta = get_post_custom($post_id);
	    
	    update_post_meta($post_id, 'incsub_event_paid', $_POST['incsub_event_paid']);
	    update_post_meta($post_id, 'incsub_event_fee', $_POST['incsub_event_fee']);
	    
	    //for any other plugin to hook into
	    do_action( 'incsub_event_save_payments_meta', $post_id, $meta );
	}
	
	$bid = $wpdb->get_var("SELECT id FROM ".Booking::tablename('events')." WHERE id = {$post->ID};");
	if ($bid) {
	    $wpdb->query(
		$wpdb->prepare("UPDATE ".Booking::tablename('events')." SET user_id = %d, venue = %s, title = %s, start = %s, end = %s, next_event_id = %d, status = %s WHERE id = {$post->ID};",
		    $post->post_author, $_POST['incsub_event_venue'], $post->post_title, date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_start']} {$_POST['incsub_event_start_time']}")),
		    date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_end']} {$_POST['incsub_event_end_time']}")), 0, $_POST['incsub_event_status'])
	    );
	} else {
	    $wpdb->query(
		$wpdb->prepare(
		    "INSERT INTO ".Booking::tablename('events')." VALUES (%d, %d, %s, %s, %s, %s, %d, %s);",
		    $post->ID, $post->post_author, $_POST['incsub_event_venue'], $post->post_title, date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_start']} {$_POST['incsub_event_start_time']}")),
		    date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_end']} {$_POST['incsub_event_end_time']}")), 0, $_POST['incsub_event_status'])
		);
	}
    }
    
    function post_type_link($permalink, $post_id, $leavename) {
	global $event_variation;
	
	$post = get_post($post_id);
	
	$rewritecode = array(
	    '%incsub_event%',
	    '%event_year%',
	    '%event_monthnum%'
	);
	
	if ($post->post_type == 'incsub_event' && '' != $permalink) {
	    
	    $ptype = get_post_type_object($post->post_type);
	    
	    $start = time();
	    $end = time();
	    
	    $meta = get_post_custom($post->ID);
	    if (isset($meta["incsub_event_start"]) && isset($meta["incsub_event_start"][$event_variation[$post->ID]])) {
		$start = strtotime($meta["incsub_event_start"][$event_variation[$post->ID]]);
	    }
	    
	    $year = date('Y', $start);
	    $month = date('m', $start);
	    
	    $rewritereplace = array(
	    	($post->post_name == "")?$post->id:$post->post_name,
		$year,
		$month,
	    );
	    $permalink = str_replace($rewritecode, $rewritereplace, $permalink);
	} else {
	    // if they're not using the fancy permalink option
	}
	
	return $permalink;
    }
    
    function add_rewrite_rules($rules){
	$new_rules = array();
	
	unset($rules[$this->_options['default']['slug'].'/([0-9]{4})/([0-9]{1,2})/?$']);
	unset($rules[$this->_options['default']['slug'].'/([0-9]{4})/?$']);
	$new_rules[$this->_options['default']['slug'].'/([0-9]{4})/?$'] = 'index.php?year=$matches[1]&post_type=incsub_event';
	$new_rules[$this->_options['default']['slug'].'/([0-9]{4})/([0-9]{1,2})/?$'] = 'index.php?year=$matches[1]&monthnum=$matches[2]&post_type=incsub_event';
	$new_rules[$this->_options['default']['slug'].'/([0-9]{4})/([0-9]{2})/(.+?)/?$'] = 'index.php?event_year=$matches[1]&event_monthnum=$matches[2]&incsub_event=$matches[3]';
	
	return array_merge($new_rules, $rules);
    }
    
    function check_rewrite_rules($value) {
	//prevent an infinite loop
	if ( ! post_type_exists( 'incsub_event' ) )
	    return;
	
	if (!is_array($value))
	    $value = array();
	
	$array_key = $this->_options['default']['slug'].'/([0-9]{4})/?$';
	if ( !array_key_exists($array_key, $value) ) {
	    $this->flush_rewrite();
	}
	$array_key = $this->_options['default']['slug'].'/([0-9]{4})/([0-9]{1,2})/?$';
	if ( !array_key_exists($array_key, $value) ) {
	    $this->flush_rewrite();
	}
	$array_key = $this->_options['default']['slug'].'/([0-9]{4})/([0-9]{1,2})/(.+?)/?$';
	if ( !array_key_exists($array_key, $value) ) {
	    $this->flush_rewrite();
	}
    }
    
    function query_vars($vars) {
	array_push($vars, 'event_year');
	array_push($vars, 'event_monthnum');
	return $vars;
    }
    
    function manage_posts_columns($old_columns)	{
	global $post_status;
	
	$columns['cb'] = $old_columns['cb'];
	$columns['title'] = $old_columns['title'];
	$columns['start'] = __('When', 'eab');
	$columns['venue'] = __('Where', 'eab');
	$columns['author'] = $old_columns['author'];
	$columns['date'] = $old_columns['date'];
	
	return $columns;
    }
    
    function manage_posts_custom_column($column) {
	global $post;
	
	$meta = get_post_custom();
	
	//unserialize
	foreach ($meta as $key => $val) {
	    $meta[$key] = maybe_unserialize($val[0]);
	}

	switch ($column) {
	    case "venue":
	        echo $meta['incsub_event_venue'];
	        break;
	    case "start":
	        echo date(get_option('date_format', 'Y-m-d'), strtotime($meta['incsub_event_start']));
	        break;
	}
    }
    
    /**
     * Activation hook
     * 
     * Create tables if they don't exist and add plugin options
     * 
     * @see     http://codex.wordpress.org/Function_Reference/register_activation_hook
     * 
     * @global	object	$wpdb
     */
    function install() {
        global $wpdb;
        
	/**
	 * WordPress database upgrade/creation functions
	 */
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    	
	// Get the correct character collate
	if ( ! empty($wpdb->charset) )
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	if ( ! empty($wpdb->collate) )
	    $charset_collate .= " COLLATE $wpdb->collate";
	
	$sql_main = "CREATE TABLE IF NOT EXISTS ".Booking::tablename('bookings')." (
			`id` BIGINT NOT NULL AUTO_INCREMENT,
                        `event_id` BIGINT NOT NULL ,
                        `user_id` BIGINT NOT NULL ,
			`timestamp` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
			`status` ENUM( 'paid', 'yes', 'maybe', 'no' ) NOT NULL DEFAULT 'no' ,
	    		PRIMARY KEY (`id`),
			UNIQUE KEY `event_id_2` (`event_id`,`user_id`),
			KEY `event_id` (`event_id`),
			KEY `user_id` (`user_id`),
			KEY `timestamp` (`timestamp`),
			KEY `status` (`status`)
		    ) ENGINE = InnoDB {$charset_collate};";
	dbDelta($sql_main);
        
        $sql_main = "CREATE TABLE ".Booking::tablename('booking_meta')." (
			`id` BIGINT NOT NULL AUTO_INCREMENT,
			`booking_id` BIGINT NOT NULL ,
                        `meta_key` VARCHAR(255) NOT NULL ,
                        `meta_value` TEXT NOT NULL DEFAULT '',
	    		PRIMARY KEY (`id`),
			KEY `booking_id` (`booking_id`),
			KEY `meta_key` (`meta_key`)
		    ) ENGINE = InnoDB {$charset_collate};";
	dbDelta($sql_main);

        
	// Default options
	$this->_options['default'] = array();
	
        add_option('event_default', $this->_options['default']);
    }
    
    function user_has_cap($allcaps, $caps = null, $args = null) {
	global $current_user, $blog_id, $post;
	
	$capable = false;
	
	if (preg_match('/(_event|_events)/i', join($caps, ',')) > 0) {
	    if (in_array('administrator', $current_user->roles)) {
		foreach ($caps as $cap) {
		    $allcaps[$cap] = 1;
		}
		return $allcaps;
	    }
	    foreach ($caps as $cap) {
		$capable = false;
		switch ($cap) {
		    case 'read_events':
			print 1;
			$capable = true;
			break;
		    default:
			if (isset($args[1]) && isset($args[2])) {
			    if (current_user_can(preg_replace('/_event/i', '_post', $cap), $args[1], $args[2])) {
				$capable = true;
			    }
			} else if (isset($args[1])) {
			    if (current_user_can(preg_replace('/_event/i', '_post', $cap), $args[1])) {
				$capable = true;
			    }
			} else if (current_user_can(preg_replace('/_event/i', '_post', $cap))) {
			    $capable = true;
			}
			break;
		}
		
		if ($capable) {
		    $allcaps[$cap] = 1;
		}
	    }
	}
	return $allcaps;
    }
    
    function flush_rewrite() {
	global $wp_rewrite;
	
	$wp_rewrite->flush_rules();
    }
    
    /**
     * Deactivation hook
     * 
     * @see		http://codex.wordpress.org/Function_Reference/register_deactivation_hook
     *  
     * @global	object	$wpdb
     */
    function uninstall() {
    	global $wpdb;
	// Nothing to do
    }
    
    /**
     * Add the admin menus
     * 
     * @see		http://codex.wordpress.org/Adding_Administration_Menus
     */
    function admin_menu() {
	if (get_option('eab_setup', false) == false) {
	    add_submenu_page('edit.php?post_type=incsub_event', __("Get Started", $this->_translation_domain), __("Get started", $this->_translation_domain), 'manage_options', 'eab_welcome', array(&$this,'welcome_render'));
	}
	add_submenu_page('edit.php?post_type=incsub_event', __("Event Settings", $this->_translation_domain), __("Settings", $this->_translation_domain), 'manage_options', 'eab_settings', array(&$this,'settings_render'));
    }
    
    function cron_schedules($schedules) {
	$schedules['thirtyminutes'] = array( 'interval' => 1800, 'display' => __('Once every half an hour', INCSUB_SUPPORT_LANG_DOMAIN) );
	
	return $schedules;
    }
    
    function welcome_render() {
	?>
	<div class="wrap">
	    <div id="icon-events-general" class="icon32"><br/></div>
	    <h2><?php _e('Getting started', $this->_translation_domain); ?></h2>
	</div>
	<?php
    }
    function views_list($views) {
	global $wp_query;
	
	$avail_post_stati = wp_edit_posts_query();
	$num_posts = wp_count_posts( 'incsub_event', 'readable' );
	
	foreach ( get_post_stati(array('post_type' => 'incsub_event'), 'objects') as $status ) {
	    $class = '';
	    $status_name = $status->name;
	    if ( !in_array( $status_name, $avail_post_stati ) )
	        continue;
	    
	    if ( empty( $num_posts->$status_name ) )
	        continue;
	    
	    if ( isset($_GET['post_status']) && $status_name == $_GET['post_status'] )
	        $class = ' class="current"';
	    
	    $views[$status_name] = "<li><a href='edit.php?post_type=incsub_event&amp;post_status=$status_name'$class>" . sprintf( _n( $status->label_count[0], $status->label_count[1], $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
	}
	
	return $views;
    }
    
    function settings_render() {
	if(!current_user_can('manage_options')) {
  		echo "<p>" . __('Nice Try...', $this->translation_domain) . "</p>";  //If accessed properly, this message doesn't appear.
  		return;
  	}
	if ($_GET['incsub_event_settings_saved'] == 1) {
	    echo '<div class="updated fade"><p>'.__('Settings saved.', $this->translation_domain).'</p></div>';
        }
	?>
	<div class="wrap">
	    <div id="icon-events-general" class="icon32"><br/></div>
	    <h2><?php _e('Events Settings', $this->_translation_domain); ?></h2>
	    <p><?php _e('Welcome to settings page for the Events plugin', $this->_translation_domain); ?></p>
	    <form method="post" action="edit.php?post_type=incsub_event&page=eab_settings">
		<?php wp_nonce_field('incsub_event-update-options'); ?>
		<fieldset id="eab-settings-general" class="eab-settings-section">
		    <legend><?php _e('Setup', $this->_translation_domain); ?></legend>
		    <label for="incsub_event-slug"><?php _e('Set the event root slug', $this->translation_domain); ?>
			/<input type="text" size="20" id="incsub_event-slug" name="event_default[slug]" value="<?php print $this->_options['default']['slug']; ?>" />
		    </label> <a href="#eab-help-slug" class="eab-info" >&nbsp;</a>
		    <div class="clear"></div>
		    <div id="eab-help-slug" class="eab-help-content">
			<?php _e('Change the root slug for events', $this->_translation_domain); ?>
		    </div>
		    
		    <label for="incsub_event-accept_payments"><?php _e('Will you be using payments for your events?', $this->translation_domain); ?>
			<input type="checkbox" size="20" id="incsub_event-accept_payments" name="event_default[accept_payments]" value="1" <?php print ($this->_options['default']['accept_payments'] == 1)?'checked="checked"':''; ?> />
		    </label> <a href="#eab-help-accept-payment" class="eab-info" >&nbsp;</a>
		    <div class="clear"></div>
		    <div id="eab-help-accept-payment" class="eab-help-content">
			<?php _e('Check this to accept payments for your events', $this->_translation_domain); ?>
		    </div>
		</fieldset>
		<fieldset id="eab-settings-paypal" class="eab-settings-section">
		    <legend><?php _e('Payment', $this->_translation_domain); ?></legend>
		    <label for="incsub_event-currency"><?php _e('Currency', $this->translation_domain); ?>
			<input type="text" size="4" id="incsub_event-currency" name="event_default[currency]" value="<?php print $this->_options['default']['currency']; ?>" />
		    </label> <a href="#eab-help-paypal-currency" class="eab-info" >&nbsp;</a>
		    <div class="clear"></div>
		    <div id="eab-help-paypal-currency" class="eab-help-content">
			<?php _e('Which currency will you be accepting payment in?', $this->_translation_domain); ?>
		    </div>
		    
		    <label for="incsub_event-paypal_email"><?php _e('PayPal E-Mail', $this->translation_domain); ?>
			<input type="text" size="20" id="incsub_event-paypal_email" name="event_default[paypal_email]" value="<?php print $this->_options['default']['paypal_email']; ?>" />
		    </label> <a href="#eab-help-paypal-email" class="eab-info" >&nbsp;</a>
		    <div class="clear"></div>
		    <div id="eab-help-paypal-email" class="eab-help-content">
			<?php _e('PayPal e-mail address payments should be made to', $this->_translation_domain); ?>
		    </div>
		</fieldset>
		
		<p class="submit">
		    <input type="submit" name="submit_settings" value="<?php _e('Save Changes', $this->translation_domain) ?>" />
		</p>
	    </form>
	</div>
	<?php
    }
    
    function widgets_init() {
	require_once 'widgets/Widget.class.php';
	require_once 'widgets/Attendees_Widget.class.php';
	require_once 'widgets/Popular_Widget.class.php';
	require_once 'widgets/Upcoming_Widget.class.php';
	
	register_widget('Eab_Attendees_Widget');
	register_widget('Eab_Popular_Widget');
	register_widget('Eab_Upcoming_Widget');
    }
}

function eab_expire_events() {
    global $wpdb;
    
    $query = "SELECT a.ID FROM {$wpdb->prefix}posts a LEFT JOIN {$wpdb->prefix}postmeta b ON (a.ID = b.post_id) WHERE a.post_status = 'publish' AND a.post_type = 'incsub_event' AND b.meta_key = 'incsub_event_start' AND b.meta_value < NOW();";
    $posts = $wpdb->get_results($query, ARRAY_A);
    
    foreach ($posts as $post) {
        $post['post_status'] = 'expire';
        wp_update_post( $post );
        wp_transition_post_status('expire', 'publish', $post);
    }
}
    
include_once 'template-tags.php';

define('EAB_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/');

// Lets get things started
$booking = new Booking();

if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
