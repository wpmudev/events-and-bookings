<?php
/*
Plugin Name: Events +
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Description: Events gives you a flexible WordPress-based system for organizing parties, dinners, fundraisers - you name it.
Author: WPMU DEV
Text Domain: eab
WDP ID: 249
Version: 1.9.7-beta1
Author URI: http://premium.wpmudev.org
*/

 /*
Authors - S H Mohanjith (Incsub), Ve Bailovity (Incsub), Ignacio (Incsub), Ashok Kumar Nath (WPMU DEV)
Contributors - Hoang Ngo (WPMU DEV), Hakan Evin
 */

/**
 * Eab_EventsHub object
 *
 * Allow your readers to register for events you organize
 *
 * @since 1.0.0
 * @author S H Mohanjith <moha@mohanjith.net>
 */
class Eab_EventsHub {

    /**
     * Current version.
	 * @TODO Update version number for new releases
     * @var	string
     */
    const CURRENT_VERSION 		= '1.9.7';

    /**
     * Translation domain
	 * @var string
     */
	const TEXT_DOMAIN 			= 'eab';

	const BOOKING_TABLE 		= 'bookings';
	const BOOKING_META_TABLE 	= 'booking_meta';

    /**
     * Options instance.
	 * @var object
     */
    public $_data;

