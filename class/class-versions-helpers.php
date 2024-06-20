<?php
/**
 * This class contains utility functions used throughout the plugin
 *
 * @package nine3versions
 */

namespace nine3versions;

/**
 * Class definition
 */
final class Versions_Helpers {
	/**
	 * Helper function to enumerate a post hierachy tree from a given post ID
	 *
	 * @param int  $post_id the given post ID.
	 * @param bool $flat whether the returned array should be hierarchical or not.
	 */
	public function get_page_tree( $post_id, $flat = false ) {
		$parent_id = wp_get_post_parent_id( $post_id );

		$posts_array[ $post_id ] = [
			'ID'     => $post_id,
			'parent' => $parent_id,
			'title'  => get_the_title( $post_id ),
			'status' => get_post_status( $post_id ),
		];

		// Get an array of all descendant objects of given ID.
		$children = get_pages(
			[
				'child_of'    => $post_id,
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'sort_column' => 'menu_order, post_title',
				'sort_order'  => 'ASC',
			]
		);

		// Rework array to include parent ID.
		$children = wp_list_pluck( $children, 'post_parent', 'ID' );
		foreach ( $children as $key => &$child ) {
			$child = [
				'ID'     => $key,
				'parent' => $child,
				'title'  => get_the_title( $key ),
				'status' => get_post_status( $key ),
			];
		}

		$posts_array = array_merge( $posts_array, $children );

		if ( $flat ) {
			foreach ( $posts_array as &$post ) {
				$children = get_pages(
					[
						'child_of'    => $post['ID'],
						'parent'      => $post['ID'],
						'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ],
						'sort_column' => 'menu_order, post_title',
						'sort_order'  => 'ASC',
					]
				);

				if ( $children ) {
					$children         = wp_list_pluck( $children, 'ID' );
					$post['children'] = $children;
				}
			}
			return $posts_array;
		}

		$array_tree = $this->build_tree( $posts_array, $parent_id );
		return $array_tree;
	}

	/**
	 * Recursive helper function to turn a flat array into a hierarchical tree
	 *
	 * @param array $elements flat array of elements to build.
	 * @param int   $parent_id parent of element tree.
	 */
	public function build_tree( array &$elements, $parent_id = 0 ) {
		$branch = [];

		foreach ( $elements as &$element ) {
			if ( $element['parent'] == $parent_id ) {
				$children = $this->build_tree( $elements, $element['ID'] );
				if ( $children ) {
					$element['children'] = $children;
				}
				$branch[ $element['ID'] ] = $element;
				unset( $element );
			}
		}
		return $branch;
	}

	/**
	 * Recursive function to clone hierarchical array of posts
	 *
	 * @param array $elements the hierarchical array.
	 * @param int   $parent_id post_parent to insert under.
	 */
	public function clone_posts( array &$elements, $parent_id = false, $count = 0 ) {
		foreach ( $elements as $element ) {
			$parent_id   = $parent_id ? $parent_id : $element['parent'];
			$new_post_id = $this->duplicate( $element['ID'], $parent_id, $element['title'] );

			// Check for errors.
			if ( is_wp_error( $new_post_id ) ) {
				return $new_post_id;
			}

			// Set new selected page ID.
			if ( ! is_wp_error( $new_post_id ) && $count === 0 ) {
				unset( $_COOKIE['_nine3v_pageId'] );
				unset( $_COOKIE['_nine3v_pageTitle'] );
				setcookie( '_nine3v_pageId', $new_post_id, 0, '/' );
				setcookie( '_nine3v_pageTitle', get_the_title( $new_post_id ), 0, '/' );
			}

			// Recurse!
			if ( isset( $element['children'] ) ) {
				$count++;
				$this->clone_posts( $element['children'], $new_post_id, $count );
			}
		}
	}

	/**
	 * Duplicate post
	 *
	 * @param int    $post_id post to clone.
	 * @param int    $parent_id post parent.
	 * @param string $post_title post title.
	 */
	public function duplicate( $post_id, $parent_id, $post_title ) {
		global $nine3v;

		$oldpost   = get_post( $post_id );
		$exclude   = [ 'ID', 'guid' ];
		$post_args = [
			'post_status' => 'draft',
			'post_parent' => $parent_id,
			'post_title'  => $post_title,
			'post_date'   => gmdate( 'Y-m-d H:i:s' ),
		];

		// Loop through allowed old post args and add to new args.
		foreach ( $oldpost as $key => $value ) {
			if ( ! in_array( $key, $exclude ) && ! isset( $post_args[ $key ] ) ) {
				$post_args[ $key ] = $value;
			}
		}

		// Fix post content.
		$post_args['post_content'] = $this->cleanup_content( $post_args['post_content'] );

		// Check if post_name is a 'version' and increment.
		if ( preg_match( '/(?i)v\d/', $post_title ) ) {
			$post_args['post_name'] = $this->get_version_string( $post_title, true );
		}

		// Add post.
		$new_post_id = wp_insert_post( $post_args );

		// Copy/add post metadata.
		add_post_meta( $new_post_id, 'nine3_cloned', true );
		add_post_meta( $new_post_id, 'nine3_original', $post_id );
		add_post_meta( $new_post_id, 'version_date', gmdate( 'Ymd' ) );

		// Check if the post is a 'version' and increment the meta.
		if ( preg_match( '/(?i)v\d/', $post_title ) ) {
			add_post_meta( $new_post_id, 'version', $this->get_version_string( $post_title ) );
		}

		$data = get_post_custom( $post_id );
		foreach ( $data as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
			}
		}

