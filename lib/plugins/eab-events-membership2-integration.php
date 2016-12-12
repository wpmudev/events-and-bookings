<?php
/*
Plugin Name: Membership 2 Integration
Description: Allows Events+ to Integrate with our Membership 2 plugin, so that members can receive a alternative fee for paid events. <br /><b>Requires <a href="http://premium.wpmudev.org/project/membership">Membership 2 plugin</a>.</b>
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0.1
Author: WPMU DEV
Developer: Philipp Stracker
AddonType: Integration
*/

/*
Detail: Adds a field to the Event page so that you can select which membership(s) will be exempt from payments for a selected paid event.
*/

class Eab_Events_Membership2_Integration {

	/**
	 * Add-on specific data is saved in this post-meta element.
	 */
	const META_KEY = 'eab_membership2';

	/**
	 * Holds the Membership2 API instance that we use for communication with
	 * Membership2.
	 *
	 * @type MS_Controller_Api
	 */
	protected $api = null;

	/**
	 * Creates the Addon instance - this is the starting point of the Add-on.
	 *
	 * @since 1.0
	 */
	public static function serve() {
		static $Inst = null;

		if ( null === $Inst ) {
			$Inst = new Eab_Events_Membership2_Integration();
		}
	}

	/**
	 * Constructor initializes the Add-on.
	 *
	 * This function is called instantly when this file is loaded, before all
	 * plugins are loaded.
	 *
	 * @since 1.0
	 */
	private function __construct() {
		// ms_init is the Membership2 hook that tells us the API is ready.
		add_action( 'ms_init', array( $this, 'init' ) );

		// If the me_init hook is not fired then we will show a nag message.
		add_action( 'admin_notices', array( $this, 'show_nag' ) );
	}

	/**
	 * Hooks to the main plugin Events+
	 *
	 * This function is called by the WordPress action `ms_init`
	 *
	 * @since 1.0
	 */
	public function init( $api ) {
		// Okay, Membership2 is obviously active. Let's not annoy the admin...
		remove_action( 'admin_notices', array( $this, 'show_nag' ) );

		// Grab the reference to the API instance, so we can use it later.
		$this->api = $api;

		// Add a new metabox to the events to select discounted memberships.
		add_filter(
			'eab-event_meta-event_meta_box-after',
			array( $this, 'event_meta_box' )
		);

		// Save the discounted memberships details when an event is saved.
		add_action(
			'eab-event_meta-save_meta',
			array( $this, 'save_membership_meta' )
		);
		add_action(
			'eab-events-recurrent_event_child-save_meta',
			array( $this, 'save_membership_meta' )
		);

		add_filter(
			'eab-event-show_pay_note',
			array( $this, 'will_show_pay_note' ), 10, 2
		);
		add_filter(
			'eab-event-payment_status',
			array( $this, 'status' ), 10, 2
		);
		add_filter(
			'eab-payment-event_price',
			array( $this, 'get_event_price' ), 10, 2
		);
		add_filter(
			'eab-payment-event_price-for_user',
			array( $this, 'get_event_price_for_user' ), 10, 3
		);
	}

	/**
	 * Show a warning if the Add-on is activated but Membership2 is not active.
	 *
	 * @since 1.0
	 */
	public function show_nag() {
		printf(
			'<div class="error"><p>' .
			__( 'You need to install and activate the %sMembership 2%s plugin for the Membership 2 Integration to work', Eab_EventsHub::TEXT_DOMAIN ) .
			'</p></div>',
			'<a href="http://premium.wpmudev.org/project/membership">',
			'</a>'
		);
	}

