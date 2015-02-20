<?php

/**
 * SpectrOMSettings
 * Simplifies the use of the WordPress Settings API
 */
class SpectrOMSettings
{
	private $_args = NULL;

	public function __construct($args)
	{
		$this->_args = $args;

		// TODO: do sanity check on $args data

		add_action('admin_init', array(&$this, 'init_settings'));
	}

	/**
	 * Callback for the 'admin_init' action; used to initialize settings APIs
	 */
	public function init_settings()
	{
		register_setting(
			$this->/*get_option(), //*/get_group(),						// option group
			$this->get_option(),					// option name
			array(&$this, 'validate_options')		// validation callback
		);

		foreach ($this->_args['sections'] as $section_id => $section) {
			add_settings_section(
				$section_id,							// id
				$section['title'],						// title
				array(&$this, 'section_callback'),		// callback
				$this->get_page());						// page

			// add all the fields
			foreach ($section['fields'] as $field_id => $field) {
				add_settings_field(
					$field_id,								// id
					'<label for="' . $field_id. '">' . esc_html($field['title']) . '</label>',	// setting title
					array(&$this, 'display_field'),			// display callback
					$this->get_page(), // get_group(),		// settings page
					$section_id,							// settings section
					array($section_id, $field_id));
			}
		}
	}

	/**
	 * Displays the input field
	 * @param array $args An array with the $section_id in the first element and the $field_id value in the second element
	 * @throws Exception
	 */
	public function display_field($args)
	{
		$field = $this->_get_field($args[0], $args[1]);

		if (NULL !== $field) {
			$section_id = $args[0];
			$field_id = $args[1];

			if (!isset($field['value']))
				$field['value'] = '';

			switch ($field['type'])
			{
			case 'text':
				echo '<input type="text" id="', $field_id, '" name="', $section_id, '[', $field_id, ']" ';
				$this->_render_class('regular-text', $field);
				if (isset($field['value']))
					echo ' value="', esc_attr($field['value']), '" ';
				echo ' />', PHP_EOL;
				break;

			case 'select':
				echo '<select id="', $field_id, '" name="', $field_id, '">', PHP_EOL;
				if (isset($field['option-title']))
					echo '<option value="0">', esc_html($field['option-title']), '</option>', PHP_EOL;
				foreach ($field['options'] as $opt_name => $opt_value) {
					echo '<option value="', $opt_value, '" ';
					if ($opt_value == $field['value'])
						echo ' selected="selected" ';
					echo '>', esc_html($opt_name), '</option>', PHP_EOL;
				}
				echo '</select>', PHP_EOL;
				break;

			case 'radio':
				foreach ($field['options'] as $opt_name => $opt_value) {
					echo '<input type="radio" name="', $field_id, '" value="', $opt_name, '" >';
					echo '&nbsp;', esc_html($opt_value), '&nbsp;';
				}
				break;

			case 'checkbox':
				echo '<input type="checkbox" id="', $field_id, '" name="', $field_id, '" ';
				if (isset($field['value']) && $field['value'])
					echo ' checked="checked"';
				echo ' />';
				break;

			case 'textarea':
				echo '<textarea id="', $field_id, '" name="', $field_id, '" ';
				if (isset($field['size']) && is_array($field['size']))
					echo ' cols="', $field['size'][0], '" rows="', $field['size'][1], '" ';
				echo '>', esc_textarea($field['value']), '</textarea>';
				break;

			case 'button':
				echo '<button type="button" id="', $field_id, '" name="', $field_id, '" ';
				$this->_render_class('', $field);
				echo '>', esc_html($field['value']), '</button>';
				break;

			case 'password':
			case 'datepicker':
			case 'message':
			case 'custom':
				break;

			default:
				throw new Exception('unrecognized field type value: ' . $field['type']);
			}

			if (isset($field['afterinput']))
				echo '&nbsp;', esc_html($field['afterinput']);

			if (isset($field['description']))
				echo '<p class="description">', esc_html($field['description']), '</p>';
		}
	}

	/**
	 * Renders the class= attribute on the element being constructed
	 * @param string $class Class names to render
	 * @param array $field The $fields array object where more CSS class references are.
	 */
	private function _render_class($class, $field)
	{
		echo ' class="', $class, ' ';
		if (isset($field['class']))
			echo $field['class'];
		echo '" ';
	}

	/**
	 * Searches through data array looking for the named section
	 * @param string $section The section name if found, otherwise null
	 */
	private function _get_section($section)
	{
		if (isset($this->_args['sections'][$section]))
			return ($this->_args['sections'][$section]);
		return (NULL);
	}

	/*
	 * Retrieve the field array information from the section and field ids
	 * @param string $section The section id name to look for the field id under
	 * @param string $field The field id within the section to look for
	 * @returns array() the field array if found; otherwise NULL
	 */
	private function _get_field($section, $field)
	{
		if (isset($this->_args['sections'][$section]['fields'][$field]))
			return ($this->_args['sections'][$section]['fields'][$field]);
		return (NULL);
	}

	/**
	 * Callback function; outputs the header for the current section
	 * @param array $arg
	 */
	public function section_callback($arg)
	{

		$section_id = $arg['id'];
		$section = $this->_get_section($section_id);
		echo '<p>', $section['description'], '</p>', PHP_EOL;
	}

	/**
	 * Performs validation operations on posted data from form
	 * @param $input Input data
	 */
	public function validate_options($input)
	{
//OOSettings::log(__METHOD__.'()');
		//return ($valid);
////////
		$this->get_options();
		if (isset($input['name']) && !empty($input['name']))
			$valid['name'] = sanitize_text_field($input['name']);
		if (isset($input['phone']) && !empty($input['phone']))
			$valid['phone'] = sanitize_text_field($input['phone']);
		if (isset($input['key']) && !empty($input['key']))
			$valid['key'] = sanitize_text_field($input['key']);

		return ($valid);
	}

	public function get_page()
	{
		return ($this->_args['page']);
	}
	public function get_group()
	{
		return ($this->_args['group']);
	}
	public function get_option()
	{
		return ($this->_args['option']);
	}
	public function get_header($section = NULL)
	{
		if (NULL !== $section) {
			if (isset($this->_args['sections'][$section]['title']))
				return ($this->_args['sections'][$section]['title']);
		}
		return ('');
	}
	public function settings_fields($group = NULL)
	{
		if (NULL === $group)
			$group = $this->get_group();
		settings_fields($group);
	}
	public function settings_sections($section = NULL)
	{
		if (NULL === $section) {
			$sections = array($this->get_page()); // array_keys($this->_args['sections']);
		} else if (is_string($section)) {
			$sections = array($section);
		} else if (is_array($section)) {
			$sections = $section;
		} else {
			throw new Exception('unrecognized parameter type');
		}

		foreach ($sections as $sect) {
			do_settings_sections($sect);
		}
	}
}

// EOF