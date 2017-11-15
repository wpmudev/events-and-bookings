<?php


class Eab_Admin_Get_Started_Menu {
	public function __construct( $parent ) {
		$id = add_submenu_page(
			$parent,
			__("Get Started", eab_domain()),
			__("Get started", eab_domain()),
			'manage_options',
			'eab_welcome',
			array($this,'render')
		);

		$eab = events_and_bookings();
		$this->_data = $eab->_data;
		$this->_api = $eab->_api;
	}

	function render() {
		?>
		<div class="wrap">
			<h1><?php _e('Getting started', eab_domain() ); ?></h1>

			<p>
				<?php _e('Events gives you a flexible WordPress-based system for organizing parties, dinners, fundraisers - you name it.', eab_domain() ) ?>
			</p>

			<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center">
				<div id="eab-actionlist" class="eab-metabox postbox">
					<h3 class="eab-hndle"><?php _e('Getting Started', eab_domain() ); ?></h3>
					<div class="eab-inside">
						<div class="eab-note"><?php _e('You\'re almost ready! Follow these steps and start creating events on your WordPress site.', eab_domain() ); ?></div>
						<ol>
							<li>
								<?php _e('Before creating an event, you\'ll need to configure some basic settings, like your root slug and payment options.', eab_domain() ); ?>
								<a href="<?php echo esc_url('edit.php?post_type=incsub_event&page=eab_settings&eab_step=1'); ?>" class="eab-goto-step button" id="eab-goto-step-0" ><?php _e('Configure Your Settings', eab_domain() ); ?></a>
							</li>
							<li>
								<?php _e('Now you can create your first event.', eab_domain() ); ?>
								<a href="<?php echo esc_url('post-new.php?post_type=incsub_event&eab_step=2'); ?>" class="eab-goto-step button"><?php _e('Add an Event', eab_domain() ); ?></a>
							</li>
							<li>
								<?php _e('You can view and edit your existing events whenever you like.', eab_domain() ); ?>
								<a href="<?php echo esc_url('edit.php?post_type=incsub_event&eab_step=3'); ?>" class="eab-goto-step button"><?php _e('Edit Events', eab_domain() ); ?></a>
							</li>
							<li>
								<?php _e('The archive displays a list of upcoming events on your site.', eab_domain() ); ?>
								<a href="<?php echo home_url($this->_data->get_option('slug')) . '/'; ?>" class="eab-goto-step button"><?php _e('Events Archive', eab_domain() ); ?></a>
							</li>
						</ol>
					</div>
				</div>
			</div>

			<?php if (!defined('WPMUDEV_REMOVE_BRANDING') || !constant('WPMUDEV_REMOVE_BRANDING')) { ?>
				<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center">
					<div id="eab-helpbox" class="eab-metabox postbox">
						<h3 class="eab-hndle"><?php _e('Need help?', eab_domain() ); ?></h3>
						<div class="eab-inside">
							<ol>
								<li><a href="http://premium.wpmudev.org/project/events-and-booking"><?php _e('Check out the Events plugin page on WPMU DEV', eab_domain() ); ?></a></li>
								<li><a href="http://premium.wpmudev.org/forums/tags/events-and-bookings"><?php _e('Post a question about this plugin on our support forums', eab_domain() ); ?></a></li>
								<li><a href="http://premium.wpmudev.org/project/events-and-booking/installation/"><?php _e('Watch a video of the Events plugin in action', eab_domain() ); ?></a></li>
							</ol>
						</div>
					</div>
				</div>
			<?php } ?>

			<div class="clear"></div>

			<div class="eab-dashboard-footer">

			</div>
		</div>
		<?php
	}
}