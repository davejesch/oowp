<?php

if (!class_exists('SpectrOMValidation', FALSE)) {

class SpectrOMValidation
{
	private $_errors = array();

	protected $_error_messages = array();

	protected $_custom_callback = NULL;
	protected $_custom_error = NULL;

	public $options = array();
	public $type = NULL;
	public $param = NULL;

	public function __construct()
	{
		// Set message per type
		$this->_error_messages = array(
			'required' => __('This field is required.', 'spectrom'),
			'numeric' => __('This field must be a number.', 'spectrom'),
			'email' => __('This field must be an email.', 'spectrom'),
			'alphanumeric' => __('This field only accepts alphanumeric characters.', 'spectrom'),
			'alpha' => __('This field only accepts alpha letters.', 'spectrom'),
			'name' => __('This field only accepts alpha letters, spaces, dashes(-), and apostrophes(\').', 'spectrom'),
			'past' => __('Please enter a date in the past.', 'spectrom'),
			'maxlen' => __('This field is too long, should be no more than %d characters.', 'spectrom'),
			'minlen' => __('This field is too short, should be at least %d characters.', 'spectrom'),
			'website' => __('This field must be a valid website.', 'spectrom'),
			'date' => __('This field must be a valid date.', 'spectrom'),
			'positive' => __('This field must be positive.', 'spectrom'),
			'int' => __('This field must be int value.', 'spectrom'),
			'maxval' => __('This field value should be no more than %d.', 'spectrom'),
			'minval' => __('This field value should be at least %d.', 'spectrom'),
			'password' => __('The password should be at least %d characters.', 'spectrom'),
			'custom' => '%s',
			'unknown' => __('Unrecognized validation rule: "%s"', 'spectrom'),
		);
	}

	/**
	 * Validate value based on type
	 * @param  mixed $value The value to be validated
	 * @param  array $rules An array containing the validation rules to check against
	 * @return boolean TRUE if the data is valid according to all the rules; otherwise FALSE
	 */
	public function validate(&$value, $rules = array())
	{
		$results = TRUE;
		$param = 0;

		foreach ($rules as $rule) {
			if (FALSE !== strpos($rule, ':'))
				list($type, $param) = explode(':', $rule, 2);
			else
				$type = $rule;

			switch ($type)
			{
			case 'positive':
				if ($value < 0)
					$results = $this->add_message($type);
				break;

			case 'int':
				if (!ctype_digit($value))
					$results = $this->add_message($type);
				break;

			case 'required':
				if ('' === trim($value))
					$results = $this->add_message($type);
				break;

			case 'numeric':
				if (!is_numeric($value))
					$results = $this->add_message($type);
				break;

			case 'email':
				if (!is_email($value))
					$results = $this->add_message($type);
				break;

			case 'alphanumeric':
				$comp = str_replace('_', '', $value);
//				return (empty($comp) ? TRUE : ctype_alnum($comp));
				if (!empty($comp) && !ctype_alnum($comp))
					$results = $this->add_message($type);
				break;

			case 'alpha':
				$comp = str_replace(' ', '', $value); // allow spaces
//				return (empty($comp) ? TRUE : ctype_alpha($comp));
				if (!empty($comp) && !ctype_alnum($comp))
					$results = $this->add_message($type);
				break;

			case 'name':
				$comp = str_replace(array(' ', '-', '\''), '', $value); // allow spaces, dash and apostrophe
//				return (empty($comp) ? TRUE : ctype_alpha($comp));
				if (!empty($comp) && !ctype_alnum($comp))
					$results = $this->add_message($type);
				break;

			case 'maxlen':
				 if (strlen($value) > intval($param))
					 $results = $this->add_message($type, intval($param));
				 break;

			case 'minlen':
				if (strlen($value) < intval($param))
					$results = $this->add_message($type, intval($param));
				break;

			case 'maxval':
				if ($value > $param)
					$results = $this->add_message($type, $param);
				break;

			case 'minval':
				if ($value < $param)
					$results = $this->add_message($type, $param);
				break;

			case 'past':
				if (strtotime($value) >= time())
					$results = $this->add_message($type);
				break;

			case 'website':
				$v = trim($value);
				if (!empty($v)) {		// accept empty values
					if (FALSE === strpos($value, '://'))
						$value = 'http://' . $value;

					if (FALSE === filter_var($value, FILTER_VALIDATE_URL))
						$results = $this->add_message($type);
				}
				break;

			case 'date':
//				$d = new DateTime($value);
//				return ($d && $d->ToString('Y-m-d') == $value);
				$comp = strtotime($value);
				if (0 === $comp)
					$results = $this->add_message($type);
				break;

			case 'password':
				$comp = trim($value);
				if (!empty($v) && strlen($comp) > intval($param))
					$results = $this->add_message($type, intval($param));
				break;

			case 'custom':
				if (NULL !== $this->_custom_callback && NULL !== $this->_custom_error &&
					call_user_func_array($this->_custom_callback, array($value)))
					$results = $this->add_message($type, $this->_custom_error);
				break;

			default:
				$results = $this->add_message('unknown', $type);
				break;
			}
		}

		return ($results);
	}

	public function set_custom_validation($callback, $error_msg)
	{
		$this->_custom_callback = $callback;
		$this->_custom_error = $error_msg;
	}

	/**
	 * Adds a message to this list of validation exceptions
	 * @param string $type The validation rule name to display the corresponding validation error for
	 * @param int $param The parameter value to display within the error message or NULL
	 * @return boolean Always returns a boolean FALSE
	 */
	private function _add_message($type, $param = NULL)
	{
		if (NULL === $param)
			$this->_errors[] = $this->_error_messages[$type];
		else
			$this->_errors[] = sprintf($this->_error_messages[$type], $param);
		return (FALSE);
	}

	/**
	 * Return error messages from validation
	 * @return array
	 */
	public function get_errors()
	{
		return ($this->_errors);
	}
}

} // class_exists

// EOF
