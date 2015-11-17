<?php

/**
 * Class Eab_Admin
 *
 * Manages all the admin side (code will be added in following versions)
 */
class Eab_Admin {

	public function __construct() {
		$this->includes();

		add_action('admin_init', array( $this, 'admin_init' ), 0);

		add_action( 'admin_menu', array( $this, 'admin_menus' ) );

		add_action('admin_notices', array($this, 'check_permalink_format'));

		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
		add_action('admin_print_styles', array($this, 'admin_print_styles') );

		add_action('manage_incsub_event_posts_custom_column', array($this, 'manage_posts_custom_column'));
		add_filter('manage_incsub_event_posts_columns', array($this, 'manage_posts_columns'), 99);
	}

	private function includes() {
		include_once( 'class-eab-settings-menu.php' );
		include_once( 'class-eab-shortcodes-menu.php' );
		include_once( 'class-eab-get-started-menu.php' );
	}

	function admin_init() {
		// Check for tables first
		if ( ! $this->_blog_has_tables() )
			eab_activate();

		if (get_option('eab_activation_redirect', false)) {
			delete_option('eab_activation_redirect');
			if (!(is_multisite() && is_super_admin()) || !is_network_admin()) {
				wp_redirect('edit.php?post_type=incsub_event&page=eab_welcome');
			}
		}

		// Register scripts/styles
		wp_register_script('eab_admin_js', plugins_url('events-and-bookings/js/eab-admin.js'), array('jquery'), Eab_EventsHub::CURRENT_VERSION);
		wp_register_style('eab_admin', plugins_url('events-and-bookings/css/admin.css'), null, Eab_EventsHub::CURRENT_VERSION);

		if (defined('AGM_PLUGIN_URL')) {
			add_action('admin_print_scripts-post.php', array($this, 'js_editor_button'));
			add_action('admin_print_scripts-post-new.php', array($this, 'js_editor_button'));
		}

		$event_localized = array(
			'view_all_bookings' => __('View all RSVPs', Eab_EventsHub::TEXT_DOMAIN),
			'back_to_gettting_started' => __('Back to getting started', Eab_EventsHub::TEXT_DOMAIN),
			'start_of_week' => get_option('start_of_week'),
		);

		wp_localize_script('eab_admin_js', 'eab_event_localized', $event_localized);

	}

	function check_permalink_format () {
		if (get_option('permalink_structure')) return;
		echo '<div class="error"><p>' .
		     sprintf(
			     __('You must must update your permalink structure to something other than default to use Events. <a href="%s">You can do so here.</a>', Eab_EventsHub::TEXT_DOMAIN),
			     admin_url('options-permalink.php')
		     ) .
		     '</p></div>';
	}

