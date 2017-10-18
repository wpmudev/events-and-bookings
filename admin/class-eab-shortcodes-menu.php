<?php


class Eab_Admin_Shortcodes_Menu {
	public function __construct( $parent ) {
		$id = add_submenu_page(
			$parent,
			__("Event Shortcodes", eab_domain()),
			__("Shortcodes", eab_domain()),
			'edit_events',
			'eab_shortcodes',
			array( $this, 'render' ) );

	}

	function render () {
		// Filter the help....
		$help = apply_filters('eab-shortcodes-shortcode_help', array());

		if (!class_exists('WpmuDev_HelpTooltips'))
			require_once eab_plugin_dir(). 'lib/class_wd_help_tooltips.php';

		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );

		$out = '';
		$count = 0;
		$half = (int)(count($help) / 2);

		$out .= '<div class="postbox-container">';
		foreach ($help as $shortcode) {
			$out .= '<div class="eab-metabox postbox"><h3 class="eab-hndle">' . $shortcode['title'] . '</h3>';
			$out .= '<div class="eab-inside">';
			$out .= '	<div class="eab-settings-settings_item">';
			$out .= '		<strong>' . __('Tag:', eab_domain() ) . '</strong> <code>[' . $shortcode['tag'] . ']</code>';
			if (!empty($shortcode['note'])) $out .= '<div class="eab-note">' . $shortcode['note'] . '</div>';
			$out .= '	</div>';
			if (!empty($shortcode['arguments'])) {
				$out .= ' <div class="eab-settings-settings_item" style="line-height:1.5em"><strong>' . __('Arguments:', eab_domain() ) . '</strong>';
				foreach ($shortcode['arguments'] as $argument => $data) {
					if (!empty($shortcode['advanced_arguments']) && !current_user_can('manage_options')) {
						if (in_array($argument, $shortcode['advanced_arguments'])) continue;
					}
					$type = !empty($data['type'])
						? eab_call_template('util_shortcode_argument_type_string_info', $data['type'], $argument, $shortcode['tag'], $tips)
						: false
					;
					$out .= "<div class='eab-shortcode-attribute_item'><code>{$argument}</code> - {$data['help']} {$type}</div>";
				}
				$out .= '</div><!-- Attributes -->';
			}
			$out .= '</div></div>';
			$count++;
			if ($count == $half) $out .= '</div><div class="postbox-container eab-postbox_container-last">';
		}
		$out .= '</div>';

		echo '<div class="wrap">
				<h1>' . __('Events Shortcodes', eab_domain() ) . '</h1>
				<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center columns-2">';
		echo $out;

		echo '</div></div>';
	}
}