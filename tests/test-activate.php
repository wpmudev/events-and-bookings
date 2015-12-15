<?php

class EAB_Test_Activate extends Events_UnitTestCase {

	function test_activate() {
		global $wpdb;

		$table = $wpdb->prefix . 'eab_bookings';
		$results = $wpdb->get_results( "DESCRIBE $table");

		$this->assertNotEmpty( $results );

		$table = $wpdb->prefix . 'eab_booking_meta';
		$results = $wpdb->get_results( "DESCRIBE $table");

		$this->assertNotEmpty( $results );

		$this->assertEquals( array(), get_option( 'event_default' ) );
		$this->assertTrue( get_option( 'eab_activation_redirect' ) );
	}

}