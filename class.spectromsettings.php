<?php

/**
 * SpectrOMSettings
 * Simplifies the use of the WordPress Settings API
 */
if (!class_exists('SpectrOMSettings', FALSE)) {

class SpectrOMSettings
{
	private $_args = NULL;
	private $_output_style = FALSE;
	private $_errors = NULL;

	public function __construct($args)
	{
		$this->_args = $args;

		// TODO: do sanity check on $args data

		// create a 'id' element within the fields[] array
		foreach ($this->_args['sections'] as $section_id => &$section) {
			foreach ($section['fields'] as $field_id => &$field) {
				$field['id'] = $field_id;
			}
		}

		add_action('admin_init', array(&$this, 'init_settings'));
	}

	/**
	 * Callback for the 'admin_init' action; used to initialize settings APIs
	 */
	public function init_settings()
	{
		if (isset($_GET['settings-updated'])) {
			$err = get_settings_errors();
			$errors = array();
			foreach ($err as $msg) {
				if ('general' !== $msg['setting'] && 'settings_updated' !== $msg['code'])
					$errors[] = $msg;
			}
			$this->_errors = $errors;
			global $wp_settings_errors;
			$wp_settings_errors = array();
			if (0 == count($this->_errors))
				add_action('admin_notices', array(&$this, 'saved_notice'));
			else
				add_action('admin_notices', array($this, 'error_notice'));
		}

		register_setting(
			$this->get_group(),								// option group
			$this->get_option(),							// option name
			array(&$this, 'validate_options')				// validation callback
		);

		foreach ($this->_args['sections'] as $section_id => $section) {
			add_settings_section(
				$section_id,								// id
				$section['title'],							// title
				array(&$this, 'section_callback'),			// callback
				$this->get_page());							// page

			// add all the fields
			foreach ($section['fields'] as $field_id => $field) {
				$label = '<label for="' . $field_id. '"' .
					(isset($field['tooltip']) ? ' title="' . esc_attr($field['tooltip']) . '" ' : '') .
					'>' . esc_html($field['title']) .
					($this->_is_required($field) ? '<span class="required">*</span>' : '') .
					'</label>';

				add_settings_field(
					$field_id,								// id
					$label,									// setting title
					array(&$this, 'display_field'),			// display callback
					$this->get_page(),						// settings page
					$section_id,							// settings section
					array($section_id, $field_id));
			}
		}
	}

	/**
	 * Outputs a notice letting the user know there were errors
	 */
	public function error_notice()
	{
		echo '<div class="error settings-error">';
		echo '<p><strong>';
		printf(_n('There was %1$d error with the form contents.',
				'There were %1$d errors with the form contents.',
				count($this->_errors), 'spectrom'),
				count($this->_errors));
		echo '</strong></p>';
		echo '</div>';
	}

	/**
	 * Outputs a notice letting the user know their settings were saved
	 */
	public function saved_notice()
	{
		echo '<div class="updated fade">';
		echo '<p><strong>', __('Your changes have been saved.', 'ooplugin'), '</strong></p>';
		echo '</div>';
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

			$field_name = $this->get_option() . '[' . $field_id . ']';
			switch ($field['type'])
			{
			case 'text':
			case 'password':
				if ('password' === $field['type'])
					$type = 'password';
				else
					$type = 'text';
				echo '<input type="', $type, '" id="', $field_id, '" name="', $field_name, '" ';
				$this->_render_class('regular-text', $field);
				if (isset($field['value']))
					echo ' value="', esc_attr($field['value']), '" ';
				echo ' />', PHP_EOL;
				break;

			case 'select':
				echo '<select id="', $field_id, '" name="', $field_name, '">', PHP_EOL;
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
					if ($field['value'] === $opt_name)
						$checked = ' checked="checked" ';
					else
						$checked = '';
					echo '<input type="radio" name="', $field_name, '" value="', $opt_name, '"', $checked, ' >';
					echo '&nbsp;', esc_html($opt_value), '&nbsp;';
				}
				break;

			case 'checkbox':
				echo '<input type="checkbox" id="', $field_id, '" name="', $field_name, '" ';
				if (isset($field['value']) && $field['value'])
					echo ' checked="checked"';
				echo ' />';
				break;

			case 'textarea':
				echo '<textarea id="', $field_id, '" name="', $field_name, '" ';
				if (isset($field['size']) && is_array($field['size']))
					echo ' cols="', $field['size'][0], '" rows="', $field['size'][1], '" ';
				echo '>', esc_textarea($field['value']), '</textarea>';
				break;

			case 'button':
				echo '<button type="button" id="', $field_id, '" name="', $field_name, '" ';
				$this->_render_class('', $field);
				echo '>', esc_html($field['value']), '</button>';
				break;

			case 'message':
				break;

			case 'custom':
				do_action('spectrom_settings_output_field', $field);
				break;

			case 'datepicker':
				break;

			default:
				throw new Exception('unrecognized field type value: ' . $field['type']);
			}

			// check for any errors
			$err = $this->_get_errors($field_id);
			if (0 !== count($err)) {
				foreach ($err as $msg) {
					echo '<p class="spectrom-error">', esc_html($msg), '</p>';
				}
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
	 * Checks if the current field is required
	 * @param array $field The array that describes the field to be checked
	 * @return boolean TRUE if the 'required' rule is in the validation rules; otherwise FALSE
	 */
	private function _is_required($field)
	{
		$rules = explode(' ', isset($field['validation']) ? $field['validation'] : '');
		if (in_array('required', $rules))
			return (TRUE);
		return (FALSE);
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

	/**
	 * Retrieve a list of errors for the named settings id
	 * @param string $name The name of the settings id to look for
	 * @return array The list of errors found for the name settings id
	 */
	private function _get_errors($name)
	{
		$ret = array();
		if (NULL !== $this->_errors) {
			foreach ($this->_errors as $error) {
				if ($name === $error['setting'])
					$ret[] = $error['message'];
			}
		}
		return ($ret);
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
		if (!$this->_output_style) {
			$this->_output_style = TRUE;
			echo '<style>', PHP_EOL;
			echo 'table.form-table th, table.form-table td { padding: 5px 5px }', PHP_EOL;
			echo 'table.form-table label span.required { color: red; margin-left: .7em }', PHP_EOL;
			echo 'table.form-table input:hover, table.form-table textarea:hover { border: 1px solid #777700 }', PHP_EOL;
			echo 'table.form-table input.invalid { border: 1px solid red }', PHP_EOL;
			echo 'p.spectrom-error { color: #dd3d36; border-left: 4px solid #dd3d36; padding-left: 8px; }', PHP_EOL;
			echo '</style>', PHP_EOL;
		}

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
		$valid = array();
		$validator = new SpectrOMValidation();

		if (NULL !== $input) {
			foreach ($this->_args['sections'] as $section_id => $section) {
				foreach ($section['fields'] as $field_id => $field) {
					$data = '';
					if (isset($input[$field_id]))
						$data = $input[$field_id];

					$is_valid = TRUE;
					if (isset($field['validation'])) {
						$rules = explode(' ', $field['validation']);
						$is_valid = $validator->validate($data, $rules, $field);
					}

					if ($is_valid)
						$valid[$field_id] = $data;
					// if the ['revert_unvalidated'] settings form option is present, use the old value from the form field
					else if (isset($this->_args['revert_unvalidated']))
						$valid[$field_id] = isset($field['value']) ? $field['value'] : '';
				}
			}
		}
		return ($valid);
	}

	/**
	 * Get the ['page'] settings form option
	 * @return string The ['page'] value from the settings form array argument
	 */
	public function get_page()
	{
		return ($this->_args['page']);
	}

	/**
	 * Get the ['group'] settings form option
	 * @return string The ['group'] value from the settings form array argument
	 */
	public function get_group()
	{
		return ($this->_args['group']);
	}

	/**
	 * Get the ['option'] settings form option
	 * @return string The ['option'] value from the settings form array argument
	 */
	public function get_option()
	{
		return ($this->_args['option']);
	}

	/**
	 * Retrieves the ['title'] element from the settings section
	 * @param string $section The id of the settings section to retrieve the title from
	 * @return string The ['title'] value from the named settings section
	 */
	public function get_header($section = NULL)
	{
		if (NULL !== $section) {
			if (isset($this->_args['sections'][$section]['title']))
				return ($this->_args['sections'][$section]['title']);
		}
		return ('');
	}

	/**
	 * Wrapper method for the settings_fields() function. Outputs the settings fields for the named settings group
	 * @param string $group The settings group name or NULL to use the default group name from the arguments
	 */
	public function settings_fields($group = NULL)
	{
		if (NULL === $group)
			$group = $this->get_group();
		settings_fields($group);
	}

	/**
	 * Wrapper method for the settings_sections() function. Outputs the settings field for hte settings section
	 * @param string $section The name of the section. Can be NULL to output all for the page, a string for a single section, or an array for multiple sections.
	 * @throws Exception If the $section value is unrecognized
	 */
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

		foreach ($sections as $sect)
			do_settings_sections($sect);
	}
}

} // class_exists

// EOF