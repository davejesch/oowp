<?php

abstract class SpectrOMMetaBox
{
	private $_css_class = NULL;
	private $_title = NULL;
	private $_callback = NULL;
	private $_post_type = NULL;
	private $_context = NULL;
	private $_priority = NULL;
	private $_args = NULL;

	public function __construct($css_class, $title, $callback, $post_type = 'post', $context = 'normal', $priority = 'default', $args = array())
	{
		if (!is_admin())
			return;			// no need to set up metabox unless running in the admin

		$this->_css_class = $css_class;
		$this->_title = $title;
		$this->_callback = $callback;
		$this->_post_type = $post_type;
		$this->_context = $context;
		$this->_priority = $priority;
		$this->_args = $args;

		add_action('add_meta_boxes', array(&$this, 'add_meta_boxes'));
		add_action('save_post', array(&$this, 'save_data'));
	}

	/**
	 * Callback for adding metaboxes to the edit page
	 */
	public function add_meta_boxes()
	{
		add_meta_box($this->_css_class, $this->_title, $this->_callback, $this->_post_type, $this->_context, $this->_priority, $this->_args);
	}

	/**
	 * Removes the metabox from the page
	 */
	public function remove()
	{
		remove_meta_box($this->_css_class, $this->_post_type, $this->_context);
	}

	/**
	 * Abstract method used for saving metabox data
	 * @param int $post_id The ID of the post being edited
	 */
	abstract public function save_data($post_id);
}
