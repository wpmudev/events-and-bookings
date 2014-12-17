<?php

class Eab_AddonHandler {
	
	private function __construct () {
		define('EAB_PLUGIN_ADDONS_DIR', EAB_PLUGIN_DIR . 'lib/plugins', true);
		$this->_load_active_plugins();
	}
	
	public static function serve () {
		$me = new Eab_AddonHandler;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('wp_ajax_eab_activate_plugin', array($this, 'json_activate_plugin'));
		add_action('wp_ajax_eab_deactivate_plugin', array($this, 'json_deactivate_plugin'));
	}
	
	private function _load_active_plugins () {
		$active = $this->get_active_plugins();

		foreach ($active as $plugin) {
			$path = self::plugin_to_path($plugin);
			if (!file_exists($path)) continue;
			else @require_once($path);
		}
	}
	
	function json_activate_plugin () {
		$status = $this->_activate_plugin($_POST['plugin']);
		echo json_encode(array(
			'status' => $status ? 1 : 0,
		));
		exit();
	}

	function json_deactivate_plugin () {
		$status = $this->_deactivate_plugin($_POST['plugin']);
		echo json_encode(array(
			'status' => $status ? 1 : 0,
		));
		exit();
	}

	public static function get_active_plugins () {
		$active = get_option('eab_activated_plugins');
		$active = $active ? $active : array();

		return $active;
	}
	
	public static function is_plugin_active ($plugin) {
		$active = self::get_active_plugins();
		return in_array($plugin, $active);
	}

	public static function get_all_plugins () {
		$all = glob(EAB_PLUGIN_ADDONS_DIR . '/*.php');
		$all = $all ? $all : array();
		$ret = array();
		foreach ($all as $path) {
			$ret[] = pathinfo($path, PATHINFO_FILENAME);
		}
		return $ret;
	}

	public static function plugin_to_path ($plugin) {
		$plugin = str_replace('/', '_', $plugin);
		return EAB_PLUGIN_ADDONS_DIR . '/' . "{$plugin}.php";
	}

	public static function get_plugin_info ($plugin) {
		$path = self::plugin_to_path($plugin);
		$default_headers = array(
			'Name' => 'Plugin Name',
			'Author' => 'Author',
			'Description' => 'Description',
			'Plugin URI' => 'Plugin URI',
			'Version' => 'Version',
			'Detail' => 'Detail',
			'Type' => 'AddonType'
		);
		return get_file_data($path, $default_headers, 'plugin');
	}

	private function _activate_plugin ($plugin) {
		$active = self::get_active_plugins();
		if (in_array($plugin, $active)) return true; // Already active

		$active[] = $plugin;
		return update_option('eab_activated_plugins', $active);
	}

	private function _deactivate_plugin ($plugin) {
		$active = self::get_active_plugins();
		if (!in_array($plugin, $active)) return true; // Already deactivated

		$key = array_search($plugin, $active);
		if ($key === false) return false; // Haven't found it

		unset($active[$key]);
		return update_option('eab_activated_plugins', $active);
	}
	
