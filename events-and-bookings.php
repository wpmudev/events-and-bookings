<?php
/*
 Plugin Name: Events and Booking
 Plugin URI: http://premium.wpmudev.org/project/events-and-booking
 Description: 
 Author: S H Mohanjith (Incsub)
 WDP ID: 
 Version: 1.0.0
 Author URI: http://premium.wpmudev.org
*/
/**
 * @global      object  $booking   Convenient access to the booking object
 */
global $booking;

/**
 * Booking object (PHP4 compatible)
 * 
 * Allow your readers to register for events you organize
 * 
 * @since 1.0.0
 * @author S H Mohanjith <moha@mohanjith.net>
 */
class Booking {
    
    /**
     * @todo Update version number for new releases
     * 
     * @var		string	$_current_version	Current version
     */
    var $_current_version = '1.0.0';
    
    /**
     * @var		string	$_translation_domain	Translation domain
     */
    var $_translation_domain = 'eab';
    
    /**
     * @var		array	$_options		Consolidated options
     */
    var $_options = array();
    
    /**
     * Get the table name with prefixes
     * 
     * @global	object	$wpdb
     * @param	string	$table	Table name
     * @return	string			Table name complete with prefixes
     */
    function tablename($table) {
	global $wpdb;
    	// We use a single table for all events accross the network
	return $wpdb->prefix.'eab_'.$table;
    }
    
    /**
     * Activation hook
     * 
     * Create tables if they don't exist and add plugin options
     * 
     * @see     http://codex.wordpress.org/Function_Reference/register_activation_hook
     * 
     * @global	object	$wpdb
     */
    function install() {
        global $wpdb;
        
	/**
	 * WordPress database upgrade/creation functions
	 */
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    	
	// Get the correct character collate
	if ( ! empty($wpdb->charset) )
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	if ( ! empty($wpdb->collate) )
	    $charset_collate .= " COLLATE $wpdb->collate";
	
        
        $sql_main = "CREATE TABLE ".Booking::tablename('events')." (
			id BIGINT NOT NULL AUTO_INCREMENT,
                        user_id BIGINT NOT NULL,
                        venue TEXT NOT NULL DEFAULT '',
                        description TEXT NOT NULL DEFAULT '',
                        start TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
                        end TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
                        next_event_id BIGINT NOT NULL DEFAULT 0,
			timestamp TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
			status ENUM( 'open', 'closed', 'expired', 'archived' ) NOT NULL DEFAULT 'no' ,
	    		PRIMARY KEY (`id`),
			KEY `blog_id` (`blog_id`),
			KEY `time` (`time`),
			KEY `venue` (`venue`),
			KEY `archived` (`archived`)
		    ) ENGINE = InnoDB {$charset_collate};";
	dbDelta($sql_main);
        
        $sql_main = "CREATE TABLE ".Booking::tablename('event_meta')." (
			id BIGINT NOT NULL AUTO_INCREMENT,
			event_id BIGINT NOT NULL ,
                        meta_key VARCHAR(255) NOT NULL ,
                        meta_value TEXT NOT NULL DEFAULT '',
	    		PRIMARY KEY (`id`),
			KEY `booking_id` (`blog_id`),
			KEY `meta_key` (`timestamp`)
		    ) ENGINE = InnoDB {$charset_collate};";
	dbDelta($sql_main);
        
	$sql_main = "CREATE TABLE ".Booking::tablename('bookings')." (
			id BIGINT NOT NULL AUTO_INCREMENT,
                        event_id BIGINT NOT NULL ,
                        user_id BIGINT NOT NULL ,
			timestamp TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
			status ENUM( 'paid', 'yes', 'maybe', 'no' ) NOT NULL DEFAULT 'no' ,
	    		PRIMARY KEY (`id`),
			KEY `event_id` (`blog_id`),
			KEY `user_id` (`blog_id`),
			KEY `timestamp` (`timestamp`),
			KEY `status` (`archived`)
		    ) ENGINE = InnoDB {$charset_collate};";
	dbDelta($sql_main);
        
        $sql_main = "CREATE TABLE ".Booking::tablename('booking_meta')." (
			id BIGINT NOT NULL AUTO_INCREMENT,
			booking_id BIGINT NOT NULL ,
                        meta_key VARCHAR(255) NOT NULL ,
                        meta_value TEXT NOT NULL DEFAULT '',
	    		PRIMARY KEY (`id`),
			KEY `booking_id` (`blog_id`),
			KEY `meta_key` (`timestamp`)
		    ) ENGINE = InnoDB {$charset_collate};";
	dbDelta($sql_main);

        
	// Default options
	$this->_options['default'] = array();
	
        add_option('event_default', $this->_options['default']);
    }
    
    /**
     * Deactivation hook
     * 
     * @see		http://codex.wordpress.org/Function_Reference/register_deactivation_hook
     *  
     * @global	object	$wpdb
     */
    function uninstall() {
    	global $wpdb;
	// Nothing to do
    }
}

// Lets get things started
$booking = new Booking();

if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
