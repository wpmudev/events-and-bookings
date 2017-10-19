<?php
/*
Plugin Name: Events Maps Options
Description: Maps will, by default, use the global settings for Google Maps plugin. Use this add-on to apply Events-specific settings.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Integration
*/

class Eab_Maps_EventMapsOptions {

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Maps_EventMapsOptions;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_filter('eab-maps-map_defaults', array($this, 'apply_defaults'));
	}

	function apply_defaults ($options) {
		return $this->_data->get_option('google_maps-overrides');
	}

	function show_nags () {
		if (!class_exists('AgmMapModel')) {
			echo '<div class="error"><p>' .
				sprintf(__("You'll need <a href='%s'>Google Maps</a> plugin installed and activated for Events Maps Options add-on to work", Eab_EventsHub::TEXT_DOMAIN), 'http://premium.wpmudev.org/project/wordpress-google-maps-plugin') .
			'</p></div>';
		}
	}

	function save_settings ($options) {
		if ( !isset( $_POST['google_maps'] ) ) {
			return $options;
		}

		$data = stripslashes_deep( $_POST['google_maps'] );
		$options['google_maps-overrides'] = !empty( $data['overrides'] ) ? array_filter($data['overrides']) : array();
		return $options;
	}

	function show_settings () {
		$map_types = array(
			'ROADMAP' 	=> __('ROADMAP', 'agm_google_maps'),
			'SATELLITE' => __('SATELLITE', 'agm_google_maps'),
			'HYBRID' 	=> __('HYBRID', 'agm_google_maps'),
			'TERRAIN' 	=> __('TERRAIN', 'agm_google_maps'),
		);
		$map_units = array(
			'METRIC' 	=> __('Metric', 'agm_google_maps'),
			'IMPERIAL' 	=> __('Imperial', 'agm_google_maps'),
		);
		$options = $this->_data->get_option('google_maps-overrides');
?>
<div id="eab-settings-event_maps_options" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Events Maps Options', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<p><em><?php _e('Any setting you leave empty here will be inherited from the default Google Maps plugin settings.', Eab_EventsHub::TEXT_DOMAIN); ?></em></p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Map size', Eab_EventsHub::TEXT_DOMAIN)?></th>
				<td>
					<label for="eab-google_maps-width">
						<?php _e('Width:', Eab_EventsHub::TEXT_DOMAIN); ?>
						<input type="text" size="4" id="eab-google_maps-width" name="google_maps[overrides][width]" value="<?php esc_attr_e(@$options['width']); ?>" /><em class="eab-inline_help">px</em>
					</label>
					<span class="eab-hspacer">&times;</span>
					<label for="eab-google_maps-height">
						<?php _e('Height:', Eab_EventsHub::TEXT_DOMAIN); ?>
						<input type="text" size="4" id="eab-google_maps-height" name="google_maps[overrides][height]" value="<?php esc_attr_e(@$options['height']); ?>" /><em class="eab-inline_help">px</em>
					</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Map appearance', Eab_EventsHub::TEXT_DOMAIN)?></th>
				<td>
					<style>
						.eab_inner_table td{padding: 0 !important}
					</style>
					<table celpadding="5" cellspacing="5" class="eab_inner_table">
						<tr>
							<td valign="top">
								<label for="eab-google_maps-zoom">
									<?php _e('Zoom:', Eab_EventsHub::TEXT_DOMAIN); ?>
								</label>
							</td>
							<td valign="top">
								<input type="text" size="4" id="eab-google_maps-zoom" name="google_maps[overrides][zoom]" value="<?php esc_attr_e(@$options['zoom']); ?>" />
								<em class="eab-inline_help"><?php _e('Numeric value', Eab_EventsHub::TEXT_DOMAIN); ?></em>
							</td>
						</tr>
						<tr>
							<td valign="top">
								<label for="eab-google_maps-type">
									<?php _e('Type:', Eab_EventsHub::TEXT_DOMAIN); ?>
								</label>
							</td>
							<td valign="top">
								<select name="google_maps[overrides][map_type]">
									<option value=""></option>
									<?php foreach ($map_types as $type => $label) { ?>
										<option value="<?php esc_attr_e($type); ?>"
											<?php selected(@$options['map_type'], $type); ?>
										><?php echo $label; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top"><label for="eab-google_maps-units"><?php _e('Units:', Eab_EventsHub::TEXT_DOMAIN); ?></label></td>
							<td valign="top">
								<select name="google_maps[overrides][units]">
									<option value=""></option>
									<?php foreach ($map_units as $units => $label) { ?>
										<option value="<?php esc_attr_e($units); ?>"
											<?php selected(@$options['units'], $units); ?>
										><?php echo $label; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top">
								<input type="hidden" name="google_maps[overrides][show_images]" value="" />
						<input type="checkbox" id="eab-google_maps-show_images" name="google_maps[overrides][show_images]" value="1" <?php checked(1, @$options['show_images']); ?> />
							</td>
							<td valign="top"><label for="eab-google_maps-show_images"><?php _e('Show images', Eab_EventsHub::TEXT_DOMAIN); ?></label></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php
	}
}
Eab_Maps_EventMapsOptions::serve();