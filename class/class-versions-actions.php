<?php
/**
 * This class is responsible for performing all ajax and wp query actions needed to clone/edit/delete posts
 *
 * @package nine3versions
 */

namespace nine3versions;

/**
 * Class definition
 */
final class Versions_Actions {
	/**
	 * Key name for our transient
	 *
	 * @var string $array_trans_key
	 */
	public $array_trans_key = 'nine3v_array_trans';

	/**
	 * Construct
	 */
	public function __construct() {
		// Ajax action callback to add page.
		add_action( 'wp_ajax_nine3v-edit', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-clone-page', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-add', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-view', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-move', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-clone', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-order', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-delete', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-clear', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-replace', [ $this, 'nine3v_ajax_callback' ] );
		add_action( 'wp_ajax_nine3v-publish', [ $this, 'nine3v_ajax_callback' ] );

		// Modify admin query to display parent and it's children.
		add_action( 'pre_get_posts', [ $this, 'parent_descedants_query' ] );
	}

	/**
	 * 'wp_ajax_nine3v-edit' action hook callback
	 * 'wp_ajax_nine3v-add' action hook callback
	 * 'wp_ajax_nine3v-view' action hook callback
	 * 'wp_ajax_nine3v-clone' action hook callback
	 * 'wp_ajax_nine3v-delete' action hook callback
	 * 'wp_ajax_nine3v-clear' action hook callback
	 * 'wp_ajax_nine3v-replace' action hook callback
	 * 'wp_ajax_nine3v-publish' action hook callback
	 * Gets post ID and redirects to page edit screen
	 */
	public function nine3v_ajax_callback() {
		// Verify nonce.
		$this->verify_nonce();

		// Setup response array.
		$response['success'] = false;

		if ( isset( $_REQUEST['action'] ) ) {
			$action  = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
			$page_id = sanitize_text_field( wp_unslash( $_REQUEST['pageId'] ) );
			$data    = isset( $_REQUEST['data'] ) ? json_decode( wp_unslash( $_REQUEST['data'] ), true ) : []; // phpcs:ignore

			if ( ! $page_id ) {
				$response['message'] = __( 'Page ID not provided.', 'nine3versions' );
				echo wp_json_encode( $response );
				wp_die();
			}

			switch ( $action ) {
				case 'nine3v-edit':
					$response = $this->edit_page( $page_id );
					break;
				case 'nine3v-clone-page':
					$response = $this->clone_page( $page_id );
					break;
				case 'nine3v-add':
					$response = $this->add_page( $page_id, $data );
					break;
				case 'nine3v-view':
					$list_table = new Versions_List_Table( $page_id, $data );
					$response   = $list_table->ajax_response();
					$response['message'] = __( 'Pages Loaded', 'luna' );
					$response['delay']   = 500;
					break;
				case 'nine3v-clone':
					$response = $this->clone_page_tree( $page_id, $data );
					break;
				case 'nine3v-move':
					$response = $this->move_page_tree( $page_id, $data );
					delete_transient( $this->array_trans_key );
					break;
				case 'nine3v-delete':
					$response = $this->delete_page_tree( $page_id );
					delete_transient( $this->array_trans_key );
					break;
				case 'nine3v-order':
					$response = $this->reorder_page( $page_id, $data );
					delete_transient( $this->array_trans_key );
					break;
				case 'nine3v-clear':
					$response = [
						'success' => true,
						'message' => __( 'Selection cleared.', 'nine3versions' ),
					];
					break;
				case 'nine3v-replace':
					$response = $this->search_replace_tree( $page_id, $data );
					delete_transient( $this->array_trans_key );
					$this->purge_wpe_cache();
					break;
				case 'nine3v-publish':
					$response = $this->publish_tree( $page_id );
					delete_transient( $this->array_trans_key );
					$this->purge_wpe_cache();
					break;
				default:
					$response['message'] = __( 'Action not provided.', 'nine3versions' );
			}
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * Gets post ID and returns post edit link
	 *
	 * @param int $page_id the page to edit.
	 */
	private function edit_page( $page_id ) {
		$edit_link = get_edit_post_link( $page_id );
		$response  = [
			'success' => $edit_link ? true : false,
			'message' => $edit_link ? __( 'Redirecting...', 'nine3versions' ) : __( 'Page not found.', 'nine3versions' ),
			'target'  => $edit_link ? $edit_link : false,
		];
		return $response;
	}

	/**
	 * Adds new child page for given page ID
	 *
	 * @param int   $page_id the page to edit.
	 * @param array $data contains post data to add to new page.
	 */
	private function add_page( $page_id, $data ) {
		$post_args = [
			'post_title'  => isset( $data['title'] ) ? $data['title'] : __( 'New post draft', 'nine3versions' ),
			'post_parent' => $page_id,
			'post_type'   => get_post_type( $page_id ),
		];

		$new_post = wp_insert_post( $post_args, true );
		$is_error = is_wp_error( $new_post );

		$response = [
			'success' => ! $is_error,
			'message' => $is_error ? $new_post->get_error_message() : __( 'Redirecting...', 'nine3versions' ),
			'target'  => $is_error ? false : get_edit_post_link( $new_post ),
		];
		return $response;
	}

	/**
	 * Gets post ID and clones the current page
	 *
	 * @param int $page_id the page to clone.
	 */
	private function clone_page( $page_id ) {
		global $nine3v;

		// Check post ID exists.
		if ( ! is_numeric( $page_id ) || ! get_post_type( $page_id ) ) {
			$response['message'] = __( 'Page ID does not exist.', 'nine3versions' );
			return $response;
		}

		$parent_post = get_post_parent( $page_id );
		$page_parent = $parent_post ? $parent_post->ID : 0;
		$page_title  = get_the_title( $page_id ) . ' (' . __( 'Cloned', 'nine3versions' ) . ')';
		$cloned      = $nine3v->helpers->duplicate( $page_id, $page_parent, $page_title );

		// Check for errors.
		if ( is_wp_error( $cloned ) ) {
			$response['message']  = __( 'Something went wrong:', 'nine3versions' );
			$response['message'] .= "\n" . $cloned->get_error_message();
		} else {
			$response = [
				'success' => true,
				'message' => __( 'Page successfully cloned.', 'nine3versions' ),
			];
		}

		return $response;
	}

	/**
	 * Gets post ID and clones the hierarchy tree
	 *
	 * @param int   $page_id the page to clone (and it's descendants).
	 * @param array $data contains post data to add to new page.
	 */
	private function clone_page_tree( $page_id, $data ) {
		global $nine3v;

		// Check post ID exists.
		if ( ! is_numeric( $page_id ) || ! get_post_type( $page_id ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Page ID does not exist.', 'nine3versions' ),
			];
			return $response;
		}
		
		$array_tree = $nine3v->helpers->get_page_tree( $page_id, true );

		// Add custom title if set.
		if ( isset( $data['title'] ) ) {
			$array_tree[0]['title'] = $data['title'];
		}

		$offset     = isset( $data['offset'] ) ? $data['offset'] : 0;
		$total      = isset( $data['total'] ) ? $data['total'] : count( $array_tree );
		$last_added = isset( $data['added'] ) ? $data['added'] : [];

		// If no array tree offset, we're done here.
		if ( ! isset( $array_tree[ $offset ] ) ) {
			$response = [
				'complete' => true,
				'success'  => true,
				'message'  => __( 'Total posts added', 'nine3versions' ) . ': ' . $total,
			];
	
			return $response;
		}

		// Set up vars and loop thorugh post tree.
		$post_id    = $array_tree[ $offset ]['ID'];
		$post_title = $array_tree[ $offset ]['title'];
		$parent_id  = isset( $data['parent'] ) ? $data['parent'] : $array_tree[ $offset ]['parent'];

		// Check if current post is child of another.
		$parent_key = $nine3v->helpers->get_multidim_key( $post_id, $array_tree, 'children' );
		if (
			$parent_key !== null &&
			isset( $array_tree[ $parent_key ]['ID'] ) &&
			isset( $data['added'][ $array_tree[ $parent_key ]['ID'] ] )
		) {
			$parent_id = $data['added'][ $array_tree[ $parent_key ]['ID'] ];
		}

		$response = [
			'total'   => $total,
			'page_id' => $page_id,
			'added'   => $last_added,
		];

		while ( $offset < $total ) {
			$new_post_id = $nine3v->helpers->duplicate( $post_id, $parent_id, $post_title );
			$offset++;
			$response['parent'] = $parent_key !== null ? $parent_id : $new_post_id;
			$response['offset'] = $offset;
			$response['added'][ $post_id ] = $new_post_id;
			$response['success'] = is_wp_error( $new_post_id ) ? false : true;

			return $response;
		}
	}

	/**
	 * Gets post ID and parent ID to move the hierarchy tree there
	 *
	 * @param int   $page_id the page to clone (and it's descendants).
	 * @param array $data contains new parent ID.
	 */
	private function move_page_tree( $page_id, $data ) {
		global $nine3v;

		if ( ! isset( $data['parentId'] ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Parent ID not provided.', 'nine3versions' ),
			];
		}

		// Update post to new parent.
		$parent_id  = sanitize_text_field( wp_unslash( $data['parentId'] ) );
		$post_array = [
			'ID'          => $page_id,
			'post_parent' => $parent_id,
		];
		$moved      = wp_update_post( $post_array );

		// Check for errors.
		if ( is_wp_error( $moved ) ) {
			$response['message']  = __( 'Something went wrong:', 'nine3versions' );
			$response['message'] .= "\n" . $moved->get_error_message();
		} else {
			$response = [
				'success' => true,
				'message' => __( 'Page tree successfully moved.', 'nine3versions' ),
			];
		}

		return $response;
	}

	/**
	 * Gets post ID and deletes the hierarchy tree
	 *
	 * @param int $page_id the page to clone (and it's descendants).
	 */
	private function delete_page_tree( $page_id ) {
		$posts_array[] = $page_id;

		// Get an array of all descendant objects of given ID.
		$children    = get_pages(
			[
				'child_of'    => $page_id,
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ],
			]
		);
		$children    = wp_list_pluck( $children, 'ID' );
		$posts_array = array_merge( $posts_array, $children );
		$is_error    = false;
		$not_deleted = [];

		// Delete.
		foreach ( $posts_array as $post_id ) {
			$deleted = wp_delete_post( $post_id );
			if ( ! $deleted ) {
				$is_error      = true;
				$not_deleted[] = $post_id;
			}
		}

		// Check for errors.
		if ( $is_error ) {
			$response['message']  = __( 'Some posts could not be deleted', 'nine3versions' );
			$response['message'] .= ': ' . implode( ', ', $not_deleted );
		} else {
			$response = [
				'success' => true,
				'message' => __( 'Page tree successfully deleted.', 'nine3versions' ),
			];
		}

		return $response;
	}

	/**
	 * Gets post ID and updates menu_order param
	 *
	 * @param int   $page_id the page to update.
	 * @param array $data contains order number.
	 */
	private function reorder_page( $page_id, $data ) {
		$updated = wp_update_post(
			[
				'ID'         => $page_id,
				'menu_order' => $data['order'],
			]
		);

		$response = [
			'success' => ( is_wp_error( $updated ) || ! $updated ) ? false : true,
			'message' => ( is_wp_error( $updated ) || ! $updated ) ? __( 'Update failed.', 'nine3versions' ) : __( 'Order updated.', 'nine3versions' ),
		];
		return $response;
	}

	/**
	 * Search/replace strings in given page ID tree
	 *
	 * @param int   $page_id the page to search/replace (and it's descendants).
	 * @param array $data contains from and to strings to search/replace.
	 */
	private function search_replace_tree( $page_id, $data ) {
		global $nine3v;

		// Check post ID exists.
		if ( ! is_numeric( $page_id ) || ! get_post_type( $page_id ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Page ID does not exist.', 'nine3versions' ),
			];
			return $response;
		}

		$array_tree = $nine3v->helpers->get_page_tree( $page_id, true );

		// Run search/replace.
		$replaced = false;
		if ( $array_tree && isset( $data['from'] ) && isset( $data['to'] ) ) {
			$replaced = $nine3v->helpers->search_replace( $array_tree, $data['from'], $data['to'] );
		}

		// Check for errors.
		if ( ! $replaced ) {
			$response['message'] = __( 'Something went wrong...', 'nine3versions' );
		} else {
			$response = [
				'success' => true,
				'message' => __( 'Search/replace run succesfully.', 'nine3versions' ),
			];

			$response['message'] .= "\n" . __( 'Content rows replaced: ', 'nine3versions' ) . $replaced['posts'];
			$response['message'] .= "\n" . __( 'Meta rows replaced: ', 'nine3versions' ) . $replaced['postmeta'];
		}

		return $response;
	}

	/**
	 * Publish page tree
	 *
	 * @param int $page_id the page to publish (and it's descendants).
	 */
	private function publish_tree( $page_id ) {
		global $nine3v;

		// Check post ID exists.
		if ( ! is_numeric( $page_id ) || ! get_post_type( $page_id ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Page ID does not exist.', 'nine3versions' ),
			];
			return $response;
		}

		$array_tree = $nine3v->helpers->get_page_tree( $page_id, true );

		// Run search/replace.
		$published = false;
		if ( $array_tree ) {
			$published = $nine3v->helpers->publish( $array_tree );
		}

		// Check for errors.
		if ( ! $published ) {
			$response['message'] = __( 'Something went wrong...', 'nine3versions' );
		} else {
			$response = [
				'success' => true,
				'message' => __( 'Total posts published: ', 'nine3versions' ) . $published,
			];
		}

		return $response;
	}

	/**
	 * 'pre_get_posts' action hook callback
	 * Modifies query to display only the parent and it's descendant posts in page listing screen
	 *
	 * @param WP_Query $query the current query.
	 */
	public function parent_descedants_query( $query ) {
		global $nine3v;

		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_GET['post_parent'] ) ) {
			return;
		}

		global $pagenow;
		if ( $pagenow === 'edit.php' && $query->query['post_type'] === 'page' ) {
			$parent_id   = sanitize_text_field( wp_unslash( $_GET['post_parent'] ) );
			$posts_array = $nine3v->helpers->get_descendants( $parent_id );
			$query->set( 'post__in', $posts_array );
			$query->set( 'orderby', 'post__in' );
		}
	}

	/**
	 * Verifies nonce in ajax call and returns json response
	 */
	private function verify_nonce() {
		global $nine3v;

		// phpcs:ignore
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], $nine3v->nonce_action ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Invalid request. Unable to verify nonce.', 'nine3versions' ),
			];
			echo wp_json_encode( $response );
			wp_die();
		}
	}

	/**
	 * Purges WPEngine cache
	 */
	private function purge_wpe_cache() {
		if ( method_exists( 'wpecommon', 'purge_varnish_cache' ) ) {
			\wpecommon::purge_varnish_cache();
		}

		if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
			\WpeCommon::purge_memcached();
		}
	
		if ( method_exists( 'WpeCommon', 'clear_maxcdn_cache' ) ) {
			\WpeCommon::clear_maxcdn_cache();
		}
	
		if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			\WpeCommon::purge_varnish_cache();
		}
	}
}
