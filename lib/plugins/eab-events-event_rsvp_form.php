<?php
/*
Plugin Name: Event RSVP Form (Beta)
Description: A form builder to create your own RSVP form. Please make sure, "Additional registration fields", "RSVP with email address" and "Allow Facebook and Twitter Login?" are disabled.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 0.1 - Beta
Author: WPMU DEV
AddonType: Events
*/

if( ! class_exists( 'Eab_Events_CustomRSVPForm' ) )
{
    class Eab_Events_CustomRSVPForm
    {
        
        private $_data;
	
        private function __construct ()
        {
            $this->_data = Eab_Options::get_instance();
        }
        
        public static function serve ()
        {
            $me = new self();
            $me->_add_hooks();
        }
        
        private function _add_hooks ()
        {
            add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts') );
            add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts') );
            add_action( 'eab-settings-after_appearance_settings', array( $this, 'show_settings' ) );
            add_filter( 'eab-settings-before_save', array( $this, 'save_settings' ) );
            add_filter( 'eab_rsvps_form_end_before', array( $this, 'insert_custom_rsvp_form' ) );
			add_action( 'incsub_event_booking_yes_meta', array( $this, 'save_custom_form_values' ), 99, 3 );
			add_filter( 'eab_guest_list_username_after', array( $this, 'display_rsvp_extra_information' ), 99, 4 );
			add_action( 'template_redirect', array( $this, 'save_visitors_rsvp_info' ) );
			add_filter( 'eab-exporter-csv-row', array( $this, 'add_additional_data_in_csv_header' ), 99, 5 );
        }
        
        public function register_scripts()
        {
            wp_enqueue_style(
                'eab-form-builder-style',
                plugins_url( 'events-and-bookings/css/' ) . 'eab-form-builder.css',
                '',
                Eab_EventsHub::CURRENT_VERSION
            );
			
			wp_enqueue_script(
                'eab-form-builder-script',
                plugins_url( 'events-and-bookings/js/' ) . 'eab-form-builder.js',
                array( 'jquery', 'underscore' ),
                //Eab_EventsHub::CURRENT_VERSION,
				filemtime( EAB_PLUGIN_DIR . 'js/eab-form-builder.js' ),
                true
            );
			wp_localize_script( 'eab-form-builder-script', 'eabRSVP', array(
																		   'logged_in' => is_user_logged_in(),
																		   'error_msg' => __( 'You have missed one or more required fields', Eab_EventsHub::TEXT_DOMAIN )
																	) );

        }
        
        public function show_settings()
        {
            $data = $this->_data->get_option( 'eab_rsvp_element' );
            ?>
            <div id="eab-settings-rsvp-custom-form" class="eab-metabox postbox">
                <h3 class="eab-hndle"><?php _e( 'RSVP Builder', Eab_EventsHub::TEXT_DOMAIN ); ?></h3>
                <div class="eab_form_wrap">
                    <div class="eab_form_canvas">
                        <h4><?php _e( 'Select element from right side', Eab_EventsHub::TEXT_DOMAIN ); ?></h4>
                        <div class="eab_form_stage">
                            
                            <?php if( isset( $data['eab_element_id'] ) && count( $data['eab_element_id'] ) > 0 ) : ?>
                                <?php foreach( $data['eab_element_id'] as $id ) : ?>
                                
                                <div class="eab_element_rendered" id="<?php echo $id; ?>">
                                    <input type="hidden" name="eab_rsvp_element[eab_element_id][]" value="<?php echo $id; ?>">
                                    <input type="hidden" name="eab_rsvp_element[<?php echo $id; ?>][type]" value="<?php echo $data[$id]['type']; ?>">
                                    <h4><?php echo ucfirst( $data[$id]['type'] ) ?> <span>Remove</span></h4>
                                    <div class="eab_element_rendered_box">
                                        <table cellpadding="5" cellspacing="5" width="100%">
                                            <tr>
                                                <td width="100"><strong><?php _e( 'Label', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                                <td><input type="text" name="eab_rsvp_element[<?php echo $id; ?>][label]" value="<?php echo $data[$id]['label']; ?>"></td>
                                            </tr>
                                            <?php if( $data[$id]['type'] != 'text' && $data[$id]['type'] != 'textarea' ) : ?>
                                            <tr>
                                                <td width="100"><strong><?php _e( 'Values - comma separated', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                                <td><input type="text" name="eab_rsvp_element[<?php echo $id; ?>][values]" value="<?php echo $data[$id]['values']; ?>"></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td><strong><?php _e( 'Required?', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                                <td>
                                                    <input <?php echo $data[$id]['required'] == 0 ? 'checked' : '' ?> type="radio" name="eab_rsvp_element[<?php echo $id; ?>][required]" value="0"> <?php _e( 'No', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                                    <input <?php echo $data[$id]['required'] == 1 ? 'checked' : '' ?> type="radio" name="eab_rsvp_element[<?php echo $id; ?>][required]" value="1"> <?php _e( 'Yes', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php
                                if( isset( $id ) && $id != '' )
                                {
                                    $id = explode( 'eab_element_id_', $id );
                                    $id = $id[1] + 1;
                                }
                                else
                                {
                                    $id = 0;
                                }
                            ?>
                            <input type="hidden" name="eab_rsvp_element[]" id="eab_rsvp_id_flag" value="<?php echo $id; ?>">
                            
                        </div>
                    </div>
                    <div class="eab_form_elements">
                        <h4><?php _e( 'Elements', Eab_EventsHub::TEXT_DOMAIN ); ?></h4>
                        <div class="eab_elements">
                            <div class="eab_element" data-option="text"><?php _e( 'Text', Eab_EventsHub::TEXT_DOMAIN ); ?></div>
                            <div class="eab_element" data-option="textarea"><?php _e( 'Textarea', Eab_EventsHub::TEXT_DOMAIN ); ?></div>
                            <div class="eab_element" data-option="radio"><?php _e( 'Radio', Eab_EventsHub::TEXT_DOMAIN ); ?></div>
                            <div class="eab_element" data-option="checkbox"><?php _e( 'Checkbox', Eab_EventsHub::TEXT_DOMAIN ); ?></div>
                            <div class="eab_element" data-option="dropdown"><?php _e( 'Select Dropdown', Eab_EventsHub::TEXT_DOMAIN ); ?></div>
                        </div>
                    </div>
                    <div class="eab_clr"></div>
                </div>
            </div>
            <script type="text/template" class="eab_form_template_text">
                <div class="eab_element_rendered" id="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[eab_element_id][]" value="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[<%= data.uniqueID %>][type]" value="text">
                    <h4>Text <span>Remove</span></h4>
                    <div class="eab_element_rendered_box">
                        <table cellpadding="5" cellspacing="5" width="100%">
                            <tr>
                                <td width="100"><strong><?php _e( 'Label', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][label]"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e( 'Required?', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="0"> <?php _e( 'No', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="1"> <?php _e( 'Yes', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </script>
            
            <script type="text/template" class="eab_form_template_textarea">
                <div class="eab_element_rendered" id="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[eab_element_id][]" value="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[<%= data.uniqueID %>][type]" value="textarea">
                    <h4>Textarea <span>Remove</span></h4>
                    <div class="eab_element_rendered_box">
                        <table cellpadding="5" cellspacing="5" width="100%">
                            <tr>
                                <td width="100"><strong><?php _e( 'Label', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][label]"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e( 'Required?', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="0"> <?php _e( 'No', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="1"> <?php _e( 'Yes', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </script>
            
            <script type="text/template" class="eab_form_template_radio">
                <div class="eab_element_rendered" id="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[eab_element_id][]" value="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[<%= data.uniqueID %>][type]" value="radio">
                    <h4>Radio <span>Remove</span></h4>
                    <div class="eab_element_rendered_box">
                        <table cellpadding="5" cellspacing="5" width="100%">
                            <tr>
                                <td width="100"><strong><?php _e( 'Label', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][label]"></td>
                            </tr>
                            <tr>
                                <td width="100"><strong><?php _e( 'Values - comma separated', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][values]"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e( 'Required?', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="0"> <?php _e( 'No', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="1"> <?php _e( 'Yes', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </script>
            
            <script type="text/template" class="eab_form_template_checkbox">
                <div class="eab_element_rendered" id="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[eab_element_id][]" value="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[<%= data.uniqueID %>][type]" value="checkbox">
                    <h4>Checkbox <span>Remove</span></h4>
                    <div class="eab_element_rendered_box">
                        <table cellpadding="5" cellspacing="5" width="100%">
                            <tr>
                                <td width="100"><strong><?php _e( 'Label', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][label]"></td>
                            </tr>
                            <tr>
                                <td width="100"><strong><?php _e( 'Values - comma separated', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][values]"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e( 'Required?', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="0"> <?php _e( 'No', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="1"> <?php _e( 'Yes', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </script>
            
            <script type="text/template" class="eab_form_template_dropdown">
                <div class="eab_element_rendered" id="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[eab_element_id][]" value="<%= data.uniqueID %>">
                    <input type="hidden" name="eab_rsvp_element[<%= data.uniqueID %>][type]" value="dropdown">
                    <h4><?php _e( 'Dropdown', Eab_EventsHub::TEXT_DOMAIN ); ?> <span><?php _e( 'Remove', Eab_EventsHub::TEXT_DOMAIN ); ?></span></h4>
                    <div class="eab_element_rendered_box">
                        <table cellpadding="5" cellspacing="5" width="100%">
                            <tr>
                                <td width="100"><strong><?php _e( 'Label', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][label]"></td>
                            </tr>
                            <tr>
                                <td width="100"><strong><?php _e( 'Values - comma separated', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td><input type="text" name="eab_rsvp_element[<%= data.uniqueID %>][values]"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e( 'Required?', Eab_EventsHub::TEXT_DOMAIN ); ?></strong></td>
                                <td>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="0"> <?php _e( 'No', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                    <input type="radio" name="eab_rsvp_element[<%= data.uniqueID %>][required]" value="1"> <?php _e( 'Yes', Eab_EventsHub::TEXT_DOMAIN ); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </script>
            <?php
        }
        
        public function save_settings( $options )
        {
            if( ! empty( $_POST['eab_rsvp_element'] ) ) $options['eab_rsvp_element'] = $_POST['eab_rsvp_element'];
            return $options;
        }
        
        public function insert_custom_rsvp_form( $html )
        {
            ob_start();
            $data = $this->_data->get_option( 'eab_rsvp_element' );
            ?>
            <?php if( isset( $data['eab_element_id'] ) && count( $data['eab_element_id'] ) > 0 ) : ?>
			<div class="eab_rsvp_custom_form">
				<?php if( ! is_user_logged_in() ) : global $post; ?>
				<form action="#" method="post">
				<input type="hidden" name="eab_rsvp_custom_form_with_email" value="1">
				<input type="hidden" name="eab_rsvp_custom_form[eab_event_id]" value="<?php echo $post->ID ?>">
				<?php endif; ?>
					<table cellpadding="5" cellspacing="5">
					<?php foreach( $data['eab_element_id'] as $id ) : ?>
						<?php $required = isset( $data[$id]['required'] ) ? $data[$id]['required'] : 0; ?>
						<tr class="eab_field_<?php echo $required == 1 ? 'required' : '' ?>">
							<td valign="top"><?php echo $data[$id]['label']; ?> <?php echo $required == 1 ? '*' : '' ?></td>
							<?php if( $data[$id]['type'] != 'text' && $data[$id]['type'] != 'textarea' ) : ?>
							<td class="eab_field_col"><?php echo $this->_render_element( $data[$id]['type'], $data[$id]['label'], $data[$id]['values'] ) ?></td>
							<?php else : ?>
							<td class="eab_field_col"><?php echo $this->_render_element( $data[$id]['type'], $data[$id]['label'] ) ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					<?php if( ! is_user_logged_in() ) : ?>
						<tr class="eab_field_required">
							<td valign="top"><?php _e( 'Email', Eab_EventsHub::TEXT_DOMAIN ); ?> *</td>
							<td class="eab_field_col"><?php echo $this->_render_element( 'text', 'eab_email' ) ?></td>
						</tr>
					<?php endif; ?>
					</table>
					<div class="eab_rsvp_form_error_msg"></div>
					<input class="button eab_rsvp_form_submit" type="submit" name="action_yes" value="<?php _e( 'Join', Eab_EventsHub::TEXT_DOMAIN ); ?>">
				<?php if( ! is_user_logged_in() ) : ?>
				</form>
				<?php endif; ?>
			</div>
            <?php endif; ?>
            <?php
            $html = $html . ob_get_clean();
            
            return $html;
        }
        
        protected function _render_element( $type, $label, $values = '' )
        {
            switch( $type )
            {
                case 'text':
                    $html = '<input type="text" name="eab_rsvp_custom_form[' . $this->_label_to_name( $label ) . ']">';
                    break;
                
                case 'textarea':
                    $html = '<textarea name="eab_rsvp_custom_form[' . $this->_label_to_name( $label ) . ']"></textarea>';
                    break;
                
                case 'radio':
                    $html = '';
                    $values = explode( ',', $values );
                    $values_trimmed = array_map( 'trim', $values );
                    foreach( $values_trimmed as $key => $value )
                    {
                        $html .= '<label><input type="radio" name="eab_rsvp_custom_form[' . $this->_label_to_name( $label ) . ']" value="' . $value . '"> ' . $values[$key] . ' </label>';
                    }
                    break;
                
                case 'checkbox':
                    $html = '';
                    $values = explode( ',', $values );
                    $values_trimmed = array_map( 'trim', $values );
                    foreach( $values_trimmed as $key => $value )
                    {
                        $html .= '<label><input type="checkbox" name="eab_rsvp_custom_form[' . $this->_label_to_name( $label ) . '][]" value="' . $value . '"> ' . $values[$key] . ' </label>';
                    }
                    break;
                
                case 'dropdown':
                    $html = '<select name="eab_rsvp_custom_form[' . $this->_label_to_name( $label ) . ']">';
                    $values = explode( ',', $values );
                    $values_trimmed = array_map( 'trim', $values );
                    foreach( $values_trimmed as $key => $value )
                    {
                        $html .= '<option value="' . $value . '">' . $value . '</option>';
                    }
                    $html .= '</select>';
                    break;
            }
            
            return $html;
        }
        
        protected function _label_to_name( $label )
        {
            return strtolower( str_replace( ' ', '_', $label ) );
        }
		
		public function save_custom_form_values( $event_id, $user_id, $post )
		{
			update_user_meta( $user_id, $this->_create_user_meta_name( $event_id, $user_id ), $post['eab_rsvp_custom_form'] );
		}
		
		protected function _create_user_meta_name( $event_id, $user_id )
		{
			return 'eab_event_form_' . $event_id . '_user_' . $user_id;
		}
		
		public function display_rsvp_extra_information( $content, $event, $user_id, $user_data )
		{
			$user_meta = get_user_meta( $user_id, $this->_create_user_meta_name( $event->get_id(), $user_id ) );
			$data = $this->_data->get_option( 'eab_rsvp_element' );
			
			$html = '';
			$html .= '<div class="rsvp_extra_info">';
				foreach( $data['eab_element_id'] as $id ) :
				$html .= '<div class="rsvp_extra_info_fields">';
					$value = $user_meta[0][ $this->_label_to_name( $data[$id]['label'] ) ];
					
					if( is_array( $value ) ) $value = implode( ', ', $value );
					
					$html .= $data[$id]['label'] . ': ' . $value;
				$html .= '</div>';
				endforeach;
			$html .= '</div>';
			
			return $content . $html;
		}
		
		public function save_visitors_rsvp_info()
		{
			
			
			if( isset( $_POST['eab_rsvp_custom_form_with_email'] ) && $_POST['eab_rsvp_custom_form_with_email'] == 1 )
			{
				$eab_rsvp_custom_form = $_POST['eab_rsvp_custom_form'];
				$user = $this->_create_user( $eab_rsvp_custom_form['eab_email'] );
				
				if ( is_object( $user ) && ! empty( $user->ID ) )
				{
					$this->_login_user( $user );
					$eab = events_and_bookings();
					$eab->process_rsvps( $eab_rsvp_custom_form['eab_event_id'], $user->ID );
				}
			}
			
		}
		
		private function _create_user ( $email )
		{
			list( $username, $domain ) = explode( '@', $email, 2 );
			$username = sanitize_user( trim( $username ) );
			while ( username_exists( $username ) ) {
				$username .= rand( 0, 9 );
			}
	
			$password = wp_generate_password( 12, false );
			$user_id = wp_create_user( $username, $password, $email );
	
			if ( empty( $user_id ) || is_wp_error( $user_id ) ) return false;
	
			// Notification email??
			if( apply_filters( 'eab_custom_rsvp_user_notification_mail', true ) )
			{
				wp_new_user_notification( $user_id, $password );
			}
	
			return get_userdata( $user_id );
			
		}
		
		private function _login_user ( $user )
		{
			if ( empty( $user->ID ) || empty( $user->user_login ) ) return false;
			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID ); // Logged in with email, yay
			do_action( 'wp_login', $user->user_login );
		}
		
		public function add_additional_data_in_csv_header( $headers, $event, $booking, $user_data )
		{
			$user_meta = get_user_meta( $user_data->id, $this->_create_user_meta_name( $event->get_id(), $user_data->id ) );
			$data = $this->_data->get_option( 'eab_rsvp_element' );
			
			foreach( $data['eab_element_id'] as $id )
			{
				$value = $user_meta[0][ $this->_label_to_name( $data[$id]['label'] ) ];
				if( is_array( $value ) ) $value = implode( ', ', $value );
				
				$headers[ $data[$id]['label'] ] = $value;
			}
			
			return $headers;
		}
        
    }
    
    Eab_Events_CustomRSVPForm::serve();
}