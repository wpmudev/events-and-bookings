<?php
global $wpdb, $event_id;
$all_bookings = $wpdb->get_results("SELECT * FROM ".Booking::tablename('bookings')." WHERE status != 'no' AND event_id = {$event_id} ORDER BY id ASC");
?>
<div class="wrap">
    <div id="icon-edit-bookings" class="icon32 icon32-bookings-page"><br></div>
    <h2><?php _e('Bookings', 'eab'); ?></h2>
    
    <table class="widefat" id="invoice_sorter_table">
	<thead>
		<tr>
			<th class="check-column"><input type="checkbox" id="CheckAll" /></th>
			<th class="invoice_id_col"><?php _e('Event Id', 'eab'); ?></th>
			<th><?php _e('Title', 'eab'); ?></th>
			<th><?php _e('Venue', 'eab'); ?></th>
			<th><?php _e('Start', 'eab'); ?></th>
			<th><?php _e('End', 'eab'); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
            <?php
            if (count($all_events) > 0) {
                foreach ($all_events as $event) {
                ?>
                <tr>
                    <td><input type="checkbox" name="event_id[<?php echo $event->id; ?>]" id="event_id_<?php echo $event->id; ?>" /></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php
                }
            } else {
            ?>
                <tr>
                    <td>&nbsp;</td>
                    <td colspan="6" ><?php _e('No events found', 'eab'); ?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
</div>
