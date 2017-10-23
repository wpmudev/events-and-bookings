<?php
/**
 * Manage addons
 */
class Eab_AddonHandler {

	private function __construct () {
		define( 'EAB_PLUGIN_ADDONS_DIR', EAB_PLUGIN_DIR . 'lib/plugins', true );
		$this->_load_active_plugins();
	}

	public static function serve () {
		$me = new Eab_AddonHandler;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action( 'wp_ajax_eab_activate_plugin', array( $this, 'json_activate_plugin' ) );
		add_action( 'wp_ajax_eab_deactivate_plugin', array( $this, 'json_deactivate_plugin' ) );

		add_action( 'wp_ajax_eab-activate-selected', array( $this, 'json_activate_selected' ) );
		add_action( 'wp_ajax_eab-deactivate-selected', array( $this, 'json_deactivate_selected' ) );
	}

	private function _load_active_plugins () {
		$active = $this->get_active_plugins();

		foreach ( $active as $plugin ) {
			$path = self::plugin_to_path( $plugin );
			if ( !file_exists( $path ) ) continue;
			else @require_once( $path );
		}
	}

	function json_activate_plugin () {
		$status = $this->_activate_plugin( $_POST['plugin'] );
		echo json_encode( array(
			'status' => $status ? 1 : 0,
		));
		exit();
	}

	function json_deactivate_plugin () {
		$status = $this->_deactivate_plugin( $_POST['plugin'] );
		echo json_encode( array(
			'status' => $status ? 1 : 0,
		));
		exit();
	}

	function json_activate_selected () {
		$data 		= stripslashes_deep( $_POST );
		$plugins 	= $data['plugins'];
		$error 		= false;
		if ( !empty( $plugins ) && is_array( $plugins ) ) foreach( $plugins as $plugin ) {
			$status = $this->_activate_plugin($plugin);
			if (!$status) $error = true;
		} else {
			$error = true;
		}
		echo json_encode(array(
			'status' => !$error ? 1 : 0,
		));
		exit();
	}

	function json_deactivate_selected () {
		$data 		= stripslashes_deep($_POST);
		$plugins 	= $data['plugins'];
		$error 		= false;
		if (!empty( $plugins ) && is_array( $plugins ) ) foreach( $plugins as $plugin ) {
			$status = $this->_deactivate_plugin( $plugin );
			if (!$status) $error = true;
		} else {
			$error = true;
		}
		echo json_encode(array(
			'status' => !$error ? 1 : 0,
		));
		exit();
	}

	public static function get_active_plugins () {
		$active = get_option('eab_activated_plugins');
		$active = $active ? $active : array();

		return $active;
	}

	public static function is_plugin_active ( $plugin ) {
		$active = self::get_active_plugins();
		return in_array( $plugin, $active );
	}

	public static function get_all_plugins () {
		$all = glob( EAB_PLUGIN_ADDONS_DIR . '/*.php' );
		$all = $all ? $all : array();
		$ret = array();
		foreach ( $all as $path ) {
			$ret[] = pathinfo( $path, PATHINFO_FILENAME );
		}
		return $ret;
	}

	public static function plugin_to_path ( $plugin ) {
		$plugin = str_replace( '/', '_', $plugin);
		return EAB_PLUGIN_ADDONS_DIR . '/' . "{$plugin}.php";
	}

	public static function get_plugin_info ( $plugin ) {
		$path 				= self::plugin_to_path( $plugin );
		$default_headers 	= array(
			'Name' 				=> 'Plugin Name',
			'Author' 			=> 'Author',
			'Description' 		=> 'Description',
			'Plugin URI' 		=> 'Plugin URI',
			'Version' 			=> 'Version',
			'Detail' 			=> 'Detail',
			'Type' 				=> 'AddonType',
			'Deprecated' 		=> 'Deprecated',
			'Required Class' 	=> 'Required Class',
		);
		return get_file_data( $path, $default_headers, 'eab_addon' );
	}

	private function _activate_plugin ( $plugin ) {
		if ( !current_user_can( 'manage_options' ) ) {
			return false;
		}

		$active = self::get_active_plugins();
		if ( in_array( $plugin, $active ) ) {
			return false; // Already active
		}

		$active[] = $plugin;
		return update_option( 'eab_activated_plugins', $active );
	}

