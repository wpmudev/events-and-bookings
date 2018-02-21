<?php
/*
Plugin Name: Additional registration fields
Description: Allows you to add additional registration fields.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events, RSVP
*/

class Eab_Rsvps_AdditionalRegistrationFields {

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Rsvps_AdditionalRegistrationFields;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_appearance_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_filter('eab-javascript-api_vars', array($this, 'update_api_messages'));
		add_action('wp_footer', array($this, 'inject_tmp_scripts'));

		add_filter('eab-user_registration-wordpress-field_validation', array($this, 'validate_additional_fields'), 10, 3);
		add_action('eab-user_registered-wordpress', array($this, 'save_additional_fields'), 10, 3);

		if ($this->_data->get_option('additional_fields-export')) {
			add_filter('eab-exporter-csv-row', array($this, 'inject_export_columns'), 10, 4);
		}
	}

	function inject_export_columns ($row, $event, $booking, $user) {
		if (empty($user->ID)) return $row;
		$has_empty = true;
		
		$fields = Eab_Rsvps_Arf_Model::get($user->ID);
		if (empty($fields) && !$has_empty) return $row;
		
		foreach ($fields as $key => $value) {
			if (empty($value) && !$has_empty) continue;
			$row[$key] = '' === $value
				? __('N/A', Eab_EventsHub::TEXT_DOMAIN)
				: $value
			;
		}

		return $row;
	}

	function validate_additional_fields ($status, $data, $email_rsvp = false ) {
		if ( $email_rsvp ) {
			$fields = Eab_Rsvps_Arf_Model::get_email_rsvp();
		} else {
			$fields = Eab_Rsvps_Arf_Model::get_all();
		}
		if (empty($fields)) return $status;

		foreach ($fields as $field) {
			if (empty($field["id"])) continue;
			$id = $field["id"];
			$value = !empty($data[$id]) ? trim($data[$id]) : false;
			if (!empty($field["required"]) && empty($value)) return false;
		}

		return $status;
	}

	function save_additional_fields ($user_id, $data, $email_rsvp = false ) {
		if ( $email_rsvp ) {
			$fields = Eab_Rsvps_Arf_Model::get_email_rsvp();
		} else {
			$fields = Eab_Rsvps_Arf_Model::get_all();
		}
		if (empty($fields)) return false;

		foreach ($fields as $field) {
			if (empty($field["id"])) continue;
			$id = $field["id"];
			$value = !empty($data[$id]) ? trim($data[$id]) : false;
			$value = wp_strip_all_tags($value);

			update_user_meta($user_id, $id, $value);
		}
	}

	function update_api_messages ($api) {
		if (!empty($api['required_field_missing'])) return $api;
		$api['required_field_missing'] = __('A required field is missing.', Eab_EventsHub::TEXT_DOMAIN);
		return $api;
	}

	function inject_tmp_scripts () {
		$fields = Eab_Rsvps_Arf_Model::get_all();
		if (empty($fields)) return false;
		$fields_email_rsvp = Eab_Rsvps_Arf_Model::get_email_rsvp();

		?>
<script>
(function ($) {

var eab_rarf_fields = <?php echo json_encode($fields); ?>;
var eab_rare_fields = <?php echo json_encode($fields_email_rsvp); ?>;

function get_fields_html( email_rsvp ) {
	var additional_class = '',
		prefix
	;
	if ( "undefined" === typeof email_rsvp ) {
		array_fields = eab_rarf_fields;
		prefix = 'eab-rarf-';
	} else {
		array_fields = eab_rare_fields;
		additional_class = ' eab-additional-registration-field';
		prefix = 'eab-rare-';
	}

	var additive = '';
	$.each(array_fields, function (idx, field) {
		additive += '' +
			'<p class="eab-wordpress_login-element">' +
				'<label for="eab-rarf-' + field.id + '">' +
					field.label +
				'</label>' +
				'<input type="' + field.type + '" value="" id="' + prefix + field.id + '" data-key="' + field.id + '" class="' + additional_class + '" />' +
			'</p>' +
		'';

	});

	return  additive;
}

$(document).on("eab-api-registration-form_rendered", function () {
	var $root = $("#eab-wordpress_login-registration_wrapper"),
		$last = $root.find(".eab-wordpress_login-element:last"),
		additive = '';

	additive = get_fields_html();
	$last.after(additive);
});

$(document).on("eab-api-email_rsvp-form_rendered", function() {
	var $root = $("#eab-rsvps-rsvp_with_email");
	$root.after( get_fields_html( 'email_rsvp' ) );
});

$(document).on("eab-api-registration-data", function (e, data, deferred) {
	$.each(eab_rarf_fields, function (idx, field) {
		var $field = $("#eab-rarf-" + field.id),
			value = $field.is(":checkbox") ? $field.is(":checked") : $.trim($field.val())
		;
		if (!value && field.required) {
			$('#eab-wordpress-signup-status').text(l10nEabApi.required_field_missing);
			deferred.reject();
			return false;
		}
		data[field.id] = value;
	});
});

})(jQuery);
</script>
		<?php
	}

	function show_settings () {
		wp_enqueue_script('underscore');

		$rsvp_with_email = Eab_AddonHandler::is_plugin_active('eab-rsvps-rsvp_with_email');
		$fields = Eab_Rsvps_Arf_Model::get_all();
		$fields = is_array($fields) ? $fields : array();
		$_types = array(
			'text' => __('Text', Eab_EventsHub::TEXT_DOMAIN),
			'checkbox' => __('Checkbox', Eab_EventsHub::TEXT_DOMAIN),
		);
		?>
<div id="eab-settings-eab_arf" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Additional Fields', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div id="eab-arf-additional_fields">
		<?php foreach ($fields as $field) { ?>
			<div class="eab-arf-field" style="line-height:1.8em">
				<b><?php echo esc_html($field['label']); ?></b> <em><small>(<?php echo esc_html($field['type']); ?>)</small></em>
				<br />
				<?php echo esc_html('Required', Eab_EventsHub::TEXT_DOMAIN); ?>: <b><?php echo esc_html(($field['required'] ? __('Yes', Eab_EventsHub::TEXT_DOMAIN) : __('No', Eab_EventsHub::TEXT_DOMAIN))); ?></b>
				<?php if ( $rsvp_with_email ) { ?>
					<br />
					<?php echo esc_html('Add to RSVP with email', Eab_EventsHub::TEXT_DOMAIN); ?>: <b><?php echo esc_html(($field['email_rsvp'] ? __('Yes', Eab_EventsHub::TEXT_DOMAIN) : __('No', Eab_EventsHub::TEXT_DOMAIN))); ?></b>
				<?php } ?>
				<br />
				<!--<?php _e('E-mail macro:', Eab_EventsHub::TEXT_DOMAIN); ?> <code><?php echo esc_html(Eab_Rsvps_Arf_Model::get_macro($field['label'])); ?></code>
				<span class="description"><?php _e('This is the placeholder you can use in your emails.', Eab_EventsHub::TEXT_DOMAIN); ?></span> -->
				<input type="hidden" name="eab-arf-additional_fields[]" value="<?php echo rawurlencode(json_encode($field)); ?>" />
				<a href="#remove" class="eab-arf-additional_fields-remove"><?php echo esc_html('Remove', Eab_EventsHub::TEXT_DOMAIN); ?></a>
				<br />
			</div>
		<?php } ?>
		</div>
		<div id="eab-arf-new_additional_field">
			<h4><?php _e('Add new field', Eab_EventsHub::TEXT_DOMAIN); ?></h4>
			<label for="eab-arf-new_additional_field-label">
				<?php _e('Field label:', Eab_EventsHub::TEXT_DOMAIN); ?>
				<input type="text" value="" id="eab-arf-new_additional_field-label" />
			</label>
			<label for="eab-arf-new_additional_field-type">
				<?php _e('Field type:', Eab_EventsHub::TEXT_DOMAIN); ?>
				<select id="eab-arf-new_additional_field-type">
				<?php foreach ($_types as $type => $label) { ?>
					<option value="<?php esc_attr_e($type); ?>"><?php echo esc_html($label); ?></option>
				<?php } ?>
				</select>
			</label>
			<label for="eab-arf-new_additional_field-required">
				<input type="checkbox" value="" id="eab-arf-new_additional_field-required" />
				<?php _e('Required?', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
			<?php if ( $rsvp_with_email ) { ?>
				<label for="eab-arf-new_additional_field-email_rsvp">
					<input type="checkbox" value="" id="eab-arf-new_additional_field-email_rsvp" />
					<?php _e('Add to RSVP with email?', Eab_EventsHub::TEXT_DOMAIN); ?>
				</label>
			<?php } ?>
			<button type="button" class="button-secondary" id="eab-arf-new_additional_field-add"><?php _e('Add', Eab_EventsHub::TEXT_DOMAIN); ?></button>
		</div>
	</div>
	<div class="eab-inside">
		<h4><?php _e('Options', Eab_EventsHub::TEXT_DOMAIN); ?></h4>
		<label for="eab-arf-additional_fields-options-export">
			<input type="hidden" name="eab-arf-additional_fields-options-export" value="" />
			<input type="checkbox" id="eab-arf-additional_fields-options-export" name="eab-arf-additional_fields-options-export" value="1" <?php checked(true, $this->_data->get_option('additional_fields-export')); ?> />
			<?php _e('Include additional fields in data export', Eab_EventsHub::TEXT_DOMAIN); ?>
		</label>
	</div>
</div>
<script id="eab-arf-additional_fields-template" type="text/template">
	<div class="eab-arf-field">
		<b>{{= label }}</b> <em><small>({{= type }})</small></em>
		<br />
		<?php echo esc_html('Required', Eab_EventsHub::TEXT_DOMAIN); ?>: <b>{{= required ? '<?php echo esc_js(__("Yes", Eab_EventsHub::TEXT_DOMAIN)); ?>' : '<?php echo esc_js(__("No", Eab_EventsHub::TEXT_DOMAIN)); ?>' }}</b>
		<?php if ( $rsvp_with_email ) { ?>
			<?php echo esc_html('Add to RSVP with email', Eab_EventsHub::TEXT_DOMAIN); ?>: <b>{{= email_rsvp ? '<?php echo esc_js(__("Yes", Eab_EventsHub::TEXT_DOMAIN)); ?>' : '<?php echo esc_js(__("No", Eab_EventsHub::TEXT_DOMAIN)); ?>' }}</b>
		<?php } ?>
		<input type="hidden" name="eab-arf-additional_fields[]" value="{{= escape(_value) }}" />
		<a href="#remove" class="eab-arf-additional_fields-remove"><?php echo esc_html('Remove', Eab_EventsHub::TEXT_DOMAIN); ?></a>
	</div>
</script>
<script>
(function ($) {
    
    var WPMU_UnderscoreSettingsOverride = {
        evaluate: /\{\{(.+?)\}\}/gim,
        interpolate: /\{\{=(.+?)\}\}/gim,
        escape: /\{\{-(.+?)\}\}/gim
    };

var tpl = $("#eab-arf-additional_fields-template").html();

function add_new_field () {
	var $new_fields = $("#eab-arf-new_additional_field").find("input,select"),
		$root = $("#eab-arf-additional_fields"),
		data = {}
	;
	$new_fields.each(function () {
		var $me = $(this),
			name = $me.attr("id").replace(/eab-arf-new_additional_field-/, ''),
			value = $me.is(":checkbox") ? $me.is(":checked") : $me.val()
		;
		data[name] = value;
	});
	data._value = JSON.stringify(data);
	$root.append( _.template( tpl, WPMU_UnderscoreSettingsOverride )( data ) );
	return false;
}

function remove_field () {
	var $me = $(this);
	$me.closest(".eab-arf-field").remove();
	return false;
}

$(function () {
	$(document).on("click", "#eab-arf-new_additional_field-add", add_new_field);
	$(document).on("click", ".eab-arf-additional_fields-remove", remove_field);
});

})(jQuery);
</script>
<style>
.eab-arf-field {
	border: 1px solid #ccc;
	border-radius: 3px;
	padding: 1em;
	margin-bottom: 1em;
	width: 40%;
}
.eab-arf-field .eab-arf-additional_fields-remove {
	display: block;
	float: right;
}
</style>
		<?php
	}
	
	function save_settings ($options) {
		if (empty($_POST['eab-arf-additional_fields'])) return $options;
		$data = stripslashes_deep($_POST['eab-arf-additional_fields']);
		$options['additional_fields'] = array();
		foreach ($data as $field) {
			if (!$field) continue;
			$field = json_decode(rawurldecode($field), true);
			if (!empty($field['label'])) $field['label'] = wp_strip_all_tags($field['label']);
			if (empty($field['label'])) continue;
			$options['additional_fields'][] = $field;
		}

		$options['additional_fields-export'] = !empty($_POST['eab-arf-additional_fields-options-export']);

		return $options;
	}

}

