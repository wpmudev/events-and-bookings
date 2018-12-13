<?php

/**
 * New MP (3.0+) implementation
 */
class Eab_MP_Bridge {

	private $_data;
	
	// The Attribute title that will hold the date variations for recurring events
	const VARIATION_NAME = 'Event Variation';

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('mp_order/new_order', array($this, 'dispatch_mp_product_if_order_paid'), 10, 1);
		add_action('mp_order_order_paid', array($this, 'mp_product_order_paid'), 10, 1);
		add_action( 'mp_cart/after_remove_item', array( $this, 'remove_event_rsvp' ), 10, 2 );

		// Display
		add_filter('eab-event-payment_forms', array($this, 'process_event_payment_forms'), 10, 2);
        add_action('incsub_event_booking_yes', array($this, 'add_event_product_to_cart'), 10, 2);
        add_action('incsub_event_booking_maybe', array($this, 'add_event_product_to_cart'), 10, 2);
		add_filter('eab-events-event_details-price', array($this, 'show_product_price'), 10, 2);

		// Regular Events+ product selection
		add_filter('eab-event_meta-event_price', array($this, 'show_event_product_selection'), 10, 2);
		add_action('incsub_event_save_payments_meta', array($this, 'save_event_product_selection'));
		// Resync top-level/singular price on related Product update
		add_action('wp_insert_post', array($this, 'resync_marketpress_product_price'), 10, 2);

		// Recurring events
		add_action('eab-events-recurring_instances-deleted', array($this, 'thrash_old_product_variations')); // Thrash old variations
		add_action('eab-events-recurrent_event_child-save_meta', array($this, 'save_event_product_variations')); // Spawn variations
		
        // Create the Product variations for the Recurring Event dates
		add_action( 'eab-events-spawn_recurring_instances-after', array( $this, 'save_recurring_child_event_product_variations' ), 10, 2 );

		// Archiving
		add_action('eab-scheduler-event_archived', array($this, 'archived_event_mp_cleanup'));
                add_action( 'eab-rsvps-button-no', array( &$this, 'eab_mp_variation_check' ), 99, 2 );
                
