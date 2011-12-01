<?php

class Eab_Upcoming_Widget extends Eab_Widget {
    
    function __construct() {
	$widget_ops = array( 'description' => __('Display List of Upcoming Events', $this->translation_domain) );
        $control_ops = array( 'title' => __('Upcoming', $this->translation_domain));
        
	parent::WP_Widget( 'incsub_event_upcoming', __('Upcoming Events', $this->translation_domain), $widget_ops, $control_ops );
    }
    
    function widget($args, $instance) {
	global $wpdb, $current_site, $post, $wiki_tree;
	
	extract($args);
	
	$options = $instance;
	
	$title = apply_filters('widget_title', empty($instance['title']) ? __('Upcoming', $this->translation_domain) : $instance['title'], $instance, $this->id_base);
	$_events = get_posts('post_type=incsub_event&meta_key=incsub_event_start&orderby=meta_value&order=ASC&post_status=publish&numberposts=10');
	
	if (is_array($_events) && count($_events) > 0) {
	?>
	<?php echo $before_widget; ?>
	<?php echo $before_title . $title . $after_title; ?>
            <div id="event-popular">
		<ul>
		    <?php
			foreach ($_events as $_event) {
		    ?>
			<li><a href="<?php print get_permalink($_event->ID); ?>" class="<?php print ($_event->ID == $post->ID)?'current':''; ?>" ><?php print $_event->post_title; ?></a>
			</li>
		    <?php
			}
		    ?>
		</ul>
            </div>
        <br />
        <?php echo $after_widget; ?>
	<?php
	}
    }
    
    function update($new_instance, $old_instance) {
	$instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('Upcoming', $this->translation_domain), 'hierarchical' => 'yes') );
        $instance['title'] = strip_tags($new_instance['title']);
	
        return $instance;
    }
    
    function form($instance) {
	$instance = wp_parse_args( (array) $instance, array( 'title' => __('Upcoming', $this->translation_domain)));
        $options = array('title' => strip_tags($instance['title']));
	
	?>
	<div style="text-align:left">
            <label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;"><?php _e('Title', $this->translation_domain); ?>:<br />
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
            </label>
	    <input type="hidden" name="eab-submit" id="eab-submit" value="upcoming" />
	</div>
	<?php
    }
}
