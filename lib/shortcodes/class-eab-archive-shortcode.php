<?php

class Eab_Archive_Shortcode extends Eab_Codec {

	private $args = array();
	private $query = array();

	public function __construct( $args ) {
		$this->args  = $args;
		$this->query = $this->_to_query_args( $args );

		if ( $this->args['paged'] ) {
			$requested_page     = get_query_var( 'page' );
			$requested_page     = $requested_page ? $requested_page : get_query_var( 'paged' );
			$this->args['page'] = $requested_page ? $requested_page : $this->args['page'];
		}
	}

	public function output( $content = false ) {
		$output = $content;
		$method = false;

		$events = array();
		if ( is_multisite() && $this->args['network'] ) {
			$events = Eab_Network::get_upcoming_events( 30 );
		} else {
			$order_method = $this->args['order']
				? create_function( '', 'return "' . $this->args['order'] . '";' )
				: false;

			if ( $order_method ) {
				add_filter( 'eab-collection-date_ordering_direction', $order_method );
			}

			// Lookahead - depending on presence, use regular upcoming query, or poll week count
			if ( $this->args['lookahead'] ) {
				$method = $this->args['weeks']
					? create_function( '', 'return ' . $this->args['weeks'] . ';' )
					: false;

				if ( $method ) {
					add_filter( 'eab-collection-upcoming_weeks-week_number', $method );
				}

				$events = Eab_CollectionFactory::get_upcoming_weeks_events( $this->args['date'], $this->query );

				if ( $method ) {
					remove_filter( 'eab-collection-upcoming_weeks-week_number', $method );
				}
			} else {
				// No lookahead, get the full month only
				$events = Eab_CollectionFactory::get_upcoming_events( $this->args['date'], $this->query );
			}
			if ( $order_method ) {
				remove_filter( 'eab-collection-date_ordering_direction', $order_method );
			}
		}

		$args = $this->args;
		$output = Eab_Template::util_apply_shortcode_template( $events, $args );

		if ( $output ) {
			if ( $this->args['paged'] && ! ( is_multisite() && $this->args['network'] ) ) {
				if ( $method ) {
					add_filter( 'eab-collection-upcoming_weeks-week_number', $method );
				}
				$events_query = $this->args['lookahead']
					? Eab_CollectionFactory::get_upcoming_weeks( $this->args['date'], $this->query )
					: Eab_CollectionFactory::get_upcoming( $this->args['date'], $this->query );
				if ( $method ) {
					remove_filter( 'eab-collection-upcoming_weeks-week_number', $method );
				}
				$output .= eab_call_template( 'get_shortcode_paging', $events_query, $this->args );
			}
		}

		if ( ! $this->args['override_styles'] ) {
			wp_enqueue_style( 'eab_front' );
		}
		if ( ! $this->args['override_scripts'] ) {
			wp_enqueue_script( 'eab_event_js' );
			do_action( 'eab-javascript-do_enqueue_api_scripts' );
		}

		return $output;
	}
}