		// Replace every instance of the version number with the new one in post content.
		$old_version = $nine3v->urls->check_ancestor_meta( $post_id, 'version' );
		$new_version = $nine3v->urls->check_ancestor_meta( $new_post_id, 'version' );
		if ( $old_version && $new_version ) {
			$old_version  = '/' . trim( $old_version, '/' );
			$old_version  = str_replace( '.', '-', $old_version );
			$new_version  = '/' . trim( $new_version, '/' );
			$new_version  = str_replace( '.', '-', $new_version );
			$updated_args = [
				'ID'           => $new_post_id,
				'post_content' => str_replace( $old_version, $new_version, $post_args['post_content'] ),
			];
			$new_post_id  = wp_update_post( $updated_args );
		}

		// Delete any non-required meta.
		delete_post_meta( $new_post_id, 'latest_version' );
		delete_post_meta( $new_post_id, 'original_id' );
		delete_post_meta( $new_post_id, 'version_number' );
		delete_post_meta( $new_post_id, 'redirect_to_url' );
		delete_post_meta( $new_post_id, 'page_primary_menu' );

		return $new_post_id;
	}

	/**
	 * Runs search/replace for given page tree
	 *
	 * @param array  $page_tree array of posts.
	 * @param string $from search for.
	 * @param string $to replace with.
	 */
	public function search_replace( $page_tree, $from, $to ) {
		global $wpdb;

		$post_ids     = wp_list_pluck( $page_tree, 'ID' );
		$posts_string = implode( ',', $post_ids );

		// Replace in posts.
		$content_query = $wpdb->query(
			"UPDATE $wpdb->posts
			SET post_content = REPLACE(post_content, '$from', '$to')
			WHERE ID IN ($posts_string)"
		);

		// Replace in postmeta
		$meta_query = $wpdb->query(
			"UPDATE $wpdb->postmeta
			SET meta_value = REPLACE(meta_value, '$from', '$to')
			WHERE post_id IN ($posts_string)"
		);

		return [ 'posts' => $content_query, 'postmeta' => $meta_query ];
	}

	/**
	 * Publishes given page tree
	 *
	 * @param array  $page_tree array of posts.
	 */
	public function publish( $page_tree ) {
		global $wpdb;

		$post_ids     = wp_list_pluck( $page_tree, 'ID' );
		$posts_string = implode( ',', $post_ids );

		// Replace in posts.
		$status_query = $wpdb->query(
			"UPDATE $wpdb->posts
			SET post_status = REPLACE(post_status, 'draft', 'publish')
			WHERE ID IN ($posts_string)"
		);

		return $status_query;
	}

	/**
	 * Cleans up copied content
	 *
	 * @param string $content the post content.
	 */
	private function cleanup_content( $content ) {
		$content = str_replace( 'u003c', '<', $content );
		$content = str_replace( 'u003e', '>', $content );
		$content = str_replace( 'u0022', "'", $content );
		$content = str_replace( 'u0026', '&', $content );
		$content = str_replace( [ '\r\n', '\t' ], '<br>', $content );
		return $content;
	}

	/**
	 * Recursiverly creates a flat array of posts and their children
	 *
	 * @param int   $parent_id the post to get descendants for.
	 * @param array $posts array of post IDS passed by reference.
	 */
	public function get_descendants( $parent_id, &$posts = [] ) {
		$children = get_pages(
			[
				'child_of'    => $parent_id,
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'sort_column' => 'menu_order, post_title',
				'sort_order'  => 'ASC',
			]
		);
		$children = wp_list_pluck( $children, 'ID' );
		array_unshift( $children, $parent_id );
		return $children;
	}

	/**
	 * Sanitize string and return correct 'version' string
	 *
	 * @param string $string the string to sanitize.
	 * @param bool   $slugify whether to return URL friendly slug.
	 */
	private function get_version_string( $string, $slugify = false ) {
		if ( preg_match( '/(?i)v\d.*(-|.)\d/', $string, $matches ) ) {
			$version = strtolower( $matches[0] );
			return $slugify ? str_replace( [ '.', ' ' ], '-', $version ) : str_replace( ' ', '-' , $version );
		}
	}

	/**
	 * Find key of given value in multidimensional array
	 *
	 * @param mixed  $search value we're searching for.
	 * @param array  $array multidimentional array to search.
	 * @param string $column column within the array to look within.
	 */
	public function get_multidim_key( $search, array $array, string $column ) {
		foreach ( $array as $key => $value ) {
			if ( ! isset( $value[ $column ] ) ) {
				continue;
			}

			if ( is_array( $value[ $column ] ) ) {
				foreach ( $value[ $column ] as $nested ) {
					if ( $search == $nested ) {
						return $key;
					}
				}
			} elseif ( $search == $value[ $column ] ) {
				return $key;
			}
		}
	}
}