	private function _blog_has_tables () {
		global $wpdb;
		$table = Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE); // Check only one
		return ($wpdb->get_var("show tables like '{$table}'") == $table);
	}

	function admin_enqueue_scripts() {
		if (!$this->_check_admin_page_id())
			return;
		wp_enqueue_script('eab_jquery_ui');
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('eab_admin_js');
	}

	function admin_print_styles() {
		if (!$this->_check_admin_page_id())
			return;
		wp_enqueue_style('eab_jquery_ui');
		wp_enqueue_style('eab_admin');
	}

	function js_editor_button() {
		wp_enqueue_script('thickbox');
		wp_enqueue_script('eab_editor',  plugins_url('events-and-bookings/js/editor.js'), array('jquery'));
		wp_localize_script('eab_editor', 'eab_l10nEditor', array(
			'loading' => __('Loading maps... please wait', Eab_EventsHub::TEXT_DOMAIN),
			'use_this_map' => __('Insert this map', Eab_EventsHub::TEXT_DOMAIN),
			'preview_or_edit' => __('Preview/Edit', Eab_EventsHub::TEXT_DOMAIN),
			'delete_map' => __('Delete', Eab_EventsHub::TEXT_DOMAIN),
			'add_map' => __('Add Map', Eab_EventsHub::TEXT_DOMAIN),
			'existing_map' => __('Existing map', Eab_EventsHub::TEXT_DOMAIN),
			'no_existing_maps' => __('No existing maps', Eab_EventsHub::TEXT_DOMAIN),
			'new_map' => __('Create new map', Eab_EventsHub::TEXT_DOMAIN),
			'advanced' => __('Advanced mode', Eab_EventsHub::TEXT_DOMAIN),
			'advanced_mode_activate_help' => __('Activate Advanced mode to select individual maps to merge into one new map or to batch delete maps', Eab_EventsHub::TEXT_DOMAIN),
			'advanced_mode_help' => __('To create a new map from several maps select the maps you want to use and click Merge locations', Eab_EventsHub::TEXT_DOMAIN),
			'advanced_off' => __('Exit advanced mode', Eab_EventsHub::TEXT_DOMAIN),
			'merge_locations' => __('Merge locations', Eab_EventsHub::TEXT_DOMAIN),
			'batch_delete' => __('Batch delete', Eab_EventsHub::TEXT_DOMAIN),
			'new_map_intro' => __('Create a new map which can be inserted into this post or page. Once you are done you can manage all maps below', Eab_EventsHub::TEXT_DOMAIN),
		));
	}

	private function _check_admin_page_id () {
		$_page_ids = array (
			'incsub_event_page_eab_welcome',
			'edit-incsub_event',
			'incsub_event',
			'incsub_event_page_eab_shortcodes',
			'incsub_event_page_eab_settings',
		);
		$screen = get_current_screen();
		if (!in_array($screen->id, $_page_ids)) return false;
		return true;
	}

	/**
	 * Add the admin menus
	 *
	 * @see		http://codex.wordpress.org/Adding_Administration_Menus
	 */
	function admin_menus() {
		global $submenu;

		$root_key = 'edit.php?post_type=incsub_event';

		if (get_option('eab_setup', false) == false) {

			new Eab_Admin_Get_Started_Menu( $root_key );

			if (isset($submenu[$root_key]) && is_array($submenu[$root_key])) foreach ($submenu[$root_key] as $k=>$item) {
				if ($item[2] == 'eab_welcome') {
					$submenu[$root_key][1] = $item;
					unset($submenu[$root_key][$k]);
				}
			}
		}

		new Eab_Admin_Settings_Menu( $root_key );
		new Eab_Admin_Shortcodes_Menu( $root_key );

		do_action('eab-admin-add_pages', $root_key);

		if (isset($submenu[$root_key]) && is_array($submenu[$root_key])) ksort($submenu[$root_key]);
	}

	function manage_posts_custom_column($column) {
		global $post;

		switch ($column) {
			case "attendees":
				global $wpdb;
				$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
				$yes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s;", $event->get_id(), Eab_EventModel::BOOKING_YES));
				$no = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s;", $event->get_id(), Eab_EventModel::BOOKING_NO));
				$maybe = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s;", $event->get_id(), Eab_EventModel::BOOKING_MAYBE));
				printf('<b>' . __('Attending / Undecided', Eab_EventsHub::TEXT_DOMAIN) . ':</b> %d / %d<br />', $yes, $maybe);
				printf('<b>' . __('Not Attending', Eab_EventsHub::TEXT_DOMAIN) . ':</b> %d', $no);
				echo '&nbsp;';
				echo '<a class="button" href="' . admin_url('index.php?eab_export=attendees&event_id='. $event->get_id()) . '" class="eab-export_attendees">' .
				     __('Export', Eab_EventsHub::TEXT_DOMAIN) .
				     '</a>';
				break;
			case "start":
				$event = new Eab_EventModel($post);
				$df = get_option('date_format', 'Y-m-d');
				if (!$event->is_recurring()) {
					echo
						date_i18n($df, $event->get_start_timestamp()) .
						' - ' .
						date_i18n($df, $event->get_end_timestamp())
					;
				} else {
					$repeats = $event->get_supported_recurrence_intervals();
					$title = @$repeats[$event->get_recurrence()];
					$start = date_i18n($df, $event->get_recurrence_starts());
					$end = date_i18n($df, $event->get_recurrence_ends());
					printf(__("From %s, repeats every %s until %s", Eab_EventsHub::TEXT_DOMAIN), $start, $title, $end);
				}
				break;
			case "venue":
				$event = new Eab_EventModel($post);
				echo $event->get_venue_location();
				break;
			case "event":
				$event = new Eab_EventModel($post);
				$post_type_object = get_post_type_object($post->post_type);
				$edit_link = get_edit_post_link($event->get_id());

				$statuses = array();
				if ('draft' == $post->post_status) $statuses[] = __('Draft');
				if ('private' == $post->post_status) $statuses[] = __('Private');
				if ('pending' == $post->post_status) $statuses[] = __('Pending');
				$status = $statuses ? ' - <span class="post-state">' . join(', ', $statuses) . '</span>' : '';

				$title = (current_user_can($post_type_object->cap->edit_post, $event->get_id()) && 'trash' != $post->post_status)
					? '<strong>' . '<a class="row-title" href="' . $edit_link .'" title="' . esc_attr(sprintf(__('Edit &#8220;%s&#8221;' ), $event->get_title())) . '">' . $event->get_title() . '</a>&nbsp;' . $status . '</strong>'
					: '<strong>' . $event->get_title() . '&nbsp;' . $status . '</strong>'
				;

				if (current_user_can($post_type_object->cap->edit_post, $event->get_id()) && 'trash' != $post->post_status) {
					$actions['edit'] = '<a title="' . esc_attr(__('Edit Event', Eab_EventsHub::TEXT_DOMAIN)) . '" href="' . $edit_link . '">' . __('Edit') . '</a>';
					if (!$event->is_recurring()) $actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="' . esc_attr(__( 'Edit this Event inline', Eab_EventsHub::TEXT_DOMAIN)) . '">' . __('Quick&nbsp;Edit') . '</a>';
				}

				if (current_user_can($post_type_object->cap->delete_post, $event->get_id())) {
					if ('trash' == $post->post_status) {
						$actions['untrash'] = "<a title='" . esc_attr(__('Restore this Event from the Trash', Eab_EventsHub::TEXT_DOMAIN)) . "' href='" . wp_nonce_url(admin_url(sprintf($post_type_object->_edit_link . '&amp;action=untrash', $event->get_id())), 'untrash-' . $post->post_type . '_' . $event->get_id()) . "'>" . __('Restore') . "</a>";
					} else if (EMPTY_TRASH_DAYS) {
						$actions['trash'] = '<a class="submitdelete" title="' . esc_attr(__('Move this Event to the Trash', Eab_EventsHub::TEXT_DOMAIN)) . '" href="' . get_delete_post_link($event->get_id()) . '">' . __('Trash') . '</a>';
					}
					if ('trash' == $post->post_status || !EMPTY_TRASH_DAYS) {
						$actions['delete'] = "<a class='submitdelete' title='" . esc_attr(__('Delete this Event permanently', Eab_EventsHub::TEXT_DOMAIN)) . "' href='" . get_delete_post_link($event->get_id(), '', true ) . "'>" . __('Delete Permanently') . "</a>";
					}
				}

				if ('trash' != $post->post_status) {
					$event_id = $event->get_id();
					if ($event->is_recurring()) {
						$children = Eab_CollectionFactory::get_all_recurring_children_events($event);
						if (!$children || !($children[0]) instanceof Eab_EventModel) $event_id = false;
						else $event_id = $children[0]->get_id();
					}
					if ($event_id) {
						$actions['view'] = '<a href="' . get_permalink($event_id) . '" title="' . esc_attr(sprintf(__('View &#8220;%s&#8221;'), $event->get_title())) . '" rel="permalink">' . __('View') . '</a>';
					}
				}

				echo $title;
				if (!empty($actions)) {
					foreach ($actions as $action => $link) {
						$actions[$action] = "<span class='{$action}'>{$link}</span>";
					}
				}
				echo '<div class="row-actions">' . join('&nbsp;|&nbsp;', $actions) . '</div>';
				get_inline_data($post);
				break;
		}
	}

	function manage_posts_columns($old_columns)	{
		$columns['cb'] = $old_columns['cb'];
		$columns['event'] = $old_columns['title'];

		// Allow for WPML translation field
		if (isset($old_columns['icl_translations'])) {
			$columns['icl_translations'] = $old_columns['icl_translations'];
		}

		$columns['start'] = __('When', Eab_EventsHub::TEXT_DOMAIN);
		$columns['venue'] = __('Where', Eab_EventsHub::TEXT_DOMAIN);
		$columns['author'] = $old_columns['author'];
		$columns['date'] = $old_columns['date'];
		$columns['attendees'] = __('RSVPs', Eab_EventsHub::TEXT_DOMAIN);

		return $columns;
	}

}