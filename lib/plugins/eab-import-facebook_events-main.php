<?php

if (!class_exists('WpmuDev_Wp_Oauth')) require_once(EAB_PLUGIN_DIR . 'lib/class_wd_wpmu_oauth.php');
if (!class_exists('Eab_Importer')) require_once(EAB_PLUGIN_DIR . 'lib/class_eab_importer.php');
if( ! class_exists( 'Facebook\Facebook' ) ) require_once dirname( __FILE__ ) . '/lib/Facebook/autoload.php';

class Eab_Fbe_Oauth_FacebookEventsImporter extends Eab_FB_Plugin_Oauth_RO {
	public function get_data_key ($key) {
		return $this->_get_data_key("fbe_importer-{$key}");
	}
}

class Eab_Fbe_Importer_FacebookEventsImporter extends Eab_ScheduledImporter {

	private $_data;
	private $_oauth;
	private $_http_headers = array(
		'method' => 'GET',
		'timeout' => '5',
		'redirection' => '5',
		'blocking' => true,
		'compress' => false,
		'decompress' => true,
		'sslverify' => false,
	);

	protected function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_oauth = new Eab_Fbe_Oauth_FacebookEventsImporter;
		parent::__construct();
	}

	public static function serve () { return new Eab_Fbe_Importer_FacebookEventsImporter; }

	public function check_schedule () {
		$last_run = (int)get_option($this->get_schedule_key());
		$run_each = $this->_data->get_option('fbe_importer-run_each');
		$run_each = $run_each ? $run_each : 3600;
		$next_run = $last_run + $run_each;
		return ($next_run < eab_current_time());
	}

	public function update_schedule () {
		return update_option($this->get_schedule_key(), eab_current_time());
	}


	public function import () {
		$sync_id = $this->_data->get_option('fbe_importer-fb_user');
		$this->import_events($sync_id);
	}

	public function map_to_raw_events_array ($id) {
		$items = $this->_get_request_items($id);
		return $items;
	}

	public function map_to_post_type ($source) {
 		$author = $this->_data->get_option('fbe_importer-calendar_author');
		$data = array(
			'post_type' => Eab_EventModel::POST_TYPE,
			'post_status' => 'publish',
			'post_title' => isset($source['name']) ? $source['name']  : '',
			'post_content' => isset($source['description']) ? $source['description']  : '',
			'post_date' => date('Y-m-d H:i:s', strtotime($source['updated_time'])),
			'post_author' => $author,
		);

                return $data;
	}

	public function map_to_post_meta ($source) {
		$meta = array();

		$meta['eab_fbe_event'] = $source['id'];
		$meta['incsub_event_status'] = Eab_EventModel::STATUS_OPEN; // Open by default

		// Metadata - timestamps
		$start = isset($source['start_time']) ? strtotime($source['start_time']) : false;
		$end = isset($source['end_time']) ? strtotime($source['end_time']) : false;
		if ($start) $meta['incsub_event_start'] = date('Y-m-d H:i:s', $start);
		if ($end) $meta['incsub_event_end'] = date('Y-m-d H:i:s', $end);

		// Metadata - location
		$venue = isset($source['location']) ? $source['location'] : false;
		if ($venue) $meta['incsub_event_venue'] = $venue;

		return $meta;
	}


	public function is_imported ($source) {
		global $wpdb;
		$id = esc_sql($source['id']);
                $res = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='eab_fbe_event' AND meta_value='{$id}'");

                return $res;
	}

	public function is_recurring ($source) {
		return false; // Facebook doesn't support recurring events atm.
	}

	private function _get_request_items ($id) {
		$token = $this->_oauth->is_authenticated();
		if ( $token ) {
			$api_key = $this->_data->get_option('fbe_importer-client_id');
			$api_secret = $this->_data->get_option('fbe_importer-client_secret');
			$sync_user = !empty($id)
					? $id
					: 'me'
			;

			$fb = new Facebook\Facebook(array(
					'app_id' => $api_key,
					'app_secret' => $api_secret,
			));

			$response = $fb->get('/' . $sync_user . '/events?fields=id,name,description,start_time,end_time,updated_time', $token);
			$items = $response->getDecodedBody();

			return !empty($items['data'])
				? $items['data']
				: array()
			;
		}
		return array();
	}

	private function get_schedule_key () {
		return 'last_' . __CLASS__ . '_run--';
	}
}


/**
 * Setup & auth handler.
 */
class Eab_Calendars_FacebookEventsImporter {

