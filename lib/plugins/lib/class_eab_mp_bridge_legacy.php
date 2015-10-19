<?php

/**
 * Legacy MarketPress implementation
 * For versions that actually work
 */
class Eab_MP_Bridge_Legacy {
	
	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('mp_new_order', array($this, 'dispatch_mp_product_if_order_paid'));
		// Payment processing, bind to events
		add_action('order_received_to_order_paid', array($this, 'mp_product_order_paid'));

		// Display
		add_filter('eab-event-payment_forms', array($this, 'process_event_payment_forms'), 10, 2);
		add_filter('eab-events-event_details-price', array($this, 'show_product_price'), 10, 2);

		// Regular Events+ product selection
		add_filter('eab-event_meta-event_price', array($this, 'show_event_product_selection'), 10, 2);
		add_action('incsub_event_save_payments_meta', array($this, 'save_event_product_selection'));
		// Resync top-level/singular price on related Product update
		add_action('wp_insert_post', array($this, 'resync_marketpress_product_price'), 10, 2);

		// Recurring events
		add_action('eab-events-recurring_instances-deleted', array($this, 'thrash_old_product_variations')); // Thrash old variations
		add_action('eab-events-recurrent_event_child-save_meta', array($this, 'save_event_product_variations')); // Spawn variations

