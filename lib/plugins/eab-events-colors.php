<?php
/*
Plugin Name: Colors
Description: Allows you to easily tweak the background color for your events.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.1
Author: WPMU DEV
AddonType: Events
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
        add_action('wp_footer', array($this, 'inject_footer_script'), 10);
	}
	
	function enqueue_dependencies () {
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
	}

    function inject_footer_script(){
        $colors = $this->_data->get_option("eab-colors");
        $colors = $colors ? $colors : array();
        if (empty($colors)) return false;

        $use_widget = $this->_data->get_option('eab-colors-use_widget');
        if(!$use_widget) return false;

        $colors_json = json_encode( $colors );
        ?>
        <script type="text/javascript">
            var eab_colors_object = jQuery.parseJSON( '<?php echo $colors_json; ?>' );
            var eab_widget_colors_update = function($){
                $.each(eab_colors_object, function( key, value ) {
                    $( "td.eab-has_events" ).has( "a."+key ).addClass( key );
                });
            };
            jQuery(document).ready(
                function($){
                    eab_widget_colors_update($);
                }
            );
            jQuery(document).bind('eab-cuw-render_complete', function () {
                    eab_widget_colors_update(jQuery);
                }
            );
        </script>
    <?php
    }

	function inject_color_settings () {
		$colors = $this->_data->get_option("eab-colors");
		$colors = $colors ? $colors : array();
		if (empty($colors)) return false;

		$use_widget = $this->_data->get_option('eab-colors-use_widget');
		$use_expanded_widget = $this->_data->get_option('eab-colors-use_expanded_widget');

		$default = !empty($colors['__default__']) ? $colors['__default__'] : false;
		$style = '';
		if ($default && empty($default['skip'])) {
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
			if (!empty($color['skip'])) continue; // We won't be using this
			$selectors = array(
				'.wpmudevevents-calendar-event.' . sanitize_html_class($class),
			);
			if ($use_widget) {
                $selectors[] = 'td.eab-has_events.' . sanitize_html_class($class);
                $selectors[] = 'td.eab-has_events.' . sanitize_html_class($class) . ' a';
				$selectors[] = '.eab-upcoming_calendar_widget.' . sanitize_html_class($class) . ' td.eab-has_events';
				$selectors[] = '.eab-upcoming_calendar_widget.' . sanitize_html_class($class) . ' td.eab-has_events a';
			}
			if ($use_expanded_widget) {
				$exp = '#wpmudevevents-upcoming_calendar_widget-shelf .wpmudevevents-upcoming_calendar_widget-event.' . sanitize_html_class($class);
				$selectors[] = $exp;
				$selectors[] = "{$exp} span";
			}
			$style .= join(',', $selectors) . ' {' .
			'';
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
		if ($use_expanded_widget) {
			$style .= '#wpmudevevents-upcoming_calendar_widget-shelf .wpmudevevents-upcoming_calendar_widget-event { display: block; padding: .2em; }';
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

		$use_widget = $this->_data->get_option('eab-colors-use_widget') ? 'checked="checked"' : '';
		$use_expanded_widget = $this->_data->get_option('eab-colors-use_expanded_widget') ? 'checked="checked"' : '';
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png');
?>
<div id="eab-settings-colors" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Event Colors', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
	<?php foreach ($categories as $category) { ?>
		<div class="eab-settings-settings_item">
		<?php
			if ( is_object( $category )  ) {
				$label = sprintf(__('Event category: &quot;%s&quot;', Eab_EventsHub::TEXT_DOMAIN), esc_html($category->name));
				$cat = sanitize_html_class($category->slug);
				$for = esc_attr("eab-colors-{$category->slug}");
				$value_bg = $default_bg;
				$value_fg = $default_bg;
				if ( isset ( $colors[$cat] ) && isset( $colors[$cat]['bg'] ) ) {
					$value_bg = !empty($colors[$cat]['bg']) || '#' == $colors[$cat]['bg'] ? esc_attr($colors[$cat]['bg']) : $default_bg;
				}
				if ( isset ( $colors[$cat] ) && isset( $colors[$cat]['fg'] ) ) {
					$value_fg = !empty($colors[$cat]['fg']) || '#' == $colors[$cat]['fg'] ? esc_attr($colors[$cat]['fg']) : $default_fg;
				}

			} else {
				$label = __('Default', Eab_EventsHub::TEXT_DOMAIN);
				$cat = '__default__';
				$for = esc_attr("eab-colors-{$cat}");
				$value_bg = $default_bg;
				$value_fg = $default_bg;
				if ( isset ( $colors[$cat] ) && isset( $colors[$cat]['bg'] ) ) {
					$value_bg = !empty($colors[$cat]['bg']) || '#' == $colors[$cat]['bg'] ? esc_attr($colors[$cat]['bg']) : $default_bg;
				}
				if ( isset ( $colors[$cat] ) && isset( $colors[$cat]['fg'] ) ) {
					$value_fg = !empty($colors[$cat]['fg']) || '#' == $colors[$cat]['fg'] ? esc_attr($colors[$cat]['fg']) : $default_fg;
				}

				$default_bg = $value_bg;
				$default_fg = $value_fg;
			}
		?>
			<b><?php echo $label; ?></b><br />
			<label for="<?php echo $for; ?>-bg">
				<span class="eab-color-text"><?php _e('Background', Eab_EventsHub::TEXT_DOMAIN); ?></span><br/>
				<input type="color" name="eab-colors[<?php echo $cat; ?>][bg]" value="<?php echo $value_bg; ?>" />
			</label>
			<label for="<?php echo $for; ?>-fg">
				<span class="eab-color-text"><?php _e('Text', Eab_EventsHub::TEXT_DOMAIN); ?></span><br/>
				<input type="color" name="eab-colors[<?php echo $cat; ?>][fg]" value="<?php echo $value_fg; ?>" />
			</label>
			<label for="<?php echo $for; ?>-skip">
				<input type="checkbox" id="<?php echo $for; ?>-skip" name="eab-colors[<?php echo $cat; ?>][skip]" value="1" <?php echo !empty($colors[$cat]['skip']) ? 'checked="checked"' : ''; ?> />
				<?php _e('Skip', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
		</div>
	<?php } ?>
		<div class="eab-settings-settings_item">
			<button id="eab-colors-reset_to_defaults" class="button"><?php _e('Reset to defaults', Eab_EventsHub::TEXT_DOMAIN); ?></button>
		</div>
		<div class="eab-settings-settings_item">
			<input type="hidden" name="eab-colors-use_widget" value="" />
			<input type="checkbox" name="eab-colors-use_widget" id="eab-colors-use_widget" value="1" <?php echo $use_widget; ?> />
			<label for="eab-colors-use_widget"><?php esc_html_e(__('Apply backgrounds to single-category calendar widget', Eab_EventsHub::TEXT_DOMAIN)); ?></label>
			<span><?php echo $tips->add_tip(__('Selecting this option will propagate foreground and background color to your Calendar widget events, provided there is only one category selected for displaying.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
		</div>
		<div class="eab-settings-settings_item">
			<input type="hidden" name="eab-colors-use_expanded_widget" value="" />
			<input type="checkbox" name="eab-colors-use_expanded_widget" id="eab-colors-use_expanded_widget" value="1" <?php echo $use_expanded_widget; ?> />
			<label for="eab-colors-use_expanded_widget"><?php esc_html_e(__('Apply colors to calendar widget expanded events', Eab_EventsHub::TEXT_DOMAIN)); ?></label>
		</div>
	</div>
</div>
<script>
(function ($) {
$(function () {
	var $fields = $('#eab-settings-colors input[type="color"]');
        
	if ($fields.length && $fields.wpColorPicker) $fields.wpColorPicker();
	$("#eab-colors-reset_to_defaults").click(function (e) {
            e.preventDefault();
            for( var i = 0; i < $fields.length; i++ ) {
                var labelAttr = $( $fields[i] ).closest( 'label' ).attr( 'for' );
                if( labelAttr == 'eab-colors-__default__-bg' ) {
                    // For some reason .val( '#'75AB24 ) is not working here!
                    $( $fields[i] ).attr( 'value', '#75AB24' );
                }
                else if( labelAttr == 'eab-colors-__default__-fg' ) {
                    $( $fields[i] ).attr( 'value', '#FFFFFF' );
                }
            }
            
            $( 'input[name="submit_settings"]' ).click();
		//$fields.val('');
	});
});
})(jQuery);
</script>
<?php		
	}
	
	function save_settings ($options) {
		if (!empty($_POST['eab-colors'])) $options['eab-colors'] = $_POST['eab-colors'];
		$options['eab-colors-use_widget'] = !empty($_POST['eab-colors-use_widget']);
		$options['eab-colors-use_expanded_widget'] = !empty($_POST['eab-colors-use_expanded_widget']);
		return $options;
	}
}

Eab_Events_Colors::serve();
