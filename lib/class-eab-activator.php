<?php

class Eab_Activator {

	/**
	 * Activation hook
	 *
	 * Create tables if they don't exist and add plugin options
	 *
	 * @see     http://codex.wordpress.org/Function_Reference/register_activation_hook
	 *
	 * @global	object	$wpdb
	 */
	public static function run() {
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

		$sql_main = "CREATE TABLE IF NOT EXISTS ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." (
				`id` BIGINT NOT NULL AUTO_INCREMENT,
	            `event_id` BIGINT NOT NULL ,
	            `user_id` BIGINT NOT NULL ,
				`timestamp` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' ,
				`status` ENUM( 'paid', 'yes', 'maybe', 'no' ) NOT NULL DEFAULT 'no' ,
		    		PRIMARY KEY (`id`),
				UNIQUE KEY `event_id_2` (`event_id`,`user_id`),
				KEY `event_id` (`event_id`),
				KEY `user_id` (`user_id`),
				KEY `timestamp` (`timestamp`),
				KEY `status` (`status`)
			    ) ENGINE = InnoDB {$charset_collate};";
		dbDelta($sql_main);

		$sql_main = "CREATE TABLE IF NOT EXISTS ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_META_TABLE)." (
				`id` BIGINT NOT NULL AUTO_INCREMENT,
				`booking_id` BIGINT NOT NULL ,
	            `meta_key` VARCHAR(255) NOT NULL ,
	            `meta_value` TEXT NOT NULL,
		    		PRIMARY KEY (`id`),
				KEY `booking_id` (`booking_id`),
				KEY `meta_key` (`meta_key`)
			    ) ENGINE = InnoDB {$charset_collate};"; // MySQL strict mode fix, thanks @KJA!
		dbDelta($sql_main);


		if (!get_option('event_default', false)) add_option('event_default', array());
		if (!get_option('eab_activation_redirect', true)) add_option('eab_activation_redirect', true);
	}

}