<?php

require('class.spectrombasemodel.php');

if (!class_exists('SpectrOMQueryBuilder')) {

/**
 * Query builder class constructs a query based on information provided in several method calls
 */
class SpectrOMQueryBuilder extends SpectrOMBaseModel
{
	protected $_check_columns = FALSE;				// default column checking to OFF
	protected $_columns = NULL;						// list of column in table; used with column checking
	protected $_last_query = NULL;					// the constructed query last built

	protected $_select = array();					// list of columns for the SELECT
	protected $_from = NULL;						// name of table to SELECT from
	protected $_joins = array();					// list of JOIN types
	protected $_where = array();					// list of WHERE conditions; this is an array of stdClass object
	protected $_where_group = NULL;					// list of WHERE conditions within a group
	protected $_where_group_condition = NULL;		// the condition used for the WHERE group
	protected $_order_by = NULL;					// the ordering specifier
	protected $_order = NULL;						// the ORDERing column
	protected $_group = NULL;						// GROUP BY column
	protected $_page_items = NULL;					// number of items for the LIMIT clause
	protected $_page = NULL;						// the page number for the LIMIT clause

	/**
	 * Sets the column checking feature. Recommended to be ON during development
	 * @param mixed $check The new column checking status
	 * @return object A reference to the current class
	 */
	public function set_check_columns($check)
	{
		if ($check)
			$this->_check_columns = TRUE;
		else
			$this->_check_columns = FALSE;
		return ($this);
	}

	public function last_query()
	{
		return ($this->_last_query);
	}

	/**
	 * Determines if a given column name exists among the table's column names
	 * @param string $name The name of the column
	 * @return boolean TRUE if the column exists otherwise FALSE
	 */
	public function has_column($name)
	{
		if (NULL === $this->_columns) {
			$sql = "SHOW COLUMNS FROM `{$this->_wpdb->prefix}{$this->_table}`";
			$res = $this->_wpdb->get_results($sql, ARRAY_A);
			$this->_columns = array();
			foreach ($res as $row)
				$this->_columns[] = $row['Field'];
		}
		// check for a table reference (table.column) in the name
		// return TRUE since the column is referenced in another table and we can't verify it
		if (FALSE !== strpos($name, '.'))
			return (TRUE);
		if (in_array($name, $this->_columns))
			return (TRUE);
		return (FALSE);
	}

	/**
	 * Escape data and optionally enclose within quotes
	 * @param mixed $data The data to be escaped
	 * @param string $quote The quoting style to use
	 * @return string The escaped data
	 */
	public function escape($data, $quote = '')
	{
		return ($quote . esc_sql($data) . $quote);
	}

	/**
	 * Resets all of the data used to track the constructed query
	 * @return object A reference to the current class
	 */
	public function reset()
	{
		$this->_select = array();
		$this->_joins = array();
		$this->_where = array();
		$this->_from = $this->_where_group = $this->_where_group_condition =
			$this->_order_by = $this->_order =
			$this->_group =
			$this->_page_items = $this->_page = NULL;
		return ($this);
	}

	/**
	 * Run a query from the current set of data, returning a single row.  Resets the query data after running
	 * @param constant $type The data set type to return, one of: OBJECT, ARRAY_A, ARRAY_N
	 * @return mixed The results of the query
	 */
	public function run($type = ARRAY_A)
	{
		$sql = $this->_build_query();
		$res = $this->_wpdb->get_row($sql, $type);
		$this->reset();
		return ($res);
	}

	/**
	 * Run a query from the current set of data, returning multiple rows. Resets the query data after running
	 * @param constant $type The data set type to return, one of: OBJECT, ARRAY_A, ARRAY_N
	 * @return mixed The results of the query
	 */
	public function results($type = ARRAY_A)
	{
		$sql = $this->_build_query();
		$res = $this->_wpdb->query($sql, $type);
		$this->reset();
		return ($res);
	}

	/**
	 * Constructs an DELETE statement from the currently tracked WHERE clauses and the $data array
	 * @param int $limit The number of rows to delete, for use in a LIMIT clause
	 * @param string $table The table name to delete from, if not using the instance's current table
	 * @return int The number of rows deleted
	 */
	public function delete($limit = 1, $table = NULL)
	{
		if (NULL === $table)
			$table = $this->_table;
		$sql = "DELETE FROM `{$this->_wpdb->prefix}{$table}`";

		if (!empty($this->_where))
			$sql .= $this->get_where();

		$limit = intval($limit);
		$sql .= ' LIMIT ' . $limit;

		$rows = $this->_wpdb->query($sql);
		return ($rows);
	}

	/**
	 * Constructs an UPDATE statement from the currently tracked WHERE clauses and the $data array
	 * @param mixed $data An associative array or object representing the name=value pairs to update
	 * @param int $limit The number of rows to update, for use in a LIMIT clause
	 * @param string $tbl The table name to update, if not using the instance's current table
	 * @return int The number of rows updated
	 */
	public function update($data, $limit = 1, $tbl = NULL)
	{
		if (NULL === $tbl)
			$tbl = $this->_table;
		$sql = "UPDATE `{$this->_wpdb->prefix}{$tbl}`";

		$sql .= ' SET ' . $this->_make_values($data);
	
		if (!empty($this->_where))
			$sql .= $this->get_where();

		$limit = intval($limit);
		$sql .= ' LIMIT ' . $limit;

		$rows = $this->_wpdb->query($sql);
		return ($rows);
	}

	/**
	 * Builds an SQL query from all of the currently tracked SELECT, JOIN, WHERE, etc. data
	 * @return string The constructed SQL query
	 */
	private function _build_query()
	{
		$sql = 'SELECT ';
		if (NULL !== $this->_select)
			$sql .= $this->_build_select(); // implode(',', $this->_select);
		else
			$sql .= '*';
		$sql .= PHP_EOL;

		if (NULL === $this->_from)
			$sql .= " FROM `{$this->_wpdb->prefix}{$this->_table}` ";
		else
			$sql .= " FROM `{$this->_from}` ";
		$sql .= PHP_EOL;

		if (NULL !== $this->_joins) {
			$sql .= $this->_build_joins();
		}

		if (NULL !== $this->_where) {
			$where = $this->_build_where();
			if ('' !== $where)
				$sql .= ' WHERE ' . $where;
			$sql .= PHP_EOL;
		}

		if (NULL !== $this->_order) {
			$sql .= " ORDER BY {$this->_order_by} {$this->_order} ";
			$sql .= PHP_EOL;
		}

		// TODO: add GROUP BY clause

		if (NULL !== $this->_page_items) {
echo 'page items: ', $this->_page_items, ' page=', $this->_page, PHP_EOL;
			$items = $this->_page_items;
			if (NULL === $this->_page) {
				$limit = strval($items);
			} else {
				$page = ((NULL === $this->_page) ? 1 : $this->_page) * $items;
				$limit = strval($page) . ',' . strval($items);
			}
			$sql .= " LIMIT {$limit}";
		} else
			$sql .= ' LIMIT 1 --';

		return ($this->_last_query = $sql);
	}

	/** the following are methods used to add data to the query builder instance **/

	/**
	 * Adds a SELECT column name to the list of columns being tracked
	 * @param string|array $name The column name
	 * @param string $as An optional AS column name to add to the name
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function select($name, $as = NULL)
	{
		if (is_array($name)) {
			foreach ($name as $column) {
				$this->select($column);
			}
		} else {
			if ($this->_check_columns && !$this->has_column($name))
				throw new Exception("Column `{$name}` not found in table `{$this->_table}`");
			$column = $this->_fix_name($name);
			if (NULL !== $as)
				$column .= " AS `{$as}` ";
			$this->_select[] = $column;
		}
		return ($this);
	}

	/**
	 * Adds an SQL function name to the SELECT list
	 * @param string $func The SQL function name
	 * @param string $name The column name
	 * @param string $as An optional AS clause name
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function select_function($func, $name, $as = NULL)
	{
		if ($this->_check_columns && !$this->has_column($name))
			throw new Exception("Column `{$name}` not found in table `{$this->_table}`");
		$column = "{$func}(`{$name}`)";
		if (NULL !== $as)
			$column .= " AS `{$as}`";
		$this->_select[] = $column;
		return ($this);
	}

	/**
	 * Adds a MAX() function reference to the SELECT list
	 * @param string $name The column name
	 * @param string $as An optional AS clause name
	 * @return object A reference to the current class
	 */
	public function select_max($name, $as = NULL)
	{
		return ($this->select_function('MAX', $name, $as));
	}

	/**
	 * Adds a MIN() function reference to the SELECT list
	 * @param string $name The column name
	 * @param string $as An optional AS clause name
	 * @return object A reference to the current class
	 */
	public function select_min($name, $as = NULL)
	{
		return ($this->select_function('MIN', $name, $as));
	}

	/**
	 * Adds a AVG() function reference to the SELECT list
	 * @param string $name The column name
	 * @param string $as An optional AS clause name
	 * @return object A reference to the current class
	 */
	public function select_avg($name, $as = NULL)
	{
		return ($this->select_function('AVG', $name, $as));
	}

	/**
	 * Adds a SUM() function reference to the SELECT list
	 * @param string $name The column name
	 * @param string $as An optional AS clause name
	 * @return object A reference to the current class
	 */
	public function select_sum($name, $as = NULL)
	{
		return ($this->select_function('SUM', $name, $as));
	}

	/**
	 * Constructs a SELECT clause from the list of $_select values
	 * @return string The list of SELECTed column names
	 */
	protected function _build_select()
	{
		return (implode(',', $this->_select));
	}

	/**
	 * Sets the table name to be used in the FROM statement
	 * @param string $table The name of the table to be specified in the FROM portion of the statement
	 * @param boolean $prefix TRUE if the table prefix is to be added to the table name
	 * @return object A reference to the current class
	 */
	public function from($table, $prefix = TRUE)
	{
		$pref = '';
		if ($prefix)
			$pref = $this->_wpdb->prefix;
		$this->_from = $pref . $table;
		return ($this);
	}

	/**
	 * Adds a JOIN clause to the list of $_joins being tracked
	 * @param string $table The table name being JOINed
	 * @param string $condition The ON condition for the JOIN
	 * @param string $orientation The type of JOIN
	 * @param string $as An option AS name for the JOINed table
	 * @return object A reference to the current class
	 * @throws Exception If the $orientation value is not recognized
	 */
	public function join($table, $condition, $orientation = 'LEFT', $as = NULL)
	{
		// validate the $orientation
		if (!in_array($orientation, array('LEFT', 'LEFT INNER', 'LEFT OUTER', 'RIGHT', 'RIGHT INNER', 'RIGHT OUTER')))
			throw new Exception('Unrecognized JOIN specification: ' . $orientation);

		$join = "{$orientation} JOIN `{$table}`";
		if (NULL !== $as)
			$join .= " AS `{$as}`";
		$join .= " ON {$condition}";

		$this->_joins[] = $join;
		return ($this->where_reset());
	}

	/**
	 * Return a list of the JOIN clauses currently being tracked
	 * @return string All of the current JOIN clauses
	 */
	protected function _build_joins()
	{
		return (implode(PHP_EOL, $this->_joins));
	}

	/* The 'where' stdClass can have the following properties:
	 * ->operator = the conditional operator, one of either 'AND' or 'OR'
	 * ->column = the column name
	 * ->val = the value to compare with the column name
	 * ->op = the comparison operator for the column and val, on of '=', '!=', '<', '>', '<=', '>='
	 * ->cond = a full condition, no processing will be performed
	 * ->in = a list of values to use in an IN ( ) clause
	 * ->group = an array of where objects to be placed within parenthesis
	 */

	/**
	 * Adds a 'where' object to the list of $_where conditions being tracked
	 * @param string $col The column name for the condition
	 * @param mixed $val The value to compare agains
	 * @param string $op The comparison operator, one of '=', '!=', '<', '>', '<=', '>='
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function where($col, $val = -1, $op = '=')
	{
		$args = func_num_args();

		$where = new stdClass();
		$where->operator = 'AND';
		switch ($args)
		{
		case 1:
			if (is_array($col)) {
				foreach ($col as $name => $val)
					$this->where($name, $val);
				return ($this);
			} else {
				$where->cond = $col;
			}
			break;
		case 2:
			$where->column = $col;
			$where->val = $val;
			$where->op = '=';
			break;
		case 3:
			$where->column = $col;
			$where->val = $val;
			if (!in_array(trim($op), array('=', '!=', '<', '>', '<=', '>=')))
					throw new Exception('unrecognized comparison operator: ' . $op);
			$where->op = $op;
			break;
		}
		return ($this->_where_add($where));
	}

	/**
	 * Adds a WHERE condition, preceeding it with an 'OR' operator
	 * @param string $col The column name for the condition
	 * @param mixed $val The value to compare agains
	 * @param string $op The comparison operator, one of '=', '!=', '<', '>', '<=', '>='
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function where_or($col, $val = -1, $op = '=')
	{
		$this->where($col, $val, $op);
		$where = $this->_last_where();
		$where->operator = 'OR';
		return ($this->_where_add($where));
	}

	/**
	 * Begins a WHERE group, allowing nested conditions within parenthesis for the WHERE clause
	 * @param string $cond The conditional preceeding the parenthesized group of values
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function where_group($cond = 'AND')
	{
		if ('AND' !== $cond && 'OR' !== $cond)
			throw new Exception('unrecognized conditional: ' . $cond);

		$this->_where_group = array();
		$this->_where_group_condition = $cond;
		return ($this);
	}

	/**
	 * Ends a WHERE group, closing any previous where_group() sets of conditions
	 * @return object A reference to the current class
	 */
	public function where_end_group()
	{
		if (NULL !== $this->_where_group) {
			$where = new stdClass();
			$where->operator = $this->_where_group_condition;
			$where->group = $this->_where_group;
			$this->_where[] = $where;

			$this->_where_group = NULL;
			$this->_where_group_condition = NULL;
		}
		return ($this);
	}

	/**
	 * Adds a WHERE IN condition to the list of $_where conditions being tracked
	 * @param string $col The column name to compare against
	 * @param array $list A list of values to be placed within the IN ( ) clause
	 * @param string $op A comparison operator, '=' means 'IN' and anything else is considered 'NOT IN'
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function where_in($col, $list, $op = '=')
	{
		if (!is_array($list))
			throw new Exception('The list parameter must be an array');
		if (!in_array($op, array('=', '!=', '<>')))
			throw new Exception('Unrecognized operator: ' . $op);

		$where = new stdClass();
		$where->operator = 'AND';
		$where->column = $col;
		$where->in = $list;
		$where->op = $op;

		return ($this->_where_add($where));
	}

	/**
	 * Adds a WHERE IN condition to the list of $_where conditions being tracked, preceeded with an 'OR' condition
	 * @param string $col The column name to compare against
	 * @param array $list A list of values to be placed within the IN ( ) clause
	 * @param string $op A comparison operator, '=' means 'IN' and anything else is considered 'NOT IN'
	 * @return object A reference to the current class
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	public function where_in_or($col, $list, $op = '=')
	{
		$this->where_in($col, $list, $op);
		$where = $this->_last_where();
		$where->operator = 'OR';
		return ($this->_where_add($where));
	}

	/**
	 * Resets all of the values used to track WHERE conditions. Useful when used in making JOIN clauses
	 * @return object A reference to the current class
	 */
	public function where_reset()
	{
		$this->_where = array();
		$this->_where_group = NULL;
		$this->_where_group_condition = NULL;
		return ($this);
	}

	/**
	 * Constructs the WHERE clause of the SQL statement from the current set of $_where conditions
	 * @return string The set of WHERE conditions
	 * @throws Exception If column checking is turned on and the column name is invalid
	 */
	protected function _build_where()
	{
		// need to make a local copy so recursion works
		$where_list = $this->_where;

		// force the end of any where_group()s created
		$this->where_end_group();

		$ret = '';
		foreach ($where_list as $where) {
			if ($this->_check_columns && isset($where->column)) {
				if (!$this->has_column($where->column))
					throw new Exception("Column `{$where->column}` not found in table `{$this->_table}`");
			}

			if ('' !== $ret)
				$ret .= " {$where->operator} ";
			if (isset($where->column))
				$ret .= "`{$where->column}`";

			if (isset($where->group)) {
				$ret .= ' ( ';
				$this->_where = $where->group;
				$ret .= $this->_build_where() . ' ) ';
//die('clause: ' . $ret . PHP_EOL);
			} else if (isset($where->cond)) {
				$ret .= " {$where->cond} ";
			} else if (isset($where->in)) {
				if ('=' === $where->op)
					$ret .= ' IN ';
				else
					$ret .= ' NOT IN ';
				$ret .= '( ';
				$comma = '';
				foreach ($where->in as $val) {
					if (is_numeric($val))
						$ret .= $comma . $val;
					else
						$ret .= $comma . $this->escape($val, '\'');
					$comma = ',';
				}
				$ret .= ' ) ';
			} else {
				if (NULL === $where->val) {
					if ('=' === $where->op)
						$ret .= ' IS NULL ';
					else
						$ret .= ' IS NOT NULL ';
				} else {
					$ret .= " {$where->op} " . $this->escape($where->val, '\'');;
				}
			}
		}
		return ($ret);
	}

	/**
	 * Returns the WHERE clause constructed from the current set of tracked conditions. Mostly used for debugging
	 * @return string The SQL WHERE clause
	 */
	public function get_where()
	{
		return ($this->_build_where());
	}

	/**
	 * Adds a WHERE clause object to either the $_where_group (if active) or the $_where list
	 * @param stdClass $where The 'where' object to add
	 * @return object A reference to the current class
	 */
	private function _where_add($where)
	{
		if (NULL !== $this->_where_group)
			$this->_where_group[] = $where;
		else
			$this->_where[] = $where;
		return ($this);
	}

	/**
	 * Returns the last 'where' object that was added to either the $_where_group (if active) or the $_where list
	 * @return stdClass The 'where' object last added
	 */
	private function _last_where()
	{
		if (NULL !== $this->_where_group)
			$ret = array_pop($this->_where_group);
		else
			$ret = array_pop($this->_where);
		return ($ret);
	}

	/**
	 * Specify ordering for the statement being built
	 * @param string $column The name of the column to perform ordering on
	 * @param string $ordering The ordering mode, one of 'ASC' or 'DESC'
	 * @return object A reference to the current class
	 * @throws Exception If the $ordering value is not recognized or if the column name is not recognized
	 */
	public function order_by($column, $ordering = 'ASC')
	{
		if ('ASC' !== $ordering && 'DESC' !== $ordering)
			throw new Exception('Unrecognized ordering specified: ' . $ordering);

		if ($this->_check_columns && !$this->has_column($column))
			throw new Exception("Column `{$name}` not found in table `{$this->_table}`");

		$column = $this->_fix_name($column);

		$this->_order_by = $column;
		$this->_order = $ordering;

		return ($this);
	}

	/**
	 * Sets the values used in the LIMIT clause of the SQL statement
	 * @param int $per_page The number of items to return in the result set
	 * @param int $page The page number to use for the result set
	 * @return object A reference to the current class
	 */
	public function limit($per_page, $page = NULL)
	{
		$this->_page_items = intval($per_page);

		if (NULL !== $page)
			$this->_page = intval($page);

		return ($this);
	}

	/**
	 * Helper function to adjust the backticks on a column name
	 * @param string $name The column name in 'name' or 'table.name' notation
	 * @return string The adjusted column name with backticks added: '`name`' or '`table`.`name`' notation
	 */
	private function _fix_name($name)
	{
		$split = explode('.', $name, 2);
		$split[0] = trim($split[0], '`');
		if (isset($split[1]))
			$split[1] = trim($split[1], '`');

		$ret = '`' . implode('`.`', $split) . '`';
		return ($ret);
	}

	/**
	 * Return the column name from a potential `table`.`column` name combiniation
	 * @param string $name The column name, with optional backticks and table specification
	 * @return string The column name portion of the (potentially) complex column reference
	 */
	private function _get_column($name)
	{
		$name = str_replace('`', '', $name);
		if (FALSE != strpos($name, '.')) {
			$parts = explode('.', $name, 2);
			$name = $parts[1];
		}
		return ($name);
	}
}

} // class_exists

// EOF