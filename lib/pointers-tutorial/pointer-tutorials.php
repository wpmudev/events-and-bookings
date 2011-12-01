<?php
/*
Pointer Tutorials Module
By Aaron Edwards (Incsub)
http://uglyrobot.com/

Copyright 2011-2012 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( !class_exists( 'Pointer_Tutorial' ) ) {
	
	/*
	* class Pointer_Tutorial
	*
	* @author Aaron Edwards (Incsub)
	* @version 1.0
	* @requires 3.3
	*
	*	@param string $tutorial_name Required: The name of this tutorial. Used for user settings and css classes.
	*	@param bool $redirect_first_load Optional: Set to true to redirect and show first step for those who have not completed the tutorial. Default true
	*	@param bool $force_completion Optional: Set to true to redirect and show the current step for those who have not completed the tutorial. Basically forces the tutorial to be completed or dismissed. Default false.
	*/
	class Pointer_Tutorial {
		
		private $registered_pointers = array();
		private $page_pointers = array();
		private $tutorial_name = '';
		private $admin_css = '';
		private $textdomain = 'pointers';
		private $capability = 'manage_options';
		
		//these are public in case you need to change them directly after registering the tutorial
		public $redirect_first_load = true;
		public $force_completion = false;
		
		/*
		 * function add_step
		 *
		 *	Create your tutorial using this method. 
		 * 
		 *	@param string $tutorial_name Required: The name of this tutorial. Used for user settings and css classes.
		 *	@param bool $redirect_first_load Optional: Set to true to redirect and show first step for those who have not completed the tutorial. Default true
		 *	@param bool $force_completion Optional: Set to true to redirect and show the current step for those who have not completed the tutorial. Basically forces the tutorial to be completed or dismissed. Default false.
		 */
		function __construct( $tutorial_name, $redirect_first_load = true, $force_completion = false ) {
			global $wp_version;
			
			//requires WP 3.3
			if ( version_compare($wp_version, '3.3-beta4', '<') )
				return false;
			
			$this->tutorial_name = sanitize_key( $tutorial_name );
			$this->redirect_first_load = $redirect_first_load;
			$this->force_completion = $force_completion;
		}
		
		/*
		 * function add_step
		 *
		 *	Register your individual steps using this method. 
		 * 
		 *	@param string $url Required: The admin url of the step. Can be just index.php, but better to pass a full url from admin_url() or network_admin_url() functions.
		 *	@param string $hook Required: This is the wordpress hook suffix for the page. This is returned by add_menu_page() or can be nabbed from the $hook_suffix global
		 *	@param string $selector Required: The jQuery selector to attach the pointer to. It should only select one DOM element.
		 *	@param string $title Optional: The title of the pointer. Leave empty to add no title/icon. No HTML allowed.
		 *	@param array|string $args Required: The javascript arguments for the pointer jQuery plugin. content, position, pointerClass, pointerWidth, etc.
		 */
		public function add_step( $url, $hook, $selector, $title, $args ) {
			
			//add title if given
			if ( !empty($title) )
				$args['content'] = '<h3>' . esc_js($title) . '</h3>' . $args['content'];
			
			//if urls are incomplete grab them
			if ( strpos( $url, '://' ) === false )
				$url = is_network_admin() ? network_admin_url($url) : admin_url($url);
			
			//register the pointer	
			$this->registered_pointers[] = array( 'url' => $url, 'hook' => $hook, 'selector' => $selector, 'title' => $title, 'args' => $args );
		}
		
		/*
		 * function set_capability
		 *
		 *	Customizes the capability the user requires to view this tutorial.
		 * 
		 *	@param string $capability the wordpress capability. Defaults to manage_options
		 */
		public function set_capability( $capability ) {
			$this->capability = trim( $capability );
		}
		
		/*
		 * function set_textdomain
		 *
		 *	Customizes the textdomain for translating buttons and such.
		 * 
		 *	@param string $domain the textdomain for i18n
		 */
		public function set_textdomain( $domain ) {
			$this->textdomain = trim( $domain );
		}
		
		/*
		 * function add_style
		 *
		 *	A shortcut to customize the css for the entire tutorial. Use this to change colors, fonts, etc.
		 * 
		 *	@param string $css the css selectors and attributes that will be printed inside <style> tags
		 */
		public function add_style( $css ) {
			$this->admin_css .= "\n" . trim($css);
		}
		
		/*
		 * function add_icon
		 *
		 *	A shortcut to override the title with a custom icon of your choosing for the entire tutorial.
		 *	If you need to customize the icons for individual steps use add_style.
		 * 
		 *	@param string $url Url to the icon image file. Should be 32x32 normally
		 */
		public function add_icon( $url ) {
			$this->add_style( '.wpmudev_dashboard-pointer .wp-pointer-content h3:before { background-image: url("' . $url . '"); }' );
		}
		
		/*
		 * function initialize
		 *
		 *	Call after setting up the tutorial to initialize it and make it active
		 */
		public function initialize() {
			
			if ( !current_user_can($this->capability) )
				return false;
			
			$this->catch_tutorial_start(); //load start listener
			
			$current_step = get_user_meta( get_current_user_id(), "current-{$this->tutorial_name}-step", true );
			
			// entire tutorial has been dismissed
			if ( $current_step >= count($this->registered_pointers) )
				return;
			
			if ( is_admin() && !defined('DOING_AJAX') ) {
				//if first load redirect is true and on first step force us there
				if ( $this->redirect_first_load && !$current_step && strpos( $this->registered_pointers[0]['url'], $_SERVER['REQUEST_URI'] ) === false ) {
					wp_redirect( $this->registered_pointers[0]['url'] );
					exit;
				}
				
				//if force_completion is true and on first step force us there
				if ( $this->force_completion && $current_step && strpos( $this->registered_pointers[$current_step]['url'], $_SERVER['REQUEST_URI'] ) === false ) {
					wp_redirect( $this->registered_pointers[$current_step]['url'] );
					exit;
				}
			}
			
			add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
			add_action( "wp_ajax_dismiss-{$this->tutorial_name}-pointer", array( &$this, 'ajax_dismiss' ) );
		}
		
		/*
		 * function start_link
		 *
		 *	Returns a url that can be linked to that when clicked starts the tutorial at a given step.
		 *	Must be called after steps are registered.
		 * 
		 *	@param int $step What step to link to start at. Defaults to first step.
		 *	@return string|bool Url to put in a link or false if they don't have that capability.
		 */
		public function start_link($step = 0) {
			if ( !current_user_can($this->capability) )
				return false;
			
			return add_query_arg( array($this->tutorial_name.'-start' => $step), $this->registered_pointers[$step]['url'] );
		}
		
		/*
		 * function restart	
		 *
		 * Restarts the tutorial at the given step with a redirect if neccessary.
		 * Must be called before headers are sent and after steps are registered.
		 *
		 * 	@param int $step What step to link to start at. Defaults to first step.
		 */
		public function restart($step = 0) {
			update_user_meta( get_current_user_id(), "current-{$this->tutorial_name}-step", $step );
			$this->force_completion = true; //set temporarily so it will redirect if necessary
		}
		
		
		
		
		/* ---------------- Private Internal Methods ---------------- */
		/* ---------------------------------------------------------- */
		
		/**
		 * Initializes the new feature pointers.
		 *
		 */
		function enqueue_scripts( $hook_suffix ) {	

			// Get current step
			$current_step = (int) get_user_meta( get_current_user_id(), "current-{$this->tutorial_name}-step", true );
			
			$temp_array = array_slice( $this->registered_pointers, $current_step, 1000, true );
			foreach ($temp_array as $key => $val) {
				if ( $val['hook'] == $hook_suffix )
					$this->page_pointers[$key] = $val;
				else
					break; //drop out on first pointer on another page
			}

			//skip if no page pointers for this page
			if ( !count($this->page_pointers) )
				return;
			
			//add any custom css
			add_action( 'admin_print_styles', array(&$this, 'admin_styles') );
			
			// Bind pointer print function
			add_action( 'admin_print_footer_scripts', array( &$this, 'print_footer_list' ) );
	
			// Add pointers script and style to queue
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );
		}
		
		/**
		 * Prints the admin css.
		 *
		 */
		function admin_styles() {
			if ( !empty( $this->admin_css ) ) {
				echo '<style type="text/css">';
				echo $this->admin_css;
				echo "\n</style>\n";
			}
		}
		
		/**
		 * Handles the AJAX step complete callback.
		 *
		 */
		function ajax_dismiss() {
			if ( !is_numeric($_POST['pointer']) )
				die( '0' );
							
			$pointer = intval($_POST['pointer']) + 1;
		
			update_user_meta( get_current_user_id(), "current-{$this->tutorial_name}-step", $pointer );
			die( '1' );
		}
		
		/**
		 * Listens for clicks to start/restart a tutorial, or jumping to a step.
		 *
		 */
		function catch_tutorial_start() {
			if ( is_admin() && isset($_GET[$this->tutorial_name.'-start']) )
				$this->restart( intval($_GET[$this->tutorial_name.'-start']) );
		}
		
		/**
		 * Print the pointer javascript data in the footer.
		 */
		function print_footer_list() {
			?>
			<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
			<?php
			$count = 0;
			foreach ( $this->page_pointers as $pointer_id => $settings) {
				$count++;
				
				extract( $settings );
				
				//add our tutorial class for styling
				if (empty($args['pointerClass']))
					$args['pointerClass'] = $this->tutorial_name . '-pointer';
				else
					$args['pointerClass'] .= ' ' . $this->tutorial_name . '-pointer';
				
				//add our buttons
				//$args['content'] .= '<div class="wp-pointer-buttons">';
				//$args['content'] .= '<a class="previous" href="#" style="float:left;">&laquo; Previous</a>';
				//$args['content'] .= '<a class="next" href="#" style="float:right;">Next &raquo;</a>';
				//$args['content'] .= '</div>';
				
				//get next link thats on a different page
				$next_link = '';
				$next_pointer = '';
				$next_name = __('Next &raquo;', $this->textdomain);
				if ( $count >= count($this->page_pointers) && isset($this->registered_pointers[$pointer_id+1]) ) {
					$next_url = $this->registered_pointers[$pointer_id+1]['url'];
					$next_link = ", function() { window.location = '$next_url'; }";
					$next_title = $this->registered_pointers[$pointer_id+1]['title'];
				} else if ( isset($this->page_pointers[$pointer_id+1]) ) {
					$next_pointer = $this->page_pointers[$pointer_id+1]['selector'];
					$next_pointer_id = $pointer_id + 1;
					$next_pointer = "$('$next_pointer').pointer( options$next_pointer_id ).pointer('open');";
					$next_title = $this->page_pointers[$pointer_id+1]['title'];
				} else {
					$next_name = __('Dismiss', $this->textdomain);
					$next_title = __('Dismiss this tutorial', $this->textdomain);
				}
				
				?>
				var options<?php echo $pointer_id; ?> = <?php echo json_encode( $args ); ?>;
	
				options<?php echo $pointer_id; ?> = $.extend( options<?php echo $pointer_id; ?>, {
					close: function() {
						$.post( ajaxurl, {
							pointer: '<?php echo $pointer_id; ?>',
							action: 'dismiss-<?php echo $this->tutorial_name; ?>-pointer'
						}<?php echo $next_link; ?>);
						<?php echo $next_pointer; ?>
					},
					buttons: function( event, t ) {
						var button = $('<a class="next button" href="#" title="<?php echo esc_attr($next_title); ?>"><?php echo $next_name; ?></a>');
						return button.bind( 'click.pointer', function() {
							t.element.pointer('close');
							return false;
						});
					}
				});
				
				<?php if ($count == 1) { ?>
				$('<?php echo $selector; ?>').pointer( options<?php echo $pointer_id; ?> ).pointer('open');
				<?php
				}
			}
			
			?>
			});
			//]]>
			</script>
			<?php
			
		}
	
	}
}
?>