	/**
	 * Updates the event-details meta box that is displayed in the event editor.
	 *
	 * We will add some new fields in the end of discounted memberships.
	 *
	 * @since  1.0
	 * @param  string $content The default HTML code of the event editor.
	 * @return string The modified HTML code which includes our meta box.
	 */
	public function event_meta_box( $content ) {
		global $post;
		$data = $this->get_infos( $post->ID );
		$memberships = $this->api->list_memberships( true );

		$close_class = 'closed';
		if ( 'auto-draft' == $post->post_status ) {
			$close_class = '';
		}

		ob_start();
		/*
		 * We only need one CSS style from the Membership2 plugin, so we
		 * copy-paste that relevant part here instead of loading the whole file.
		 */
		?>
		<style>
		.ms-membership-wrap label {
			width: 120px;
			display: inline-block;
		}
		.ms-membership-wrap input {
			width: 120px;
		}
		.ms-membership {
			display: inline-block;
			border-radius: 3px;
			color: #FFF;
			background: #888;
			padding: 1px 5px;
			font-size: 12px;
			height: 20px;
			line-height: 20px;
			margin-bottom: 1px;
			max-width: 120px;
			text-overflow: ellipsis;
			white-space: nowrap;
			overflow: hidden;
		}
		.eab-membership2-box .eab_meta_column_box {
			cursor: pointer;
		}
		.eab-membership2-box .handlediv:before {
			margin-top: -8px;
		}
		.js .meta-box-sortables .postbox .eab-membership2-box.closed .handlediv:before {
			content: '\f140';
		}
		.eab-membership2-box.closed .eab_meta_column_box {
			border-bottom: 0;
		}
		.eab-membership2-box.closed .misc-eab-section {
			display: none;
		}
		</style>
		<div class="eab_meta_box eab-membership2-box <?php echo esc_attr( $close_class ); ?>">
			<input type="hidden" name="event_membership2_meta" value="1" />
			<div class="eab_meta_column_box">
				<?php _e( 'Membership 2 Prices', Eab_EventsHub::TEXT_DOMAIN ); ?>
				<div class="handlediv eab-membership2-toggle"></div>
			</div>
			<div class="misc-eab-section">
				<?php foreach ( $memberships as $membership ) :
					$the_item = array();
					$the_price = '';
					$the_id = 'eab-m2-' . $membership->id;
					if ( isset( $data[$membership->id] ) ) {
						$the_item = $data[$membership->id];
						if ( ! is_array( $the_item ) ) { $the_item = array(); }
					}
					if ( $the_item['has_price'] ) {
						$the_price = $the_item['price'];
					}
					?>
					<div class="ms-membership-wrap">
						<label for="<?php echo esc_attr( $the_id ); ?>">
							<?php $membership->name_tag(); ?>
						</label>
						<input type="number"
							id="<?php echo esc_attr( $the_id ); ?>"
							name="eab_membership2[<?php echo $membership->id; ?>][price]"
							min="0"
							step="any"
							placeholder="<?php _e( 'Default Price', Eab_EventsHub::TEXT_DOMAIN ); ?>"
							value="<?php echo esc_attr( $the_price ); ?>"
							/>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		jQuery(function() {
			var box = jQuery( '.eab-membership2-box' ),
				inside = box.find( '.misc-eab-section' ),
				toggle = box.find( '.eab_meta_column_box' ),
				has_price = jQuery( '#incsub_event_paid' );

			function show_price_details() {
				if ( '1' === has_price.val() ) {
					box.show();
				} else {
					box.hide();
				}
			}

			function toggle_membership_prices() {
				box.toggleClass( 'closed' );
			}

			has_price.change( show_price_details );
			toggle.click( toggle_membership_prices );
			show_price_details();
		});
		</script>
		<?php
		$content .= ob_get_clean();

		return $content;
	}

	/**
	 * Save the membership-IDs to the event meta data.
	 *
	 * The value we save was entered by the form added via our custom meta-box
	 * self::event_meta_box() below.
	 *
	 * @since 1.0
	 */
	public function save_membership_meta( $post_id ) {
		if ( empty( $_POST['event_membership2_meta'] ) ) {
			return false;
		}

		$data = $this->get_infos( $_POST['eab_membership2'] );
		$this->set_infos( $post_id, $data );
	}

	/**
	 * Whether 'You havent paid for this event' note should be displayed.
	 *
	 * If user is a member and his membership is free then return false (i.e.
	 * don't show pay note).
	 * Otherwise return whatever sent here.
	 *
	 * @since  1.0
	 */
	public function will_show_pay_note( $show_pay_note, $event_id ) {
		$data = $this->get_infos( $event_id );
		$user = $this->api->get_current_member();

		foreach ( $data as $membership_id => $membership ) {
			if ( $user->has_membership( $membership_id ) ) {
				// The member has subscribed to this membership.

				if ( isset( $membership['has_price'] ) && 0 == $membership['price'] ) {
					// The membership has a custom price and custom price is 0.
					return false;
				}
			}
		}

		return $show_pay_note;
	}

