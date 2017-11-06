<?php

class Eab_Month_Navigation_Widget extends Eab_Widget {
	public function __construct () {
		$widget_ops = array(
			'classname' => __CLASS__, 
			'description' => __( 'Displays a form to navigation to other month archive.', $this->translation_domain ),
		);

		parent::__construct( __CLASS__, __( 'Monthly Event Archive Navigation', $this->translation_domain ), $widget_ops );
	}
	
	public function form ($instance) {
		$title		= isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$text		= isset( $instance['text'] ) ? esc_attr( $instance['text'] ) : '';
		$year_from 	= isset( $instance['year_from'] ) ? esc_attr( $instance['year_from'] ) : '';
		$year_to 	= isset( $instance['year_to'] ) ? esc_attr( $instance['year_to'] ) : '';
		
		$text 		= empty( $text ) ? __( 'Browse', $this->translation_domain ) : $text;
		$year_to 	= empty( $year_to ) ? date( 'Y' ) : $year_to;
		$year_from 	= empty( $year_from ) ? $year_to - 5 : $year_from;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Title:', $this->translation_domain ) ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'title' ) ?>" id="<?php echo $this->get_field_id( 'title' ) ?>" class="widefat" value="<?php echo $title ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'year_from' ) ?>"><?php _e( 'Year From:', $this->translation_domain ) ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'year_from' ) ?>" id="<?php echo $this->get_field_id( 'year_from' ) ?>" class="widefat" value="<?php echo $year_from ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'year_to' ) ?>"><?php _e( 'Year To:', $this->translation_domain ) ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'year_to' ) ?>" id="<?php echo $this->get_field_id( 'year_to' ) ?>" class="widefat" value="<?php echo $year_to ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'text' ) ?>"><?php _e( 'Browse Button Text:', $this->translation_domain ) ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'text' ) ?>" id="<?php echo $this->get_field_id( 'text' ) ?>" class="widefat" value="<?php echo $text ?>">
		</p>
		<?php
	}
	
	public function update ( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] 		= isset( $new_instance['title'] ) ? strip_tags($new_instance['title']) : '';
		$instance['text'] 		= isset( $new_instance['text'] ) ?strip_tags( $new_instance['text'] ) : '';
		$instance['year_from'] 	= isset( $new_instance['year_from'] ) ?strip_tags( $new_instance['year_from'] ) : '';
		$instance['year_to'] 	= isset( $new_instance['year_to'] ) ?strip_tags( $new_instance['year_to'] ) : '';
		
		delete_transient( $this->get_field_id( 'cache' ) );

		return $instance;
	}
	
	public function widget ( $args, $instance ) {
		extract($args);
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $before_widget;
		?>
		<div data-eab-widget_id="<?php echo (int) $this->number; ?>">
			<?php if ($title) echo $before_title . $title . $after_title; ?>
			<form action="#" post="post">
				<p>
					<label for="eab_widget_year"><?php _e( 'Select Year', $this->translation_domain ) ?></label>
					<select id="eab_widget_year">
						<?php for( $i = $instance['year_to']; $i >= $instance['year_from']; $i-- ) : ?>
						<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
						<?php endfor; ?>
					</select>
				</p>
				<p>
					<label for="eab_widget_month"><?php _e( 'Select Month', $this->translation_domain ) ?></label>
					<?php
						$months = array(
							__( 'January', $this->translation_domain ),
							__( 'February', $this->translation_domain ),
							__( 'March', $this->translation_domain ),
							__( 'April', $this->translation_domain ),
							__( 'May', $this->translation_domain ),
							__( 'June', $this->translation_domain ),
							__( 'July', $this->translation_domain ),
							__( 'August', $this->translation_domain ),
							__( 'September', $this->translation_domain ),
							__( 'October', $this->translation_domain ),
							__( 'November', $this->translation_domain ),
							__( 'December', $this->translation_domain )
						);
					?>
					<select id="eab_widget_month">
						<?php foreach( $months as $key => $month ) : ?>
						<option value="<?php echo $key + 1; ?>"><?php echo $month; ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php $text = empty( $instance['text'] ) ? __( 'Browse', $this->translation_domain ) : $instance['text']; ?>
				<p><input onclick="show_monthly_archive()" type="button" id="eab_widget_btn" value="<?php echo $text ?>"></p>
			</form>
			<?php $eab = events_and_bookings(); ?>
			<script type="text/javascript">
			function show_monthly_archive() {
				var year 	= document.getElementById( 'eab_widget_year' ).value,
					month 	= document.getElementById( 'eab_widget_month' ).value,
					url		= '<?php echo home_url($eab->_data->get_option('slug')) . '/'; ?>' + year + '/' + month;
				
				window.location.href = url;
				
				return false;
			}
			</script>
		</div>
		<?php
		echo $after_widget;	
	}
}