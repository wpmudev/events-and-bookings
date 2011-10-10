<?php
global $wpdb;
$ldom = date('t', strtotime($_REQUEST['month']."-01"));
$fdow = date('N', strtotime($_REQUEST['month']."-01"));
$week_start = get_option('start_of_week', 1);

$all_events = $wpdb->get_results("SELECT * FROM ".Booking::tablename('events')." WHERE status != 'archived' `start` BETWEEN '".$_REQUEST['month']."-01' AND '".$_REQUEST['month']."-".$ldom."' ORDER BY `start` ASC");
?>
<div class="wrap">
    <div id="icon-edit-events" class="icon32 icon32-events-page"><br></div>
    <h2><?php _e('Events', 'eab'); ?> <a class="add-new-h2" href="admin.php?page=eab_new_event">Add New</a></h2>
    
    
    <table class="calendar" id="eab_calendar">
	<tbody>
            <tr>
            <?php
            for ($i=0; $i<($fdow-$week_start); $i++) {
            ?>
                <td class="day empty">&nbsp;</td>
            <?php
            }
            if ($week_start == 0) {
                $week_start = 7;
            }
            for ($i=1; $i<$ldom; $i++) {
                if (date('N', strtotime($_REQUEST['month']."-{$i}")) == $week_start) {
            ?>
                </tr>
                <tr>
            <?php
                }
            ?>
                <td><?php print $i; ?> <input type="checkbox" name="event_id[<?php echo $event->id; ?>]" id="event_id_<?php echo $event->id; ?>" /></td>
            <?php
            }
            ?>
            </tr>
            <?php
            ?>
        </tbody>
    </table>
</div>
