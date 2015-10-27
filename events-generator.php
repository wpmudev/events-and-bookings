<?php

/*
Plugin Name: Event Generator
Author: Ashok
Version: 1.1
*/

if( ! class_exists( 'Event_Generator' ) ) {
	class Event_Generator{

		public function __construct() {
			add_action( 'init', array( &$this, 'init' ) );
		}

		public function init() {
			add_action( 'admin_menu', array( &$this, 'register_generator_page' ) );
			add_action( 'admin_action_generate_event', array( &$this, 'generate_event' ) );
			add_action( 'admin_footer', array( &$this, 'ei_script' ) );
		}

		public function register_generator_page() {
			add_submenu_page( 'edit.php?post_type=incsub_event', 'Event Generator', 'Event Generator', 'manage_options', 'event-generator', array( &$this, 'event_generator_cb' ) );
		}

		public function event_generator_cb() {
			?>
			<div class="wrap">
				<h2>Event Generator</h2>
				<?php if( isset( $_REQUEST['event_action'] ) ) { ?>
					<div class="updated">
						<p>Events created!</p>
					</div>
				<?php } ?>
				<form action="<?php echo admin_url( 'admin.php?action=generate_event' ) ?>" method="post">
					<div id="poststuff">
						<div class="postbox">
							<h3 class="hndle">Configurations</h3>
							<div class="inside">
								<table class="form-table">
									<tr valign="top">
										<th scope="row">No of Events</th>
										<td><input type="text" name="eg[number]" size="25"></td>
										<th scope="row">&nbsp;</th>
										<td scope="row">&nbsp;</td>
									</tr>
									<tr>
										<th scope="row">Vennue</th>
										<td>
											<input type="text" name="eg[vennue]" size="25">
										</td>
										<th scope="row">No of Recurring Events</th>
										<td>
											<input type="text" name="eg[recurring]" size="25">
										</td>
									</tr>
									<tr>
										<th scope="row">Paid?</th>
										<td>
											<select name="eg[paid]">
												<option value="1">Yes</option>
												<option value="0" selected="selected">No</option>
											</select>
										</td>
										<th scope="row">Fee</th>
										<td>
											<input type="text" name="eg[fee]" size="25">
										</td>
									</tr>
									<tr>
										<th scope="row">Status</th>
										<td>
											<select name="eg[status]">
												<option value="open">Open</option>
												<option value="closed">Closed</option>
												<option value="expired">Expired</option>
												<option value="archived">Archived</option>
											</select>
										</td>
										<th scope="row">Repeats Every</th>
										<td>
											<select name="eg[every]">
												<option value="daily">Day</option>
												<option value="weekly">Week</option>
												<option value="week_count">Week Count</option>
												<option value="dow">Day of the Week</option>
												<option value="monthly" selected="selected">Month</option>
												<option value="yearly">Year</option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Start</th>
										<td>
											<input type="text" name="eg[start]" size="25" placeholder="yyyy-mm-dd hh:mm">
										</td>
										<th scope="row">End</th>
										<td>
											<input type="text" name="eg[end]" size="25" placeholder="yyyy-mm-dd hh:mm">
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<input type="submit" name="eg[submit]" value="Create Events" class="button button-primary">
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</form>
			</div>
			<?php
		}

		public function ei_script() {
			?>
			<script type="text/javascript">
				jQuery(function($){

				});
			</script>
			<?php
		}

		public function generate_event() {
			if( isset( $_POST['eg'] ) ) {
				$number = ! isset( $_POST['eg']['number'] ) || $_POST['eg']['number'] == '' ? 10 : $_POST['eg']['number'];
			}

			$recurring = $_POST['eg']['recurring'];
			$re = 0;

			$html = 'Orci sociis neque tristique euismod aliquam a luctus mi eu felis vivamus diam nisl urna in a vehicula parturient suspendisse parturient posuere eleifend semper. Lacinia mattis fringilla metus condimentum ut a a ac dui pulvinar adipiscing egestas id rhoncus hendrerit adipiscing facilisi vestibulum parturient fringilla vestibulum sed parturient parturient. Etiam non tristique tincidunt dui vehicula tempor consectetur aptent aliquet consectetur amet elit feugiat commodo.
​
Arcu duis nibh vestibulum pharetra nascetur nulla dis aliquet tortor maecenas mi arcu neque urna consectetur natoque at a. A ullamcorper a nulla pretium mi laoreet felis elementum adipiscing augue leo consectetur a maecenas. Parturient scelerisque taciti a amet ipsum a scelerisque nec id a pharetra eu molestie erat tincidunt a duis curabitur. ';

			for( $i = 0; $i < $number; $i++ ) {
				$ex_title = ( $re < $recurring ) ? ' - Recurring' : '';
				$event_post = array(
					'post_title' => 'Event title ' . $i . ' - ' . uniqid() . $ex_title,
					'post_content' => $html,
					'post_status' => 'publish',
					'post_type' => 'incsub_event'
				);

				$post_id = wp_insert_post( $event_post );

				$event = new Eab_EventModel( get_post( $post_id ) );

				// Location
				update_post_meta( $post_id, 'incsub_event_venue', 'Dhaka' );
				// Status
				update_post_meta( $post_id, 'incsub_event_status', strip_tags( $_POST['eg']['status'] ) ); // closed, expired, archive
				// Paid?
				update_post_meta( $post_id, 'incsub_event_paid', $_POST['eg']['paid'] ); // Another value is 1
				update_post_meta( $post_id, 'incsub_event_fee', $_POST['eg']['fee'] ); // If incsub_event_paid is 1, then we need to set a fee
				// When
				add_post_meta( $post_id, 'incsub_event_start', date( 'Y-m-d H:i:s', strtotime( $_POST['eg']['start'] ) ) );
				add_post_meta( $post_id, 'incsub_event_no_start', 0 ); // Another value is 1 if no start time is set
				add_post_meta( $post_id, 'incsub_event_end', date( 'Y-m-d H:i:s', strtotime( $_POST['eg']['end'] ) ) );
				add_post_meta( $post_id, 'incsub_event_no_end', 0 ); // Another value is 1 if no end time is set

				if( $re < $recurring ) {
					$repeat = array(
						'repeat_start' => $_POST['eg']['start'],
						'repeat_end' => $_POST['eg']['end'],
						'repeat_every' => 'monthly', // daily, weekly, week_count, dow, monthly, yearly
						'month' => 1, // 1 to 12
						'day' => '28', // 1 to 31
						'weekday' => 1, // 0 t0 6
						'week' => 'first', // second, third, fourth, fifth, last
						'time' => '10:00',
						'duration' => 2
					);

					$start = $repeat['repeat_start'] ? strtotime( $repeat['repeat_start'] ) : eab_current_time();
					$end =  $repeat['repeat_end'] ? strtotime( $repeat['repeat_end'] ) : eab_current_time();
					if ($end <= $start) {
						// BAH! Wrong order
					}
					$interval = $repeat['repeat_every'];
					$time_parts = array(
						'month' => @$repeat['month'],
						'day' => @$repeat['day'],
						'weekday' => @$repeat['weekday'],
						'week' => @$repeat['week'],
						'time' => @$repeat['time'],
						'duration' => @$repeat['duration'],
					);

					$event->spawn_recurring_instances($start, $end, $interval, $time_parts);

					$re++;
				}

			}

			wp_redirect( admin_url( 'edit.php?post_type=incsub_event&page=event-generator&event_action=done' ) );
			exit;
		}

	}

	new Event_Generator();
}