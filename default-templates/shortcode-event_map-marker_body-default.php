<?php $class_pfx = !empty($args['class']) ? $args['class'] : 'eab-events_map'; ?>
<div class='<?php echo $class_pfx; ?>-venue'>
	<a href="<?php echo get_permalink($events->get_id()); ?>">
		<?php echo $events->get_venue_location(); ?>
	</a>
</div><div class='<?php echo $class_pfx; ?>-dates'>
	<?php echo eab_call_template('get_event_dates', $events); ?>
</div>