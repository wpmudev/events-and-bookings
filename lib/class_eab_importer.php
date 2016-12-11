<?php

abstract class Eab_Importer {

/* ----- Interface ----- */

	/**
	 * Fetches source and maps raw data to array.
	 * @param  mixed $source Implementation-specific source for importing.
	 * @return array         Raw elements map
	 */
	public abstract function map_to_raw_events_array ($source);

	/**
	 * Maps raw importable element to Event post type.
	 * @param  mixed $source Raw importable
	 * @return array         Associated array suitable for `wp_insert_post`
	 */
	public abstract function map_to_post_type ($source);

	/**
	 * Maps raw importable element to a set of Event meta fields.
	 * @param  mixed $source Raw importable
	 * @return array         Array of meta fields.
	 */
	public abstract function map_to_post_meta ($source);

	/**
	 * Check if the raw importable has already been imported.
	 * @param  mixed  $source Raw importable
	 * @return boolean         True if imported, false otherwise
	 */
	public abstract function is_imported ($source);

	/**
	 * Check if the raw importable is a recurring event.
	 * @param  mixed  $source Raw importable
	 * @return boolean         True if recurring, false otherwise
	 */
	public abstract function is_recurring ($source);


/* ----- Shared code, helpers ----- */
	
	public function import_events ($source) {
                remove_action( 'transition_post_status',     '_transition_post_status',                  5, 3 );
		$events = (array)$this->map_to_raw_events_array($source);
		foreach ($events as $raw) {
			if (!$this->is_imported($raw)){
                                $this->import_event($raw);
                        }
		}
                add_action( 'transition_post_status',     '_transition_post_status',                  5, 3 );
	}

	public function import_event ($source) {
		if ($this->is_recurring($source)) return false; // We currently do not support recurring events
		$post = $this->map_to_post_type($source);
		if (!$post || !is_array($post)) return false;
		$post['post_type'] = Eab_EventModel::POST_TYPE;
		
		$meta = $this->map_to_post_meta($source);
                
		$post_id = wp_insert_post($post);
		if (!$post_id) return false; // Log error

		foreach ($meta as $key => $value) {
			update_post_meta($post_id, $key, $value);
		}
		return true;
	}
}


abstract class Eab_ScheduledImporter extends Eab_Importer {

	protected function __construct () {
		$this->_add_hooks();
	}

	/**
	 * Implementation specific schedule check.
	 * @return bool True to execute import, false otherwise.
	 */
	public abstract function check_schedule ();

	/**
	 * Implementation specific schedule update.
	 */
	public abstract function update_schedule ();

	/**
	 * Implementation-specific root import method.
	 * Required for modified blind runner implementation.
	 */
	public abstract function import ();

	public function check_import_schedule () {
		if (!$this->check_schedule()) return false;
		$this->import();
		$this->update_schedule();
	}

	protected function _add_hooks () {
		add_action('eab_scheduled_jobs', array($this, 'check_import_schedule'));
	}
}