	public static function create_addon_settings () {
	
		if (!class_exists('WpmuDev_HelpTooltips')) 
			require_once dirname(__FILE__) . '/lib/class_wd_help_tooltips.php';
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(plugins_url('events-and-bookings/img/information.png'));
		
		$all = self::get_all_plugins();
		$active = self::get_active_plugins();
		$sections = array();

		$thead = "<table class='widefat' id='eab_addons_hub'>";
		$thead .= '<thead>';
		
		$tbody = '<thead>';
		$tbody .= "<tbody>";
		foreach ($all as $plugin) {
			$plugin_data = self::get_plugin_info($plugin);
			if (empty($plugin_data['Name'])) continue; // Require the name
			if (!empty($plugin_data['Type'])) $sections[] = $plugin_data['Type'];
			
			$is_active = in_array($plugin, $active);
			
			$tbody .= '<tr' . (!empty($plugin_data['Type']) ? ' data-type="' . sanitize_html_class($plugin_data['Type']) : '' ) . '"' . '>';
			$tbody .= "<td width='30%'>";
			$tbody .= '<b id="' . esc_attr($plugin) . '">' . $plugin_data['Name'] . '</b>';
			$tbody .= "<br />";
			$tbody .= ($is_active
				?
				'<a href="#deactivate" class="eab_deactivate_plugin" eab:plugin_id="' . esc_attr($plugin) . '">' . __('Deactivate', Eab_EventsHub::TEXT_DOMAIN) . '</a>'
				:
				'<a href="#activate" class="eab_activate_plugin" eab:plugin_id="' . esc_attr($plugin) . '">' . __('Activate', Eab_EventsHub::TEXT_DOMAIN) . '</a>'
			);
			$tbody .= "</td>";
			$tbody .= '<td>' .
				$plugin_data['Description'] .
				'<br />' .
				sprintf(__('Version %s', Eab_EventsHub::TEXT_DOMAIN), $plugin_data['Version']) .
				'&nbsp;|&nbsp;' .
				sprintf(__('by %s', Eab_EventsHub::TEXT_DOMAIN), '<a href="' . $plugin_data['Plugin URI'] . '">' . $plugin_data['Author'] . '</a>');
			if ($plugin_data['Detail']) {
				$tbody .= '&nbsp;' . $tips->add_tip($plugin_data['Detail']);
			}
			$tbody .= '</td>';
			$tbody .= "</tr>";
		}
		$tbody .= "</tbody>";
		$tbody .= "</table>";

		if (!(defined('EAB_PREVENT_SETTINGS_SECTIONS') && EAB_PREVENT_SETTINGS_SECTIONS)) {
			$sections = array_values(array_unique($sections));
			array_unshift($sections, '');
			$links = array();
			if (!empty($sections)) foreach ($sections as $sect) {
				$type = !empty($sect) ? "data-type='{$sect}'" : 'class="selected"';
				$name = !empty($sect) ? $sect : __('Show all', Eab_EventsHub::TEXT_DOMAIN);
				$links[] = "<a href='#filter' {$type}>{$name}</a>";
			}
			if (!empty($links)) {
				$thead .= '<tr><td class="filters" colspan="2">' . 
					'<b>' . __('Filter', Eab_EventsHub::TEXT_DOMAIN) . ':</b> ' .
					join(' | ', $links) . 
				'</td></tr>';
			}
		}

		$thead .= '<tr>';
		$thead .= '<th width="30%">' . __('Name', Eab_EventsHub::TEXT_DOMAIN) . '</th>';
		$thead .= '<th>' . __('Description', Eab_EventsHub::TEXT_DOMAIN) . '</th>';
		$thead .= '</tr>';

		echo $thead . $tbody;

		echo <<<EOWdcpPluginJs
<script type="text/javascript">
(function ($) {
$(function () {
	$(".eab_activate_plugin").click(function () {
		var me = $(this);
		var plugin_id = me.attr("eab:plugin_id");
		$.post(ajaxurl, {"action": "eab_activate_plugin", "plugin": plugin_id}, function (data) {
			window.location = window.location;
		});
		return false;
	});
	$(".eab_deactivate_plugin").click(function () {
		var me = $(this);
		var plugin_id = me.attr("eab:plugin_id");
		$.post(ajaxurl, {"action": "eab_deactivate_plugin", "plugin": plugin_id}, function (data) {
			window.location = window.location;
		});
		return false;
	});
	$("#eab_addons_hub .filters a").click(function (e) {
		e.preventDefault();
		var type = $(this).attr("data-type"),
			selector = '#eab_addons_hub tbody tr[data-type="' + type + '"]'
		;
		if (!type) {
			$("#eab_addons_hub tbody tr").show();
		} else {
			$("#eab_addons_hub tbody tr").hide();
			$(selector).show();
		}
		$("#eab_addons_hub .filters a").removeClass('selected');
		$(this).addClass("selected");
		return false;
	});
});
})(jQuery);
</script>
EOWdcpPluginJs;
	}
}