		// Archiving
		add_action('eab-scheduler-event_archived', array($this, 'archived_event_mp_cleanup'));
	}

	function archived_event_mp_cleanup ($event) {
		if (!is_object($event) || !method_exists($event, 'get_id')) return false; // Invalid parameter
		$parent_event_id = $event->is_recurring_child();
		if ($parent_event_id) { 
			// Recurring event instance
			$linked_product_id = get_post_meta($parent_event_id, 'eab_product_id', true);
			if (!$linked_product_id) return false;
			
			$meta = get_post_custom($linked_product_id);
			$skus = !empty($meta['mp_sku'][0]) ? maybe_unserialize($meta['mp_sku'][0]) : false;
			if (empty($skus)) return false;

			$prices = !empty($meta['mp_price'][0]) ? maybe_unserialize($meta['mp_price'][0]) : false;
			if (empty($prices)) return false;

			$var_names = !empty($meta['mp_var_name'][0]) ? maybe_unserialize($meta['mp_var_name'][0]) : false;
			if (empty($var_names)) return false;
			
			$event_id = $event->get_id();

			foreach ($skus as $id => $sku) {
				if ($sku != $event_id) continue;

				unset($skus[$id]);
				if (!empty($prices[$id])) unset($prices[$id]);
				if (!empty($var_names[$id])) unset($var_names[$id]);

				update_post_meta($linked_product_id, 'mp_sku', $skus);
				update_post_meta($linked_product_id, 'mp_price', $prices);
				update_post_meta($linked_product_id, 'mp_var_name', $var_names);

				break;
			}
		} else {
			// Regular event
			$linked_product_id = get_post_meta($event_id, 'eab_product_id', true);
			if (!$linked_product_id) return false;

			$product = get_post($linked_product_id, 'ARRAY_A');
			$product['post_status'] = 'draft';
			wp_update_post($product);
		}
	}

	private function _is_mp_present () {
		return class_exists('MarketPress');
	}

	/**
	 * Returns properly formatted product price for Event on the front end.
	 */
	function show_product_price ($price, $event_id) {
		if (!$this->_is_mp_present()) return $price;

		$event = new Eab_EventModel(get_post($event_id));
		$parent_event_id = $event->is_recurring_child();
		if (!$parent_event_id) {
			$linked_product_id = get_post_meta($event_id, 'eab_product_id', true);
			if (!$linked_product_id) return $price;
			
			return mp_product_price(false, $linked_product_id, false);
		} else {
			// Recurring event child. Figure out parent-linked product ID and appropriate SKU
			$linked_product_id = get_post_meta($parent_event_id, 'eab_product_id', true);
			if (!$linked_product_id) return $price;

			$skus = maybe_unserialize(get_post_meta($linked_product_id, 'mp_sku', true));
			if (empty($skus)) return $price;

			$price_id = in_array($event_id, $skus) ? array_search($event_id, $skus) : false;
			if (false === $price_id) return $price;
			
			$prices = maybe_unserialize(get_post_meta($linked_product_id, 'mp_price', true));
			if (empty($prices)) return $price;
			
			$raw_price = isset($prices[$price_id]) ? $prices[$price_id] : false;
			if (!$raw_price) return $price;

			// Sales test
			$is_sale = get_post_meta($linked_product_id, 'mp_is_sale', true);
			if ($is_sale) {
				$sales = maybe_unserialize(get_post_meta($linked_product_id, 'mp_sale_price', true));
				if (!empty($sales) && !empty($sales[$price_id])) $raw_price = $sales[$price_id];
			}
			// End sales test

			global $mp;
			return apply_filters('mp_product_price_tag', '<span class="mp_product_price">' . $mp->format_currency('', $raw_price) . '</span>', $linked_product_id, '');
		}
		return $price; // This won't happen, but whatever
	}

	/**
	 * Overrides "Fee" portion of the Events editor dialog.
	 */
	function show_event_product_selection ($fee, $event_id) {
		if (!$this->_is_mp_present()) return $fee;

		$out = '';
		$category_id = $this->_data->get_option('payment-ppvp-category');
		$query_args = array(
			'post_type' => 'product',
			'posts_per_page' => -1,
		);
		if ($category_id) {
			$query_args['tax_query'] = array(array(
				'taxonomy' => 'product_category',
				'field' => 'id',
				'terms' => $category_id,
			));
		}
		$query = new WP_Query($query_args);
		$linked_product_id = get_post_meta($event_id, 'eab_product_id', true);

		if (empty($query->posts) && !$linked_product_id) return $fee;
		else if (empty($query->posts)) $query->posts = array(get_post($linked_product_id));

		if (!$linked_product_id) {
			$out = $fee . '<br />' . __('... or, please select a Product', Eab_EventsHub::TEXT_DOMAIN);
		} else {
			$out = __('Select a pricing Product', Eab_EventsHub::TEXT_DOMAIN);
		}

		$out .= ':<br /><select name="eab_e2mp_product_id"><option value=""></option>';
		if (!empty($query->posts)) foreach ($query->posts as $product) {
			$linked_event_id = get_post_meta($product->ID, 'eab_event_id', true);
			if (!empty($linked_event_id) && $linked_event_id != $event_id) continue;
			$out .= '<option value="' . $product->ID . '" ' . selected($linked_product_id, $product->ID, false) . ">{$product->post_title}</option>";
		}
		$out .= '</select>';
		return $out;
	}
	
	/**
	 * Saves initial related Product selection, for singular/top-level events.
	 */
	function save_event_product_selection ($event_id) {
		//if (empty($_POST['eab_e2mp_product_id'])) return false;
		$product_id = !empty($_POST['eab_e2mp_product_id']) ? $_POST['eab_e2mp_product_id'] : false;
		$this->_establish_relation($event_id, $product_id);
	}

	/**
	 * Trashes Product variations for recurring Event instances, when the recurring instances get dropped.
	 */
	function thrash_old_product_variations ($event_id) {
		$product_id = !empty($_POST['eab_e2mp_product_id']) ? $_POST['eab_e2mp_product_id'] : false;
		$price = $this->_get_quick_product_price($product_id);

		delete_post_meta($product_id, 'mp_var_name');
		delete_post_meta($product_id, 'mp_sku');
		delete_post_meta($product_id, 'mp_price');

		// Add initial product price, so we have something to work with
		add_post_meta($product_id, 'mp_var_name', array(''));
		add_post_meta($product_id, 'mp_sku', array(0));
		add_post_meta($product_id, 'mp_price', array($price));

		add_action('eab-mp-variation-thrash', $event_id, $product_id);
	}

	/**
	 * Spawns instance variations for Product, when recurring instance is created.
	 */
	function save_event_product_variations ($instance_id) {
		$product_id = !empty($_POST['eab_e2mp_product_id']) ? $_POST['eab_e2mp_product_id'] : false;

		$event = new Eab_EventModel(get_post($instance_id));
		$quick_price = $this->_get_quick_product_price($product_id);

		$meta = get_post_meta($product_id, 'mp_var_name', true);
		if (!$meta || !is_array($meta)) {
			/*
			add_post_meta($product_id, 'mp_var_name', array(''));
			add_post_meta($product_id, 'mp_sku', array(0));
			add_post_meta($product_id, 'mp_price', array(0));
			$meta = array(0);
			*/
			$meta = array();
		}

		$max = count($meta);
		$meta[$max] = date_i18n(get_option("date_format"), $event->get_start_timestamp());
		
		$sku = get_post_meta($product_id, 'mp_sku', true);
		$sku[$max] = $instance_id;
		
		$price = get_post_meta($product_id, 'mp_price', true);
		$price[$max] = $quick_price;

		$unset_first = false;
		if (empty($meta[0]) && empty($sku[0])) {
			unset($meta[0]);
			$meta = array_values($meta);

			unset($sku[0]);
			$sku = array_values($sku);

			unset($price[0]);
			$price = array_values($price);

			$unset_first = true;
		}


		update_post_meta($product_id, 'mp_var_name', $meta);
		update_post_meta($product_id, 'mp_sku', $sku);
		update_post_meta($product_id, 'mp_price', $price);

		do_action('eab-mp-variation-meta', $product_id, $max, $instance_id, $event->is_recurring_child(), $unset_first);

		// Add a download link, so that app will be a digital product
		//$file = get_post_meta($product_id, 'mp_file', true);
		//if ( !$file ) add_post_meta( $product_id, 'mp_file', get_permalink( $instance_id ) );
	}

	/**
	 * Resyncs singular/top-level event price on linked Product update.
	 */
	function resync_marketpress_product_price ($post_id, $post=null) {
		if (defined('DOING_AJAX')) return false;
		if (!$post || empty($post->post_type)) return false;
		if ('product' != $post->post_type) return false;

		$event_id = $this->is_event_ticket($post_id);
		if (!$event_id) return false;

		$price = $this->_get_quick_product_price($product_id);
		update_post_meta($event_id, 'incsub_event_fee', $price);
	}

	/**
	 * Returns related Product instead of standard payment forms for appropriate events.
	 */
	function process_event_payment_forms ($form, $event_id) {
		if (!$this->_is_mp_present()) return $form;
		
		$event = new Eab_EventModel(get_post($event_id));
		$recurring = $event->is_recurring_child();
		$event_id = $recurring ? $recurring : $event_id;
		$product_id = get_post_meta($event_id, 'eab_product_id', true);
		if (!$product_id) return $form;

		return strip_shortcodes(mp_product(false, $product_id, false, 'excerpt'));
	}

	function dispatch_mp_product_if_order_paid ($order) {
		if ('mp_order' != $order->post_type || 'order_paid' != $order->post_status) return false;
		$this->mp_product_order_paid($order);
	}

	/**
	 * Order is completed = payment done.
	 * Update event booking meta to indicate this, for Event-related Products.
	 */
	function mp_product_order_paid ($order) {
		$user_id = $order->post_author;
		$total_payment = $event_ids = array();
		if (is_array($order->mp_cart_info) && count($order->mp_cart_info)) foreach($order->mp_cart_info as $product_id => $variations) {
			foreach ($variations as $variation) {
				$event_id = $this->order_to_event_id($product_id, $variation);
				if (!$event_id) continue;
				$event_ids[] = $event_id;
				$tickets = !empty($total_payment[$event_id]) ? $total_payment[$event_id] : array(
					'count' => 0,
					'order_id' => $order->ID,
					'product_id' => $product_id,
				);
				$tickets['count'] += (int)$variation['quantity'];
				$total_payment[$event_id] = $tickets;
			}
		}

		$user_bookings = array();
		$event_ids = join(',', array_unique($event_ids));

		global $wpdb;
		$table = Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE);
		$bookings = $wpdb->get_results($wpdb->prepare("SELECT event_id,id FROM {$table} WHERE user_id=%d AND event_id IN ({$event_ids})", $user_id));
		foreach ($bookings as $booking) {
			$user_bookings[$booking->event_id] = $booking->id;
		}

		foreach ($total_payment as $event_id => $booking_info) {
			if (empty($user_bookings[$event_id])) {
				//continue; // Possibly paying for wrong variation!
				// Well, the guy apparently paid... so book him
				$wpdb->query(
				    $wpdb->prepare("INSERT INTO ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." VALUES(null, %d, %d, NOW(), 'yes') ON DUPLICATE KEY UPDATE `status` = 'yes';", $event_id, $user_id)
				);
				$user_bookings[$event_id] = $wpdb->insert_id;
				// --todo: Add to BP activity stream
				do_action( 'incsub_event_booking_yes', $event_id, $user_id );
				// End booking extras
			}
			$booking_id = $user_bookings[$event_id];
			Eab_EventModel::update_booking_meta($booking_id, 'booking_transaction_key', serialize($booking_info));
			Eab_EventModel::update_booking_meta($booking_id, 'booking_ticket_count', $booking_info['count']);
		}
	}

	/**
	 * Checks for top-level/singular event relationship.
	 */
	function is_event_ticket ($product_id) {
		return get_post_meta($product_id, 'eab_event_id', true);
	}

	/**
	 * Maps order to a related Event ID, if possible.
	 */
	function order_to_event_id ($product_id, $variation=array()) {
		$event_id = get_post_meta($product_id, 'eab_event_id', true);
		if (!$event_id) return false;

		$event = new Eab_EventModel(get_post($event_id));
		if (!$event->is_recurring()) return $event_id;

		// Event is recurring, check SKUs
		return !empty($variation['SKU'])
			? $variation['SKU']
			: false
		;
	}

	/**
	 * Quickly scans product for default (first-available) price.
	 */
	private function _get_quick_product_price ($product_id) {
		$meta = get_post_custom($product_id);
		$mp_price = !empty($meta['mp_price'][0]) ? maybe_unserialize($meta['mp_price'][0]) : array();
		rsort($mp_price, SORT_NUMERIC);
		$price = !empty($mp_price[0]) ? (float)$mp_price[0] : false;
		return $price;
	}

	/**
	 * Establishes Product-to-Event relationship, makes sure it's unique.
	 */
	private function _establish_relation ($event_id, $product_id) {
		if (!$event_id) return false;
		$old_product_id = get_post_meta($event_id, 'eab_product_id', true);
		$price = $product_id 
			? $this->_get_quick_product_price($product_id) 
			: false
		;

		if ($product_id) {
			// Cross-link
			// 1. Ensure uniqueness
			global $wpdb;
			$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE (meta_key='eab_product_id' AND meta_value='{$product_id}') OR (meta_key='eab_event_id' AND meta_value='{$event_id}')");
			// So after this is done, drop any caching for current event
			clean_post_cache($event_id);

			// Set up cross-linking
			update_post_meta($event_id, 'eab_product_id', $product_id);
			update_post_meta($product_id, 'eab_event_id', $event_id);
			update_post_meta($event_id, 'incsub_event_fee', $price);
		} else if ($old_product_id) {
			// Break cross-linking
			update_post_meta($event_id, 'eab_product_id', $product_id);
			update_post_meta($old_product_id, 'eab_event_id', false);
		}
	}

}
