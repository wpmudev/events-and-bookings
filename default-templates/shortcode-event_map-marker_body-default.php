<?php 
$class_pfx = !empty($args['class']) ? $args['class'] : 'eab-events_map'; 
$content = '';
if ($args['show_date']) $content .= eab_call_template('get_event_dates', $events);
if ($args['show_excerpt']) $content .= '<div class="eab-event-excerpt">' . $events->get_excerpt_or_fallback($args['excerpt_length']) . '</div>';
?>
<div class='<?php echo $class_pfx; ?>-venue'>
	<a href="<?php echo get_permalink($events->get_id()); ?>">
		<?php echo $events->get_venue_location(); ?>
	</a>
</div><div class='<?php echo $class_pfx; ?>-dates'>
	<?php echo $content; ?>
</div>