	private function _deactivate_plugin ( $plugin ) {
		if ( !current_user_can( 'manage_options' ) ) return false;

		$active = self::get_active_plugins();
		if ( !in_array( $plugin, $active ) ) {
			return false; // Already deactivated
		}

		$key = array_search( $plugin, $active );
		if ( $key === false || $key === null ) {
			return false; // Haven't found it
		}

		unset( $active[ $key ] );
		return update_option( 'eab_activated_plugins', $active );
	}

	public static function create_addon_settings () {

		if ( !class_exists( 'WpmuDev_HelpTooltips' ) ) {
			require_once EAB_PLUGIN_DIR . 'lib/class_wd_help_tooltips.php';
		}
			
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );

		$all 		= self::get_all_plugins();
		$active 	= self::get_active_plugins();
		$sections 	= array();

		self::_display_status_message();

		$thead = "<table class='widefat' id='eab_addons_hub'>";
		$thead .= '<thead>';

		$tbody = '<thead>';
		$tbody .= "<tbody>";
		foreach ( $all as $plugin ) {
			$plugin_data = self::get_plugin_info( $plugin );
			if ( empty( $plugin_data['Name'] ) ) continue; // Require the name

			// Merge in the sections
			$types = array();
			if ( !empty( $plugin_data['Type'] ) ) {
				$types 		= array_map( 'trim', array_values( explode( ',', $plugin_data['Type'] ) ) );
				$sections 	= array_merge( $sections, $types );
			}

			$is_active = in_array( $plugin, $active );

			if ( 'yes' == $plugin_data['Deprecated'] ) {
				if ( empty( $plugin_data['Required Class'] ) ) {
					// No dependency, so hide the add-on if it is deactivated.
					if ( ! $is_active ) { continue; }
				} elseif ( ! class_exists( $plugin_data['Required Class'] ) ) {
					// Only hide the deprecated add-on when required class is missing.
					continue;
				}
			}

			$tbody .= '<tr' . ( !empty( $types ) ? ' data-type="' . esc_attr( join( ',', $types ) ) : '' ) . '" class="' . ($is_active ? 'active' : 'inactive') . '">';
			if ( !( defined( 'EAB_PREVENT_SETTINGS_SECTIONS') && EAB_PREVENT_SETTINGS_SECTIONS ) ) {
				$tbody .= '<td>' .
					'<input type="checkbox" value="' . esc_attr($plugin) . '" />' .
				'</td>';
			}
			$tbody .= "<td width='30%'>";
			$tbody .= '<b id="' . esc_attr( $plugin ) . '">' . $plugin_data['Name'] . '</b>';
			$tbody .= "<br />";
			$tbody .= ( $is_active
				?
				'<a href="#deactivate" class="eab_deactivate_plugin" eab:plugin_id="' . esc_attr( $plugin ) . '">' . __( 'Deactivate', Eab_EventsHub::TEXT_DOMAIN ) . '</a>'
				:
				'<a href="#activate" class="eab_activate_plugin" eab:plugin_id="' . esc_attr( $plugin ) . '">' . __( 'Activate', Eab_EventsHub::TEXT_DOMAIN ) . '</a>'
			);
			$tbody .= "</td>";
			$tbody .= '<td>' .
				$plugin_data['Description'] .
				'<br />' .
				sprintf(__('Version %s', Eab_EventsHub::TEXT_DOMAIN), $plugin_data['Version']) .
				'&nbsp;|&nbsp;' .
				sprintf(__('by %s', Eab_EventsHub::TEXT_DOMAIN), '<a href="' . $plugin_data['Plugin URI'] . '">' . $plugin_data['Author'] . '</a>');
			if ( $plugin_data['Detail'] ) {
				$tbody .= '&nbsp;' . $tips->add_tip( $plugin_data['Detail'] );
			}
			$tbody .= '</td>';
			$tbody .= "</tr>";
		}
		$tbody .= "</tbody>";
		$tbody .= "</table>";

