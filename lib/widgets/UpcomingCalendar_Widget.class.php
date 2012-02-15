<?php

class Eab_CalendarUpcoming_Widget extends Eab_Widget {
	
	function __construct () {
		$widget_ops = array('classname' => __CLASS__, 'description' => __('Displays List of Upcoming Events from your entire network', $this->translation_domain));
		
		add_action('wp_print_styles', array($this, 'css_load_styles'));
		add_action('wp_print_scripts', array($this, 'js_load_scripts'));
		
		parent::WP_Widget(__CLASS__, __('Calendar Upcoming', $this->translation_domain), $widget_ops);
	}
	
	function css_load_styles () {
		wp_enqueue_style('eab-upcoming_calendar_widget-style', plugins_url('events-and-bookings/css/upcoming_calendar_widget.css'));
	}

	function js_load_scripts () {
		wp_enqueue_script('eab-upcoming_calendar_widget-script', plugins_url('events-and-bookings/js/upcoming_calendar_widget.js'), array('jquery'));
	}
	
	function form($instance) {
		$title = esc_attr($instance['title']);
		$date = esc_attr($instance['date']);
		
		$html .= '<p>';
		$html .= '<label for="' . $this->get_field_id('title') . '">' . __('Title:', 'wdfb') . '</label>';
		$html .= '<input type="text" name="' . $this->get_field_name('title') . '" id="' . $this->get_field_id('title') . '" class="widefat" value="' . $title . '"/>';
		$html .= '</p>';
/*
		$html .= '<p>';
		$html .= '<label for="' . $this->get_field_id('limit') . '">' . __('Display only this many events:', 'wdfb') . '</label>';
		$html .= '<select name="' . $this->get_field_name('limit') . '" id="' . $this->get_field_id('limit') . '">';
		for ($i=1; $i<11; $i++) {
			$html .= '<option value="' . $i . '" ' . (($limit == $i) ? 'selected="selected"' : '') . '>' . $i . '</option>';
		}
		$html .= '</select>';
		$html .= '</p>';
*/		
		echo $html;
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['date'] = strip_tags($new_instance['date']);

		return $instance;
	}
	
	function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$date = $instance['date'];
		
		$date = time(); // Refactor
		$events = $this->_get_events($date);
	
		echo $before_widget;
		if ($title) echo $before_title . $title . $after_title;
		if ($events) {
			$year = date('Y', $date);
			$month = date('m', $date);
			$time = strtotime("{$year}-{$month}-01");
			/*
			echo sprintf(
            	__('Events for %s', Booking::$_translation_domain),
            	date("M Y", $time)
			);
			 */
			?>
			<table width="100%" class="eab-upcoming_calendar_widget">
                		<thead>
                			<tr>
                				<th>S</th>
                				<th>M</th>
                				<th>T</th>
                				<th>W</th>
                				<th>T</th>
                				<th>F</th>
                				<th>S</th>
                			</tr>
                		</thead>
                		<tbody>
                    <?php
						$days = (int)date('t', $time);
						$first = (int)date('w', strtotime(date('Y-m-01', $time)));
						$last = (int)date('w', strtotime(date('Y-m-' . $days, $time)));
								
						$pad_bottom = $last ? 6 - $last : 0;
						
						$post_info = array();
						foreach ($events as $post) {
							$event_starts = get_post_meta($post->ID, 'incsub_event_start');
							$event_ends = get_post_meta($post->ID, 'incsub_event_end');
							$post_info[] = array(
								'id' => $post->ID,
								'title' => $post->post_title,
								'event_starts' => $event_starts,
								'event_ends' => $event_ends,
							);
						}

						echo ($first ? '<tr><td colspan="' . $first . '">&nbsp;</td>' : '<tr>');
						for ($i=1; $i<=$days; $i++) {
							$date = date('Y-m-' . sprintf("%02d", $i), $time);
							$dow = (int)date('w', strtotime($date));
							$current_day_start = strtotime("{$date} 00:00"); 
							$current_day_end = strtotime("{$date} 23:59");
							if (0 == $dow) echo '</tr><tr>';
							
							$titles = array();
							$event_data = array();
							foreach ($post_info as $ipost) {
								for ($k = 0; $k < count($ipost['event_starts']); $k++) {
									$start = strtotime($ipost['event_starts'][$k]);
									$end = strtotime($ipost['event_ends'][$k]);
									if ($start < $current_day_end && $end > $current_day_start) {
										$titles[] = esc_attr($ipost['title']);
										$event_data[] = '<a class="wpmudevevents-upcoming_calendar_widget-event" href="' . get_permalink($ipost['id']) . '">' . 
											$ipost['title'] .
											'<span class="wpmudevevents-upcoming_calendar_widget-event-info">' . 
												date_i18n(get_option('date_format'), $start) . ' ' . get_eab_event_venue($ipost['id']) .
											'</span>' .
										'</a>'; 
									}
								}
							} 
							
							$activity = '';
							if ($titles && $event_data) {
								$ttl = join(', ', $titles);
								$einfo = join('<br />', $event_data);
								$activity = "<p><a href='#' title='{$ttl}'>{$i}</a><span class='wdpmudevevents-upcoming_calendar_widget-info_wrapper' style='display:none'>{$einfo}</span></p>";
							} else {
								$activity = "<p>{$i}</p>";
							}
							$today = ($date == date('Y-m-d')) ? 'class="today"' : '';
							echo "<td {$today}>{$activity}</td>";
						}
						
						echo ($pad_bottom ? '<td colspan="' . $pad_bottom . '">&nbsp;</td></tr>' : '</tr>');
				?>
					</tbody>
				</table>
			<?php
		} else {
			echo '<p>' . __('No upcoming events on network', $this->translation_domain) . '</p>';
		}
		echo $after_widget;	
	}

	private function _get_events ($date_stamp) {
		$time = $date_stamp ? $date_stamp : time();
		$year = (int)date('Y', $time);
		$month = date('m', $time);
		$time = strtotime("{$year}-{$month}-01");
		
		$start_month = $month ? sprintf("%02d", $month) : date('m');
		if ($start_month < 12) {
			$end_month = sprintf("%02d", (int)$month+1);
			$end_year = $year;
		} else {
			$end_month = '01';
			$end_year = $year+1;
		}				
		
		$events_query = new WP_Query(array(
		 	'post_type' => 'incsub_event',
    		'suppress_filters' => false, 
    		'meta_query' => array(
    			array(
        			'key' => 'incsub_event_start',
        			'value' => "{$end_year}-{$end_month}-01 00:00",
        			'compare' => '<',
        			'type' => 'DATETIME'
    			),
    			array(
        			'key' => 'incsub_event_end',
        			'value' => "{$year}-{$start_month}-01 00:00",
        			'compare' => '>=',
        			'type' => 'DATETIME'
    			),
    		)
		));
		return $events_query->posts;
	}
}
