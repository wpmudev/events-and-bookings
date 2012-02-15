<?php
global $booking, $wpdb, $wp_query;
$year = $wp_query->query_vars['event_year'];
$year = $year ? $year : date('Y');
$month = $wp_query->query_vars['event_monthnum'];
$month = $month ? $month : date('m');
$time = strtotime("{$year}-{$month}-01");

get_header( 'event' );
?>
	<div id="primary">
            <div id="wpmudevevents-wrapper">
                <h2><?php echo sprintf(
                	__('Events for %s', Booking::$_translation_domain),
                	date("M Y", $time)
				); ?></h2>
                <div class="wpmudevevents-list">
                	<table width="100%">
                		<thead>
                			<tr>
                				<th>Sunday</th>
                				<th>Monday</th>
                				<th>Tuesday</th>
                				<th>Wednesday</th>
                				<th>Thursday</th>
                				<th>Friday</th>
                				<th>Saturday</th>
                			</tr>
                		</thead>
                		<tbody>
                    <?php
						$days = (int)date('t', $time);
						$first = (int)date('w', strtotime(date('Y-m-01', $time)));
						$last = (int)date('w', strtotime(date('Y-m-' . $days, $time)));
								
						$pad_bottom = $last ? 6 - $last : 0;
						
						$post_info = array();
						foreach ($wp_query->posts as $post) {
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
							
							$activity = '';
							foreach ($post_info as $ipost) {
								for ($k = 0; $k < count($ipost['event_starts']); $k++) {
									$start = strtotime($ipost['event_starts'][$k]);
									$end = strtotime($ipost['event_ends'][$k]);
									if ($start < $current_day_end && $end > $current_day_start) {
										$activity .= '<a class="wpmudevevents-calendar-event" href="' . get_permalink($ipost['id']) . '">' . 
											$ipost['title'] .
											'<span class="wpmudevevents-calendar-event-info">' . 
												date_i18n(get_option('date_format'), $start) . ' ' . get_eab_event_venue($ipost['id']) .
											'</span>' . 
										'</a>';
									}
								}
							} 
							
							$activity = $activity ? $activity : '<p>&nbsp;</p>';
							$today = ($date == date('Y-m-d')) ? 'class="today"' : '';
							echo "<td {$today}>{$i}<br />{$activity}</td>";
						}
						
						echo ($pad_bottom ? '<td colspan="' . $pad_bottom . '">&nbsp;</td></tr>' : '</tr>');
				?>
					</tbody>
				</table>
				
				<div class="event-pagination">
					<?php 
						$prev = date('Y/m', ($time - 28*86400)); 
						$next = date('Y/m', ($time + 32*86400));
					?>
					<a href="<?php echo site_url("/events/{$prev}"); ?>">Prev</a>
					<a href="<?php echo site_url("/events/{$next}"); ?>">Next</a>
				</div>
		</div>
	</div>
<script type="text/javascript">
(function ($) {
$(function () {
// Info popups
$(".wpmudevevents-calendar-event")
	.mouseenter(function () {
		$(this).find(".wpmudevevents-calendar-event-info").show();
	})
	.mouseleave(function () {
		$(this).find(".wpmudevevents-calendar-event-info").hide();
	})
;
});
})(jQuery);
</script>
<?php get_footer( 'event' ); ?>
