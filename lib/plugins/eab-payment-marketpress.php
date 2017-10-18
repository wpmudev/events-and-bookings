<?php
/*
Plugin Name: Payments via MarketPress Products
Description: Allows you to integrate Events+ with MarketPress
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 2.0
Author: WPMU DEV
AddonType: Integration
*/


/**
 * Dispatch the listeners
 */
class Eab_Payments_PaymentViaProducts {

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Payments_PaymentViaProducts;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('plugins_loaded', array($this, 'dispatch_ordering_actions'), 99);

		// Admin
		add_action('admin_notices', array($this, 'show_mp_presence_nag'));
		add_action('admin_notices', array($this, 'show_event_relationship_for_products'));
		// Settings
		add_action('eab-settings-after_payment_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
	}

	/**
	 * Workaround for MP3.0 user error triggering,
	 * and overall event changes
	 */
	public function dispatch_ordering_actions () {
		if ($this->_is_old_mp()) {
			if (!class_exists('Eab_MP_Bridge_Legacy')) require_once( 'lib/class_eab_mp_bridge_legacy.php');
			Eab_MP_Bridge_Legacy::serve();
		} else {
			if (!class_exists('Eab_MP_Bridge')) require_once('lib/class_eab_mp_bridge.php');
			Eab_MP_Bridge::serve();
		}
	}

	/**
	 * Admin notification for Products page.
	 */
	function show_event_relationship_for_products () {
		$screen = get_current_screen();
		if (empty($screen->id) || empty($screen->parent_base)) return false;
		if ('product' != $screen->id || 'edit' != $screen->parent_base) return false;

		global $post;
		$event_id = $this->is_event_ticket($post->ID);
		if (!$event_id) return false;

		$title = get_the_title($event_id);
		$link = admin_url("post.php?post={$event_id}&action=edit");
		echo '<div class="updated"><p>' . sprintf(__('This Product is used as admittance payment for <a href="%s">%s</a>.'), $link, $title) . '</p></div>';
	}

	private function _is_mp_present () {
		return class_exists('MarketPress');
	}

	private function _is_old_mp () {
		if (!$this->_is_mp_present()) return false;
		if (!defined('MP_VERSION')) return false;

		return version_compare(MP_VERSION, '3.0', '<');
	}

	function show_mp_presence_nag () {
		if ($this->_is_mp_present()) return false;
		echo '<div class="error">' .
			'<p>' .
				__( 'You need to install and activate the <a href="http://premium.wpmudev.org/project/e-commerce">MarketPress</a> plugin for the &quot;Payments via MarketPress Products&quot; to work', Eab_EventsHub::TEXT_DOMAIN ) .
			'</p>'.
		'</div>';
	}

	/**
	 * Checks for top-level/singular event relationship.
	 */
	function is_event_ticket ($product_id) {
		return get_post_meta($product_id, 'eab_event_id', true);
	}


	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		
		$category_id = $this->_data->get_option('payment-ppvp-category');
		$categories = get_terms('product_category', array(
			'hide_empty' => false,
		));
?>
<div id="eab-settings-mp_payments" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Payments via MarketPress Products settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-payment-ppvp-category"><?php _e('Limit Products selection to this Product Category', Eab_EventsHub::TEXT_DOMAIN); ?>: </label>
	    	<?php if (!empty($categories)) { ?>
	    	<select name="eab_event-payment-ppvp-category" id="eab_event-payment-ppvp-category">
	    		<option value=""></option>
	    	<?php foreach ($categories as $category) { ?>
	    		<option value="<?php esc_attr_e($category->term_id); ?>" <?php selected($category_id, $category->term_id); ?> ><?php esc_html_e($category->name); ?></option>
	    	<?php } ?>
	    	</select>
	    	<?php } else { ?>
	    		<em><?php _e('No Product Categories', Eab_EventsHub::TEXT_DOMAIN); ?></em>
	    	<?php } ?>
			<span><?php echo $tips->add_tip(__('Use this setting to limit the scope of your Products that can be used as Events payments.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['payment-ppvp-category'] = isset( $_POST['eab_event-payment-ppvp-category'] ) ? $_POST['eab_event-payment-ppvp-category'] : '';
		return $options;
	}
}
Eab_Payments_PaymentViaProducts::serve();