<?php 

/**
 * Plugin name: Image Size
 * Description: Create image sizes.
 * Author: Laszlo Felfoldi
 * Verion: 0.0.1
 */

class ImageSize {

	private static $instance;
	public static function getInstance() {
		if ( !( self::$instance instanceof ImageSize ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {

		add_action( 'init', [ $this, 'rewrite' ] );
		add_filter( 'query_vars', [ $this, 'vars' ] );
		add_action( 'template_redirect', [ $this, 'parse' ] );

		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'sizes' ], 11, 2 );
		add_filter( 'wp_get_attachment_url', [ $this, 'url' ], 11, 2 );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'src' ], 11, 3 );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'prepare' ] );
		add_filter( 'wp_get_attachment_metadata', [ $this, 'meta' ], 11, 2 );

		//add_action( 'admin_enqueue_scripts', [ $this, 'script' ] );

	}

	public function rewrite() {

		add_rewrite_rule( '^image/(.+)/([0-9]*x[0-9]*)/?', 'index.php?image_slug=$matches[1]&image_size=$matches[2]', 'top' );
		add_rewrite_rule( '^image/(.+)/?', 'index.php?image_slug=$matches[1]', 'top' );

	}

	public function vars( $vars ) {
		$vars[] = 'image_slug';
		$vars[] = 'image_size';
		return $vars;
	}

	public function parse() {

		global $wp_query;

		if ( !empty( $wp_query->query_vars['image_slug'] ) ) {

			$posts = get_posts([
				'post_type' => 'attachment',
				'name' => $wp_query->query_vars['image_slug'],
				'numberposts' => 1
			]);

			if ( isset( $posts[0] ) ) {

				$post = $posts[0];
				$file_out = $this->get_resized_image( $post->ID, $wp_query->query_vars['image_size'] );

				wp_redirect( $file_out );
				exit;	

			}

			$wp_query->set_404();

		}

	}

	private function get_resized_image( $post_id, $size ) {

		$file = get_attached_file( $post_id );
		$size_parts = explode( 'x', $size );

		$editor = wp_get_image_editor( $file );
		$o_size = $editor->get_size();

		$info = pathinfo( $file );
		$dims = image_resize_dimensions( $o_size['width'], $o_size['height'], $size_parts[0] ?: null, $size_parts[1] ?: null, !empty( $size_parts[0] ) && !empty( $size_parts[1] ) );

		$filepath = dirname( $file ) . '/' . $info['filename'] . '_' . ( !empty( $size_parts[0] ) && !empty( $size_parts[1] ) ? $size : $dims[4] . 'x' . $dims[5] ) . '.' . $info['extension'];

		if ( file_exists( $filepath ) ) {
			return $this->create_url( $filepath );
		}

		if (
			( !empty( $size_parts[0] ) || !empty( $size_parts[1] ) ) &&
			( !empty( $size_parts[0] ) && $size_parts[0] < $o_size['width'] ) ||
			( !empty( $size_parts[1] ) && $size_parts[1] < $o_size['height'] )
		) {

			$editor->resize( $size_parts[0] ?: null, $size_parts[1] ?: null, !empty( $size_parts[0] ) && !empty( $size_parts[1] ) );
			$editor->save(  $filepath );

			return $this->create_url( $filepath );			

		}

		return $this->create_url( $file );

	} 

	private function create_url( $file ) {
		return str_replace( dirname( $file, 5 ), site_url(), $file );
	}

	public function sizes( $sizes, $metadata ) {
		return [];
	}

	public function url( $url, $post_id ) {

		$post = get_post( $post_id );
		if ( wp_attachment_is_image( $post_id ) ) {
			$url = site_url() . '/image/' . $post->post_name . '/';
		}
		return $url;

	}

	public function src( $image, $attachment_id, $size ) {

		$post = get_post( $attachment_id );
		$image[0] = site_url() . '/image/' . $post->post_name . '/' . ( $size == 'full' ? '' : $image[1] . 'x' . $image[2] . '/' );
		return $image;

	}

	/*public function script() {
		wp_enqueue_script( 'image-size', plugins_url( 'script.js', __FILE__ ), [ 'jquery', 'underscore' ], true );
	}*/

	public function prepare( $response ) {

		$response['sizes']['thumbnail'] = [
			'url' => site_url() . '/image/' . $response['name'] . '/150x150/',
			'height' => 150,
			'width' => 150,
			'orientation' => $response['sizes']['full']['orientation']
		];
		
		return $response;

	}

	public function meta( $data, $post_id ) {

		$post = get_post( $post_id );
		$data['sizes']['thumbnail'] = [
			'file' => $post->post_name . '/150x150/',
			'width' => 150,
			'height' => 150
		];
		return $data;

	}

}

ImageSize::getInstance();