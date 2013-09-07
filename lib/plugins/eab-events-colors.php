<?php
/*
Plugin Name: Colors
Description: Allows you to easily tweak the background color for your events.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: Ve Bailovity (Incsub)
*/

class Eab_Events_Colors {
	
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}
	
	public static function serve () {
		$me = new Eab_Events_Colors;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('eab-settings-after_appearance_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		add_action('admin_head-incsub_event_page_eab_settings', array($this, 'enqueue_dependencies'));
	
		add_action('wp_head', array($this, 'inject_color_settings'));
	}
	
	function enqueue_dependencies () {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		//wp_enqueue_style('eab-event-capabilities', plugins_url(basename(EAB_PLUGIN_DIR) . '/css/eab-event-capabilities.css'));
		//wp_enqueue_script('eab-event-capabilities', plugins_url(basename(EAB_PLUGIN_DIR) . '/js/eab-event-capabilities.js'), array('jquery'));
	}

	function inject_color_settings () {
		$colors = $this->_data->get_option("eab-colors");
		$colors = $colors ? $colors : array();
		if (empty($colors)) return false;

		$default = !empty($colors['__default__']) ? $colors['__default__'] : false;
		$style = '';
		if ($default) {
			$style .= '.wpmudevevents-calendar-event {';
			if (!empty($default['bg'])) {
			$style .= '' .
				'background: ' . $default['bg'] . ' !important;' .
				'border-color: ' . $default['bg'] . ' !important;' .
			'';
			}
			if (!empty($default['fg'])) {
				$style .= 'color: ' . $default['fg'] . ' !important;';
			}
			$style .= '}';
			unset($colors['__default__']);
		}
		foreach ($colors as $class => $color) {
			$style .= '.wpmudevevents-calendar-event.' . sanitize_html_class($class) . ' {';
			if (!empty($color['bg'])) {
				$style .= '' .
					'background: ' . $color['bg'] . ' !important;' .
					'border-color: ' . $color['bg'] . ' !important;' .
				'';
			}
			if (!empty($color['fg'])) {
				$style .= 'color: ' . $color['fg'] . ' !important;';
			}
			$style .= '}';
		}
?>
<style type="text/css">
	<?php echo $style; ?>
</style>
<?php
	}
	
	function show_settings () {
		$categories = get_terms('eab_events_category', array(
			'hide_empty' => false,
		));
		array_unshift($categories, 'default');
		$colors = $this->_data->get_option("eab-colors");
		$colors = $colors ? $colors : array();

		$default_bg = '#75AB24';
		$default_fg = '#FFFFFF';
?>
<div id="eab-settings-colors" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Event Colors :', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
	<?php foreach ($categories as $category) { ?>
		<div class="eab-settings-settings_item">
		<?php
			if (is_object($category)) {
				$label = sprintf(__('Event category: &quot;%s&quot;', Eab_EventsHub::TEXT_DOMAIN), esc_html($category->name));
				$cat = sanitize_html_class($category->slug);
				$for = esc_attr("eab-colors-{$category->slug}");
				$value_bg = !empty($colors[$cat]['bg']) || '#' == $colors[$cat]['bg'] ? esc_attr($colors[$cat]['bg']) : $default_bg;
				$value_fg = !empty($colors[$cat]['fg']) || '#' == $colors[$cat]['fg'] ? esc_attr($colors[$cat]['fg']) : $default_fg;
			} else {
				$label = __('Default', Eab_EventsHub::TEXT_DOMAIN);
				$cat = '__default__';
				$for = esc_attr("eab-colors-{$cat}");
				$value_bg = !empty($colors[$cat]['bg']) || '#' == $colors[$cat]['bg'] ? esc_attr($colors[$cat]['bg']) : $default_bg;
				$value_fg = !empty($colors[$cat]['fg']) || '#' == $colors[$cat]['fg'] ? esc_attr($colors[$cat]['fg']) : $default_fg;

				$default_bg = $value_bg;
				$default_fg = $value_fg;
			}
		?>
			<b><?php echo $label; ?></b><br />
			<label for="<?php echo $for; ?>-bg">
				<?php _e('Background', Eab_EventsHub::TEXT_DOMAIN); ?>
				<input type="color" name="eab-colors[<?php echo $cat; ?>][bg]" value="<?php echo $value_bg; ?>" />
			</label>
			<label for="<?php echo $for; ?>-fg">
				<?php _e('Text', Eab_EventsHub::TEXT_DOMAIN); ?>
				<input type="color" name="eab-colors[<?php echo $cat; ?>][fg]" value="<?php echo $value_fg; ?>" />
			</label>
		</div>
	<?php } ?>
		<div class="eab-settings-settings_item">
			<button id="eab-colors-reset_to_defaults" class="button"><?php _e('Reset to defaults', Eab_EventsHub::TEXT_DOMAIN); ?></button>
		</div>
	</div>
</div>
<script>
(function ($) {
$(function () {
	var $fields = $('#eab-settings-colors input[type="color"]');
	if ($fields.length && $fields.wpColorPicker) $fields.wpColorPicker();
	$("#eab-colors-reset_to_defaults").click(function () {
		$fields.val('');
	});
});
})(jQuery);
</script>
<?php		
	}
	
	function save_settings ($options) {
		if (!empty($_POST['eab-colors'])) $options['eab-colors'] = $_POST['eab-colors'];
		return $options;
	}
}

Eab_Events_Colors::serve();
