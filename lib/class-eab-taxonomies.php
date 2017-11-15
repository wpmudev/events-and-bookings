<?php

class Eab_Taxonomies {

	const POST_TYPE = 'incsub_event';

	public function register() {
		$this->register_post_type();
		$this->register_taxonomy();
		$this->register_post_status();
	}

	public function register_post_type() {
		$data = Eab_Options::get_instance();

		$labels = array(
			'name' => __('Events', eab_domain() ),
			'singular_name' => __('Event', eab_domain() ),
			'add_new' => __('Add Event', eab_domain() ),
			'add_new_item' => __('Add New Event', eab_domain() ),
			'edit_item' => __('Edit Event', eab_domain() ),
			'new_item' => __('New Event', eab_domain() ),
			'view_item' => __('View Event', eab_domain() ),
			'search_items' => __('Search Event', eab_domain() ),
			'not_found' =>  __('No event found', eab_domain() ),
			'not_found_in_trash' => __('No event found in Trash', eab_domain() ),
			'menu_name' => __('Events', eab_domain() )
		);

		$supports = array( 'title', 'editor', 'author', 'venue', 'thumbnail', 'comments');
		$supports = apply_filters('eab-event-post_type-supports', $supports);

		$event_type_args = array(
			'labels' => $labels,
			'public' => true,
			'show_ui' => true,
			'publicly_queryable' => true,
			'capability_type' => 'event',
			'hierarchical' => false,
			'map_meta_cap' => true,
			'query_var' => true,
			'supports' => $supports,
			'rewrite' => array( 'slug' => $data->get_option('slug'), 'with_front' => false ),
			'has_archive' => true,
			'menu_icon' => 'dashicons-calendar-alt',
		);

		register_post_type(
			self::POST_TYPE,
			apply_filters('eab-post_type-register', $event_type_args)
		);
	}

	public function register_taxonomy() {
		$data = Eab_Options::get_instance();

		register_taxonomy(
			'eab_events_category',
			Eab_EventModel::POST_TYPE,
			array(
				'labels' => array(
					'name' => __('Event Categories', eab_domain() ),
					'singular_name' => __('Event Category', eab_domain() ),
				),
				'hierarchical' => true,
				'public' => true,
				'rewrite' => array(
					'slug' => $data->get_option('slug'),
					'with_front' => true,
				),
				'capabilities' => array(
					'manage_terms' => 'manage_categories',
					'edit_terms' => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_events',
				),
			)
		);
	}

	public function register_post_status() {
		$pts_args = array(
			'show_in_admin_all_list' => false,
			'label' => __( 'Recurrent', 'eab' )
		);

		$pts_args['label_count'] = _n_noop(
			'Recurrent <span class="count">(%s)</span>',
			'Recurrent <span class="count">(%s)</span>',
			'eab'
		);

		if ( is_admin() )
			$pts_args['protected'] = true;
		else
			$pts_args['public'] = true;

		register_post_status(Eab_EventModel::RECURRENCE_STATUS, $pts_args);
	}
}