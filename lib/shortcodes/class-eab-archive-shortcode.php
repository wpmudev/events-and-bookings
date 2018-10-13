<?php

class Eab_Archive_Shortcode extends Eab_Codec {

	private $args = array();
	private $query = array();

	public function __construct( $args ) {
		$this->args  = $args;

		if ( $this->args['paged'] ) {
			$requested_page     = get_query_var( 'page' );
			$requested_page     = $requested_page ? $requested_page : get_query_var( 'paged' );
			$this->args['page'] = $requested_page ? $requested_page : $this->args['page'];
		}
		
		$this->query = $this->_to_query_args( $this->args );
	}

	public function output( $content = false ) {
		$output = $content;
		$method = false;

		$events = array();
		
		if ( is_multisite() && $this->args['network'] ) {
			$events = Eab_Network::get_archive_events( 30 );
		} else {
			$order_method = $this->args['order']
				? create_function( '', 'return "' . $this->args['order'] . '";' )
				: false;

			if ( $order_method ) {
				add_filter( 'eab-collection-date_ordering_direction', $order_method );
			}

            if ( $this->args['end_date'] ) {
			    $start = !empty($this->args['date']) ? $this->args['date'] : eab_current_time();
			    $start_date = create_function( '', 'return "' . date('Y-m-d', $start) .' 00:00";');
			    $end_date = create_function( '', 'return "' . date('Y-m-d', $this->args['end_date']) . ' 23:59";');
			    
			    add_filter('eab-collection-date_range_start', $start_date);
			    add_filter('eab-collection-date_range_end', $end_date); 

			    $events = Eab_CollectionFactory::get_date_range_events( $start, $this->query );
			    
			    remove_filter( 'eab-collection-date_range_start', $start_date );
			    remove_filter( 'eab-collection-date_range_end', $end_date );
            } elseif ( $this->args['day_only']) {
			    $date = !empty($this->args['date']) ? $this->args['date'] : eab_current_time();
			    $ddate = create_function( '', 'return "' . date('Y-m-d', $date) .'";');
			    
			    add_filter('eab-collection-daily_events_date', $ddate);

			    $events = Eab_CollectionFactory::get_daily_events( $date, $this->query );
			    
			    remove_filter( 'eab-collection-daily_events_date', $ddate );
			} else {
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
			}
			if ( $order_method ) {
				remove_filter( 'eab-collection-date_ordering_direction', $order_method );
			}
		}
                
		if( $this->args['network'] && is_multisite() && $this->args['categories'] ) {
			$events = $this->_get_network_events_by_categories( $events );
		}
		elseif( $this->args['network'] && is_multisite() && $this->args['category'] ) {
			$events = $this->_get_network_events_by_category( $events );
		}

		$args = $this->args;
		$output = Eab_Template::util_apply_shortcode_template( $events, $args );

		if ( $output ) {
			if ( $this->args['paged'] && ! ( is_multisite() && $this->args['network'] ) ) {
			    if ( $this->args['end_date'] ) {
					add_filter('eab-collection-date_range_start', $start_date);
					add_filter('eab-collection-date_range_end', $end_date);
				
					$events_query = Eab_CollectionFactory::get_date_range( $start, $this->query );
				
					remove_filter( 'eab-collection-date_range_start', $start_date );
					remove_filter( 'eab-collection-date_range_end', $end_date );
                } elseif ($this->args['day_only']) {
					add_filter('eab-collection-daily_events_date', $ddate);
				
					$events_query = Eab_CollectionFactory::get_daily( $date, $this->query );

					remove_filter( 'eab-collection-daily_events_date', $ddate );
			    } else {
					if ( $method ) {
						add_filter( 'eab-collection-upcoming_weeks-week_number', $method );
					}
					$events_query = $this->args['lookahead']
						? Eab_CollectionFactory::get_upcoming_weeks( $this->args['date'], $this->query )
						: Eab_CollectionFactory::get_upcoming( $this->args['date'] , $this->query);
					if ( $method ) {
						remove_filter( 'eab-collection-upcoming_weeks-week_number', $method );
					}
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
        
	private function _get_network_events_by_categories( $events ) {
		if( $this->args['categories']['type'] == 'id' ) {
			if( count( $this->args['categories']['value'] ) > 1 ) {
				$sites = wp_get_sites();
				$cats = array();

				foreach( $sites as $site ) {
					switch_to_blog( $site['blog_id'] );

					foreach( $this->args['categories']['value'] as $cat ) {
						$term = get_term( $cat, 'eab_events_category' );
						
						if( ! is_object( $term ) ) continue;
						
						if( $term->slug != '' ) {
							$cats[] = $term->slug;
						}
					}

					restore_current_blog();
				}
			}
		}

		$modified_events = array();
		foreach( $events as $event ) {
			switch_to_blog( $event->blog_id );

			$terms = wp_get_object_terms( $event->ID,  'eab_events_category' );
			$t = array();
			foreach( $terms as $val ) {
				$t[] = $val->slug;
			}

			$commonElements = array_intersect( $t, $cats );

			if( count( $commonElements ) > 0 ) {
				$modified_events[] = $event;
			}

			restore_current_blog();
		}

		return count( $modified_events ) > 0 ? $modified_events : $events;
	}
	
	private function _get_network_events_by_category( $events ) {
		if( $this->args['category']['type'] == 'id' ) {
			$sites = wp_get_sites();
			$cats = array();

			foreach( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

					$term = get_term( $this->args['category']['value'], 'eab_events_category' );
					
					if( ! is_object( $term ) ) continue;
					
					if( $term->slug != '' ) {
						$cats[] = $term->slug;
					}

				restore_current_blog();
			}
		} elseif( $this->args['category']['type'] == 'slug' ) {
			$cats = array( $this->args['category']['value'] );
		}

		$modified_events = array();
		foreach( $events as $event ) {
			switch_to_blog( $event->blog_id );

			$terms = wp_get_object_terms( $event->ID,  'eab_events_category' );
			$t = array();
			foreach( $terms as $val )
			{
				$t[] = $val->slug;
			}

			$commonElements = array_intersect( $t, $cats );

			if( count( $commonElements ) > 0 )
			{
				$modified_events[] = $event;
			}

			restore_current_blog();
		}

		return count( $modified_events ) > 0 ? $modified_events : $events;
	}
}