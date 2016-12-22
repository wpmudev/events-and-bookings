<?php
/*
Plugin Name: Import: Facebook Events
Description: Sync your local and Facebook events.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Integration
*/

if( strnatcmp( phpversion(), '5.4.0' ) >= 0 )
{
        require_once( dirname( __FILE__ ) . '/eab-import-facebook_events-main.php');
}
else
{
        add_action( 'admin_notices', 'eab_show_php_notice' );
        function eab_show_php_notice() {
                ?>
                <div class="notice notice-error is-dismissible">
                        <p><?php _e( 'You must need php 5.4 or greater to use <b>Import: Facebook Events</b> addon.', Eab_EventsHub::TEXT_DOMAIN ); ?></p>
                </div>
                <?php
        }
}