<div class="eab-event <?php esc_attr_e($args['class']); ?>">
	<h4><?php echo  $events->get_title(); ?></h4>
	<div class="eab-event-body">
		<?php echo eab_call_template('get_single_content', $events); ?>
	</div>
</div>