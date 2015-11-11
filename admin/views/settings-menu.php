<?php if ( $updated ): ?>
	<div class="updated fade"><p><?php _e('Settings saved.', eab_domain() ); ?></p></div>
<?php endif; ?>

<div class="wrap <?php echo esc_attr($tabbable); ?> <?php echo esc_attr($hide); ?>">
	<h1><?php _e('Events Settings', eab_domain() ); ?></h1>
	<?php if (defined('EAB_PREVENT_SETTINGS_SECTIONS') && EAB_PREVENT_SETTINGS_SECTIONS) { ?>
		<div class="eab-note">
			<p><?php _e('This is where you manage your general settings for the plugin and how events are displayed on your site.', eab_domain() ); ?>.</p>
		</div>
	<?php } ?>
	<form method="post" action="edit.php?post_type=incsub_event&page=eab_settings">
		<?php wp_nonce_field('incsub_event-update-options'); ?>
                <input type="hidden" name="event_default[event_settings_url]" value="" class="event_settings_url">
		<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center">
			<?php do_action('eab-settings-before_plugin_settings'); ?>
			<div id="eab-settings-general" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Plugin settings', eab_domain() ); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
						<label for="incsub_event-slug" id="incsub_event_label-slug"><?php _e('Set your root slug here:', eab_domain() ); ?></label>
						/<input type="text" size="20" id="incsub_event-slug" name="event_default[slug]" value="<?php print $this->_data->get_option('slug'); ?>" />
						<span><?php echo $tips->add_tip(__('This is the URL where your events archive can be found. By default, the format is yoursite.com/events, but you can change this to whatever you want.', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="incsub_event-accept_payments" id="incsub_event_label-accept_payments"><?php _e('Will you be accepting payment for any of your events?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="incsub_event-accept_payments" name="event_default[accept_payments]" value="1" <?php print ($this->_data->get_option('accept_payments') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Leave this box unchecked if you don\'t intend to collect payment at any time.', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="incsub_event-accept_api_logins" id="incsub_event_label-accept_api_logins"><?php _e('Allow Facebook and Twitter Login?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="incsub_event-accept_api_logins" name="event_default[accept_api_logins]" value="1" <?php print ($this->_data->get_option('accept_api_logins') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Check this box to allow guests to RSVP to an event with their Facebook or Twitter account. (If this feature is not enabled, guests will need a WordPress account to RSVP).', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="incsub_event-display_attendees" id="incsub_event_label-display_attendees"><?php _e('Display public RSVPs?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="incsub_event-display_attendees" name="event_default[display_attendees]" value="1" <?php print ($this->_data->get_option('display_attendees') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Check this box to display a "who\'s attending" list in the event details.', eab_domain() )); ?></span>
					</div>
				</div>
			</div>
			<?php if (!$theme_tpls_present) { ?>
				<div id="eab-settings-appearance" class="eab-metabox postbox">
					<h3 class="eab-hndle"><?php _e('Appearance settings', eab_domain() ); ?></h3>
					<div class="eab-inside">
						<div class="eab-settings-settings_item">
							<label for="incsub_event-override_appearance_defaults" id="incsub_event_label-override_appearance_defaults"><?php _e('Override default appearance?', eab_domain() ); ?></label>
							<input type="checkbox" size="20" id="incsub_event-override_appearance_defaults" name="event_default[override_appearance_defaults]" value="1" <?php print ($this->_data->get_option('override_appearance_defaults') == 1)?'checked="checked"':''; ?> />
							<span><?php echo $tips->add_tip(__('Check this box if you want to customize the appearance of your events with overriding templates.', eab_domain() )); ?></span>
						</div>

						<div class="eab-settings-settings_item">
							<?php if (!$archive_tpl_present) { ?>
								<label for="incsub_event-archive_template" id="incsub_event_label-archive_template"><?php _e('Archive template', eab_domain() ); ?></label>
								<select id="incsub_event-archive_template" name="event_default[archive_template]">
									<?php foreach ($templates as $tkey => $tlabel) { ?>
										<?php $selected = ($this->_data->get_option('archive_template') == $tkey) ? 'selected="selected"' : ''; ?>
										<option value="<?php esc_attr_e($tkey);?>" <?php echo $selected;?>><?php echo $tlabel;?></option>
									<?php } ?>
								</select>
								<span>
							<small><em><?php _e('* templates may not work in all themes', eab_domain() ); ?></em></small>
									<?php echo $tips->add_tip(__('Choose how the events archive is displayed on your site.', eab_domain() ) ); ?>
						</span>
							<?php } ?>
						</div>

						<div class="eab-settings-settings_item">
							<?php if (!$single_tpl_present) { ?>
								<label for="incsub_event-single_template" id="incsub_event_label-single_template"><?php _e('Single Event template', eab_domain() ); ?></label>
								<select id="incsub_event-single_template" name="event_default[single_template]">
									<?php foreach ($templates as $tkey => $tlabel) { ?>
										<?php $selected = ($this->_data->get_option('single_template') == $tkey) ? 'selected="selected"' : ''; ?>
										<option value="<?php esc_attr_e($tkey);?>" <?php echo $selected;?>><?php echo $tlabel;?></option>
									<?php } ?>
								</select>
								<span>
							<small><em><?php _e('* templates may not work in all themes', eab_domain() ); ?></em></small>
									<?php echo $tips->add_tip(__('Choose how single event listings are displayed on your site.', eab_domain() )); ?>
						</span>
							<?php } ?>
						</div>

					</div>
				</div>
			<?php } ?>

			<?php do_action('eab-settings-after_appearance_settings'); /* the hook happens whether we have appearance settings or not */ ?>

			<!-- Payment settings -->
			<div id="eab-settings-paypal" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Payment settings', eab_domain() ); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
						<label for="incsub_event-currency" id="incsub_event_label-currency"><?php _e('Currency', eab_domain() ); ?></label>
						<input type="text" size="4" id="incsub_event-currency" name="event_default[currency]" value="<?php print $this->_data->get_option('currency'); ?>" />
						<span><?php echo $tips->add_tip(sprintf(__('Nominate the currency in which you will be accepting payment for your events. For more information see <a href="%s" target="_blank">Accepted PayPal Currency Codes</a>.', eab_domain() ), 'https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_currency_codes')); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="incsub_event-paypal_email" id="incsub_event_label-paypal_email"><?php _e('PayPal E-Mail address', eab_domain() ); ?></label>
						<input type="text" size="20" id="incsub_event-paypal_email" name="event_default[paypal_email]" value="<?php print $this->_data->get_option('paypal_email'); ?>" />
						<span><?php echo $tips->add_tip(__('Add the primary email address of the PayPal account you will use to collect payment for your events.', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="incsub_event-paypal_sandbox" id="incsub_event_label-paypal_sandbox"><?php _e('PayPal Sandbox mode?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="incsub_event-paypal_sandbox" name="event_default[paypal_sandbox]" value="1" <?php print ($this->_data->get_option('paypal_sandbox') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Use PayPal Sandbox mode for testing your payments', eab_domain() )); ?></span>
					</div>
				</div>
			</div>
			<?php do_action('eab-settings-after_payment_settings'); ?>
			<?php $this->_api->render_settings($tips); // API settings ?>
			<!-- Addon settings -->
			<div id="eab-settings-addons" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Add-ons', eab_domain() ); ?></h3>
				<!--<div class="eab-inside">-->
				<?php Eab_AddonHandler::create_addon_settings(); ?>
				<br />
				<!--</div>-->
			</div>
			<?php do_action('eab-settings-after_plugin_settings'); ?>
		</div>

		<p class="submit clear">
			<input type="submit" class="button-primary" name="submit_settings" value="<?php _e('Save Changes', eab_domain() ) ?>" />
			<?php if (isset($_REQUEST['eab_step']) && $_REQUEST['eab_step'] == 1) { ?>
				<a href="edit.php?post_type=incsub_event&page=eab_welcome&eab_step=-1" class="button"><?php _e('Go back to Getting started guide', eab_domain() ) ?></a>
			<?php } ?>
		</p>
	</form>
</div>
<?php if (!empty($tabbable)) { ?>
	<div class="eab-loading-cover <?php echo esc_attr($tabbable); ?>"><h1><?php _e('Please, hold on...', eab_domain() ); ?></h1></div>
<?php } ?>