class Eab_Rsvps_Arf_Model {

	private static $_me;

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	private static function _instantiate () {
		if (self::$_me) return true;
		self::$_me = new Eab_Rsvps_Arf_Model;
	}



	public static function get ($user_id) {
		self::_instantiate();
		return self::$_me->_get_user_field_values($user_id);
	}

	public static function get_all () {
		self::_instantiate();
		return self::$_me->_get_fields();
	}

	public static function get_email_rsvp () {
		self::_instantiate();
		return self::$_me->_get_fields( true );
	}

	public static function get_clean_name ($label) {
		self::_instantiate();
		return self::$_me->_to_clean_name($label);
	}

	public static function get_macro ($label) {
		self::_instantiate();
		return self::$_me->_to_email_macro($label);
	}



	private function _get_user_field_values ($user_id) {
		$values = array();
		$fields = $this->_get_fields();
		if (empty($fields)) return $values;

		foreach ($fields as $field) {
			if (empty($field["id"])) continue;
			$key = $field["id"];
			$label = $field['label'];
			$values[$label] = get_user_meta($user_id, $key, true);
		}
		return $values;
	}

	private function _get_fields ( $email_rsvp = false ) {
		$fields = $this->_data->get_option('additional_fields');
		if (empty($fields)) return $fields;
		foreach ($fields as $idx => $field) {
			if ( $email_rsvp && empty( $field['email_rsvp'] ) ) {
				unset( $fields[ $idx ] );
				continue;
			}
			if (!empty($field['id'])) continue;
			$fields[$idx]['id'] = $this->_to_clean_name($field['label']);
		}
		return $fields;
	}

	private function _to_clean_name ($label) {
		return preg_replace('/[^-_a-z0-9]/', '', strtolower($label));
	}

	private function _to_email_macro ($label) {
		return 'FIELD_' . strtoupper($this->_to_clean_name($label));
	}
}

Eab_Rsvps_AdditionalRegistrationFields::serve();