        // When cancelling a paid Booking of an Event, we need to cancel the order too
		add_action( 'eab-rsvp_before_cancel_payment', array( $this, 'cancel_event_order' ), 10, 2 );
	}

	/**
	 * Suspend product and variations on event archival.
	 */
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

			// Clean up all the variations too
			$query = new WP_Query(array(
				'post_type'	=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
				'posts_per_page' => -1,
				'post_parent' => $linked_product_id,
				'meta_query' => array(array(
					'key' => 'sku',
					'value' => $event_id
				)),
				'fields' => 'ids'
			));
			if (!empty($query->posts)) foreach ($query->posts as $variation) {
                            if(isset($variation->ID)) {
				wp_delete_post($variation->ID, false);
                            }
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

	private function _is_old_mp () {
		if (!$this->_is_mp_present()) return false;
		if (!defined('MP_VERSION')) return false;

		return version_compare(MP_VERSION, '3.0', '>=');
	}

	/**
	 * Returns properly formatted product price for Event on the front end.
	 */
	function show_product_price ( $price, $event_id ) {

		if ( ! $this->_is_mp_present() ){
			return $price;
		}
		
		$linked_product_id = get_post_meta($event_id, 'eab_product_id', true);

		if ( ! $linked_product_id ) {
			return $price;
		}

		return mp_product_price( false, $linked_product_id, false );

		/*
		* Since the `eab_product_id` meta contains the Product Variation for the Recurring Event Date,
		* we don't need to separate recurring events from regular ones. So commenting out
		* the following part and added the part above this comment
		*/

		/*

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

			$query = new WP_Query(array(
				'post_type'	=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
				'posts_per_page' => 1,
				'post_parent' => $linked_product_id,
				'meta_query' => array(array(
					'key' => 'sku',
					'value' => $event_id
				)),
				'fields' => 'ids'
			));

			$variation_id = !empty($query->posts[0]) ? $query->posts[0] : false;
			if (empty($variation_id)) return $price;

			$raw_price = MP_Product::get_variation_meta($variation_id, 'regular_price');
			if (!$raw_price) return $price;
			

			//$product = new MP_Product($linked_product_id);
			//if ($product->on_sale()) {

			//}

			// Sales test
			//$is_sale = MP_Product::get_variation_meta($variation_id, 'price');
			//if ($is_sale) {
			//	$sales = maybe_unserialize(get_post_meta($linked_product_id, 'mp_sale_price', true));
			//	if (!empty($sales) && !empty($sales[$price_id])) $raw_price = $sales[$price_id];
			//}
			// End sales test


			return apply_filters('mp_product/display_price', '<span class="mp_product_price">' . mp_format_currency('', $raw_price) . '</span>', $raw_price, $linked_product_id);
		}
		return $price; // This won't happen, but whatever
		*/
	}

	/**
	 * Overrides "Fee" portion of the Events editor dialog.
	 */
	function show_event_product_selection ($fee, $event_id) {
		if (!$this->_is_mp_present()) return $fee;

		$out = '';
		$category_id = $this->_data->get_option('payment-ppvp-category');
		$query_args = array(
			'post_type' => MP_Product::get_post_type(),
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

		$query = new WP_Query(array(
			'post_type'	=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
			'posts_per_page' => -1,
			'post_parent' => $product_id,
			'fields' => 'ids'
		));
		if (!empty($query->posts)) foreach ($query->posts as $variation) {
                    if(isset($variation->ID)) {
			wp_delete_post($variation->ID, true);
                    }
		}

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


		update_post_meta( $product_id, 'mp_var_name', $meta );
		update_post_meta( $product_id, 'mp_sku', $sku );
		update_post_meta( $product_id, 'mp_price', $price );
		update_post_meta( $product_id, 'has_variations', true );

		do_action('eab-mp-variation-meta', $product_id, $max, $instance_id, $event->is_recurring_child(), $unset_first);
        
        /*
		// Let's get the variations going
		// Actually this will re-create variations for Parent Product for each recurrent Event created.
		// It causes resource usage when it could be avaided, as on each run it deletes previous variations.
		// It also makes it hard to set proper Product Variation to Recurring Event
		if (class_exists('MP_Installer')) {

			// Clean up all the variations first
			$query = new WP_Query(array(
				'post_type'	=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
				'posts_per_page' => -1,
				'post_parent' => $product_id
			));
			if (!empty($query->posts)) foreach ($query->posts as $variation) {
                            if(isset($variation->ID)) {
				wp_delete_post($variation->ID, true);
                            }
			}


			// Import new ones
			$mpi = MP_Installer::get_instance();
			$mpi->product_variations_transition($product_id, 'external');
		}
		*/
	}
	
	public function save_recurring_child_event_product_variations( $old_post_ids, $new_post_ids ) {

		if ( ! empty( $new_post_ids ) && class_exists( 'MP_Installer' ) ) {

			$product_id 		= false;
			$parent_id 			= false;
			$cleared_variations	= false;

			foreach ( $new_post_ids as $event_id ) {

				if ( ! $parent_id ) {
					$parent_id 			= get_post( $event_id )->post_parent;
					$product_id 		= get_post_meta( $parent_id, 'eab_product_id', true );
				}

				if ( ! $parent_id || ! $product_id ) {
					continue;
				}

				if ( ! $cleared_variations ) {
					// Clean up all the variations first
					$query 			= new WP_Query(array(
						'post_type'			=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
						'posts_per_page' 	=> -1,
						'post_parent' 		=> $product_id
					));

					if (!empty($query->posts)) foreach ($query->posts as $variation) {
						wp_delete_post($variation->ID, true);
					}

					$cleared_variations = true;

				}

				if ( $product_id ) {

					$atts = array(
						'event_id' 				=> $event_id,
						'parent_product_id' 	=> $product_id
					);

					self::create_product_variation( $atts );

				}				

			}

		}

	}


	public static function create_product_variation( $atts ) {

		$variation_tax 		= MP_Products_Screen::maybe_create_attribute( 'product_attr_' . self::VARIATION_NAME, self::VARIATION_NAME );
		$product_id 		= (int) $atts['parent_product_id'];
		$event_id 			= (int) $atts['event_id'];
		$event 				= new Eab_EventModel( get_post( $event_id ) );
		$variation_term 	= date_i18n( get_option( "date_format" ), $event->get_start_timestamp() );
		$tid 				= null;

		// Craete the Variation ( Product )
		$product_title   	= get_the_title( $product_id );
		$product_content 	= get_the_content( $product_id );
		$variation_args 	= array(
			'post_title'   => $product_title,
			'post_content' => $product_content,
			'post_status'  => 'publish',
			'post_type'    => MP_Product::get_variations_post_type(),
			'post_parent'  => $product_id,
		);  
		$variation_id = wp_insert_post( $variation_args );

		// Add meta to new Variation
		$meta = get_post_meta( $product_id );

		foreach ( $meta as $key => $value ) {
			if ( is_array( $value ) ) {
				$meta[ $key ] = $value[0];
			}
		}


		$variation_metas = array(
			'name'                       => $variation_term, //mp_get_post_value( 'post_title' ),
			'sku'                        => $meta['sku'],
			'inventory_tracking'         => $meta['inventory_tracking'],
			'inv_out_of_stock_purchase'  => 0,
			'file_url'                   => $meta['file_url'],
			'external_url'               => $meta['external_url'],
			'regular_price'              => $meta['regular_price'],
			'sale_price_amount'          => $meta['sale_price'],
			'has_sale'                   => $meta['has_sale'],
			'special_tax_rate'           => $meta['special_tax_rate'],
			'description'                => $product_content,
			'sale_price_start_date'      => '',
			'sale_price_end_date'        => '',
			'sale_price'                 => '', //array - to do
			'weight'                     => '', //array - to do
			'weight_pounds'              => '',
			'weight_ounces'              => '',
			'charge_shipping'            => $meta['charge_shipping'],
			'charge_tax'                 => $meta['charge_tax'],
			'weight_extra_shipping_cost' => $meta['weight_extra_shipping_cost'],
		);

		/* Add default post metas for variation */
		foreach ( $variation_metas as $meta_key => $meta_value ) {
			update_post_meta( $variation_id, $meta_key, sanitize_text_field( $meta_value ) );
		}

		/* Set parent thumbnail as default thumbnail for the variation */
		$post_thumbnail = get_post_thumbnail_id( $product_id );
		$variation_thumbnail = get_post_thumbnail_id( $variation_id );
		if ( is_numeric( $post_thumbnail ) && ! is_numeric( $variation_thumbnail ) ) {
			update_post_meta( $variation_id, 'mp_product_images', $post_thumbnail );
			set_post_thumbnail( $variation_id, $post_thumbnail );
		}

		$slug = sanitize_title( $variation_term );

		if ( ! term_exists( $slug, $variation_tax ) ) {
			$tid = wp_insert_term( $variation_term, $variation_tax, array(
				'slug' => $slug,
			) );			
		}
		else {
			$tid = get_term_by( 'slug', $slug, $variation_tax, ARRAY_A );
		}

		if ( ! is_null( $tid ) ) {
			wp_set_post_terms( $variation_id, $tid['term_id'], $variation_tax, true );
		}

		update_post_meta( $event_id, 'eab_product_id', $variation_id );

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
	function add_event_product_to_cart($event_id, $user_id) {

		if ( 
			! $this->_is_mp_present() || 
			! apply_filters( 'incsub_event_add_event_product_to_cart', true, $event_id, $user_id ) )
		{
			return;
		}
		
		$event = new Eab_EventModel(get_post($event_id));
		$recurring = $event->is_recurring_child();		

		/* 
		* Now recurring event have the same post meta as regular events, eab_product_id, where they store the Variation Product id for tha event date
		*/
		/*
		$product_id = false;
		
		if ($recurring) {
			$parent_product_id = get_post_meta($recurring, 'eab_product_id', true);
			$query = new WP_Query(array(
				'post_type'	=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
				'posts_per_page' => 1,
				'post_parent' => $parent_product_id,
				'meta_query' => array(array(
					'key' => 'sku',
					'value' => $event_id
				)),
				'fields' => 'ids'
			));
			$product_id = !empty($query->posts[0]) ? $query->posts[0] : false;
		} else {
			$product_id = get_post_meta($event_id, 'eab_product_id', true);
		}
		*/
		
		$product_id = get_post_meta($event_id, 'eab_product_id', true);

		if ( ! $product_id && $recurring ) {

			$parent_product_id = get_post_meta( $recurring, 'eab_product_id', true );
			$query = new WP_Query( array(
				'post_type'	=> apply_filters( 'mp_product_variation_post_type', 'mp_product_variation' ),
				'posts_per_page' => 1,
				'post_parent' => $parent_product_id,
				'meta_query' => array( array(
					'key' => 'sku',
					'value' => $event_id
				)),
				'fields' => 'ids'
			));

			$product_id = ! empty( $query->posts[0] ) ? $query->posts[0] : false;
		}

		if (!$product_id) return;

		$cart = MP_Cart::get_instance();
		$items = $cart->get_items();
		if (is_array($items) && false === array_search($product_id, array_keys($items))) {
			// Only add once, not if it's already in the cart
                        $product = new MP_Product( $product_id );
                        if( $product->has_variations() ) {
                            $cart->add_item( $_POST['action_variation'] );
                        }else{
                            $cart->add_item( $product_id );
                        }
		}

	}
        
    function process_event_payment_forms ($form, $event_id) {
		$product_id = get_post_meta($event_id, 'eab_product_id', true);
		if( !isset( $product_id ) || empty( $product_id ) ) return $form;
		if (!$this->_is_mp_present()) return $form;
		return '<p><a href="' . esc_url(mp_cart_link(false, true)) . '">' . __('Click here to purchase your ticket', Eab_EventsHub::TEXT_DOMAIN) . '</a></p>';
	}

	function dispatch_mp_product_if_order_paid ($order) {
		if ('mp_order' != $order->post_type || ! in_array( $order->post_status, array( 'order_shipped', 'order_paid' ) ) ) return false;
		$this->mp_product_order_paid($order);
	}

	/**
	 * Order is completed = payment done.
	 * Update event booking meta to indicate this, for Event-related Products.
	 */
	function mp_product_order_paid ($order) {
		$user_id = $order->post_author;
		$total_payment = $event_ids = array();

		$cart_info = is_object($order) && is_callable(array($order, 'get_cart'))
			? $order->get_cart()->get_items()
			: (isset($order->mp_cart_info) ? $order->mp_cart_info : array())
		;
                
		if (is_array($cart_info) && count($cart_info)) foreach($cart_info as $cart_id => $count) {
			$event_id = $product_id = false;

			$event_id = $this->order_to_event_id($cart_id);
			if ($this->_is_product_id($cart_id)) {
				$product_id = $cart_id;
			} else {
				$variation = get_post($cart_id);
				$product_id = $variation->post_parent;
			}

			// No variations, go for quantities
			if (!$event_id) continue;
			$event_ids[] = $event_id;
			$tickets = !empty($total_payment[$event_id]) ? $total_payment[$event_id] : array(
				'count' => 0,
				'order_id' => $order->ID,
				'product_id' => $product_id,
			);
			$tickets['count'] += (int)$count;
			$total_payment[$event_id] = $tickets;
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
	 * Check whether we're dealing with a product or variation
	 *
	 * @param int $cart_id Cart item ID
	 *
	 * @return bool
	 */
	private function _is_product_id ($cart_id) {
		$post = get_post($cart_id);
		return MP_Product::get_post_type() === $post->post_type;
	}

	/**
	 * Maps order to a related Event ID, if possible.
	 */
	function order_to_event_id ($cart_id) {
		$event_id = false;

		if ($this->_is_product_id($cart_id)) {
			$event_id = get_post_meta($cart_id, 'eab_event_id', true);
		} else {
			// this is a variation, it has event ID in SKU
                        $cart = get_post($cart_id);
			$event_id = get_post_meta( $cart->post_parent, 'eab_event_id', true );
		}

		return $event_id;
	}

	/**
	 * Quickly scans product for default (first-available) price.
	 */
	private function _get_quick_product_price ($product_id) {
            
            $product = new MP_Product( $product_id );
            if( $product->has_variations() ) {
                $variation_price = $product->get_price();
                $price = mp_format_currency( '', $variation_price['highest'] );
                $price = substr( $price, 6 );
            }else{
                $meta = get_post_custom($product_id);
		$mp_price = !empty($meta['mp_price'][0]) ? maybe_unserialize($meta['mp_price'][0]) : array();
		if (empty($mp_price)) {
			// MP3.0 price format
			$mp_price = !empty($meta['regular_price']) ? $meta['regular_price'] : array();
		}
                if(is_array($mp_price)) {
                    rsort($mp_price, SORT_NUMERIC);
                }
		$price = !empty($mp_price[0]) ? (float)$mp_price[0] : false;
            }
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
        
        public function eab_mp_variation_check( $content, $event_id ) {
            $event = new Eab_EventModel( get_post( $event_id ) );
            $product_id = get_post_meta( $event_id, 'eab_product_id', true );
            if( isset( $product_id ) && (int) $product_id > 0 ){
                $product = new MP_Product( $product_id );
                if( $product->has_variations() ) {
                    $variations = $product->get_variations();
                    
                    $html = '<select name="action_variation" style="width: 100%; margin-bottom: 10px;">';
                    foreach( $variations as $variation ){
                        $price = $variation->get_price();
                        $html .= '<option value="' . $variation->ID . '">' . $variation->get_meta( 'name' ) . ' - ' . mp_format_currency( '', $price['regular'] ) . '</option>';
                    }
                    $html .= '</select><br>';
                    $content = $html . $content;
                }
            }
            return $content;
        }
		
		public function remove_event_rsvp( $item_id, $site_id )
		{
			$event_id = $this->order_to_event_id( $item_id );
			$eab = events_and_bookings();
			$eab->update_rsvp_per_event( $event_id, get_current_user_id(), 'no' );
			do_action( 'incsub_event_booking_no', $event_id, get_current_user_id() );
			$eab->recount_bookings( $event_id );
		}
		
	// Sets the order status to `order_received`
	public function cancel_event_order( $event = null, $user_id = null ) {

		if ( is_null( $event ) || ! $event instanceof Eab_EventModel || is_null( $user_id ) ) {
			return false;
		}

		$booking_id 		= $event->get_user_booking_id( $user_id );
		$booking_meta 		= unserialize( $event->get_booking_paid( $booking_id ) );
		$error_msg          = __( 'The order status could not be updated due to unexpected error. Please try again.', Eab_EventsHub::TEXT_DOMAIN );

		// If no order id set, then no need to do anything here
		if ( ! isset( $booking_meta['order_id'] ) ) {
			return true;
		}

		$order_id = (int) $booking_meta['order_id'];
		
		$result           = wp_update_post( array(
			'ID'          => $order_id,
			'post_status' => 'order_received',
		), true );

		if ( is_wp_error( $result ) ) {
			wp_die( $error_msg );
		}

		return true;

	}
}
