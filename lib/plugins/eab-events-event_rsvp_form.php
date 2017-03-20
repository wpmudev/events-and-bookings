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
																		   'logged_in' => is_user_logged_in()
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
                        <h4><?php _e( 'Select element from left side', Eab_EventsHub::TEXT_DOMAIN ); ?></h4>
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
                    <h4>Dropdown <span>Remove</span></h4>
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
                <table cellpadding="5" cellspacing="5">
                <?php foreach( $data['eab_element_id'] as $id ) : ?>
                    <tr>
                        <td><?php echo $data[$id]['label']; ?></td>
                        <?php if( $data[$id]['type'] != 'text' && $data[$id]['type'] != 'textarea' ) : ?>
                        <td><?php echo $this->_render_element( $data[$id]['type'], $data[$id]['label'], $data[$id]['values'] ) ?></td>
                        <?php else : ?>
                        <td><?php echo $this->_render_element( $data[$id]['type'], $data[$id]['label'] ) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </table>
				<input class="button" type="submit" name="action_yes" value="<?php _e( 'Join', Eab_EventsHub::TEXT_DOMAIN ); ?>">
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
        
    }
    
    Eab_Events_CustomRSVPForm::serve();
}