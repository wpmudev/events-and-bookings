<?php
/*
Plugin Name: BuddyPress: Group Events
Description: Allows you to connect your Events with your BuddyPress groups.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.1
AddonType: BuddyPress
Author: WPMU DEV
*/

/*
Detail: Allows deeper integration of your Events with BuddyPress groups. <br /> <b>Requires BuddyPress Groups component</b>
*/ 

if( ! defined( 'EAB_SHOW_HIDDEN_GROUP' ) ) define( 'EAB_SHOW_HIDDEN_GROUP', false );

class Eab_BuddyPress_GroupEvents {
	
	const SLUG = 'group-events';
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}
	
	public static function serve () {
		$me = new Eab_BuddyPress_GroupEvents;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		
		if ($this->_data->get_option('bp-group_event-auto_join_groups')) {
			add_action('incsub_event_booking_yes', array($this, 'auto_join_group'), 10, 2);
			add_action('incsub_event_booking_maybe', array($this, 'auto_join_group'), 10, 2);
		}
		if ($this->_data->get_option('bp-group_event-private_events')) {
			add_filter('wpmudev-query', array($this, 'filter_query'));
		}

		add_action('bp_init', array($this, 'add_tab'));
		add_filter('eab-event_meta-event_meta_box-after', array($this, 'add_meta_box'));
		add_action('eab-event_meta-save_meta', array($this, 'save_meta'));
		add_action('eab-events-recurrent_event_child-save_meta', array($this, 'save_meta'));
		
		// Front page editor integration
		add_filter('eab-events-fpe-add_meta', array($this, 'add_fpe_meta_box'), 10, 2);
		add_action('eab-events-fpe-enqueue_dependencies', array($this, 'enqueue_fpe_dependencies'), 10, 2);
		add_action('eab-events-fpe-save_meta', array($this, 'save_fpe_meta'), 10, 2);

		// Upcoming and popular widget integration
		add_filter('eab-widgets-upcoming-default_fields', array($this, 'widget_instance_defaults'));
		add_filter('eab-widgets-popular-default_fields', array($this, 'widget_instance_defaults'));
		
		add_filter('eab-widgets-upcoming-instance_update', array($this, 'widget_instance_update'), 10, 2);
		add_filter('eab-widgets-popular-instance_update', array($this, 'widget_instance_update'), 10, 2);
		
		add_action('eab-widgets-upcoming-widget_form', array($this, 'widget_form'), 10, 2);
		add_action('eab-widgets-popular-widget_form', array($this, 'widget_form'), 10, 2);
		
		add_action('eab-widgets-upcoming-after_event', array($this, 'widget_event_group'), 10, 2);
		add_action('eab-widgets-popular-after_event', array($this, 'widget_event_group'), 10, 2);
	}

	function widget_instance_defaults ($defaults) {
		$defaults['show_bp_group'] = false;
		return $defaults;
	}

	function widget_instance_update ($instance, $new) {
		$instance['show_bp_group'] = (int)$new['show_bp_group'];
		return $instance;
	}

	function widget_form ($options, $widget) {
		?>
<label for="<?php echo $widget->get_field_id('show_bp_group'); ?>" style="display:block;">
	<input type="checkbox" 
		id="<?php echo $widget->get_field_id('show_bp_group'); ?>" 
		name="<?php echo $widget->get_field_name('show_bp_group'); ?>" 
		value="1" <?php echo ($options['show_bp_group'] ? 'checked="checked"' : ''); ?> 
	/>
	<?php _e('Show BuddyPress group', Eab_EventsHub::TEXT_DOMAIN); ?>
</label>
		<?php
	}

	function widget_event_group ($options, $event) {
		if (empty($options['show_bp_group'])) return false;
		$name = Eab_GroupEvents_Template::get_group_name($event->get_id());
		if (!$name) return false;
		echo '<div class="eab-event_group">' . $name . '</div>';
	}


	function filter_query ($query) {
		global $current_user;
		if (!($query instanceof WP_Query)) return $query;
		if (Eab_EventModel::POST_TYPE != @$query->query_vars['post_type']) return $query;
		if (!function_exists('groups_is_user_member')) return $query;
		
		$posts = array();
		foreach ($query->posts as $post) {
			$group = (int)get_post_meta($post->ID, 'eab_event-bp-group_event', true);
			if ($group) {
				if (!groups_is_user_member($current_user->ID, $group)) continue; 
			}
			$posts[] = $post;
		}
		$query->posts = $posts;
		$query->post_count = count($posts);
		return $query;
	}
	
	function auto_join_group ($event_id, $user_id) {
		if (!function_exists('groups_get_groups')) return false;
		if (!$this->_data->get_option('bp-group_event-auto_join_groups')) return false;
		$group_id = (int)get_post_meta($event_id, 'eab_event-bp-group_event', true);
		if (!$group_id) return false;

		groups_accept_invite($user_id, $group_id);
	}
	
	function show_nags () {
		if (!defined('BP_VERSION')) {
			echo '<div class="error"><p>' .
				__("You'll need BuddyPress installed and activated for Groups Events add-on to work", Eab_EventsHub::TEXT_DOMAIN) .
			'</p></div>';
		}
		if (!function_exists('groups_get_groups')) {
			echo '<div class="error"><p>' .
				__("You'll need to enable BuddyPress Groups component for Groups Events add-on to work", Eab_EventsHub::TEXT_DOMAIN) .
			'</p></div>';
		}
	}
	
	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png');
		$checked = $this->_data->get_option('bp-group_event-auto_join_groups') ? 'checked="checked"' : '';
		$private = $this->_data->get_option('bp-group_event-private_events') ? 'checked="checked"' : '';
		$user_groups_only = $this->_data->get_option('bp-group_event-user_groups_only') ? 'checked="checked"' : '';
		$user_groups_only_unless_superadmin = $this->_data->get_option('bp-group_event-user_groups_only-unless_superadmin') ? 'checked="checked"' : '';
		$eab_event_bp_group_event_email_grp_member = $this->_data->get_option('eab_event_bp_group_event_email_grp_member') ? 'checked="checked"' : '';
