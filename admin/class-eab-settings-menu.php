<?php

class Eab_Admin_Settings_Menu {

	public function __construct( $parent ) {
		$id = add_submenu_page(
			$parent,
			__( "Event Settings", eab_domain() ),
			__( "Settings", eab_domain() ),
			'manage_options',
			'eab_settings',
			array( $this, 'render' )
		);

		add_action( 'load-' . $id, array( $this, 'load' ) );

		$eab = events_and_bookings();
		$this->_data = $eab->_data;
		$this->_api = $eab->_api;
	}

	public function load() {
		if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'incsub_event-update-options')) {
			$this->save_options();
		}

	}

	function render() {

		$updated = isset($_GET['incsub_event_settings_saved']) && $_GET['incsub_event_settings_saved'] == 1;


		if ( ! class_exists( 'WpmuDev_HelpTooltips' ) )
			require_once eab_plugin_dir() . 'lib/class_wd_help_tooltips.php';

		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url( eab_plugin_url() . 'img/information.png' );

		if ( ! ( defined('EAB_PREVENT_SETTINGS_SECTIONS') && EAB_PREVENT_SETTINGS_SECTIONS ) )
			$tabbable = 'tabbable';
		else
			$tabbable = false;

		$hide = ! empty( $tabbable ) ? 'hide' : '';

		$archive_tpl = file_exists(get_stylesheet_directory().'/archive-incsub_event.php')
			? get_stylesheet_directory() . '/archive-incsub_event.php'
			: get_template_directory() . '/archive-incsub_event.php'
		;

		$archive_tpl_present = apply_filters(
			'eab-settings-appearance-archive_template_copied',
			file_exists($archive_tpl)
		);

		$single_tpl = file_exists(get_stylesheet_directory().'/single-incsub_event.php')
			? get_stylesheet_directory() . '/single-incsub_event.php'
			: get_template_directory() . '/single-incsub_event.php'
		;

		$single_tpl_present = apply_filters(
			'eab-settings-appearance-single_template_copied',
			file_exists($single_tpl)
		);

		$theme_tpls_present = apply_filters(
			'eab-settings-appearance-templates_copied',
			($archive_tpl_present && $single_tpl_present)
		);

		$raw_tpl_sets = glob(EAB_PLUGIN_DIR . 'default-templates/*');

		$templates = array();
		foreach ($raw_tpl_sets as $item) {
			if (!is_dir($item)) continue;
			$key = basename($item);
			$label = ucwords(preg_replace('/[^a-z0-9]+/i', ' ', $key));
			$templates[$key] = sprintf(__("Plugin: %s", eab_domain() ), $label);
		}
		foreach (get_page_templates() as $name => $tpl) {
			$templates[$tpl] = sprintf(__("Theme: %s", eab_domain() ), $name);

		}

		include_once( 'views/settings-menu.php' );
	}

	public function save_options() {

		$defaults = $this->_data->get_default_options();

		$event_default = $_POST['event_default'];

		$options = array();
		$options['slug'] 						= trim(trim($event_default['slug'], '/'));
		$options['accept_payments'] 			= empty( $event_default['accept_payments'] ) ? 0 : $event_default['accept_payments'];
		$options['accept_api_logins'] 			= empty( $event_default['accept_api_logins'] ) ? 0 : $event_default['accept_api_logins'];
		$options['display_attendees'] 			= empty( $event_default['display_attendees'] ) ? 0 : $event_default['display_attendees'];
		$options['currency'] 					= $event_default['currency'];
		$options['paypal_email'] 				= $event_default['paypal_email'];
		$options['paypal_sandbox'] 				= empty( $event_default['paypal_sandbox'] ) ? 0 : $event_default['paypal_sandbox'];

		$options['override_appearance_defaults']	= empty( $event_default['override_appearance_defaults'] ) ? 0 : $event_default['override_appearance_defaults'];
		$options['archive_template'] 			= empty( $event_default['archive_template'] ) ? '' : $event_default['archive_template'];
		$options['single_template'] 			= empty( $event_default['single_template'] ) ? '' : $event_default['single_template'];

		$options = apply_filters('eab-settings-before_save', $options);
		$this->_data->set_options($options);
                // Added by Ashok
                // Removed old redirect
                // Added new redirect, based on selected tab in Events Settings
                wp_redirect( add_query_arg( 'incsub_event_settings_saved', 1, $event_default['event_settings_url'] ) );
		//wp_redirect('edit.php?post_type=incsub_event&page=eab_settings&incsub_event_settings_saved=1');
		exit();
	}
}