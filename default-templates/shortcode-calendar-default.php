<section class="wpmudevevents-list">
<?php
if (!class_exists('Eab_CalendarTable_EventShortcodeCalendar')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_calendar_helper.php';
$renderer = new Eab_CalendarTable_EventShortcodeCalendar($events);

$renderer->set_class($args['class']);
$renderer->set_footer($args['footer']);
$renderer->set_scripts(!$args['override_scripts']);
$renderer->set_navigation($args['navigation']);
$renderer->set_track($args['track']);
$renderer->set_title_format($args['title_format']);
$renderer->set_short_title_format($args['short_title_format']);
$renderer->set_long_date_format($args['long_date_format']);
$renderer->set_thumbnail($args);
$renderer->set_excerpt($args);

echo $renderer->get_month_calendar($args['date']);
?>
</section>