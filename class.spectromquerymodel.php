<?php

class SpectrOMQueryModel
{
	protected $post_type = 'posts';
	protected $page = 0;
	protected $posts_per_page = 0;
	
	protected $args = array();
	protected $query = NULL;

	/**
	 * Constructor, used to set the QueryModel's post type
	 * @param string $post_type The name of the Custom Post Type to use for queries
	 */
	public function __construct($post_type)
	{
		$this->post_type = $post_type;
	}

	/**
	 * Sets the post type for the queries built with this instance
	 * @param string $type The name of the Custom Post Type to use for queries
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function post_type($type)
	{
		$this->post_type = $type;
		return ($this);
	}

	/**
	 * Returns the number of posts per page to be used for queries
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function get_posts_per_page()
	{
		if (0 === $this->posts_per_page)
			$this->posts_per_page = intval(get_option('posts_per_page'));

		return ($this->posts_per_page);
	}

	/**
	 * Sets the number of posts per page to be used for queries
	 * @param mixed $posts The number of posts per page to use or NULL to use the WordPress default value
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function set_posts_per_page($posts = NULL)
	{
		if (NULL === $posts)
			$posts = intval(get_option('posts_per_page'));
		$this->posts_per_page = $posts;
		return ($this);
	}

	/**
	 * Sets the page number to use for queries
	 * @param int $page The page number to use for the query
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function set_page($page)
	{
		$this->page = $page;
		return ($this);
	}

	/**
	 * Sets the query to ignore sticky posts
	 * @param boolean $ignore TRUE to ignore sticy posts or FALSE to pay attention to them
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function ignore_sticky($ignore = TRUE)
	{
		$this->args['ignore_sticky'] = $ignore;
		return ($this);
	}

	/**
	 * Sets the offset value to use for queries. Careful, this can interfere when using pagination
	 * @param int $offset The number of posts to skip
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function offset($offset = 0)
	{
		$this->args['offset'] = $offset;
		return ($this);
	}

	/**
	 * Sets the post property to perform ordering by
	 * @param type $orderby
	 * @param type $ordering
	 * @return type
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function order_by($orderby, $ordering = 'DESC')
	{
		$allowed = array('none', 'ID', 'author', 'title', 'name', 'type', 'date',
			'modified', 'parent', 'rand', 'comment_count', 'menu_order', 'meta_value',
			'meta_value_num', 'post__in');
		if (!in_array($orderby, $allowed))
			throw new Exception("order_by value '{$orderby}' not recognized.");
		if ('DESC' !== $ordering && 'ASC' !== $ordering)
			throw new Exception("ordering value '{$ordering}' not recognized.");
		
		$this->args['orderby'] = $orderby;
		$this->args['order'] = $ordering;
		return ($this);
	}

	/**
	 * Adds the pagination parameters to the query being built
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function add_pagination()
	{
		$this->posts_per_page = $this->get_posts_per_page();
		$paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
		$this->page = $paged;
		return ($this);
	}

	/**
	 * Turns off the SQL_CALC_FOUND_ROWS option in the query that WP_Query is going to build
	 * @return SpectrOMQueryModel A reference to the current instance
	 */
	public function no_found_rows()
	{
		$this->args['no_found_rows'] = TRUE;
		return ($this);
	}

	/**
	 * Constructs the WP_Query object from array parameters and previously obtained options
	 * @param array $args An array of standard options for the WP_Query class.
	 * @return WP_Query The constructed instance of WP_Query
	 */
	public function get_query($args = array())
	{
		$this->args['post_type'] = $this->post_type;
		if (0 !== $this->page && 0 !== $this->posts_per_page) {
			$this->args['paged'] = $this->page;
			$this->args['posts_per_page'] = $this->posts_per_page;
		}

		// merge parameter args with what the class has previously built
		$args = array_merge($args, $this->args);
		// create the query object
		$this->query = new WP_Query($args);

		return ($this->query);
	}
}

// EOF