?>
<div id="eab-settings-group_events" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Group Events settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-bp-group_event-auto_join_groups"><?php _e('Automatically join the group by RSVPing to events', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_event-bp-group_event-auto_join_groups" name="event_default[bp-group_event-auto_join_groups]" value="1" <?php print $checked; ?> />
			<span><?php echo $tips->add_tip(__('When your users RSVP positively to your group event, they will also automatically join the group the event belongs to.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-bp-group_event-private_events"><?php _e('Group events are private to groups', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_event-bp-group_event-private_events" name="event_default[bp-group_event-private_events]" value="1" <?php print $private; ?> />
			<span><?php echo $tips->add_tip(__('If you enable this option, users outside your groups will <b>not</b> be able to see your Group Events.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-bp-group_event-user_groups_only"><?php _e('Show only groups that user belongs to', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_event-bp-group_event-user_groups_only" name="event_default[bp-group_event-user_groups_only]" value="1" <?php print $user_groups_only; ?> />
			<span><?php echo $tips->add_tip(__('If you enable this option, users will not be able to assign events outside the groups they already belong to.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
			<br />
	    	<label for="eab_event-bp-group_event-user_groups_only-unless_superadmin"><?php _e('... except for Super-admins', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_event-bp-group_event-user_groups_only-unless_superadmin" name="event_default[bp-group_event-user_groups_only-unless_superadmin]" value="1" <?php print $user_groups_only_unless_superadmin; ?> />
			<span><?php echo $tips->add_tip(__('If you enable this option, your super-admins will be able to assign events to any group.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-bp-group_event-private_events"><?php _e('Send email to all group members when a event is created or edited', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_event_bp_group_event_email_grp_member" name="event_default[eab_event_bp_group_event_email_grp_member]" value="1" <?php print $eab_event_bp_group_event_email_grp_member; ?> />
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['bp-group_event-auto_join_groups'] = empty( $_POST['event_default']['bp-group_event-auto_join_groups'] ) ? 0 : $_POST['event_default']['bp-group_event-auto_join_groups'];
		$options['bp-group_event-private_events'] = empty( $_POST['event_default']['bp-group_event-private_events'] ) ? 0 : $_POST['event_default']['bp-group_event-private_events'];
		$options['bp-group_event-user_groups_only'] = empty( $_POST['event_default']['bp-group_event-user_groups_only'] ) ? 0 : $_POST['event_default']['bp-group_event-user_groups_only'];
		$options['bp-group_event-user_groups_only-unless_superadmin'] = empty( $_POST['event_default']['bp-group_event-user_groups_only-unless_superadmin'] ) ? 0 : $_POST['event_default']['bp-group_event-user_groups_only-unless_superadmin'];
		$options['eab_event_bp_group_event_email_grp_member'] = empty( $_POST['event_default']['eab_event_bp_group_event_email_grp_member'] ) ? 0 : $_POST['event_default']['eab_event_bp_group_event_email_grp_member'];
		return $options;
	}

	function add_meta_box ($box) {
		global $post, $current_user;
		if (!function_exists('groups_get_groups')) return $box;
		$group_id = get_post_meta($post->ID, 'eab_event-bp-group_event', true);
		
		$group_count = defined('EAB_BP_GROUPS_LIST_GROUP_LIMIT') && intval(EAB_BP_GROUPS_LIST_GROUP_LIMIT)
			? EAB_BP_GROUPS_LIST_GROUP_LIMIT
			: groups_get_total_group_count()
		;
		$group_params = array('per_page' => $group_count , 'type' => 'alphabetical', 'show_hidden' => EAB_SHOW_HIDDEN_GROUP );
		if ($this->_data->get_option('bp-group_event-user_groups_only')) {
			if (!(is_super_admin() && $this->_data->get_option('bp-group_event-user_groups_only-unless_superadmin'))) $group_params['user_id'] = $current_user->id;
		}
		$groups = groups_get_groups($group_params);
		$groups = @$groups['groups'] ? $groups['groups'] : array();
		
		$ret = '';
		$ret .= '<div class="eab_meta_box">';
		$ret .= '<div class="misc-eab-section" >';
		$ret .= '<div class="eab_meta_column_box top"><label for="eab_event-bp-group_event">' .
			__('Group event', Eab_EventsHub::TEXT_DOMAIN) . 
		'</label></div>';
		
		$ret .= __('This is a group event for', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= ' <select name="eab_event-bp-group_event" id="eab_event-bp-group_event">';
		$ret .= '<option value="">' . __('Not a group event', Eab_EventsHub::TEXT_DOMAIN) . '&nbsp;</option>';
		foreach ($groups as $group) {
			$selected = ($group->id == $group_id) ? 'selected="selected"' : '';
			$ret .= "<option value='{$group->id}' {$selected}>{$group->name}</option>";
		}
		$ret .= '</select> ';
		
		$ret .= '</div>';
		$ret .= '</div>';
		return $box . $ret;
	}

	function add_fpe_meta_box ($box, $event) {
		global $current_user;
		if (!function_exists('groups_get_groups')) return $box;
		$group_id = get_post_meta($event->get_id(), 'eab_event-bp-group_event', true);
		
		$group_count = defined('EAB_BP_GROUPS_LIST_GROUP_LIMIT') && intval(EAB_BP_GROUPS_LIST_GROUP_LIMIT)
			? EAB_BP_GROUPS_LIST_GROUP_LIMIT
			: groups_get_total_group_count()
		;
		$group_params = array('per_page' => $group_count , 'type' => 'alphabetical', 'show_hidden' => EAB_SHOW_HIDDEN_GROUP);
		if ($this->_data->get_option('bp-group_event-user_groups_only')) {
			if (!(is_super_admin() && $this->_data->get_option('bp-group_event-user_groups_only-unless_superadmin'))) $group_params['user_id'] = $current_user->id;
		}
		$groups = groups_get_groups($group_params);
		$groups = @$groups['groups'] ? $groups['groups'] : array();
		
		$ret .= '<div class="eab-events-fpe-meta_box">';
		$ret .= __('This is a group event for', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= ' <select name="eab_event-bp-group_event" id="eab_event-bp-group_event">';
		$ret .= '<option value="">' . __('Not a group event', Eab_EventsHub::TEXT_DOMAIN) . '&nbsp;</option>';
		foreach ($groups as $group) {
			$selected = ($group->id == $group_id) ? 'selected="selected"' : '';
			$ret .= "<option value='{$group->id}' {$selected}>{$group->name}</option>";
		}
		$ret .= '</select> ';
		$ret .= '</div>';
		
		return $box . $ret;
	}
	
	private function _save_meta ($post_id, $request) {
		if (!function_exists('groups_get_groups')) return false;
		if (!isset($request['eab_event-bp-group_event'])) return false;
		
		$data = (int)$request['eab_event-bp-group_event'];
		//if (!$data) return false;
		
		update_post_meta($post_id, 'eab_event-bp-group_event', $data);
		
		$email_grp_member = $this->_data->get_option('eab_event_bp_group_event_email_grp_member');
		if( isset( $email_grp_member ) &&  $email_grp_member == 1 ) {
			$grp_members = groups_get_group_members( array( 'group_id' => $data, 'exclude_admins_mods' => false ) );
			foreach( $grp_members['members'] as $member ){
				//echo $member->user_email;
				$subject = __( 'Information about a group event', Eab_EventsHub::TEXT_DOMAIN );
				$subject = apply_filters( 'eab_bp_grp_events_member_mail_subject', $subject, $member, $post_id );
				$message = __( 'Dear ' . $member->display_name . ',<br><br>An event is created/updated. I hope you will join in that event. Check the event here: ' . get_permalink( $post_id ), Eab_EventsHub::TEXT_DOMAIN );
				$message = apply_filters( 'eab_bp_grp_events_member_mail_message', $message, $member, $post_id );
				wp_mail( $member->user_email, $subject, $message );
			}
		}
		
	}
	
	function save_meta ($post_id) {
		$this->_save_meta($post_id, $_POST);
	}

	function save_fpe_meta ($post_id, $request) {
		$this->_save_meta($post_id, $request);
	}
	
	function add_tab () {
		global $bp, $current_user;
		if (!function_exists('groups_get_groups')) return false;
		if (!$bp->is_single_item) return false;

		// Don't show groups tab for non-members if Events are private to groups
		if ($this->_data->get_option('bp-group_event-private_events')) {
			if (!groups_is_user_member($current_user->id, $bp->groups->current_group->id)) return false; 
		}
		
		$name = __('Group Events', Eab_EventsHub::TEXT_DOMAIN);
		$groups_link = bp_get_group_permalink($bp->groups->current_group);//$bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/';
		
		bp_core_new_subnav_item(array(
			'name' => $name,
			'slug' => self::SLUG,
			'parent_url' => $groups_link,
			'parent_slug' => $bp->groups->current_group->slug,
			'screen_function' => array($this, 'bind_bp_groups_page'),
		));
	}
	
	function bind_bp_groups_page () {
		add_action('bp_template_content', array($this, 'show_group_events_profile_body'));
		add_action('bp_head', array($this, 'enqueue_dependencies'));
		bp_core_load_template(apply_filters('bp_core_template_plugin', 'groups/single/plugins'));
	}
	
	function enqueue_dependencies () {
		// @TODO: refactor to separate style.
		wp_enqueue_style('eab-bp-group_events', plugins_url(basename(EAB_PLUGIN_DIR) . "/default-templates/calendar/events.css"));
	}
	
	function enqueue_fpe_dependencies () {
		wp_enqueue_script('eab-buddypress-group_events-fpe', plugins_url(basename(EAB_PLUGIN_DIR) . "/js/eab-buddypress-group_events-fpe.js"), array('jquery'));
	}

	function show_group_events_profile_body () {
		global $bp;
		$timestamp = $this->_get_requested_timestamp();
		
		$collection = new Eab_BuddyPress_GroupEventsCollection($bp->groups->current_group->id, $timestamp);
		$events = $collection->to_collection();
		if (!class_exists('Eab_CalendarTable_EventArchiveCalendar')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_calendar_helper.php';
		$renderer = new Eab_CalendarTable_EventArchiveCalendar($events);
		
		do_action('eab-buddypress-group_events-before_events');
		echo '<h3>' . date_i18n('F Y', $timestamp) . '</h3>'; 
		do_action('eab-buddypress-group_events-after_head');
		echo $this->_get_navigation($timestamp);
		echo $renderer->get_month_calendar($timestamp);
		echo $this->_get_navigation($timestamp);
		do_action('eab-buddypress-group_events-after_events');
	}

	private function _get_navigation ($timestamp) {
		global $bp;
		$root = $bp->root_domain . '/' . $bp->pages->groups->slug . '/' . $bp->groups->current_group->slug . '/';
		
		$prev_url = $root . self::SLUG . date_i18n('/Y/m/', $timestamp - (28*86400));
		$next_url = $root . self::SLUG . date_i18n('/Y/m/', $timestamp + (32*86400));
		
		return '<div class="eab-bp-group_events-navigation">' .
			'<div class="eab-bp-group_events-navigation-prev" style="float:left">' .
				"<a href='{$prev_url}'>" . __('Prev', Eab_EventsHub::TEXT_DOMAIN) . '</a>' .
			'</div>' .
			'<div class="eab-bp-group_events-navigation-next" style="float:right">' .
				"<a href='{$next_url}'>" . __('Next', Eab_EventsHub::TEXT_DOMAIN) . '</a>' .
			'</div>' .
		'</div>';
	}
	
	private function _get_requested_timestamp () {
		global $bp;
		if (!$bp->action_variables) return eab_current_time();
		
		$year = (int)(isset($bp->action_variables[0]) ? $bp->action_variables[0] : date('Y'));
		$year = $year ? $year : date('Y');

		$month = (int)(isset($bp->action_variables[1]) ? $bp->action_variables[1] : date('m'));
		$month = $month ? $month : date('m');
		
		return strtotime("{$year}-{$month}-01");
	}
}



class Eab_BuddyPress_GroupEventsCollection extends Eab_UpcomingCollection {
	
	private $_group_id;
		
	public function __construct ($group_id, $timestamp=false, $args=array()) {
		$this->_group_id = $group_id;
		parent::__construct($timestamp, $args);
	}
	
	public function build_query_args ($args) {
		$args = parent::build_query_args($args);
		$args['meta_query'][] = array(
			'key' => 'eab_event-bp-group_event',
			'value' => $this->_group_id,
		);
		return $args;
	}
}
class Eab_BuddyPress_GroupEventsWeeksCollection extends Eab_UpcomingWeeksCollection {
	
	private $_group_id;
		
	public function __construct ($group_id, $timestamp=false, $args=array()) {
		$this->_group_id = $group_id;
		parent::__construct($timestamp, $args);
	}
	
	public function build_query_args ($args) {
		$args = parent::build_query_args($args);
		$args['meta_query'][] = array(
			'key' => 'eab_event-bp-group_event',
			'value' => $this->_group_id,
		);
		return $args;
	}
}

Eab_BuddyPress_GroupEvents::serve();


/**
 * Group events add-on template extension.
 */
class Eab_GroupEvents_Template extends Eab_Template {

	public static function get_group ($event_id=false) {
		if (!$event_id) {
			global $post;
			$event_id = $post->ID;
		}
		if (!$event_id) return false;
		
		$group_id = get_post_meta($event_id, 'eab_event-bp-group_event', true);
		if (!$group_id) return false;
		
		$group = groups_get_group(array('group_id' => $group_id));
		if (!$group) return false;

		return $group;
	}

	public static function get_group_name ($event_id=false) {
		$group = self::get_group($event_id);
		return (!empty($group->name))
			? $group->name
			: ''
		;
	}
}


class Eab_GroupEvents_Shortcodes extends Eab_Codec {

	protected $_shortcodes = array(
		'group_archives' => 'eab_group_archives',
	);

	public static function serve () {
		$me = new Eab_GroupEvents_Shortcodes;
		$me->_register();
	}

	function process_group_archives_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
		// Date arguments	
			'date' => false, // Starting date - default to now
			'lookahead' => false, // Don't use default monthly page - use weeks count instead
			'weeks' => false, // Look ahead this many weeks
		// Query arguments
			'category' => false, // ID or slug
			'limit' => false, // Show at most this many events
			'order' => false,
			'groups' => false, // Group ID, keyword or comma-separated list of group IDs
			'user' => false, // User ID or keyword
		// Appearance arguments
			'class' => 'eab-group_events',
			'template' => 'get_shortcode_archive_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));

		if (is_numeric($args['user'])) {
			$args['user'] = $this->_arg_to_int($args['user']);
		} else {
			if ('current' == trim($args['user'])) {
				$user = wp_get_current_user();
				$args['user'] = $user->ID;
			} else {
				$args['user'] = false;
			}
		}

		if (is_numeric($args['groups'])) {
			// Single group ID
			$args['groups'] = $this->_arg_to_int($args['groups']);
		} else if (strstr($args['groups'], ',')) {
			// Comma-separated list of group IDs
			$ids = array_map('intval', array_map('trim', explode(',', $args['groups'])));
			if (!empty($ids)) $args['groups'] = $ids;
		} else {
			// Keyword
			if (in_array(trim($args['groups']), array('my', 'my-groups', 'my_groups')) && $args['user']) {
				if (!function_exists('groups_get_groups')) return $content;
				$groups = groups_get_groups(array('user_id' => $args['user']));
				$args['groups'] = array_map('intval', wp_list_pluck($groups['groups'], 'id'));
			} else if ('all' == trim($args['groups'])) {
				if (!function_exists('groups_get_groups')) return $content;
				$groups = groups_get_groups();
				$args['groups'] = array_map('intval', wp_list_pluck($groups['groups'], 'id'));
			} else {
				$args['groups'] = false;
			}
		}
		if (!$args['groups']) return $content;

		$events = array();
		$query = $this->_to_query_args($args);
		$query['meta_query'][] = array(
			'key' => 'eab_event-bp-group_event',
			'value' => $args['groups'],
			'compare' => (is_array($args['groups']) ? 'IN' : '='),
		);

		$order_method = $args['order']
			? create_function('', 'return "' . $args['order'] . '";')
			: false
		;
		if ($order_method) add_filter('eab-collection-date_ordering_direction', $order_method);
		
		// Lookahead - depending on presence, use regular upcoming query, or poll week count
		if ($args['lookahead']) {
			$method = $args['weeks']
				? create_function('', 'return ' . $args['weeks'] . ';')
				: false;
			;
			if ($method) add_filter('eab-collection-upcoming_weeks-week_number', $method);
			$collection = new Eab_BuddyPress_GroupEventsWeeksCollection($args['groups'], $args['date'], $query);
			if ($method) remove_filter('eab-collection-upcoming_weeks-week_number', $method);
		} else {
			// No lookahead, get the full month only
			$collection =  new Eab_BuddyPress_GroupEventsCollection($args['groups'], $args['date'], $query);
		}
		if ($order_method) remove_filter('eab-collection-date_ordering_direction', $order_method);
		$events = $collection->to_collection();

		$output = eab_call_template('util_apply_shortcode_template', $events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) wp_enqueue_script('eab_event_js');
		return $output;
	}

	public function add_group_archives_shortcode_help ($help) {
		$help[] = array(
			'title' => __('BuddyPress group archives', Eab_EventsHub::TEXT_DOMAIN),
			'tag' => 'eab_group_archives',
			'arguments' => array(
				'date' => array('help' => __('Starting date - default to now', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:date'),
				'lookahead' => array('help' => __('Don\'t use default monthly page - use weeks count instead', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'boolean'),
				'weeks' => array('help' => __('Look ahead this many weeks', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'integer'),
				'category' => array('help' => __('Show events from this category (ID or slug)', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:or_integer'),
				'limit' => array('help' => __('Show at most this many events', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'integer'),
				'order' => array('help' => __('Sort events in this direction', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:sort'),
				'groups' => array('help' => __('Group ID, keywords "my-groups" or "all", or comma-separated list of group IDs', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:or_integer'),
				'user' => array('help' => __('User ID or keyword "current" - required if <code>groups</code> is set to "my-groups"', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:or_integer'),
				'class' => array('help' => __('Apply this CSS class', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string'),
    			'template' => array('help' => __('Subtemplate file, or template class call', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string'),
    			'override_styles' => array('help' => __('Toggle default styles usage', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'boolean'),
    			'override_scripts' => array('help' => __('Toggle default scripts usage', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'boolean'),
			),
			'advanced_arguments' => array('template', 'override_scripts', 'override_styles'),
		);
		return $help;
	}
}

Eab_GroupEvents_Shortcodes::serve();