    /**
     * API handler instance
     */
    public $_api;

	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance )
			self::$instance = new self();

		return self::$instance;
	}

    /**
     * Get the table name with prefixes
     *
     * @global	object	$wpdb
     * @param	string	$table	Table name
     * @return	string			Table name complete with prefixes
     */
    public static function tablename( $table ) {
		global $wpdb;
    	// We use per-blog tables for network events
		return $wpdb->prefix . 'eab_' . $table;
    }



    /**
     * Initializing object
     *
     * Plugin register actions, filters and hooks.
     */
    function __construct () {
		global $wpdb, $wp_version;


		if ( !session_id() ) {
			session_start();
		}

		// Actions
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'init', array( $this, 'process_rsvps' ), 99 ); // Bind this a bit later, so BP can load up


		add_action( 'option_rewrite_rules', array( $this, 'check_rewrite_rules' ) );

		add_action( 'wp_print_styles', array( $this, 'wp_print_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		/**
		 * Wipe out the default post actions, because we're using our own
		 * @since  WP 4.3
		 */
		add_filter( 'post_row_actions', array( $this, 'manage_post_actions' ), 10, 2 );

		add_action( 'add_meta_boxes_incsub_event', array ($this, 'meta_boxes' ) );
		add_action( 'wp_insert_post', array( $this, 'save_event_meta' ), 10, 2 );

		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_filter( 'post_updated_messages', array( $this, 'handle_post_updated_messages' ) );

		add_filter( 'single_template', array( $this, 'handle_single_template' ) );
		add_filter( 'archive_template', array( $this, 'handle_archive_template' ) );
		add_action( 'wp', array( $this, 'load_events_from_query' ), 20 );

		add_filter( 'rewrite_rules_array', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 3 );

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );

		add_filter( 'views_edit-incsub_event', array( $this, 'views_list' ) );
		add_filter( 'agm_google_maps-post_meta-address', array( $this, 'agm_google_maps_post_meta_address' ) );
		add_filter( 'agm_google_maps-options', array( $this, 'agm_google_maps_options' ) );

		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 10, 3 );

		add_filter( 'login_message', array( $this, 'login_message' ), 10 );

		$this->_data = Eab_Options::get_instance();
		$this->_api = new Eab_Api();

		// Thrashing recurrent post trashes its instances, likewise for deleting.
		add_action( 'wp_trash_post', array( $this, 'process_recurrent_trashing' ) );
		add_action( 'untrash_post', array( $this, 'process_recurrent_untrashing' ) );
		add_action( 'before_delete_post', array( $this, 'process_recurrent_deletion' ) );

		// Listen to transition from drafts and (re)spawn the recurring instances
		add_action( 'draft_to_publish', array( $this, 'respawn_recurring_instances' ) );

	    add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

        add_action( 'ms_rule_cptgroup_model_protect_posts', array( $this, 'reverse_m2_modified_event_cpt' ), 99, 2 );

	    if ( is_admin() ) {
		    require_once( 'admin/class-eab-admin.php' );
		    new Eab_Admin();
	    }
		// API login after the options have been initialized
		$this->_api->initialize();

    }

    public function reverse_m2_modified_event_cpt( $wp_query, $obj ) {
        if( ! isset( $wp_query->query_vars['post_type'] ) ) {
			return;
		} 
		
		if( is_array( $wp_query->query_vars['post_type'] ) && $wp_query->query_vars['post_type'][0] == Eab_EventModel::POST_TYPE ) {
            $wp_query->query_vars['post_type'] = Eab_EventModel::POST_TYPE;
        }
    }

	public function maybe_upgrade() {
		$current_version = get_option( 'eab_version' );
		if ( false === $current_version ) {
			// Added on 1.9.2
			flush_rewrite_rules();
			update_option( 'eab_version', self::CURRENT_VERSION );
			return;
		}
	}

	function process_recurrent_trashing ( $post_id ) {
		$event = new Eab_EventModel( get_post( $post_id ) );
		if ( !$event->is_recurring() )  { 
			return false;
		}
		$event->trash_recurring_instances();
	}

	function process_recurrent_untrashing ( $post_id ) {
		$event = new Eab_EventModel( get_post( $post_id ) );
		if ( !$event->is_recurring() )  { 
			return false;
		}
		$event->untrash_recurring_instances();
	}

	function process_recurrent_deletion ( $post_id ) {
		$event = new Eab_EventModel( get_post( $post_id ) );
		if ( !$event->is_recurring() )  { 
			return false;
		}
		$event->delete_recurring_instances();
	}

	function respawn_recurring_instances ($post) {
		if ( empty( $post->post_type ) || Eab_EventModel::POST_TYPE !== $post->post_type )  { 
			return false;
		}
		$event = new Eab_EventModel( $post );
		if ( !$event->is_recurring() )  { 
			return false;
		}

		$interval 	= $event->get_recurrence();
		$time_parts = $event->get_recurrence_parts();
		$start 		= $event->get_recurrence_starts();
		$end 		= $event->get_recurrence_ends();

		$event->spawn_recurring_instances($start, $end, $interval, $time_parts);
	}

    /**
     * Initialize the plugin
     *
     * @see		http://codex.wordpress.org/Plugin_API/Action_Reference
     * @see		http://adambrown.info/p/wp_hooks/hook/init
     */
    function init() {
		global $wpdb, $wp_rewrite, $current_user, $blog_id, $wp_version;
		$version = preg_replace('/-.*$/', '', $wp_version);

		if ( preg_match('/mu\-plugin/', PLUGINDIR ) > 0 ) {
		    load_muplugin_textdomain( self::TEXT_DOMAIN, dirname( plugin_basename( __FILE__) ).'/languages' );
		} else {
		    load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__) ).'/languages' );
		}

	    $taxonomies = new Eab_Taxonomies();
	    $taxonomies->register();

		$event_structure = '/' . $this->_data->get_option('slug'). '/%event_year%/%event_monthnum%/%incsub_event%';

		$wp_rewrite->add_rewrite_tag( "%incsub_event%", '(.+?)', "incsub_event=" );
		$wp_rewrite->add_rewrite_tag( "%event_year%", '([0-9]{4})', "event_year=" );
		$wp_rewrite->add_rewrite_tag( "%event_monthnum%", '([0-9]{2})', "event_monthnum=" );
	    //add_rewrite_rule( $this->_data->get_option('slug') . '/[0-9]{4}/[0-9]{2}/.+?/comment-page-([0-9]{1,})/?$', 'index.php?post_type=incsub_event&cpage=$matches[1]', 'top' );
        add_rewrite_rule( $this->_data->get_option('slug') . '/[0-9]{4}/[0-9]{2}/(.+)?/comment-page-([0-9]{1,})/?$', 'index.php?incsub_event=$matches[1]&cpage=$matches[2]', 'top' );

		$wp_rewrite->add_permastruct( 'incsub_event', $event_structure, false );

		//wp_register_script('eab_jquery_ui', plugins_url('events-and-bookings/js/jquery-ui.custom.min.js'), array('jquery'), self::CURRENT_VERSION);

		wp_register_script( 'eab_event_js', EAB_PLUGIN_URL . 'js/eab-event.js', array('jquery'), self::CURRENT_VERSION );
		
		wp_register_style( 'eab_jquery_ui', EAB_PLUGIN_URL . 'css/smoothness/jquery-ui-1.8.16.custom.css', null, '1.8.16' );
		

		wp_register_style( 'eab_front', EAB_PLUGIN_URL .'css/front.css' , null, self::CURRENT_VERSION );



		if ( isset( $_REQUEST['eab_step'] ) ) {
		    setcookie( 'eab_step', $_REQUEST['eab_step'], eab_current_time() + ( 3600*24 ) );
		} else if ( isset( $_COOKIE['eab_step'] ) ) {
		    $_REQUEST['eab_step'] = $_COOKIE['eab_step'];
		}

		if ( isset( $_REQUEST['eab_export'] ) ) {
			if ( !class_exists( 'Eab_ExporterFactory' ) )  { 
				require_once EAB_PLUGIN_DIR . 'lib/class_eab_exporter.php';
			}
			Eab_ExporterFactory::serve( $_REQUEST );
		}
    }

	function process_rsvps () {
		global $wpdb;
		if ( isset( $_POST['event_id'] ) && isset( $_POST['user_id'] ) ) {
		    $booking_actions 	= array( 'yes' => 'yes', 'maybe' => 'maybe', 'no' => 'no' );
			$booking_action 	= '';
		    $event_id 			= intval( $_POST['event_id'] );
			foreach( $booking_actions as $val ) {
				if( isset( $_POST['action_' . $val] ) ) {
					$booking_action = $val;
					break;
				}
			}

			$user_id = apply_filters( 'eab-rsvp-user_id', get_current_user_id(), $_POST['user_id']);

		    do_action( 'incsub_event_booking', $event_id, $user_id, $booking_action );
		    if (isset($_POST['action_yes'])) {
                $this->update_rsvp_per_event( $event_id, $user_id, 'yes' );
				// --todo: Add to BP activity stream
				do_action( 'incsub_event_booking_yes', $event_id, $user_id );
				$this->recount_bookings( $event_id );
				//wp_redirect('?eab_success_msg=' . Eab_Template::get_success_message_code(Eab_EventModel::BOOKING_YES));
				wp_redirect(
					add_query_arg(
						'eab_success_msg',
						Eab_Template::get_success_message_code(Eab_EventModel::BOOKING_YES)
					)
				);
				exit();
		    }
		    if ( isset( $_POST['action_maybe'] ) ) {
				$this->update_rsvp_per_event( $event_id, $user_id, 'maybe' );
				// --todo: Add to BP activity stream
				do_action( 'incsub_event_booking_maybe', $event_id, $user_id );
				$this->recount_bookings( $event_id );
				wp_redirect(
					add_query_arg(
						'eab_success_msg',
						Eab_Template::get_success_message_code(Eab_EventModel::BOOKING_MAYBE)
					)
				);
				exit();
		    }
		    if ( isset( $_POST['action_no'] ) ) {
				$this->update_rsvp_per_event( $event_id, $user_id, 'no' );
				// --todo: Remove from BP activity stream
				do_action( 'incsub_event_booking_no', $event_id, $user_id );
				$this->recount_bookings( $event_id );
				wp_redirect(
					add_query_arg(
						'eab_success_msg',
						Eab_Template::get_success_message_code(Eab_EventModel::BOOKING_NO)
					)
				);
				exit();
		    }
		}
	}


    function login_message( $message ) {
		if ( isset( $_REQUEST['eab'] ) && $_REQUEST['eab'] == 'y' ) {
		    $message = '<p class="message">' . __( "Excellent, few more steps! We need you to login or register to get you marked as coming!", self::TEXT_DOMAIN ) . '</p>';
		}

		if ( isset( $_REQUEST['eab'] ) && $_REQUEST['eab'] == 'm' ) {
		    $message = '<p class="message">' . __( "Please login or register to help us let you know any changes about the event and record your response!", self::TEXT_DOMAIN ) . '</p>';
		}

		if ( isset( $_REQUEST['eab'] ) && $_REQUEST['eab'] == 'n' ) {
		    $message = '<p class="message">' . __( "That's too bad you won't be able to make it, if you login or register we will be able to record your response", self::TEXT_DOMAIN ) . '</p>';
		}

		return $message;
    }

    function update_rsvp_per_event( $event_id, $user_id, $status ) {
        global $wpdb;
		$table_name = self::tablename( self::BOOKING_TABLE );
        if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$trid = $sitepress->get_element_trid( $event_id );
			$translations = $sitepress->get_element_translations( $trid );

			foreach( $translations as $key => $val ) {
				$wpdb->query(
					$wpdb->prepare( "INSERT INTO $table_name VALUES(null, %d, %d, NOW(), %s) ON DUPLICATE KEY UPDATE `status` = %s", $val->element_id, $user_id, $status, $status )
				);
			}
        } else {
			$wpdb->query(
				$wpdb->prepare("INSERT INTO $table_name VALUES(null, %d, %d, NOW(), %s) ON DUPLICATE KEY UPDATE `status` = %s", $event_id, $user_id, $status, $status )
			);
        }
	}

	/**
	 * Check RSVP
	 * 
	 * @return book
	 */
	function check_rsvp_per_event( $event_id, $user_id, $status ) {
		global $wpdb;
		$record 	= false;
		$table_name = self::tablename( self::BOOKING_TABLE );
		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$trid 			= $sitepress->get_element_trid( $event_id );
			$translations 	= $sitepress->get_element_translations( $trid );

			foreach( $translations as $key => $val ) {
				$record = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE `user_id` =  %d AND `event_id` = %d AND `status` = %s", $user_id, $val->element_id, $status ) );
			}
		} else {
			$record = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE `user_id` =  %d AND `event_id` = %d AND `status` = %s", $user_id, $event_id, $status ) );
		}
		return $record;
	}

    function recount_bookings( $event_id ) {
		global $wpdb;

		// If WPML Enabled
		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$trid = $sitepress->get_element_trid( $event_id );
			$translations = $sitepress->get_element_translations( $trid );

			foreach( $translations as $key => $val ) {
				$this->update_count_rsvp_meta( $val->element_id );
			}
		}

		$this->update_count_rsvp_meta( $event_id );
    }

    public function update_count_rsvp_meta( $event_id ) {
		global $wpdb;
		
		$table_name = self::tablename( self::BOOKING_TABLE );

        // Yes
        $yes_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE `status` = 'yes' AND event_id = %d", $event_id ) );
    	update_post_meta( $event_id, 'incsub_event_yes_count', $yes_count );

        // Maybe
        $maybe_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE `status` = 'maybe' AND event_id = %d", $event_id ) );
   	 	update_post_meta( $event_id, 'incsub_event_maybe_count', $maybe_count );
        update_post_meta( $event_id, 'incsub_event_attending_count', $maybe_count + $yes_count );

        // No
        $no_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE `status` = 'no' AND event_id = %d", $event_id ) );
        update_post_meta( $event_id, 'incsub_event_no_count', $no_count );
    }



    function agm_google_maps_post_meta_address($location) {
		global $post;

		if ( !$location && $post->post_type == 'incsub_event' ) {
		    $meta = get_post_custom( $post->ID );

		    $venue = '';
		    if ( isset( $meta["incsub_event_venue"] ) && isset( $meta["incsub_event_venue"][0] ) ) {
				$venue = stripslashes( $meta["incsub_event_venue"][0] );
				if ( preg_match_all( '/map id="([0-9]+)"/', $venue, $matches ) > 0 ) {
				    if ( isset( $matches[1] ) && isset( $matches[1][0] ) ) {
						$model 	= new AgmMapModel();
						$map 	= $model->get_map( $matches[1][0] );
						$venue 	= $map['markers'][0]['title'];
						if ( $meta["agm_map_created"][0] != $map['id'] ) {
						    update_post_meta( $post->ID, 'agm_map_created', $map['id'] );
						    return false;
						}
				    }
				} else {
					delete_post_meta( $post->ID, 'agm_map_created' );
				}
		    }

		    return $venue;
		}
		return $location;
    }

    function agm_google_maps_options( $opts ) {
		$opts['use_custom_fields'] 						= 1;
		$opts['custom_fields_options']['associate_map'] = 1;
		return $opts;
    }

    function handle_archive_template( $path ) {
		global $wp_query, $post;

		if ( ! is_post_type_archive ( 'incsub_event' ) )
		    return $path;

		$current_filter = explode( '_', current_filter() );
		$type 			= reset( $current_filter );
		$file 			= basename( $path );

		$style = file_exists( get_stylesheet_directory() . '/events.css')
			? get_stylesheet_directory_uri() . '/events.css'
			: (
				file_exists( get_template_directory() . '/events.css')
					? get_template_directory_uri() . '/events.css'
					: false
			)
		;
		$eab_type = $is_theme_tpl = false;
		if ($this->_data->get_option('override_appearance_defaults')) {
			$eab_type = $this->_data->get_option('archive_template');
			$eab_type = $eab_type ? $eab_type : '';
			$is_theme_tpl = preg_match('/\.php$/', $eab_type);
		}
		if ( !$style && !$is_theme_tpl && @$this->_data->get_option('override_appearance_defaults' ) ) {
			$style_path = file_exists( EAB_PLUGIN_DIR . "default-templates/{$eab_type}/events.css" );
			$style 		= $style_path ? plugins_url( basename( dirname(__FILE__) ) . "/default-templates/{$eab_type}/events.css" ) : $style;
		}
		if ( $style ) { 
			add_action( 'wp_head', create_function('', "wp_enqueue_style('eab-events', '$style');" ) );
		}

		if ( empty( $path ) || "$type.php" == $file ) {
			if ( $eab_type && !$is_theme_tpl ) {
				$path = EAB_PLUGIN_DIR . "default-templates/{$eab_type}/{$type}-incsub_event.php";
				if ( file_exists( $path ) ) { 
					return $path; 
				}
				else {
					// A more specific template was not found, so load the default one
				    add_filter( 'the_content', array( $this, 'archive_content' ) );
				    if ( file_exists( get_stylesheet_directory() . '/archive.php' ) ) {
						$path = get_stylesheet_directory().'/archive.php';
				    } else if ( file_exists( get_template_directory() . '/archive.php' ) ) {
						$path = get_template_directory().'/archive.php';
					} else { 
						$path = ''; 
					}
				}
			} else if ( $eab_type && $is_theme_tpl ) {
				// Selected file is a theme file
			    add_filter( 'the_content', array( $this, 'archive_content' ) );
				if ( file_exists( get_stylesheet_directory() . '/' . $eab_type ) ) {
					$path = get_stylesheet_directory() . '/' . $eab_type;
			    } else if ( file_exists( get_template_directory() . '/' . $eab_type ) ) {
					$path = get_template_directory() . '/' . $eab_type;
			    }
			} else {
			    // A more specific template was not found, so load the default one
			    add_filter( 'the_content', array( $this, 'archive_content' ) );
			    if ( file_exists( get_stylesheet_directory() . '/archive.php' ) ) {
					$path = get_stylesheet_directory() . '/archive.php';
			    } else if ( file_exists( get_template_directory() . '/archive.php' ) ) {
					$path = get_template_directory() . '/archive.php';
			    }
			}
		}
		return $path;
    }

    function archive_content( $content ) {
		global $post;
		if ( 'incsub_event' != $post->post_type ) { 
			return $content;
		}
		return Eab_Template::get_archive_content($post);
    }

    function handle_single_template( $path ) {
		global $wp_query, $post;

	    if ( ! is_a( $post, 'WP_Post' ) ) {
		    return $path;
	    }

		if ( 'incsub_event' != $post->post_type )
		    return $path;

	    $type = explode( '_', current_filter() );
		$type = reset( $type );

		$file = basename( $path );

		$style = file_exists(get_stylesheet_directory() . '/events.css')
			? get_stylesheet_directory_uri() . '/events.css'
			: (
				file_exists(get_template_directory() . '/events.css')
					? get_template_directory_uri() . '/events.css'
					: false
			)
		;
		$eab_type = $is_theme_tpl = false;
		if ($this->_data->get_option('override_appearance_defaults')) {
			$eab_type 		= $this->_data->get_option('single_template');
			$eab_type 		= $eab_type ? $eab_type : '';
			$is_theme_tpl 	= preg_match('/\.php$/', $eab_type);
		}
		if (!$style && !$is_theme_tpl && @$this->_data->get_option('override_appearance_defaults')) {
			$style_path = file_exists(EAB_PLUGIN_DIR . "default-templates/{$eab_type}/events.css");
			$style 		= $style_path ? plugins_url(basename(dirname(__FILE__)) . "/default-templates/{$eab_type}/events.css") : $style;
		}
		if ($style) add_action('wp_head', create_function('', "wp_enqueue_style('eab-events', '$style');"));

		if ( empty( $path ) || "$type.php" == $file ) {
			if ($eab_type && !$is_theme_tpl) {
				$path = EAB_PLUGIN_DIR . "default-templates/{$eab_type}/{$type}-incsub_event.php";
				if (file_exists($path)) return $path;
				else {
					// A more specific template was not found, so load the default one
				    add_filter('agm_google_maps-options', 'eab_autoshow_map_off', 99); // Shut down maps autoshowing
				    add_filter('the_content', array($this, 'single_content'));
				    if (file_exists(get_stylesheet_directory().'/single.php')) {
						$path = get_stylesheet_directory().'/single.php';
				    } else if (file_exists(get_template_directory().'/single.php')) {
						$path = get_template_directory().'/single.php';
				    } else $path = '';
				}
			} else if ($eab_type && $is_theme_tpl) {
				// Selected file is a theme file
			    add_filter('the_content', array($this, 'single_content'));
				if (file_exists(get_stylesheet_directory() . '/' . $eab_type)) {
					$path = get_stylesheet_directory() . '/' . $eab_type;
			    } else if (file_exists(get_template_directory() . '/' . $eab_type)) {
					$path = get_template_directory() . '/' . $eab_type;
			    }
			} else {
			    // A more specific template was not found, so load the default one
			    add_filter('agm_google_maps-options', 'eab_autoshow_map_off', 99); // Shut down maps autoshowing
			    add_filter('the_content', array($this, 'single_content'));
			    if (file_exists(get_stylesheet_directory().'/single.php')) {
					$path = get_stylesheet_directory().'/single.php';
			    } else if (file_exists(get_template_directory().'/single.php')) {
					$path = get_template_directory().'/single.php';
			    }
		    }
		}
		return $path;
    }

    function single_content($content) {
		global $post;
		if ('incsub_event' != $post->post_type) return $content;
		return Eab_Template::get_single_content($post, $content);
    }



    function meta_boxes() {
		global $post, $current_user;

		add_meta_box('incsub-event', __('Event Details', self::TEXT_DOMAIN), array($this, 'event_meta_box'), 'incsub_event', 'side', 'high');
		add_meta_box('incsub-event-bookings', __("Event RSVPs", self::TEXT_DOMAIN), array($this, 'bookings_meta_box'), 'incsub_event', 'normal', 'high');
		if (isset($_REQUEST['eab_step'])) {
		    add_meta_box('incsub-event-wizard', __('Are you following the step by step guide?', self::TEXT_DOMAIN), array($this, 'wizard_meta_box'), 'incsub_event', 'normal', 'low');
		}
		do_action('eab-event_meta-meta_box_registration');
    }

    function wizard_meta_box() {
		return '';
    }



    function wp_print_styles() {
		global $wp_query;

		if ((isset($wp_query->query_vars['post_type']) && $wp_query->query_vars['post_type'] == 'incsub_event') || is_tax('eab_events_category')) {
		    wp_enqueue_style('eab_front');
		}
    }

    function wp_enqueue_scripts() {
		global $wp_query;

		$script = 'var _eab_data=' . json_encode(apply_filters('eab-javascript-public_data', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'root_url' => plugins_url('events-and-bookings/img/'),
				'fb_scope' => 'email',))
			) . ';' .
			'if (!("ontouchstart" in document.documentElement)) document.documentElement.className += " no-touch";';
		if ( ! wp_script_is( 'jquery', 'done' ) ) {
			wp_enqueue_script( 'jquery' );
		}
		wp_add_inline_script( 'jquery-migrate', $script );

		if (isset($wp_query->query_vars['post_type']) && $wp_query->query_vars['post_type'] == 'incsub_event') {
		    wp_enqueue_script('eab_event_js');
		    $this->_api->enqueue_api_scripts();
			do_action('eab-javascript-enqueue_scripts');
		}

		add_action('eab-javascript-do_enqueue_api_scripts', array($this, 'enqueue_api_scripts'));

    }

    public function enqueue_api_scripts () { return $this->_api->enqueue_api_scripts(); }

    function event_meta_box () {
		global $post;

		$content = '';
		$content = apply_filters('eab-meta_box-event_meta_box-before', $content);
		$content .= $this->meta_box_part_where();
		$content .= '<div class="clear"></div>';
		$content .= $this->meta_box_part_when();
		$content .= '<div class="clear"></div>';
		$content .= $this->meta_box_part_status();
		$content .= '<div class="clear"></div>';
		if ($this->_data->get_option('accept_payments')) {
		    $content .= $this->meta_box_part_payments();
		    $content .= '<div class="clear"></div>';
		}
		$content = apply_filters('eab-event_meta-event_meta_box-after', $content);

		echo $content;
    }

    function meta_box_part_where () {
		global $post;
		$event = new Eab_EventModel($post);

		$content  = '';
		$content .= '<div class="eab_meta_box">';
		$content .= '<input type="hidden" name="incsub_event_where_meta" value="1" />';
		$content .= '<div class="misc-eab-section" >';
		$content .= '<div class="eab_meta_column_box top"><label for="incsub_event_venue" id="incsub_event_venue_label">'.__('Event location', self::TEXT_DOMAIN).'</label> <span id="eab_insert_map"></span></div>';
		$content .= '<textarea class="widefat" type="text" name="incsub_event_venue" id="incsub_event_venue" size="20" >' . $event->get_venue() . '</textarea>';
		$content .= '</div>';
		$content .= '</div>';

		return $content;
    }

    function meta_box_part_when () {
		global $post;
		$event = new Eab_EventModel($post);

		$content = '';
		$content .= '<div class="eab_meta_box">';
		$content .= '<div class="eab_meta_column_box" id="incsub_event_times_label">'.__('Event times and dates', self::TEXT_DOMAIN).'</div>';

		$content .= '<input type="hidden" name="incsub_event_when_meta" value="1" />';

		$start_dates = $event->get_start_dates();

		$content .= $this->_meta_box_part_recurring_add($event);
		if ( !$event->is_recurring() ) {
			$content .= '<div id="eab-add-more-rows">';
			if ( $start_dates ) {
			    foreach ( $start_dates as $key => $date ) {
					$start 		= $event->get_start_timestamp( $key );
					$no_start 	= $event->has_no_start_time( $key ) ? 'checked="checked"' : '';
					$end 		= $event->get_end_timestamp( $key );
					$no_end 	= $event->has_no_end_time( $key ) ? 'checked="checked"' : '';

					$content .= '<div class="eab-section-block">';
					$content .= '<div class="eab-section-heading">' . sprintf( __( 'Part %d', self::TEXT_DOMAIN ), $key+1 ) . '&nbsp' . '<a href="#remove" class="eab-event-remove_time">' . __('Remove', self::TEXT_DOMAIN) . '</a></div>';
					$content .= '<div class="misc-eab-section eab-start-section"><label for="incsub_event_start_'.$key.'">';
					$content .= sprintf( __('%sStart%s', self::TEXT_DOMAIN ), '<span>', '</span>' ).'</label>';
					$content .= '<input type="text" name="incsub_event_start['.$key.']" id="incsub_event_start_'.$key.'" class="incsub_event_picker incsub_event incsub_event_date incsub_event_start" value="'.date('Y-m-d', $start).'" size="10" readonly/> ';
					$content .= '<input type="text" name="incsub_event_start_time['.$key.']" id="incsub_event_start_time_'.$key.'" class="incsub_event incsub_event_time incsub_event_time_picker incsub_event_start_time" value="'.date('H:i', $start).'" size="3" readonly/>';
					$content .= ' <input type="checkbox" name="incsub_event_no_start_time['.$key.']" id="incsub_event_no_start_time_'.$key.'" class="incsub_event incsub_event_time incsub_event_no_start_time" value="1" ' . $no_start . ' />';
					$content .= ' <label for="incsub_event_no_start_time_'.$key.'">' . __( 'No start time', self::TEXT_DOMAIN ) . '</label>';
					$content .= '</div>';

					$content .= '<div class="misc-eab-section"><label for="incsub_event_end_'.$key.'">';
					$content .= sprintf( __('%sEnd%s', self::TEXT_DOMAIN ), '<span>', '</span>' ).'</label>';
					$content .= '<input type="text" name="incsub_event_end['.$key.']" id="incsub_event_end_'.$key.'" class="incsub_event_picker incsub_event incsub_event_date incsub_event_end" value="'.date('Y-m-d', $end).'" size="10" readonly/> ';
					$content .= '<input type="text" name="incsub_event_end_time['.$key.']" id="incsub_event_end_time_'.$key.'" class="incsub_event incsub_event_time incsub_event_time_picker incsub_event_end_time" value="'.date('H:i', $end).'" size="3" readonly/>';
					$content .= ' <input type="checkbox" name="incsub_event_no_end_time['.$key.']" id="incsub_event_no_end_time_'.$key.'" class="incsub_event incsub_event_time incsub_event_no_end_time" value="1" ' . $no_end . ' />';
					$content .= ' <label for="incsub_event_no_end_time_'.$key.'">' . __( 'No end time', self::TEXT_DOMAIN ) . '</label>';
					$content .= '</div>';
					$content .= '</div>';
			    }
			} else {
			    $i=0;
			    $content .= '<div class="eab-section-block">';
			    $content .= '<div class="eab-section-heading">' . sprintf(__( 'Part %d', self::TEXT_DOMAIN ), $i+1) . '&nbsp' . '<a href="#remove" class="eab-event-remove_time">' . __('Remove', self::TEXT_DOMAIN) . '</a></div>';
			    $content .= '<div class="misc-eab-section eab-start-section"><label class="eab-inline-label" for="incsub_event_start_'.$i.'">';
			    $content .= sprintf( __( '%sStart%s', self::TEXT_DOMAIN ), '<span>', '</span>' ).'</label>';
			    $content .= '<input type="text" name="incsub_event_start['.$i.']" id="incsub_event_start_'.$i.'" class="incsub_event_picker incsub_event incsub_event_date incsub_event_start" value="" size="10" readonly/> ';
			    $content .= '<input type="text" name="incsub_event_start_time['.$i.']" id="incsub_event_start_time_'.$i.'" class="incsub_event incsub_event_time incsub_event_time_picker incsub_event_start_time" value="" size="3" readonly/>';
				$content .= ' <input type="checkbox" name="incsub_event_no_start_time['.$i.']" id="incsub_event_no_start_time_'.$i.'" class="incsub_event incsub_event_time incsub_event_no_start_time" value="1" />';
				$content .= ' <label for="incsub_event_no_start_time_'.$i.'">' . __( 'No start time', self::TEXT_DOMAIN ) . '</label>';
			    $content .= '</div>';

			    $content .= '<div class="misc-eab-section"><label class="eab-inline-label" for="incsub_event_end_'.$i.'">';
			    $content .= sprintf( __('%sEnd%s', self::TEXT_DOMAIN ), '<span>', '</span>' ).'</label>';
			    $content .= '<input type="text" name="incsub_event_end['.$i.']" id="incsub_event_end_'.$i.'" class="incsub_event_picker incsub_event incsub_event_date incsub_event_end" value="" size="10" readonly/> ';
			    $content .= '<input type="text" name="incsub_event_end_time['.$i.']" id="incsub_event_end_time_'.$i.'" class="incsub_event incsub_event_time incsub_event_time_picker incsub_event_end_time" value="" size="3" readonly/>';
				$content .= ' <input type="checkbox" name="incsub_event_no_end_time['.$i.']" id="incsub_event_no_end_time_'.$i.'" class="incsub_event incsub_event_time incsub_event_no_end_time" value="1" />';
				$content .= ' <label for="incsub_event_no_end_time_'.$i.'">' . __( 'No end time', self::TEXT_DOMAIN ) . '</label>';
			    $content .= '</div>';
			    $content .= '</div>';
			}
			$content .= '</div>';

			$content .= '<div id="eab-add-more"><input type="button" name="eab-add-more-button" id="eab-add-more-button" class="eab_add_more" value="'.__('Click here to add another date to event', self::TEXT_DOMAIN).'"/></div>';
			$i = !empty($i) ? $i : 0;

			$content .= '<div id="eab-add-more-bank">';
			$content .= '<div class="eab-section-block">';
			$content .= '<div class="eab-section-heading">' . sprintf(__('Part bank', self::TEXT_DOMAIN), $i+1) . '&nbsp' . '<a href="#remove" class="eab-event-remove_time">' . __('Remove', self::TEXT_DOMAIN) . '</a></div>';
			$content .= '<div class="misc-eab-section eab-start-section"><label for="incsub_event_start_bank" >';
			$content .= sprintf( __('%sStart%s', self::TEXT_DOMAIN ), '<span>', '</span>' ).'</label>';
			$content .= '<input type="text" name="incsub_event_start_b[bank]" id="incsub_event_start_bank" class="incsub_event_picker_b incsub_event incsub_event_date incsub_event_start_b" value="" size="10" readonly/> ';
			$content .= '<input type="text" name="incsub_event_start_time_b[bank]" id="incsub_event_start_time_bank" class="incsub_event incsub_event_time incsub_event_time_picker incsub_event_start_time_b" value="" size="3" readonly/>';
			$content .= ' <input type="checkbox" name="incsub_event_no_start_time[bank]" id="incsub_event_no_start_time_bank" class="incsub_event incsub_event_time incsub_event_no_start_time" value="1" />';
			$content .= ' <label for="incsub_event_no_start_time_bank">' . __('No start time', self::TEXT_DOMAIN) . '</label>';
			$content .= '</div>';

			$content .= '<div class="misc-eab-section eab-end-section"><label for="incsub_event_end_bank">';
			$content .= sprintf( __('%sEnd%s', self::TEXT_DOMAIN ), '<span>', '</span>' ).'</label>';
			$content .= '<input type="text" name="incsub_event_end_b[bank]" id="incsub_event_end_bank" class="incsub_event_picker_b incsub_event incsub_event_date incsub_event_end_b" value="" size="10" readonly/> ';
			$content .= '<input type="text" name="incsub_event_end_time_b[bank]" id="incsub_event_end_time_bank" class="incsub_event incsub_event_time incsub_event_time_picker incsub_event_end_time_b" value="" size="3" readonly/>';
			$content .= ' <input type="checkbox" name="incsub_event_no_end_time[bank]" id="incsub_event_no_end_time_bank" class="incsub_event incsub_event_time incsub_event_no_end_time" value="1" />';
			$content .= ' <label for="incsub_event_no_end_time_bank">' . __('No end time', self::TEXT_DOMAIN) . '</label>';
			$content .= '</div></div>';
			$content .= '</div>';
		} else {
			$content .= $this->_meta_box_part_recurring_edit($event);
		}

		$content .= '</div>';

		return $content;
    }

	private function _meta_box_part_recurring_edit ($event) {
		$events = Eab_CollectionFactory::get_all_recurring_children_events($event);
		$dt_format = get_option('date_format') . ' ' . get_option('time_format');

		$selection = '<h4><a href="#edit-instances" id="eab_event-edit_recurring_instances">' . __('Edit instances', self::TEXT_DOMAIN) . '</a></h4>';
		$selection .= "<ul id='eab_event-recurring_instances' style='display:none'>";
		foreach ($events as $instance) {
			$url = admin_url('post.php?post=' . $instance->get_id() . '&action=edit');
			$start = date($dt_format, $instance->get_start_timestamp());
			$selection .= "<li><a href='{$url}'>{$start}</a></li>";
		}
		$selection .= '</ul>';

		return $selection;
	}

	private function _meta_box_part_recurring_add ($event) {
		if ($event->is_recurring_child()) return false;

		$supported_intervals = $event->get_supported_recurrence_intervals();

		$content = '';
		if (!$event->is_recurring()) {
			$content = '<div id="eab-start_recurrence">' .
				'<input type="button" id="eab-eab-start_recurrence-button" class="button" value="' .
					__('This is a recurring event', self::TEXT_DOMAIN) .
					'" data-eab-alter_label="' . __('This is a regular event', self::TEXT_DOMAIN) . '" ' .
				' />' .
			'</div>';
		}

		$style = $event->is_recurring() ? '' : 'style="display:none"';
		$content .= '<div id="eab_event-recurring_event" ' . $style . '>';

		$parts = wp_parse_args(
			$event->get_recurrence_parts(),
			array(
				'month' => '', 'weekday' => '', 'day' => '', 'time' => '', 'duration' => '',
			)
		);

		$starts_ts = $event->get_recurrence_starts();
		$starts_ts = $starts_ts ? $starts_ts : eab_current_time();
		$starts = date('Y-m-d', $starts_ts);

		$ends_ts = $event->get_recurrence_ends();
		$ends_ts = $ends_ts ? $ends_ts : strtotime("+1 month");
		$ends = date('Y-m-d', $ends_ts);

		// Start on...
		$content .= '<label for="eab_event-repeat_start">' . __('Start on', self::TEXT_DOMAIN);
		$content .= ' <input type="text" name="eab_repeat[repeat_start]" id="eab_event-repeat_start" value="' . $starts . '" readonly/>';
		$content .= '</label>';

		// Repeat every...
		$content .= '<br />';
		$content .= '<label for="eab_event-repeat_every">' . __('Repeat every', self::TEXT_DOMAIN);
		$content .= ' <select name="eab_repeat[repeat_every]" id="eab_event-repeat_every">';
		$content .= '<option value="">' . __('Select one', self::TEXT_DOMAIN) . '</option>';
		foreach ($supported_intervals as $key => $label) {
			$selected = $event->is_recurring($key) ? 'selected="selected"' : '';
			$content .= "<option value='{$key}' {$selected}>{$label}</option>";
		}
		$content .= '</select>';
		$content .= '</label>';

		// ... Year
		if (in_array(Eab_EventModel::RECURRANCE_YEARLY, array_keys($supported_intervals))) {
			$style = $event->is_recurring(Eab_EventModel::RECURRANCE_YEARLY) ? '' : 'style="display:none"';
			$content .= '<div class="eab_event_recurrence_mode" id="eab_event-repeat_interval-' . Eab_EventModel::RECURRANCE_YEARLY . '" ' . $style . '>';
			$content .= '<select name="eab_repeat[month]">';
			for ($i=1; $i<=12; $i++) {
				$month = date('F', strtotime("2012-{$i}-01"));
				$selected = ($month == $parts['month']) ? 'selected="selected"' : '';
				$content .= "<option value='{$i}' {$selected}>{$month}</option>";
			}
			$content .= '</select> ';
			$content .= __('On', self::TEXT_DOMAIN) . ' <input type="text" size="2" class="incsub_event_picker" name="eab_repeat[day]" id="" value="' . $parts["day"] . '" readonly /> '; // Date
			$content .= __('At', self::TEXT_DOMAIN) . ' <input type="text" size="5" class="incsub_event_time_picker" name="eab_repeat[time]" id="" value="' . $parts["time"] . '" readonly /> <small>HH:mm</small>'; // Time
			$content .= '</div>';
		}

		// ... Month
		if (in_array(Eab_EventModel::RECURRANCE_MONTHLY, array_keys($supported_intervals))) {
			$style = $event->is_recurring(Eab_EventModel::RECURRANCE_MONTHLY) ? '' : 'style="display:none"';
			$content .= '<div class="eab_event_recurrence_mode" id="eab_event-repeat_interval-' . Eab_EventModel::RECURRANCE_MONTHLY . '" ' . $style . '>';
			$content .= __('On', self::TEXT_DOMAIN) . ' <input type="text" size="2" class="incsub_event_picker" name="eab_repeat[day]" id="" value="' . $parts["day"] . '" readonly /> '; // Date
			$content .= __('At', self::TEXT_DOMAIN) . ' <input type="text" size="5" class="incsub_event_time_picker" name="eab_repeat[time]" id="" value="' . $parts["time"] . '" readonly /> <small>HH:mm</small>'; // Time
			$content .= '</div>';
		}

		// ... Week
		if (in_array(Eab_EventModel::RECURRANCE_WEEKLY, array_keys($supported_intervals))) {
			$style = $event->is_recurring(Eab_EventModel::RECURRANCE_WEEKLY) ? '' : 'style="display:none"';
			$content .= '<div class="eab_event_recurrence_mode" id="eab_event-repeat_interval-' . Eab_EventModel::RECURRANCE_WEEKLY . '" ' . $style . '>';
			$all_weekdays = range(0,6);
			$start_of_week = get_option('start_of_week', 0);
			if ($start_of_week) {
				for ($n = 1; $n<=$start_of_week; $n++) array_push($all_weekdays, array_shift($all_weekdays));
			}
			$tmp = strtotime("this Sunday") + ($start_of_week * 86400);
			foreach ($all_weekdays as $i) {
				$checked = (is_array($parts['weekday']) && in_array($i, $parts['weekday'])) ? 'checked="checked"' : '';
				$content .= "<input type='checkbox' name='eab_repeat[weekday][]' id='' value='{$i}' {$checked} /> ";
				$content .= "<label for=''>" . date("D", $tmp) . '</label><br />';
				$tmp += 86400;
			}
			$content .= __('At', self::TEXT_DOMAIN) . ' <input type="text" size="5" name="eab_repeat[time]" class="incsub_event_time_picker" id="" value="' . $parts["time"] . '" readonly /> <small>HH:mm</small>'; // Time
			$content .= '</div>';
		}

		// ... DOW
		if (in_array(Eab_EventModel::RECURRANCE_DOW, array_keys($supported_intervals))) {
			$style = $event->is_recurring(Eab_EventModel::RECURRANCE_DOW) ? '' : 'style="display:none"';
			$content .= '<div class="eab_event_recurrence_mode" id="eab_event-repeat_interval-' . Eab_EventModel::RECURRANCE_DOW . '" ' . $style . '>';

			$week_counts = array(
				'first' => __('First', self::TEXT_DOMAIN),
				'second' => __('Second', self::TEXT_DOMAIN),
				'third' => __('Third', self::TEXT_DOMAIN),
				'fourth' => __('Fourth', self::TEXT_DOMAIN),
				'fifth' => __('Fifth', self::TEXT_DOMAIN),
				'last' => __('Last', self::TEXT_DOMAIN),
			);
			$week = '<select name="eab_repeat[week]">';
			foreach ($week_counts as $count => $label) {
				$selected = !empty($parts['week']) && $count == $parts['week'] ? 'selected="selected"' : '';
				$week .= "<option value='{$count}' {$selected}>{$label}</option>";
			}
			$week .= '</select>';

			$all_weekdays = range(0,6);
			$start_of_week = get_option('start_of_week', 0);
			if ($start_of_week) {
				for ($n = 1; $n<=$start_of_week; $n++) array_push($all_weekdays, array_shift($all_weekdays));
			}
			$tmp = strtotime("this Sunday") + ($start_of_week * 86400);
			$weekday = '<select name="eab_repeat[weekday]">';
			foreach ($all_weekdays as $i) {
				$day = date('l', $tmp);
				$selected = $day == $parts['weekday'] ? 'selected="selected"' : '';
				$weekday .= "<option value='{$day}' {$selected} /> " . date_i18n("l", $tmp) . "</option>";
				$tmp += 86400;
			}
			$weekday .= '</select>';

			$content .= sprintf(__('Every %s %s', self::TEXT_DOMAIN), $week, $weekday) . '<br />';
			$content .= __('At', self::TEXT_DOMAIN) . ' <input type="text" size="5" name="eab_repeat[time]" class="incsub_event_time_picker" id="" value="' . $parts["time"] . '" readonly/> <small>HH:mm</small>'; // Time
			$content .= '</div>';
		}

		// ... Week Count
		if (in_array(Eab_EventModel::RECURRANCE_WEEK_COUNT, array_keys($supported_intervals))) {
			$style = $event->is_recurring(Eab_EventModel::RECURRANCE_WEEK_COUNT) ? '' : 'style="display:none"';
			$content .= '<div class="eab_event_recurrence_mode" id="eab_event-repeat_interval-' . Eab_EventModel::RECURRANCE_WEEK_COUNT . '" ' . $style . '>';

			$week = '<select name="eab_repeat[week]">';
			foreach (range(1,25) as $count) {
				$selected = !empty($parts['week']) && $count == $parts['week'] ? 'selected="selected"' : '';
				$week .= "<option value='{$count}' {$selected}>{$count}</option>";
			}
			$week .= '</select>';

			$all_weekdays = range(0,6);
			$start_of_week = get_option('start_of_week', 0);
			if ($start_of_week) {
				for ($n = 1; $n<=$start_of_week; $n++) array_push($all_weekdays, array_shift($all_weekdays));
			}
			$tmp = strtotime("this Sunday") + ($start_of_week * 86400);
			$weekday = '<select name="eab_repeat[weekday]">';
			foreach ($all_weekdays as $i) {
				$day = date('l', $tmp);
				$selected = $day == $parts['weekday'] ? 'selected="selected"' : '';
				$weekday .= "<option value='{$day}' {$selected} /> " . date_i18n("l", $tmp) . "</option>";
				$tmp += 86400;
			}
			$weekday .= '</select>';

			$content .= sprintf(__('Every %s weeks, on %s', self::TEXT_DOMAIN), $week, $weekday) . '<br />';
			$content .= __('At', self::TEXT_DOMAIN) . ' <input type="text" size="5" name="eab_repeat[time]" class="incsub_event_time_picker" id="" value="' . $parts["time"] . '" readonly/> <small>HH:mm</small>'; // Time
			$content .= '</div>';
		}

		// ... Day
		if (in_array(Eab_EventModel::RECURRANCE_DAILY, array_keys($supported_intervals))) {
			$style = $event->is_recurring(Eab_EventModel::RECURRANCE_DAILY) ? '' : 'style="display:none"';
			$content .= '<div class="eab_event_recurrence_mode" id="eab_event-repeat_interval-' . Eab_EventModel::RECURRANCE_DAILY . '" ' . $style . '>';
			$content .= __('At', self::TEXT_DOMAIN) . ' <input type="text" size="5" name="eab_repeat[time]" class="incsub_event_time_picker" id="" value="' . $parts["time"] . '" readonly/> <small>HH:mm</small>'; // Time
			$content .= '</div>';
		}

		// ... Until
		$content .= '<br />';
		$content .= '<label for="eab_event-repeat_end">' . __('Until', self::TEXT_DOMAIN);
		$content .= ' <input type="text" name="eab_repeat[repeat_end]" class="incsub_event_picker" id="eab_event-repeat_end" value="' . $ends . '" readonly />';
		$content .= '</label>';

		// ... Duration
		$content .= '<br />';
		$content .= '<label for="eab_event-repeat_event_duration">' . __('Event duration', self::TEXT_DOMAIN);
		$content .= ' <input type="text" name="eab_repeat[duration]" class="incsub_event_time_picker" size="2" id="eab_event-repeat_event_duration" value="' . $parts["duration"] . '" readonly/> ' . __('hours', self::TEXT_DOMAIN);
		$content .= '</label>';

		$content .= '</div>';

		return $content;
	}

    function meta_box_part_status () {
		global $post;
		$event = new Eab_EventModel($post);

		$status = $event->get_status();
		$status = $status ? $status : Eab_EventModel::STATUS_OPEN;

		$content  = '';
		$content .= '<div class="eab_meta_box">';
		$content .= '<div class="eab_meta_column_box">'.__('Event status', self::TEXT_DOMAIN).'</div>';
		$content .= '<input type="hidden" name="incsub_event_status_meta" value="1" />';
		$content .= '<div class="misc-eab-section"><label for="incsub_event_status" id="incsub_event_status_label">';
		$content .= __('What is the event status? ', self::TEXT_DOMAIN).':</label>&nbsp;';
		$content .= '<select name="incsub_event_status" id="incsub_event_status">';
		$content .= '	<option value="open" '.(($event->is_open())?'selected="selected"':'').' >'.__('Open', self::TEXT_DOMAIN).'</option>';
		$content .= '	<option value="closed" '.(($event->is_closed())?'selected="selected"':'').' >'.__('Closed', self::TEXT_DOMAIN).'</option>';
		$content .= '	<option value="expired" '.(($event->is_expired())?'selected="selected"':'').' >'.__('Expired', self::TEXT_DOMAIN).'</option>';
		$content .= '	<option value="archived" '.(($event->is_archived())?'selected="selected"':'').' >'.__('Archived', self::TEXT_DOMAIN).'</option>';
		$content .= apply_filters('eab-event_meta-extra_event_status', '', $event);
		$content .= '</select>';
		$content .= apply_filters('eab-event_meta-after_event_status', '', $event);
		$content .= '</div>';
		$content .= '<div class="clear"></div>';
		$content .= '</div>';

		return $content;
    }

    function meta_box_part_payments () {
		global $post;
		$event = new Eab_EventModel($post);

		$content  = '';
		$content .= '<div class="eab_meta_box">';
		$content .= '<input type="hidden" name="incsub_event_payments_meta" value="1" />';
		$content .= '<div class="misc-eab-section">';
		$content .= '<div class="eab_meta_column_box">'.__( 'Event type', self::TEXT_DOMAIN ).'</div>';
		$content .= '<label for="incsub_event_paid" id="incsub_event_paid_label">'.__('Is this a paid event? ', self::TEXT_DOMAIN).':</label>&nbsp;';
		$content .= '<select name="incsub_event_paid" id="incsub_event_paid" class="incsub_event_paid" >';
		$content .= '<option value="1" ' . ( $event->is_premium() ? 'selected="selected"' : '' ) . '>'.__('Yes', self::TEXT_DOMAIN).'</option>';
		$content .= '<option value="0" ' . ( $event->is_premium() ? '' : 'selected="selected"' ) . '>'.__('No', self::TEXT_DOMAIN).'</option>';
		$content .= '</select>';
		$content .= '<div class="clear"></div>';
		$content .= '<div class="incsub_event-fee_row" id="incsub_event-fee_row_label">';

		$fee = __( 'Fee', self::TEXT_DOMAIN) . ':&nbsp;' . $this->_data->get_option('currency') .
			'&nbsp;<input type="text" name="incsub_event_fee" id="incsub_event_fee" class="incsub_event_fee" value="' .
			$event->get_price() . '" size="6" /> ';
		$content .= apply_filters('eab-event_meta-event_price', $fee, $event->get_id()) . '</div>';

		$content .= '</div>';
		$content .= '</div>';

		return $content;
    }

    function bookings_meta_box () {
		global $post;
		echo '<a class="button" href="' . admin_url( 'index.php?eab_export=attendees&event_id='. $post->ID ) . '" class="eab-export_attendees">' .
			__('Export', self::TEXT_DOMAIN) . '</a>';
		echo $this->meta_box_part_bookings( $post );
	}

	function meta_box_part_bookings ( $post ) {
		$event = new Eab_EventModel( $post );

		$content  = '';
		$content .= '<div id="eab-bookings-response">';
		$content .= '<input type="hidden" name="incsub_event_bookings_meta" value="1" />';
		$content .= '<div class="bookings-list-left">';

		$content .= Eab_Template::get_admin_attendance_addition_form($event, Eab_Template::get_rsvp_status_list());

		if (
			(!$event->is_recurring() && $event->has_bookings(false))
			||
			($event->is_recurring() && $event->has_child_bookings(false))
		) {
	    	$content .= '<div id="event-booking-yes">';
            $content .= Eab_Template::get_admin_bookings(Eab_EventModel::BOOKING_YES, $event);
            $content .= '</div>';

            $content .= '<div id="event-booking-maybe">';
            $content .= Eab_Template::get_admin_bookings(Eab_EventModel::BOOKING_MAYBE, $event);
            $content .= '</div>';

	    	$content .= '<div id="event-booking-no">';
            $content .= Eab_Template::get_admin_bookings(Eab_EventModel::BOOKING_NO, $event);
            $content .= '</div>';

            $content .= apply_filters('eab-metabox-bookings-has_bookings', '', $event);
        }  else {
            $content .= __('No bookings', self::TEXT_DOMAIN);
        }
		$content .= '</div>';
		$content .= '<div class="clear"></div>';
		$content .= '</div>';

		return $content;
    }

    function save_event_meta($post_id, $post = null) {
		global $wpdb;

		//skip quick edit
		if (defined('DOING_AJAX')) return;

	    // Setting up event venue
		if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_where_meta'] ) ) {
		    $meta = get_post_custom($post_id);

		    update_post_meta($post_id, 'incsub_event_venue', strip_tags($_POST['incsub_event_venue']));

		    //for any other plugin to hook into
		    do_action( 'incsub_event_save_where_meta', $post_id, $meta );
		}

		// Setting up event status
		if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_status_meta'] ) ) {
		    $meta = get_post_custom($post_id);

		    update_post_meta($post_id, 'incsub_event_status', strip_tags($_POST['incsub_event_status']));

		    //for any other plugin to hook into
		    do_action( 'incsub_event_save_status_meta', $post_id, $meta );
		}

		// Setting up event payments
		if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_payments_meta'] ) ) {
		    $meta = get_post_custom($post_id);

			$is_paid = (int)$_POST['incsub_event_paid'];
			$fee = $is_paid ? strip_tags($_POST['incsub_event_fee']) : '';

		    update_post_meta($post_id, 'incsub_event_paid', ($is_paid ? '1' : ''));
		    update_post_meta($post_id, 'incsub_event_fee', $fee);

		    //for any other plugin to hook into
		    do_action( 'incsub_event_save_payments_meta', $post_id, $meta );
		}

		// Setting up recurring event
		if ('incsub_event' == $post->post_type && isset($_POST['eab_repeat'])) {
			$repeat = $_POST['eab_repeat'];
			$start = $repeat['repeat_start'] ? strtotime($repeat['repeat_start']) : eab_current_time();
			$end =  $repeat['repeat_end'] ? strtotime($repeat['repeat_end']) : eab_current_time();
			if ($end <= $start) {
				// BAH! Wrong order
			}
			$interval = $repeat['repeat_every'];
			$time_parts = array(
				'month' => @$repeat['month'],
				'day' => @$repeat['day'],
				'weekday' => @$repeat['weekday'],
				'week' => @$repeat['week'],
				'time' => @$repeat['time'],
				'duration' => @$repeat['duration'],
			);
			$event = new Eab_EventModel($post);
			$event->spawn_recurring_instances($start, $end, $interval, $time_parts); //@TODO: Improve
		}

		if ( $post->post_type == "incsub_event" && isset( $_POST['incsub_event_when_meta'] ) ) {
		    $meta = get_post_custom($post_id);

			delete_post_meta($post_id, 'incsub_event_start');
			delete_post_meta($post_id, 'incsub_event_no_start');
			delete_post_meta($post_id, 'incsub_event_end');
			delete_post_meta($post_id, 'incsub_event_no_end');
		   	if (isset($_POST['incsub_event_start']) && count($_POST['incsub_event_start']) > 0) foreach ($_POST['incsub_event_start'] as $i => $event_start) {
		   		if (empty($_POST['incsub_event_start'][$i]) || empty($_POST['incsub_event_end'][$i])) continue;
		   		if (!empty($_POST['incsub_event_start'][$i])) {

                                    if( $_POST['incsub_event_start_time'][$i] != '' && strpos( ':', $_POST['incsub_event_start_time'][$i] ) === false ){
                                        $_POST['incsub_event_start_time'][$i] = $_POST['incsub_event_start_time'][$i] . ':00';
                                    }

					$start_time = @$_POST['incsub_event_no_start_time'][$i] ? '00:01' : @$_POST['incsub_event_start_time'][$i];
				    add_post_meta($post_id, 'incsub_event_start', date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_start'][$i]} {$start_time}")));
				    if (@$_POST['incsub_event_no_start_time'][$i]) add_post_meta($post_id, 'incsub_event_no_start', 1);
				    else add_post_meta($post_id, 'incsub_event_no_start', 0);
				}
				if (!empty($_POST['incsub_event_end'][$i])) {

                                        if( $_POST['incsub_event_end_time'][$i] != '' && strpos( ':', $_POST['incsub_event_end_time'][$i] ) === false ){
                                            $_POST['incsub_event_end_time'][$i] = $_POST['incsub_event_end_time'][$i] . ':00';
                                        }

		   			$end_time = @$_POST['incsub_event_no_end_time'][$i] ? '23:59' : @$_POST['incsub_event_end_time'][$i];
				    add_post_meta($post_id, 'incsub_event_end', date('Y-m-d H:i:s', strtotime("{$_POST['incsub_event_end'][$i]} {$end_time}")));
				    if (@$_POST['incsub_event_no_end_time'][$i]) add_post_meta($post_id, 'incsub_event_no_end', 1);
				    else add_post_meta($post_id, 'incsub_event_no_end', 0);
				}

			}

		    //for any other plugin to hook into
		    do_action( 'incsub_event_save_when_meta', $post_id, $meta );
		}

		if ('incsub_event' == $post->post_type) do_action('eab-event_meta-save_meta', $post_id);
		if ('incsub_event' == $post->post_type) do_action('eab-event_meta-after_save_meta', $post_id);
    }

    /**
     * Kills off the view links in messages when recurring event is being saved.
     *
     * @param array $messages Post updated messages
     *
     * @return array Processed messages
     */
    function handle_post_updated_messages ($messages) {
    	if (defined('DOING_AJAX')) return $messages;
    	if (empty($messages['post'])) return $messages;

    	$post = get_post();
    	if (empty($post->post_type) || Eab_EventModel::POST_TYPE !== $post->post_type) return $messages;

    	$event = new Eab_EventModel($post);
    	if (!$event->is_recurring()) return $messages; // Normal events don't need this - just recurring

    	$hub_permalink = preg_quote(get_permalink($post->ID), '/');
    	foreach ($messages['post'] as $idx => $msg) {
    		if (!preg_match('/<a .*href=[\'"]' . $hub_permalink . '[\'"]/', $msg)) continue;
    		$messages['post'][$idx] = preg_replace('/<a .*href=[\'"]' . $hub_permalink . '[\'"].*?<\/a>/', '', $msg);
    	}

    	return $messages;
    }

    function post_type_link($permalink, $post_obj, $leavename) {
		if (empty($permalink)) return $permalink;

		$post_id = $post = false;
		if (is_object($post_obj) && !empty($post_obj->ID)/* && !empty($post_obj->post_name)*/) {
			$post_id = $post_obj->ID;
			$post = $post_obj;
		} else if (is_numeric($post_obj)) {
			$post_id = $post_obj;
			$post = get_post($post_id);
		}

		$rewritecode = array(
		    '%incsub_event%',
		    '%event_year%',
		    '%event_monthnum%'
		);

		if ($post && $post->post_type == 'incsub_event' && '' != $permalink) {
		    $starts = get_post_meta($post_id, 'incsub_event_start');
		    $start = isset($starts[0])
		    	? strtotime($starts[0])
		    	: eab_current_time()
		    ;

		    $year = date('Y', $start);
		    $month = date('m', $start);

		    $rewritereplace = array(
		    	($post->post_name == "") ? (isset($post->ID) ? $post->ID : 0) : $post->post_name,
				$year,
				$month,
		    );
		    $permalink = str_replace($rewritecode, $rewritereplace, $permalink);
		}

		return $permalink;
    }

    private function _get_rewrite_rules () {
    	$slug = $this->_data->get_option('slug');
    	return self::get_rewrite_rules($slug);
    }

    public static function get_rewrite_rules ($slug) {
    	return array(
			"{$slug}/([0-9]{4})/?$" => 'index.php?event_year=$matches[1]&post_type=incsub_event',
			"{$slug}/([0-9]{4})/([0-9]{1,2})/?$" => 'index.php?event_year=$matches[1]&event_monthnum=$matches[2]&post_type=incsub_event',
			"{$slug}/([0-9]{4})/([0-9]{1,2})/(.+?)/(^feed)/?$" => 'index.php?event_year=$matches[1]&event_monthnum=$matches[2]&incsub_event=$matches[3]',
			"{$slug}/([0-9]{4})/([0-9]{1,2})/(.+?)/feed/?$" => 'index.php?event_year=$matches[1]&event_monthnum=$matches[2]&incsub_event=$matches[3]&feed=rss2&post_type=incsub_event'
    	);
    }

    function add_rewrite_rules($rules){
		$new_rules = $this->_get_rewrite_rules();
		foreach ($new_rules as $rx => $rpl) {
			unset($rules[$rx]);
		}
		return array_merge($new_rules, $rules);
    }

    function check_rewrite_rules ($values) {
		remove_action('option_rewrite_rules', array($this, 'check_rewrite_rules'));
		//prevent an infinite loop
		if (!post_type_exists(Eab_EventModel::POST_TYPE)) return $values;

		$values = is_array($values) ? $values : array();
		$rules = $this->_get_rewrite_rules();

		foreach ($rules as $rx => $rpl) {
			if (array_key_exists($rx, $values)) continue;
			$this->flush_rewrite();
			break;
		}
		return $values;
    }

    function query_vars($vars) {
		array_push($vars, 'event_year');
		array_push($vars, 'event_monthnum');
		return $vars;
    }


	function flush_rewrite() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}





    /**
     * Filter out the actions because we're splicing in our own, for event post types.
     *
     * @param array $actions
     * @param WP_Post $post
     *
     * @return array
     */
    public function manage_post_actions ($actions, $post) {
    	if (empty($post->post_type)) return $actions;
    	return Eab_EventModel::POST_TYPE !== $post->post_type
    		? $actions
    		: array()
    	;
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
				$capable = apply_filters('eab-capabilities-user_can', $capable, $cap, $current_user, $args);

				if ($capable) {
				    $allcaps[$cap] = 1;
				}
		    }
		}
		return $allcaps;
    }




    function cron_schedules($schedules) {
		$schedules['thirtyminutes'] = array( 'interval' => 1800, 'display' => __('Once every half an hour', self::TEXT_DOMAIN) );

		return $schedules;
    }


    function views_list($views) {
		global $wp_query;

		$avail_post_stati = wp_edit_posts_query();
		$num_posts = wp_count_posts( 'incsub_event', 'readable' );

		$argvs = array('post_type' => 'incsub_event');

		foreach ( get_post_stati($argvs, 'objects') as $status ) {
		    $class = '';
		    $status_name = $status->name;

		    if (!in_array($status_name, $avail_post_stati)) continue;
		    if (empty($num_posts->$status_name)) continue;

		    if (isset($_GET['post_status']) && $status_name == $_GET['post_status']) {
		        $class = ' class="current"';
		    }

		    $views[$status_name] = "<li><a href='edit.php?post_type=incsub_event&amp;post_status=$status_name'$class>" . sprintf( _n( $status->label_count[0], $status->label_count[1], $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
		}

		return $views;
    }





    function widgets_init() {
		require_once dirname(__FILE__) . '/lib/widgets/Widget.class.php';
		require_once dirname(__FILE__) . '/lib/widgets/Attendees_Widget.class.php';
		require_once dirname(__FILE__) . '/lib/widgets/Popular_Widget.class.php';
		require_once dirname(__FILE__) . '/lib/widgets/Upcoming_Widget.class.php';
		require_once dirname(__FILE__) . '/lib/widgets/UpcomingCalendar_Widget.class.php';
		require_once dirname(__FILE__) . '/lib/widgets/EAB_Month_Navigation.php';

		register_widget('Eab_Attendees_Widget');
		register_widget('Eab_Popular_Widget');
		register_widget('Eab_Upcoming_Widget');
		register_widget('Eab_CalendarUpcoming_Widget');
		register_widget('Eab_Month_Navigation_Widget');
    }

	/**
	 * Save a message to the log file
	 */
	function log( $message='' ) {
		// Don't give warning if folder is not writable
		@file_put_contents( WP_PLUGIN_DIR . "/events-and-bookings/log.txt", $message . chr(10). chr(13), FILE_APPEND );
	}



	/**
	 * Proper query rewriting.
	 * HAVE to calculate in the year as well.
	 */
	function load_events_from_query () {
		if (is_admin()) return false;
		global $wp_query;

		if (Eab_EventModel::POST_TYPE == $wp_query->query_vars['post_type']) {
			$original_year = isset($wp_query->query_vars['event_year']) ? (int)$wp_query->query_vars['event_year'] : false;
			$year = $original_year ? $original_year : date('Y');
			$original_month = isset($wp_query->query_vars['event_monthnum']) ? (int)$wp_query->query_vars['event_monthnum'] : false;
			$month = $original_month ? $original_month : date('m');

			do_action('eab-query_rewrite-before_query_replacement', $original_year, $original_month);
			$wp_query = Eab_CollectionFactory::get_upcoming(strtotime("{$year}-{$month}-01 00:00"), $wp_query->query);
			$wp_query->is_404 = false;
			do_action('eab-query_rewrite-after_query_replacement');
		} else if (!empty($wp_query->query_vars['eab_events_category']) && empty($wp_query->query_vars['paged']) && !empty($_GET['date'])) {
			$date = strtotime($_GET['date']);
			if (!$date) return false;

			do_action('eab-query_rewrite-before_query_replacement', false, false);
			$wp_query = Eab_CollectionFactory::get_upcoming($date, $wp_query->query);
			$wp_query->is_404 = false;
			do_action('eab-query_rewrite-after_query_replacement');
		}
	}

}

function eab_autoshow_map_off ($opts) {
	@$opts['custom_fields_options']['autoshow_map'] = false;
	return $opts;
}

define( 'EAB_PLUGIN_BASENAME', basename( dirname( __FILE__ ) ), true );
define( 'EAB_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'EAB_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

include_once EAB_PLUGIN_DIR . 'template-tags.php';

if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
	include_once( 'lib/class-eab-ajax.php' );


if (!defined('EAB_OLD_EVENTS_EXPIRY_LIMIT')) define('EAB_OLD_EVENTS_EXPIRY_LIMIT', 100, true);
if (!defined('EAB_MAX_UPCOMING_EVENTS')) define('EAB_MAX_UPCOMING_EVENTS', 500, true);

require_once EAB_PLUGIN_DIR . 'lib/class_eab_error_reporter.php';
Eab_ErrorReporter::serve();

require_once EAB_PLUGIN_DIR . 'lib/class_eab_options.php';
require_once EAB_PLUGIN_DIR . 'lib/class_eab_collection.php';
require_once EAB_PLUGIN_DIR . 'lib/class_eab_codec.php';
require_once EAB_PLUGIN_DIR . 'lib/class_eab_event_model.php';
require_once EAB_PLUGIN_DIR . 'lib/class_eab_template.php';
require_once EAB_PLUGIN_DIR . 'lib/class_eab_api.php';
require_once EAB_PLUGIN_DIR . 'lib/class-eab-taxonomies.php';

// Lets get things started
$__booking = events_and_bookings(); // @TODO: Refactor

require_once EAB_PLUGIN_DIR . 'lib/class_eab_network.php';
Eab_Network::serve();
require_once EAB_PLUGIN_DIR . 'lib/class_eab_shortcodes.php';
Eab_Shortcodes::serve();
require_once EAB_PLUGIN_DIR . 'lib/class_eab_scheduler.php';
Eab_Scheduler::serve();
require_once EAB_PLUGIN_DIR . 'lib/class_eab_addon_handler.php';
Eab_AddonHandler::serve();

require_once EAB_PLUGIN_DIR . 'lib/default_filters.php';

if (is_admin()) {
	require_once EAB_PLUGIN_DIR . 'lib/class_eab_admin_tutorial.php';
	Eab_AdminTutorial::serve();

	require_once dirname(__FILE__) . '/lib/contextual_help/class_eab_admin_help.php';
	Eab_AdminHelp::serve();

	// Dashboard notification
	global $wpmudev_notices;
	if (!is_array($wpmudev_notices)) $wpmudev_notices = array();
	$wpmudev_notices[] = array(
		'id' => 249,
		'name' => 'Events +',
		'screens' => array(
			'incsub_event_page_eab_welcome',
			'incsub_event_page_eab_settings',
			'incsub_event_page_eab_shortcodes',
		),
	);
	require_once EAB_PLUGIN_DIR . '/lib/wpmudev-dash-notification.php';
}


function eab_activate() {
	include_once( 'lib/class-eab-activator.php' );
	Eab_Activator::run();
}
register_activation_hook(__FILE__, 'eab_activate' );


function eab_domain() {
	return Eab_EventsHub::TEXT_DOMAIN;
}

function events_and_bookings() {
	return Eab_EventsHub::get_instance();
}

function eab_plugin_dir() {
	return EAB_PLUGIN_DIR;
}

function eab_plugin_url() {
	return EAB_PLUGIN_URL;
}
