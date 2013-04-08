<?php


class Eab_Payment_MultiplePrices {

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Payment_MultiplePrices;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		//add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));
		add_action('admin_footer', array($this, 'load_admin_scripts'));
		
		//add_filter('eab-payment-event_price', array($this, 'get_event_prices')); // Model getter
		
		add_filter('eab-event-payment_forms', array($this, 'get_payment_forms'), 10, 2); // Template forms
		add_filter('eab-payment-event_price-for_user', array($this, 'get_user_price_selection'), 10, 3); // IPN response
		
		add_filter('eab-event_meta-event_price', array($this, 'get_event_price_metabox'), 10, 2); // Event meta
		add_filter('eab-event_meta-event_meta_box-after', array($this, 'get_event_price_metabox_template'), 10, 2); // Event meta template
		add_action('incsub_event_save_payments_meta', array($this, 'save_event_meta_tiers'));
	}

// ----- Metabox editing -----

	function load_admin_scripts() {
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if (!preg_match('/' . preg_quote(Eab_EventModel::POST_TYPE, '/') . '/', $screen->id)) return false; // Add on used pages
		}
		//wp_enqueue_script('jquery-multiselect',plugins_url('events-and-bookings/js/').'jquery.multiselect.min.js',array('jquery','jquery-ui-widget'));
		echo <<<EOJs
<script type="text/javascript">
(function ($) {

$(function () {
	var root = $("#eab-payment-multiple_prices-tiers"),
		trigger = $("#eab-payment-multiple_prices-add_tier"),
		old = $("#eab-payment-multiple_prices-singular"),
		template = $("#eab-payment-multiple_prices-template")
	;
	trigger.on("click", function () {
		console.log("click")
		if (old.length) old.hide();
		var target = root.find(".eab-payment-multiple_prices-tier:last"),
			markup = _.template(template.html(), {test:"test"})
		;
		(target.length ? target : root).append(markup);
		return false;
	});
	root.on("click", ".eab-payment-multiple_prices-remove_tier", function () {
		$(this).parents(".eab-payment-multiple_prices-tier").remove();
		return false;
	});
});
})(jQuery);
</script>
EOJs;
	}
	
	function get_event_price_metabox ($markup, $event_id) {
		$event = new Eab_EventModel($event_id);
		$price = $event->get_price();
		$markup = is_array($price) 
			? $this->_get_existing_tiers_meta_markup($price, $markup)
			: '<div id="eab-payment-multiple_prices-singular">' . $markup . '</div>'
		;
		
		return "<div id='eab-payment-multiple_prices-tiers'>{$markup} " . $this->_get_blank_tiers_meta_markup($event) . "</div>";
	}

	private function _get_existing_tiers_meta_markup ($tiers, $markup) {
		if (empty($tiers)) return $markup;
		$markup = '';
		foreach ($tiers as $tier) {
			$markup .= $this->_get_tier_meta_row($tier['label'], $tier['fee']);
		}
		return $markup;
	}

	private function _get_tier_meta_row ($label=false, $fee=false) {
		$fee = (float)$fee ? (float)$fee : '';
		$key = "fee-" . uniqid();
		return '<div class="eab-payment-multiple_prices-tier">' .
			'<input type="text" size="8" name="incsub_event_price_tier[' . $key . '][label]" value="' . esc_attr($label) . '" placeholder="Tier name" />' .
			'&nbsp;' .
			$this->_data->get_option("currency") .
			'<input type="text" size="4" name="incsub_event_price_tier[' . $key . '][fee]" value="' . esc_attr($fee) . '" placeholder="0.00" />' .
			' <a href="#remove-tier" class="eab-payment-multiple_prices-remove_tier">' . __('Remove', Eab_EventsHub::TEXT_DOMAIN) . '</a>' .
		'</div>';
	}

	private function _get_blank_tiers_meta_markup ($event) {
		return '<br /><input type="button" id="eab-payment-multiple_prices-add_tier" value="' . esc_attr(__('Add pricing tier', Eab_EventsHub::TEXT_DOMAIN)) . '" />';
	}

	function get_event_price_metabox_template ($markup) {
		$template = '
<script type="text/template" id="eab-payment-multiple_prices-template">
' . $this->_get_tier_meta_row() . '
</script>
		';
		return $markup . $template;
	}

	function save_event_meta_tiers ($post_id) {
		if (empty($_POST['incsub_event_price_tier'])) return false;
		$tiers = array();
		foreach ($_POST['incsub_event_price_tier'] as $tier) {
			$tiers[] = array(
				'label' => wp_strip_all_tags(stripslashes($tier['label'])),
				'fee' => (float)$tier['fee'],
			);
		}
		update_post_meta($post_id, 'incsub_event_fee', $tiers);
	}

