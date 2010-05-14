<?php

// Documentation: http://scribu.net/wordpress/scb-framework/scb-options.html

class scbOptions {

	protected $key;			// the option name

	protected $defaults;	// the default value(s)

	public $wp_filter_id;	// used by WP hooks

	/**
	 * Create a new set of options
	 *
	 * @param string $key Option name
	 * @param string $file Reference to main plugin file
	 * @param array $defaults An associative array of default values (optional)
	 */
	public function __construct($key, $file, $defaults = '') {
		$this->key = $key;
		$this->defaults = $defaults;

		scbUtil::add_activation_hook($file, array($this, '_update_reset'));

		scbUtil::add_uninstall_hook($file, array($this, 'delete'));
	}

	/**
	 * Get option name
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Get option values for one, many or all fields
	 *
	 * @param string|array $field The field(s) to get
	 * @return mixed Whatever is in those fields
	 */
	public function get($field = '') {
		$data = get_option($this->key);

		if ( is_array($this->defaults) )
			$data = (array) $data;
	
		return $this->_get($field, $data);
	}

	/**
	 * Get default values for one, many or all fields
	 *
	 * @param string|array $field The field(s) to get
	 * @return mixed Whatever is in those fields
	 */
	public function get_defaults($field = '') {
		return $this->_get($field, $this->defaults);
	}

	/**
	 * Set all data fields, certain fields or a single field
	 *
	 * @param string|array $field The field to update or an associative array
	 * @param mixed $value The new value (ignored if $field is array)
	 * @return null
	 */
	public function set($field, $value = '') {
		if ( is_array($field) )
			$newdata = $field;
		else
			$newdata = array($field => $value);

		$this->update(array_merge($this->get(), $newdata));
	}

	/**
	 * Reset option to defaults
	 *
	 * @return null
	 */
	public function reset() {
		$this->update($this->defaults, false);
	}

	/**
	 * Remove any keys that are not in the defaults array
	 *
	 * @return bool
	 */
	public function cleanup() {
		$this->update($this->_clean($this->get()));
	}

	/**
	 * Update raw data
	 *
	 * @param mixed $newdata
	 * @param bool $clean wether to remove unrecognized keys or not
	 * @return null
	 */
	public function update($newdata, $clean = true) {
		if ( $clean )
			$newdata = $this->_clean($newdata);

		update_option($this->key, $newdata);
	}

	/**
	 * Delete the option
	 *
	 * @return null
	 */
	public function delete() {
		delete_option($this->key);
	}


//_____INTERNAL METHODS_____


	// Add new fields with their default values
	function _update_reset() {
		if ( is_array($this->defaults) )
			$this->update(array_merge($this->defaults, $this->get()));
		else
			add_option($this->key, $this->defaults);
	}

	private function _clean($data) {
		if ( !is_array($data) || !is_array($this->defaults) )
			return $data;

		$r = array();
		foreach ( array_keys($this->defaults) as $key )
			$r[$key] = @$data[$key];

		return $r;
	}

	// Get one, more or all fields from an array
	private function _get($field, $data) {
		if ( empty($field) )
			return $data;

		if ( is_string($field) )
			return $data[$field];

		foreach ( $field as $key )
			if ( isset($data[$key]) )
				$result[] = $data[$key];

		return $result;
	}

	// Magic method: $options->field
	function __get($field) {
		return $this->get($field);
	}

	// Magic method: $options->field = $value
	function __set($field, $value) {
		$this->set($field, $value);
	}

	// Magic method: isset($options->field)
	function __isset($field) {
		$data = $this->get();
		return isset($data[$field]);
	}
}