	/**
	 * Modify payment status text if user is a member with sufficent level.
	 *
	 * @since  1.0
	 */
	public function status( $payment_status, $user_id ) {
		global $post;

		if ( 0 == $user_id ) { return $payment_status; }

		$data = $this->get_infos( $post->ID );
		$user = $this->api->get_member( $user_id );

		foreach ( $data as $membership_id => $membership ) {
			if ( $user->has_membership( $membership_id ) ) {
				// The member has subscribed to this membership.

				if ( $membership->has_price && 0 == $membership->price ) {
					// The membership has a custom price and custom price is 0.
					return 'Member';
				}
			}
		}

		return $payment_status;
	}

	/**
	 * Get the custom price for the specified event (checking for current user)
	 *
	 * @since  1.0
	 * @param  float $price Default price.
	 * @param  int $event_id Event-ID.
	 * @return float Custom price for current user.
	 */
	public function get_event_price( $price, $event_id ) {
		global $current_user;

		$custom_price = $this->get_event_price_for_user(
			$price,
			$event_id,
			$current_user->ID
		);

		if ( is_numeric( $custom_price ) ) {
			$price = $custom_price;
		}

		return $price;
	}

	/**
	 * Get the custom price for given event that is valid for a specific user.
	 *
	 * If the user is subscribed to multiple memberships then the lowest price
	 * is picked from all subscriptions.
	 *
	 * @since  1.0
	 * @param  float $price Default price.
	 * @param  int $event_id Event-ID.
	 * @param  int $user_id User-ID.
	 * @return float Custom price for current user.
	 */
	public function get_event_price_for_user( $price, $event_id, $user_id ) {
		$new_price = $price;

		if ( is_admin() ) {
			global $current_screen;
			$screen = $current_screen;
			if ( ! isset( $screen->base ) ) { $screen->base = ''; }
			if ( ! isset( $screen->post_type ) ) { $screen->post_type = ''; }

			if ( 'post' == $screen->base && 'incsub_event' == $screen->post_type ) {
				// An admin is currently editing the event:
				// Don't modify the price here!
				return $new_price;
			}
		}

		if ( is_user_logged_in() || defined( 'EAB_PROCESSING_PAYPAL_IPN' ) ) {
			$data = $this->get_infos( $event_id );
			$user = $this->api->get_member( $user_id );

			foreach ( $data as $membership_id => $membership ) {
				// Skip this membership if it does not have a custom price.
				if ( empty( $membership['has_price'] ) ) { continue; }

				if ( $user->has_membership( $membership_id ) ) {
					// The member has subscribed to this membership.

					if ( $membership['price'] < $new_price ) {
						// Choose the lowest price available.
						$new_price = $membership['price'];
					}
				}
			}
		}

		return $new_price;
	}


	// -------------------------------------------------------------------------
	// INTERNAL HELPER FUNCTIONS -----------------------------------------------
	// -------------------------------------------------------------------------


	/**
	 * Fetches the Discount details for a single event from the database.
	 *
	 * @since  1.0.0
	 * @internal
	 *
	 * @param  int|array $post_id The event-ID. Optionally this can also be an
	 *         array containing data to be sanitized.
	 * @return array An sanitized array containing the relevant details.
	 */
	private function get_infos( $post_id ) {
		$res = array();
		$memberships = $this->api->list_memberships( true );
		$defaults = array( 'price' => 0, 'has_price' => 0 );

		if ( is_numeric( $post_id ) ) {
			$meta = get_post_meta( $post_id, self::META_KEY, true );
			$calc_has_price = false;
		} elseif ( is_array( $post_id ) ) {
			$meta = $post_id;
			$calc_has_price = true;
		}

		if ( is_object( $meta ) ) { $meta = (array) $meta; }
		if ( ! is_array( $meta ) ) { $meta = array(); }

		foreach ( $memberships as $membership ) {
			$item = array();
			if ( isset( $meta[$membership->id] ) ) {
				$item = $meta[$membership->id];
			}
			$item = wp_parse_args( $item, $defaults );

			if ( $calc_has_price ) {
				$item['has_price'] = '' != $item['price'] ? 1 : 0;
			} else {
				$item['has_price'] = $item['has_price'] ? 1 : 0;
			}
			$item['price'] = max( 0, floatval( $item['price'] ) );

			$res[$membership->id] = $item;
		}

		return $res;
	}

	/**
	 * Saves the discount information of a specific event to the database.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $post_id [description]
	 * @param array $data Discount information in the same format that
	 *        get_infos() returns
	 */
	private function set_infos( $post_id, $data ) {
		update_post_meta( $post_id, self::META_KEY, $data );
	}
}

Eab_Events_Membership2_Integration::serve();