		if ( !( defined( 'EAB_PREVENT_SETTINGS_SECTIONS' ) && EAB_PREVENT_SETTINGS_SECTIONS ) ) {
			$sections = array_values( array_unique( $sections ) );
			array_unshift( $sections, '' );
			$links = array();
			if ( !empty( $sections ) ) foreach ( $sections as $sect ) {
				$type = !empty($sect) ? "data-type='{$sect}'" : 'class="selected"';
				$name = !empty($sect) ? $sect : __('All', Eab_EventsHub::TEXT_DOMAIN);
				$links[] = "<a href='#filter' {$type}>{$name}</a>";
			}
			$thead .= '<tr>';
			$thead .= '<td class="filters" colspan="3">';

			if (!empty($links)) {
				$thead .= '<div class="section type">' .
					'<b>' . __('Filter', Eab_EventsHub::TEXT_DOMAIN) . ':</b> ' .
					join(' | ', $links) .
				'</div>';
			}

			$thead .= '<div class="section show">' .
				'<b>' . __('Show', Eab_EventsHub::TEXT_DOMAIN) . ':</b> ' .
				join(' | ', array(
					'<a href="#show-all" class="selected">' . __('All', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
					'<a href="#show-active" data-type="active">' . __('Active', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
					'<a href="#show-inactive" data-type="inactive">' . __('Inactive', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
				)) .
			'</div>';

			$thead .= '<div class="section check">' .
				'<b>' . __('Check', Eab_EventsHub::TEXT_DOMAIN) . ':</b> ' .
				join(' | ', array(
					'<a href="#check-none">' . __('None', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
					'<a href="#check-active" data-type="active">' . __('Active', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
					//'<a href="#check-inactive" data-type="inactive">' . __('Inactive', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
					//'<a href="#check-all" data-type="all">' . __('All', Eab_EventsHub::TEXT_DOMAIN) . '</a>',
				)) .
				'<div class="actions">' .
					'<button type="button" class="eab-activate_selected" data-nag="' . esc_attr(__('You are about to activate multiple add-ons. Are you sure you want to do this?', Eab_EventsHub::TEXT_DOMAIN)) . '">' . __('Activate selected', Eab_EventsHub::TEXT_DOMAIN) . '</button>' .
					'&nbsp;' .
					'<button type="button" class="eab-deactivate_selected" data-nag="' . esc_attr(__('You are about to deactivate multiple add-ons. Are you sure you want to do this?', Eab_EventsHub::TEXT_DOMAIN)) . '">' . __('Deactivate selected', Eab_EventsHub::TEXT_DOMAIN) . '</button>' .
				'</div>';
			'</div>';

			$thead .= '</td>';
			$thead .= '</tr>';
		}

		$thead .= '<tr>';
		$thead .= '<th></th><th width="30%">' . __('Name', Eab_EventsHub::TEXT_DOMAIN) . '</th>';
		$thead .= '<th>' . __('Description', Eab_EventsHub::TEXT_DOMAIN) . '</th>';
		$thead .= '</tr>';

		echo $thead . $tbody;

		echo <<<EOWdcpPluginJs
<script type="text/javascript">
(function ($) {
$(function () {

	function replace_query_var (query_var, value) {
		var rx_query = query_var.match(/s$/)
			? '(' + query_var.replace(/s$/, '') + '|' + query_var + ')'
			: '(' + query_var + 's|' + query_var + ')'
		;
		var rx = new RegExp('&' + rx_query + '=[^&]+');
		if (window.location.search.match(rx)) {
			window.location.search = window.location.search.replace(rx, '&' + query_var + '=' + value);
		} else {
			window.location.search += '&' + query_var + '=' + value;
		}
	}

	$(".eab_activate_plugin").click(function () {
		var me = $(this);
		var plugin_id = me.attr("eab:plugin_id");
		$.post(ajaxurl, {"action": "eab_activate_plugin", "plugin": plugin_id}, function (data) {
			//window.location = window.location;
			if (data && "status" in data) {
				var status = parseInt(data.status, 10),
					msg = status ? 'success' : 'error'
				;
				return replace_query_var('addon', msg);
			}
			return replace_query_var('addon', 'unknown');
		}, 'json');
		return false;
	});
	$(".eab_deactivate_plugin").click(function () {
		var me = $(this);
		var plugin_id = me.attr("eab:plugin_id");
		$.post(ajaxurl, {"action": "eab_deactivate_plugin", "plugin": plugin_id}, function (data) {
			//window.location = window.location;
			if (data && "status" in data) {
				var status = parseInt(data.status, 10),
					msg = status ? 'success' : 'error'
				;
				return replace_query_var('addon', msg);
			}
			return replace_query_var('addon', 'unknown');
		}, 'json');
		return false;
	});
	$("#eab_addons_hub .filters .section.type a").click(function (e) {
		e.preventDefault();
		var type = $(this).attr("data-type"),
			selector = '#eab_addons_hub tbody tr[data-type*="' + type + '"]'
		;
		if (!type) {
			$("#eab_addons_hub tbody tr").show();
		} else {
			$("#eab_addons_hub tbody tr").hide();
			$(selector).show();
		}
		$("#eab_addons_hub .filters .section.type a").removeClass('selected');
		$(this).addClass("selected");

		$("#eab_addons_hub .filters .section.show a")
			.addClass('selected')
			.filter('[data-type]').removeClass("selected")
		;

		return false;
	});
	$("#eab_addons_hub .filters .section.show a").click(function (e) {
		e.preventDefault();
		var type = $(this).attr("data-type");

		$("#eab_addons_hub tbody tr").show();
		if ('active' === type) $("#eab_addons_hub tbody tr.inactive").hide();
		if ('inactive' === type) $("#eab_addons_hub tbody tr.active").hide();

		$("#eab_addons_hub .filters .section.show a").removeClass('selected');
		$(this).addClass("selected");

		$("#eab_addons_hub .filters .section.type a")
			.addClass('selected')
			.filter('[data-type]').removeClass("selected")
		;

		return false;
	});
	$("#eab_addons_hub .filters .section.check a").click(function (e) {
		e.preventDefault();
		var type = $(this).attr("data-type");

		$("#eab_addons_hub tbody tr :checkbox").attr("checked", false);
		if ('active' === type) $("#eab_addons_hub tbody tr.active :checkbox").attr("checked", true);
		if ('inactive' === type) $("#eab_addons_hub tbody tr.inactive :checkbox").attr("checked", true);
		if ('all' === type) $("#eab_addons_hub tbody tr :checkbox").attr("checked", true);

		return false;
	});
	$("#eab_addons_hub .filters .section.check .actions button").click(function (e) {
		e.preventDefault();

		var selection = $("#eab_addons_hub tbody tr :checkbox:checked"),
			action = $(this).is(".eab-activate_selected") ? 'eab-activate-selected' : 'eab-deactivate-selected',
			nag = $(this).attr("data-nag"),
			plugins = []
		;
		if (!selection.length) return false;
		selection.each(function () {
			plugins.push($(this).val());
		})

		if (!plugins.length) return false;
		if (nag) {
			if (!confirm(nag)) return false;
		}
		$.post(ajaxurl, {action: action, plugins: plugins}, function (data) {
			//window.location = window.location;
			if (data && "status" in data) {
				var status = parseInt(data.status, 10),
					msg = status ? 'success' : 'error'
				;
				return replace_query_var('addons', msg);
			}
			return replace_query_var('addon', 'unknown');
		}, 'json');

		return false;
	});
});
})(jQuery);
</script>
EOWdcpPluginJs;
	}

	private static function _display_status_message () {
		$msgs = array();
		if (!empty($_GET['addon'])) $msgs[] = self::_display_addon_status();
		if (!empty($_GET['addons'])) $msgs[] = self::_display_addon_bulk_status();
		foreach ($msgs as $msg) {
			$cls = 'updated';
			if (is_array($msg)) {
				$cls = !empty($msg['class']) ? sanitize_html_class($msg['class']) : $cls;
				$msg = !empty($msg['message']) ? $msg['message'] : false;
			}
			if (empty($msg)) continue;
			$msg = esc_html($msg);
			echo "<div class='{$cls}'><p>{$msg}</p></div>";
		}
	}

	private static function _display_addon_status () {
		$msg = array(
			'unknown' => __('Something might have gone wrong with add-on manipulation, please check your add-on status', Eab_EventsHub::TEXT_DOMAIN),
			'success' => __('Add-on successfully (de)activated', Eab_EventsHub::TEXT_DOMAIN),
			'error' => array(
				'class' => 'error',
				'message' => __('There was something wrong with (de)activating the add-on.', Eab_EventsHub::TEXT_DOMAIN),
			),
		);
		$status = !empty($_GET['addon']) && in_array($_GET['addon'], array_keys($msg))
			? $_GET['addon']
			: 'unknown'
		;
		return !empty($msg[$status])
			? $msg[$status]
			: false
		;
	}

	private static function _display_addon_bulk_status () {
		$msg = array(
			'unknown' => __('Something might have gone wrong with add-ons manipulation, please check your add-ons respective status', Eab_EventsHub::TEXT_DOMAIN),
			'success' => __('Selected add-ons successfully (de)activated', Eab_EventsHub::TEXT_DOMAIN),
			'error' => array(
				'class' => 'error',
				'message' => __('There was something wrong with (de)activating at least some of the selected add-ons.', Eab_EventsHub::TEXT_DOMAIN),
			),
		);
		$status = !empty($_GET['addons']) && in_array($_GET['addons'], array_keys($msg))
			? $_GET['addons']
			: 'unknown'
		;
		return !empty($msg[$status])
			? $msg[$status]
			: false
		;
	}
}
