<?php

class Eab_AdminHelp {
	
	private $_help;
	private $_sidebar;
	
	private $_pages = array (
		'list',
		'edit',
		'settings',
		'welcome',
	);
	
	private function __construct () {
		if (!class_exists('WpmuDev_ContextualHelp')) require_once 'class_wd_contextual_help.php';
		$this->_help = new WpmuDev_ContextualHelp();
		$this->_set_up_sidebar();
	}
	
	public static function serve () {
		$me = new Eab_AdminHelp;
		$me->_initialize();
	}
	
	private function _initialize () {
		foreach ($this->_pages as $page) {
			$method = "_add_{$page}_page";
			if (method_exists($this, $method)) $this->$method();
		}
		$this->_help->initialize();
	}
	
	private function _set_up_sidebar () {
		$this->_social_marketing_sidebar = '<h4>' . __('Events and Bookings', Booking::$_translation_domain) . '</h4>';
		if (defined('WPMUDEV_REMOVE_BRANDING') && constant('WPMUDEV_REMOVE_BRANDING')) {
			$this->_sidebar .= '<p>' . __('Events and Bookings gives you a flexible WordPress-based system for organizing parties, dinners, fundraisers - you name it.', Booking::$_translation_domain) . '</p>';
		} else {
				$this->_sidebar .= '<ul>' .
					'<li><a href="http://premium.wpmudev.org/project/events-and-booking" target="_blank">' . __('Project page', Booking::$_translation_domain) . '</a></li>' .
					'<li><a href="http://premium.wpmudev.org/project/events-and-booking/installation/" target="_blank">' . __('Installation and instructions page', Booking::$_translation_domain) . '</a></li>' .
					'<li><a href="http://premium.wpmudev.org/forums/tags/events-and-bookings" target="_blank">' . __('Support forum', Booking::$_translation_domain) . '</a></li>' .
				'</ul>' . 
			'';
		}
	}
	