// ----- Front-end display

	// Defunct, breaks payment forms and who knows what else...
	function get_event_prices ($price) {
		if (is_admin()) return $price;
		return is_array($price)
			? join(', ', array_map(create_function('$arg', 'return $arg["label"] . ": " . $arg["fee"];'), $price))
			: $price
		;
	}
	
	function get_payment_forms ($form, $event_id) {
		$event = new Eab_EventModel($event_id);
		$price = $event->get_price();
		if (!is_array($price)) return $form;

		$selection = '<select id="" name="amount">';
		foreach ($price as $tier) {
			$selection .= '<option value="' . (float)$tier['fee'] . '">' . 
				$tier['label'] . ": " . $this->_data->get_option("currency") . " " . $tier['fee'] .
			'</option>';
		}
		$selection .= '</select>';

		global $blog_id, $current_user;
		$content .= $this->_data->get_option('paypal_sandbox') 
			? '<form action="https://sandbox.paypal.com/cgi-bin/webscr" method="post">'
			: '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">'
		;
		$content .= '<input type="hidden" name="business" value="' . $this->_data->get_option('paypal_email') . '" />';
		$content .= '<input type="hidden" name="item_name" value="' . esc_attr($event->get_title()) . '" />';
		$content .= '<input type="hidden" name="item_number" value="' . $event->get_id() . '" />';
		$content .= '<input type="hidden" name="notify_url" value="' . 
			admin_url('admin-ajax.php?action=eab_paypal_ipn&blog_id=' . $blog_id . '&booking_id=' . $booking_id) .
		'" />';
		$content .= '<br />' . __('Please, select price tier', Eab_EventsHub::TEXT_DOMAIN) . ' ' . $selection;
		$content .= '<input type="hidden" name="return" value="' . get_permalink($event->get_id()) . '" />';
		$content .= '<input type="hidden" name="currency_code" value="' . $this->_data->get_option('currency') . '">';
		$content .= '<input type="hidden" name="cmd" value="_xclick" />';
		
		// Add multiple tickets
		$extra_attributes = apply_filters('eab-payment-paypal_tickets-extra_attributes', $extra_attributes, $event->get_id(), $booking_id);
		$content .= '' .// '<a href="#buy-tickets" class="eab-buy_tickets-trigger" style="display:none">' . __('Buy tickets', Eab_EventsHub::TEXT_DOMAIN) . '</a>' . 
			sprintf(
				//'<p class="eab-buy_tickets-target">' . __('I want to buy %s ticket(s)', Eab_EventsHub::TEXT_DOMAIN) . '</p>', 
				'<p>' . __('I want to buy %s ticket(s)', Eab_EventsHub::TEXT_DOMAIN) . '</p>', 
				'<input type="number" size="2" name="quantity" value="1" min="1" ' . $extra_attributes . ' />'
			)
		;
		
		$content .= '<input type="image" name="submit" border="0" src="https://www.paypal.com/en_US/i/btn/btn_paynow_SM.gif" alt="PayPal - The safer, easier way to pay online" />';
		$content .= '<img alt="" border="0" width="1" height="1" src="https://www.paypal.com/en_US/i/scr/pixel.gif" />';
		$content .= '</form>';
		return $content;
	}
	
	function get_user_price_selection ($price_meta, $event_id, $user_id) {
		return $price_meta;
	}
}
Eab_Payment_MultiplePrices::serve();