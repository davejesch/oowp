<?php

if (!class_exists('SpectrOMBaseModel')) {

class SpectrOMBaseModel
{
	protected $_table = NULL;
	protected $_key = NULL;
	protected $_wpdb = NULL;
	protected $_last_sql = NULL;

	public function __construct()
	{
		global $wpdb;
		$this->_wpdb = $wpdb;

		if (NULL === $this->_table)
			throw new Exception('Need to specify table name for model class');
	}

	public function get_last_sql()
	{
		return ($this->_last_sql);
	}

	/**
	 * Performs a database lookup for a single row
	 * @param mixed $id The value of the key to search on
	 * @param mixed $key The optional name of the column to search for the value `$id`
	 * @param array $where A list of additional WHERE conditions to add to the search
	 * @return object The found database row or NULL if not found
	 * @throws Exception If no default key name can be determined
	 */
	public function get($id, $key = NULL, $where = NULL)
	{
		$key = (NULL === $key) ? $this->_key : $key;
		if (NULL === $key)
			throw new Exception('No default key specified for this model');

		$sql = "SELECT *
			FROM `{$this->_wpdb->prefix}{$this->_table}`
			WHERE `{$key}` = %s ";
		if (is_array($where))
			$sql .= ' AND ' . implode(' AND ', $where);
		$sql .= ' LIMIT 1 ';

		$this->_last_sql = $this->_wpdb->prepare($sql, $id);
		$data = $this->_wpdb->get_row($this->_last_sql, OBJECT);
		return ($this->_data = $data);
	}

	public function find($id, $key, $where = NULL)
	{
		$sql = "SELECT *
			FROM `{$this->_wpdb->prefix}{$this->_table}`
			WHERE `{$key}` = %s ";
		if (NULL !== $where)
			$sql .= ' AND ' . implode(' AND ', $where);
		$sql .= ' LIMIT 1 ';

		$data = $this->_wpdb->get_row($this->_wpdb->prepare($sql, $id), OBJECT);
		return ($this->_data = $data);
	}

	public function run_update($id, $key = NULL, $data = array(), $types = NULL)
	{
		if (empty($data))
			throw new Exception('data array is empty');
		
		$key = (NULL === $key) ? $this->_key : $key;
		if (NULL === $key)
			throw new Exception('No default key specified for this model');

		$sql = " UPDATE `{$this->_wpdb->prefix}{$this->_table}`
			({$column_names}) VALUES ({$values})
			WHERE `{$key}`='" . esc_sql($id) . "'
			LIMIT 1 ";
		$res = $this->_wpdb->query($sql);
		return ($res);
	}

	/**
	 * Construct a list of data names and values formatted for SQL statements
	 * @param array $data An associative array of key=value pairs of data to be constructed into the statement
	 * @param array $types An array of type values to help with formatting the data portion of the statement
	 * @return string The resulting statement section that looks like: (`column`,`names`) VALUES ('' ... '')
	 */
	protected function _make_values($data, $types = NULL)
	{
		// build the (`column`,`names`) part of the statement
		$keys = array_keys($data);
		$column_names = '`' . implode('`,`', $keys) . '`';

		// build the VALUES ('' ... '') part of the statement
		$values = '';
		$comma = '';
		foreach ($data as $key => $val) {
			$values .= $comma;
			if (NULL === $val)
				$values .= 'NULL';
			else if (is_numeric($val))
				$values .= $val;
			else if (is_string($val))
				$values .= '\'' . esc_sql($val) . '\'';
			$comma = ',';
		}

		// put the pieces together
		return (" { {$column_names} ) VALUES ( {$values} ) ");
	}
	protected function _make_where($where, $code = 'AND')
	{
		if (NULL === $where)
			return ('');

		if (!in_array($code, array('AND', 'OR')))
			throw new Exception('unrecognized operator: "' . $code . '"');

		$ret = '';
		$op = '';
		foreach ($where as $key => $val) {
			if (is_array($val)) {
				$ret .= $op . $this->_make_where($val['where'], isset($val['code']) ? $val['code'] : ' AND ');
			} else {
				$value = '';
				if (NULL === $val)
					$value = 'IS NULL';
				else if (is_numeric($val))
					$value = '=' . intval($val);
				else if (is_string($val))
					$value = '=\'' . esc_sql($val) . '\'';
				else
					throw new Exception('unrecognized type');
				$ret .= " {$op} `{$key}` {$value} ";
			}
			$op = $code;
		}

		return ($ret);
	}
}

} // class_exists

// EOF