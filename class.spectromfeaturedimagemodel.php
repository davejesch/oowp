<?php

/**
 * Simplifies operations working with Featured Images
 */
if (!class_exists('SpectrOMFeaturedImageModel', FALSE)) {

class SpectrOMFeaturedImageModel
{
    private $post_id = NULL;
	private $thumb_id = NULL;

    public function __construct($post_id = NULL)
    {
        if (NULL === $post_id) {
            global $post;
            $post_id = $post->ID;
        }
        $this->post_id = $post_id;
    }

	/**
	 * Sets the post id to use for this instance
	 * @param int $id The post id to use to obtain the featured image
	 */
	public function set_post_id($id)
	{
		$this->post_id = $id;
	}

	/**
	 * Gets the post_id of the thumbnail for the current post id
	 * @return int The post_id of the thumbnail for the current post
	 * @throws Exception If there is no thumbnail associated with the current post
	 */
	public function get_thumbnail_id()
	{
		if (NULL === $this->thumb_id) {
			if (NULL === $this->post_id)
				throw new Exception('no post id provided');

			$this->thumb_id = get_post_thumbnail_id($this->post_id);
			if ('' === $this->thumb_id)
				throw new Exception('no post thumbnail found');
		}
		return ($this->thumb_id);
	}

	/**
	 * Checks to see if the current post has a featured image
	 * @return boolean TRUE if there is a featured image for the post; otherwise FALSE
	 */
	public function has_featured_image()
	{
		return (has_post_thumbnail($this->post_id));
	}

	/**
	 * Returns the URL for the attachment
	 * @param int $id The post_id of the post or NULL to use the instance's post id
	 * @return string The full <img> reference to the post's thumbnail
	 * @throws Exception If the post_id is not provided, nor specified for the instance.
	 */
	public function get_attachment_url($id = NULL)
	{
		if (NULL === $id)
			$id = $this->post_id;
		if (NULL === $id)
			throw new Exception('no post id provided');
		
		$feat_id = get_post_thumbnail_id($id);
		return (wp_get_attachment_url($feat_id));
	}

	/**
	 * Returns the <img> reference for the featured image
	 * @param int $thumb The thumbnail id or NULL to look up based on the instance's post id
	 * @param mixed $size The thumbnail size ('thumbnail', 'medium', 'large', 'full') or an array for the size
	 * @param array $attr Additional attributes to used when constructing the <img> tag
	 * @return string The full <img> tag that references the featured dimage
	 */
	public function get_thumbnail($thumb = NULL, $size = 'thumbnail', $attr = array())
	{
		if (NULL === $thumb)
			$thumb = $this->get_thumbnail_id();
		$ret = get_the_post_thumbnail($this->post_id, $size, $attr);
		return ($ret);
	}

	/**
	 * Returns the <img> reference for the featured image, seting the image dimensions proportionally
	 * @param int $thumb The thumbnail id or NULL to look up based on the instance's post id
	 * @param mixed $size The thumbnail size ('thumbnail', 'medium', 'large', 'full') or an array for the size
	 * @param array $attr Additional attributes to used when constructing the <img> tag
	 * @return string The full <img> tag that references the featured dimage
	 */
	public function get_thumbnail_proportional($thumb = NULL, $size = array(), $attr = array())
	{
		if (NULL === $thumb)
			$thumb = $this->get_thumbnail_id();

		$sz = $this->calc_size($thumb, $size[0], $size[1]);

		$this->width = $sz[0];
		$this->height = $sz[1];
		$this->size = $sz;
		add_filter('post_thumbnail_size', array(&$this, 'filter_thumbnail_size'));
		$ret = get_the_post_thumbnail($this->post_id, 'medium', $attr);
		remove_filter('post_thumbnail_size', array(&$this, 'filter_thumbnail_size'));
		return ($ret);
	}

	/**
	 * Callback function to "fix" the image size for get_thumbnail_proportional()
	 * @param array $size An array denoting the size of the image to generate
	 * @return array The modified size array
	 */
	public function filter_thumbnail_size($size)
	{
		$size[0] = $this->width;
		$size[1] = $this->height;
		return ($size);
	}

	/**
	 * Calculates the size of an image, resizing the image proportionally
	 * @param int $att_id The post_id of the attachment
	 * @param int $width The width of the image. If 0, will calculate based on the height.
	 * @param int $height The height of the image. If 0, will calculate based on the width.
	 * @return array Returns an array with the image size array($width, $height)
	 */
	private function calc_size($att_id, $width = 0, $height = 0)
	{
		$data = wp_get_attachment_image_src($att_id, 'medium');
		if (FALSE === $data[3]) {
			if (0 === $width || 0 === $height)
				$ret = NULL;
			else
				$ret = array($width, $height);
		} else {
			if (0 === $width && 0 !== $height) {
				$wid = intval($data[1] * ($height / $data[2]));
				$ret = array($wid, $height);
			} else if (0 === $height && 0 !== $width) {
				$hgt = intval($data[2] * ($width / $data[1]));
				$ret = array($width, $hgt);
			} else {
				$ret = array($data[1], $data[2]);
			}
		}
		return ($ret);
	}
}

} // class_exists

// EOF