	private function _add_list_page () {
		$this->_help->add_page(
			'edit-incsub_event', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Intro', Booking::$_translation_domain),
					'content' => '' .
						'<p>' .
							__('This is where you can see all your Events.', Booking::$_translation_domain) .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', Booking::$_translation_domain),
					'content' => '' .
						'<p>' . 
							__('Tutorial dialogs will guide you through the important bits.', Booking::$_translation_domain) . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="0">' . __('Restart the tutorial', Booking::$_translation_domain) . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}

	private function _add_edit_page () {
		// Determine if we have the Maps plugin
		$agm = class_exists('AgmMapModel') 
			? __('If you have the <a href="http://premium.wpmudev.org/project/wordpress-google-maps-plugin">Google Maps</a> plugin installed, you\'ll be able to use full Google Maps integration with your events', Booking::$_translation_domain) 
			: __('Your location will be automatically mapped on a Google Map. You can also create a map yourself and associate it with your event using the globe icon above the field', Booking::$_translation_domain)
		; 
		$this->_help->add_page(
			'incsub_event', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Intro', Booking::$_translation_domain),
					'content' => '' .
						'<p>' .
							__('This is where you create and edit your Events', Booking::$_translation_domain) .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_details',
					'title' => __('Event Details', Booking::$_translation_domain),
					'content' => '' .
						'<h4>' . __('Event Location', Booking::$_translation_domain) . '</h4>' .
						'<p>' . 
							__('You can enter your event address in this box.', Booking::$_translation_domain) .
							" {$agm}" . 
						'</p>' .
						'<h4>' . __('Event times and dates', Booking::$_translation_domain) . '</h4>' .
						'<p>' .
							__('You can add multiple start and ending times to your event. You can add as many of those as you\'d like.', Booking::$_translation_domain) .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', Booking::$_translation_domain),
					'content' => '' .
						'<p>' . 
							__('Tutorial dialogs will guide you through the important bits.', Booking::$_translation_domain) . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="5">' . __('Restart the tutorial', Booking::$_translation_domain) . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}

	private function _add_settings_page () {
		$this->_help->add_page(
			'incsub_event_page_eab_settings', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Intro', Booking::$_translation_domain),
					'content' => '' .
						'<p>' .
							__('This is where you set up your plugin.', Booking::$_translation_domain) .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_appearance_settings',
					'title' => __('Appearance settings', Booking::$_translation_domain),
					'content' => '' .
						'<p>' . __('This is where you determine how will your events be presented.', Booking::$_translation_domain) . '</p>' .
						'<p>' . __('If you check the "Override default appearance" option, you will be able to select among various predefined templates to change the way your Events appear.', Booking::$_translation_domain) . '</p>' .
						'<p>' . __('To go back to default plugin output, just uncheck the "Override default appearance" option at any time', Booking::$_translation_domain) . '</p>' .
						'<p>' . __('If you\'re looking to further customize the templates, you can copy a set you like from the plugin directory to your current theme directory and edit away.', Booking::$_translation_domain) . '</p>' .
						'<p><em>' . __('<b>Note:</b> these settings will not be available if you copy the templates to your themes directory for customization', Booking::$_translation_domain) . '</em></p>' .
					'',
				),
				array(
					'id' => 'eab_api_settings',
					'title' => __('API Settings', Booking::$_translation_domain),
					'content' => '' .
						'<p>' .
							__('This section becomes available if you allow logins with Twitter and Facebook, by checking the corresponding box in Plugin settings.', Booking::$_translation_domain) .
						'</p>' .
						
						'<h4>' . __('Facebook API settings', Booking::$_translation_domain) . '</h4>' .
						sprintf(__('<p>Before we begin, you need to <a target="_blank" href="http://www.facebook.com/developers/createapp.php">create a Facebook Application</a>.</p>' .
						'<p>To do so, follow these steps:</p>' .
						'<ol>' .
							'<li><a target="_blank" href="http://www.facebook.com/developers/createapp.php">Create your application</a></li>' .
							'<li>After this, go to the <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List page</a> and select your newly created application</li>' .
							'<li>Copy the value from the <strong>App ID</strong>/<strong>API key</strong> field, and enter them in the box titled "Facebook App ID"</li>' .
						'</ol>', Booking::$_translation_domain), get_bloginfo('url')) .
						
						'<h4>' . __('Twitter API settings', Booking::$_translation_domain) . '</h4>' .
						__('<p>You will also need to <a target="_blank" href="https://dev.twitter.com/apps/new">create a Twitter Application</a>.</p>' .
						'<p>To do so, follow these steps:</p>' .
						'<ol>' .
							'<li><a target="_blank" href="https://dev.twitter.com/apps/new">Create your application</a></li>' .
							'<li>Look for <strong>Callback URL</strong> field and enter your site URL in this field: <code>%s</code></li>' .
							'<li>After this, go to the <a target="_blank" href="https://dev.twitter.com/apps">Twitter Application List page</a> and select your newly created application</li>' .
							'<li>Copy the values from these fields: <strong>Consumer Key</strong> and <strong>Consumer Secret</strong>, and enter them in plugin settings.</li>' .
						'</ol>', Booking::$_translation_domain) .
					'',
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', Booking::$_translation_domain),
					'content' => '' .
						'<p>' . 
							__('Tutorial dialogs will guide you through the important bits.', Booking::$_translation_domain) . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="0">' . __('Restart the tutorial', Booking::$_translation_domain) . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}
	
	private function _add_welcome_page () {
		$this->_help->add_page(
			'incsub_event_page_eab_welcome', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Welcome', Booking::$_translation_domain),
					'content' => '' .
						'<p>' .
							__('Welcome to Events and Booking! This page will guide through setting up your plugin and posting your first events.', Booking::$_translation_domain) .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', Booking::$_translation_domain),
					'content' => '' .
						'<p>' . 
							__('Tutorial dialogs will guide you through the important bits.', Booking::$_translation_domain) . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="0">' . __('Restart the tutorial', Booking::$_translation_domain) . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}
	
	function show_screen () {
		echo '<pre>'; var_export(get_current_screen());
	}
}
