<?php
/**
 * This class extends WP_Posts_List_Table and creates the markup for the page edit screen
 *
 * @package nine3versions
 */

namespace nine3versions;

// WP_Posts_List_Table is not loaded automatically so we need to load.
if ( ! class_exists( 'WP_Posts_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
}

/**
 * Class definition
 */
final class Versions_List_Table extends \WP_Posts_List_Table {
	/**
	 * Stores selected page ID
	 *
	 * @var int $page_id
	 */
	public $page_id;

	/**
	 * Stores URL params
	 *
	 * @var array $url_data
	 */
	public $url_data;

	/**
	 * Stores post items from our custom query
	 *
	 * @var array $items
	 */
	public $items;

	/**
	 * Construct
	 *
	 * @param int   $page_id parent page ID.
	 * @param array $url_data array of URL params.
	 */
	public function __construct( $page_id, $url_data = [] ) {
		parent::__construct( [ 'screen' => convert_to_screen( 'edit-page' ) ] );

		// Set page ID.
		$this->page_id  = $page_id;
		$this->url_data = $url_data;
	}

	/**
	 * Re-run prepare items to ready up our custom parent query
	 */
	public function prepare_items() {
		parent::prepare_items();

		// Set items.
		$this->items     = $this->get_page_tree();
		$total_items     = count( $this->items );
		$per_page        = 9999;
		$pagination_args = [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		];

		if ( isset( $_REQUEST['orderby'] ) ) {
			$pagination_args['orderby'] = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
		}

		if ( isset( $_REQUEST['order'] ) ) {
			$pagination_args['order'] = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) );
		}

		// Custom pagination args.
		$this->set_pagination_args( $pagination_args );
	}

	/**
	 * Re-run parent display_rows function with new query
	 *
	 * @param array $posts the array of post items.
	 * @param int   $level hierarchical level.
	 */
	public function display_rows( $posts = [], $level = 0 ) {
		$posts = ! empty( $this->items ) ? $this->items : $posts;
		parent::display_rows( $posts, $level );
	}

	/**
	 * Rebuilding ajax response function to get all of the necessary markup
	 */
	public function ajax_response() {
		$this->prepare_items();

		extract( $this->_args ); // phpcs:ignore
		extract( $this->_pagination_args, EXTR_SKIP ); // phpcs:ignore

		ob_start();
		$this->display_rows( $this->items );
		$rows = ob_get_clean();

		ob_start();
		$this->print_column_headers();
		$headers = ob_get_clean();

		ob_start();
		$this->pagination( 'top' );
		$pagination_top = ob_get_clean();

		ob_start();
		$this->pagination( 'bottom' );
		$pagination_bottom = ob_get_clean();

		$response = [
			'success'        => false,
			'rows'           => $rows,
			'pagination'     => [
				'top'    => $pagination_top,
				'bottom' => $pagination_bottom,
			],
			'column_headers' => $headers,
		];

		if ( isset( $total_items ) ) {
			$response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );

			// Items found, so success.
			$response['success'] = true;
		}

		if ( isset( $total_pages ) ) {
				$response['total_pages'] = $total_pages;
				$response['total_pages_i18n'] = number_format_i18n( $total_pages );
		}

		return $response;
	}

	/**
	 * Return page tree query for list table
	 */
	private function get_page_tree() {
		global $nine3v;

		$posts_array = $nine3v->helpers->get_descendants( $this->page_id );
		$post_args   = [
			'posts_per_page' => -1,
			'post_type'      => 'page',
			'post_status'    => 'any',
			'post__in'       => $posts_array,
			'orderby'        => 'post__in',
		];

		// Check REQUEST superglobals.
		$is_search  = false;
		$url_params = [ 'post_status', 'author', 'orderby', 'order', 's' ];
		foreach ( $url_params as $param ) {
			if ( isset( $this->url_data[ $param ] ) ) {
				$post_args[ $param ] = $this->url_data[ $param ] === 'all' ? 'any' : sanitize_text_field( wp_unslash( $this->url_data[ $param ] ) );
				$is_search = $param === 's' ? true : false;
			}
		}

		if ( $is_search ) {
			unset( $post_args['post__in'] );
		}

		$query = new \WP_Query( $post_args );
		return $query->have_posts() ? $query->posts : [];
	}
}