	private $_data;
	private $_oauth;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_oauth = new Eab_Fbe_Oauth_FacebookEventsImporter;
	}

	public static function serve () {
		$me = new Eab_Calendars_FacebookEventsImporter;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_api_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_fbe_import_authenticate', array($this, 'json_authenticate'));
		add_action('wp_ajax_eab_fbe_import_reset', array($this, 'json_reset'));
		add_action('wp_ajax_eab_fbe_import_resync_calendars', array($this, 'json_resync_calendars'));
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );

		$api_key = $this->_data->get_option('fbe_importer-client_id');
		$api_secret = $this->_data->get_option('fbe_importer-client_secret');
		$is_authenticated = $this->_oauth->is_authenticated();

		$fb_user = false;
		$sync_user = $this->_data->get_option('fbe_importer-fb_user');
		if (!$sync_user && $is_authenticated) {
			$fb_user = $this->_oauth->get_fb_user();
			$sync_user = !empty($fb_user['id'])
				? $fb_user['id']
				: false
			;
		}

		$runs = array(
			'3600' => __('Hour', Eab_EventsHub::TEXT_DOMAIN),
			'7200' => __('Two hours', Eab_EventsHub::TEXT_DOMAIN),
			'10800' => __('Three hours', Eab_EventsHub::TEXT_DOMAIN),
			'21600' => __('Six hours', Eab_EventsHub::TEXT_DOMAIN),
			'43200' => __('Twelve hours', Eab_EventsHub::TEXT_DOMAIN),
			'86400' => __('Day', Eab_EventsHub::TEXT_DOMAIN),
		);
		$run_each = $this->_data->get_option('fbe_importer-run_each');
		$run_each = $run_each ? $run_each : 3600;

		$user = wp_get_current_user();
		$calendar_author = $this->_data->get_option('fbe_importer-calendar_author', $user->ID);
		$raw_authors = get_users(array('who' => 'authors'));
		$possible_authors = array_combine(
			wp_list_pluck($raw_authors, 'ID'),
			wp_list_pluck($raw_authors, 'display_name')
		);
		?>
		<div id="eab-settings-fbe_importer" class="eab-metabox postbox">
			<h3 class="eab-hndle"><?php _e('Facebook Events import settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item" style="line-height:1.8em">
                        <table cellpadding="5" cellspacing="5" width="100%">
                                <tr>
                                        <td valign="top" width="400">
                                                <label style="width: 100%" for="incsub_event-fbe_importer-client_id" id="incsub_event_label-fbe_importer-client_id"><?php _e('App ID', Eab_EventsHub::TEXT_DOMAIN); ?> <?php echo $tips->add_tip(__('Enter your App ID number here.', Eab_EventsHub::TEXT_DOMAIN)); ?></label>
                                        </td>
                                        <td valign="top">
                                                <input type="text" size="85" id="incsub_event-fbe_importer-client_id" name="fbe_importer[client_id]" value="<?php print $api_key; ?>" />
                                        </td>
                                </tr>
                                <tr>
                                        <td valign="top">
                                                <label style="width: 100%" for="incsub_event-fbe_importer-client_id" id="incsub_event_label-fbe_importer-client_id"><?php _e('App secret', Eab_EventsHub::TEXT_DOMAIN); ?> <?php echo $tips->add_tip(__('Enter your App secret number here.', Eab_EventsHub::TEXT_DOMAIN)); ?></label>
                                        </td>
                                        <td valign="top">
                                                <input type="text" size="85" id="incsub_event-fbe_importer-client_id" name="fbe_importer[client_secret]" value="<?php print $api_secret; ?>" />
                                        </td>
                                </tr>
                        </table>
					<div class="fbe_importer-auth_actions">
				<?php if ($is_authenticated && $api_key && $api_secret) { ?>
					<a href="#reset" class="button" id="fbe_import-reset"><?php _e('Reset', Eab_EventsHub::TEXT_DOMAIN); ?></a>
					<span><?php echo $tips->add_tip(__('Remember to also revoke the app privileges <a href="http://www.facebook.com/settings?tab=applications" target="_blank">here</a>.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
				<?php } else if ($api_key && $api_secret) { ?>
					<a href="#authenticate" class="button" id="fbe_import-authenticate"><?php _e('Authenticate', Eab_EventsHub::TEXT_DOMAIN); ?></a>
				<?php } else { ?>
					<p><em><?php _e('Enter your API info and save settings first.', Eab_EventsHub::TEXT_DOMAIN); ?></em></p>
				<?php } ?>
			</div>
		</div>
		<?php if ($is_authenticated) { ?>
		<div class="eab-settings-settings_item">
			<label><?php _e('Import events for this Facebook user ID:', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<input type="text" id="incsub_event-fbe_importer-fb_user" name="fbe_importer[fb_user]" value="<?php esc_attr_e($sync_user); ?>" />
			<small><em><?php _e('Don\'t change this field unless you are sure what you\'re doing', Eab_EventsHub::TEXT_DOMAIN); ?></em></small>
		</div>
		<div class="eab-settings-settings_item">
			<label><?php _e('Run importer every:', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<select name="fbe_importer[run_each]">
			<?php foreach ($runs as $interval => $ilabel) { ?>
				<option value="<?php echo (int)$interval; ?>" <?php echo selected($interval, $run_each); ?>><?php echo $ilabel; ?></option>
			<?php } ?>
			</select>
		</div>
		<div class="eab-settings-settings_item">
			<label><?php _e('Assign imported events to this user:', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<select name="fbe_importer[calendar_author]">
			<?php foreach ($possible_authors as $aid => $alabel) { ?>
				<option value="<?php echo $aid; ?>" <?php echo selected($aid, $calendar_author); ?>><?php echo $alabel; ?>&nbsp;</option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Select the user you wish to appear as your imported Events host.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
		</div>
		<?php if ($fb_user) { ?>
		<div class="eab-settings-settings_item">
			<input type="submit" value="<?php esc_attr_e(__('Save settings', Eab_EventsHub::TEXT_DOMAIN)); ?>" />
		</div>
		<?php } // end if fb user?>
		<?php } // end if authenticated ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {

function authenticate () {
	var loginWindow = window.open('https://facebook.com', "oauth_login", "scrollbars=no,resizable=no,toolbar=no,location=no,directories=no,status=no,menubar=no,copyhistory=no,height=400,width=800");
	$.post(ajaxurl, {
		"action": "eab_fbe_import_authenticate",
		"url": window.location.href
	}, function (data) {
		var href = data.url;
		loginWindow.location = href;
		var gTimer = setInterval(function () {
			try {
				if (loginWindow.location.hostname == window.location.hostname) {
					// We're back!
					clearInterval(gTimer);
					loginWindow.close();
					window.location.reload();
				}
			} catch (e) {}
		}, 300);
	}, "json");
	return false;
}

$(function () {
	$("#fbe_import-authenticate").on("click", authenticate);
	$("#fbe_import-reset").on("click", function () {
		$.post(ajaxurl, {"action": "eab_fbe_import_reset"}, function() {
			window.location.reload();
		});
		return false;
	});
	$("#fbe_import-resync").on("click", function () {
		$.post(ajaxurl, {"action": "eab_fbe_import_resync_calendars"}, window.location.reload);
		return false;
	});
});
})(jQuery);
</script>
<?php
	}

	function save_settings ( $options ) {
		$options['fbe_importer-client_id'] 			= $_POST['fbe_importer']['client_id'];
		$options['fbe_importer-client_secret'] 		= $_POST['fbe_importer']['client_secret'];
		$options['fbe_importer-fb_user'] 			= isset( $_POST['fbe_importer']['fb_user'] ) ? $_POST['fbe_importer']['fb_user'] : '';
		$options['fbe_importer-run_each'] 			= isset( $_POST['fbe_importer']['run_each'] ) ? $_POST['fbe_importer']['run_each'] : '';
		$options['fbe_importer-sync_calendars'] 	= isset( $_POST['fbe_importer']['sync_calendars'] ) ? $_POST['fbe_importer']['sync_calendars'] : '';
		$options['fbe_importer-calendar_author'] 	= isset( $_POST['fbe_importer']['calendar_author'] ) ? $_POST['fbe_importer']['calendar_author'] : '';
		return $options;
	}

	function json_reset () {
		$this->_oauth->reset_token();
		die;
	}

	function json_authenticate () {
		die(json_encode(array(
			"url" => $this->_oauth->get_authentication(),
		)));
	}

	function json_resync_calendars () {
		$calendars = array();
		$raw_calendars = $this->_fbe->get_calendars();
		foreach ($raw_calendars as $calendar) {
			$calendars[$calendar['id']] = $calendar['summary'];
		}
		$this->_data->set_option('fbe_importer-cached_calendars', $calendars);
		$this->_data->update();
		die;
	}

	private function _get_cached_calendars () {
		$calendars = $this->_data->get_option('fbe_importer-cached_calendars', array());
		return $calendars
			? $calendars
			: array()
		;
	}
}

Eab_Calendars_FacebookEventsImporter::serve();
Eab_Fbe_Importer_FacebookEventsImporter::serve();
