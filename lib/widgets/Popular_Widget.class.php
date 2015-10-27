<?php

class Eab_Popular_Widget extends Eab_Widget {
	
	private $_defaults;
    
    function __construct () {
    	$this->_defaults = apply_filters('eab-widgets-popular-default_fields', array( 
			'title' => __('Most Popular', $this->translation_domain),
			'excerpt' => false,
			'excerpt_words_limit' => false,
			'thumbnail' => false,
			'limit' => 5,
		));
		$widget_ops = array(
			'description' => __('Display List of Popular events', $this->translation_domain)
		);
        $control_ops = array(
        	'title' => __('Most Popular', $this->translation_domain)
        );
		parent::__construct('incsub_event_popular', __('Most Popular Events', $this->translation_domain), $widget_ops, $control_ops);
    }
    
    function widget ($args, $instance) {
		global $wpdb, $current_site, $post, $wiki_tree;
		
		extract($args);
		
		$instance = apply_filters('eab-widgets-popular-instance_read', $instance, $this);
		$options = wp_parse_args((array)$instance, $this->_defaults);
		
		$title = apply_filters('widget_title', empty($instance['title']) ? __('Most Popular', $this->translation_domain) : $instance['title'], $instance, $this->id_base);
		
		$_events = Eab_CollectionFactory::get_popular_events(array(
			'posts_per_page' => $options['limit'],
		));
		if (is_array($_events) && count($_events) > 0) {
		?>
		<?php echo $before_widget; ?>
		<?php echo $before_title . $title . $after_title; ?>
	            <div id="event-popular">
			<ul>
			    <?php
				foreach ($_events as $_event) {
					$thumbnail = $excerpt = false;
					if ($options['thumbnail']) {
						$raw = wp_get_attachment_image_src(get_post_thumbnail_id($_event->get_id()));
						$thumbnail = $raw ? @$raw[0] : false;
					}
					$excerpt = false;
					if ($options['excerpt']) {
						$words = (int)$options['excerpt_words_limit'] ? (int)$options['excerpt_words_limit'] : false;
						$excerpt = eab_call_template('util_words_limit', $_event->get_excerpt_or_fallback(), $words);
					}
			    ?>
				<li>
					<a href="<?php print get_permalink($_event->get_id()); ?>" class="<?php print ($_event->get_id() == $post->ID)?'current':''; ?>" >
						<?php if ($options['thumbnail'] && $thumbnail) { ?>
							<img src="<?php echo $thumbnail; ?>" /><br />
						<?php } ?>
						<?php print $_event->get_title(); ?>
					</a>
					<?php if ($options['excerpt'] && $excerpt) { ?>
						<p><?php echo $excerpt; ?></p>
					<?php } ?>
					<?php do_action('eab-widgets-popular-after_event', $options, $_event, $this); ?>
				</li>
			    <?php
				}
			    ?>
			</ul>
	            </div>
	        <br />
	        <?php echo $after_widget; ?>
		<?php
		} else {
			echo $before_widget .
				$before_title . $title . $after_title .
				'<p class="eab-widget-no_events">' . __('No popular events.', Eab_EventsHub::TEXT_DOMAIN) . '</p>' .
			$after_widget;
		}
    }
    
    function update ($new_instance, $old_instance) {
		$instance = $old_instance;
        $new_instance = wp_parse_args((array)$new_instance, $this->_defaults);
        
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['excerpt'] = (int)$new_instance['excerpt'];
        $instance['excerpt_words_limit'] = (int)$new_instance['excerpt_words_limit'];
        $instance['thumbnail'] = (int)$new_instance['thumbnail'];
        $instance['limit'] = (int)$new_instance['limit'];

        $instance = apply_filters('eab-widgets-popular-instance_update', $instance, $new_instance, $this);
	
        return $instance;
    }
    
    function form ($instance) {
    	$instance = apply_filters('eab-widgets-popular-instance_read', $instance, $this);
		$options = wp_parse_args((array)$instance, $this->_defaults);
        $options['title'] = strip_tags($instance['title']);	
	
	?>
	<div style="text-align:left">
            <label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;"><?php _e('Title', $this->translation_domain); ?>:<br />
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
            </label>
            <label for="<?php echo $this->get_field_id('excerpt'); ?>" style="display:block;">
				<input type="checkbox" 
					id="<?php echo $this->get_field_id('excerpt'); ?>" 
					name="<?php echo $this->get_field_name('excerpt'); ?>" 
					value="1" <?php echo ($options['excerpt'] ? 'checked="checked"' : ''); ?> 
				/>
            	<?php _e('Show excerpt', $this->translation_domain); ?>
            </label>
            <label for="<?php echo $this->get_field_id('excerpt_words_limit'); ?>" style="display:block; margin-left:1.8em">
            	<?php _e('Limit my excerpt to this many words <small>(<code>0</code> for no limit)</small>:', $this->translation_domain); ?>
				<input type="text" 
					size="2"
					id="<?php echo $this->get_field_id('excerpt_words_limit'); ?>" 
					name="<?php echo $this->get_field_name('excerpt_words_limit'); ?>" 
					value="<?php echo (int)$options['excerpt_words_limit']; ?>"
				/>
            </label>
            <label for="<?php echo $this->get_field_id('thumbnail'); ?>" style="display:block;">
				<input type="checkbox" 
					id="<?php echo $this->get_field_id('thumbnail'); ?>" 
					name="<?php echo $this->get_field_name('thumbnail'); ?>" 
					value="1" <?php echo ($options['thumbnail'] ? 'checked="checked"' : ''); ?> 
				/>
            	<?php _e('Show thumbnail', $this->translation_domain); ?>
            </label>
            <label for="<?php echo $this->get_field_id('limit'); ?>" style="line-height:35px;display:block;">
            	<?php _e('Limit', $this->translation_domain); ?>:
				<select id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>">
					<?php for ($i=1; $i<=10; $i++) { ?>
						<?php $selected = ($i == $options['limit']) ? 'selected="selected"' : ''; ?>
						<option value="<?php echo $i; ?>" <?php echo $selected;?>><?php echo $i;?></option>
					<?php } ?>
				</select> 
            </label>
            <?php do_action('eab-widgets-popular-widget_form', $options, $this); ?>
	</div>
	<?php
    }
}
