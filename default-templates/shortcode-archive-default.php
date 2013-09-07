<section class="eab-events-archive <?php esc_attr_e($args['class']); ?>">
<?php foreach ($events as $event) { ?>
	<?php $event = $event instanceof Eab_EventModel ? $event : new Eab_EventModel($event); ?>
	<article class="eab-event <?php echo eab_call_template('get_status_class', $event); ?>" id="eab-event-<?php echo $event->get_id(); ?>">
		<h4><?php echo $event->get_title(); ?></h4>
		<div class="eab-event-body">
			<?php echo eab_call_template('get_archive_content', $event); ?>
		</div>
	</article>
<?php } ?>
</section>