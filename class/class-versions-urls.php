<?php
/**
 * This class is responsible for rewriting page URLs
 *
 * @package nine3versions
 */

namespace nine3versions;

/**
 * Class definition
 */
final class Versions_URLs {
	/**
	 * Construct
	 */
	public function __construct() {
		// Change page permalink.
		add_filter( 'page_link', [ $this, 'change_page_link' ], 1, 2 );

		// Filter the URL request to accommodate for new permalink.
		// add_filter( 'request', [ $this, 'rewrite_request' ], 8, 1 );
		add_filter( 'request', [ $this, 'rewrite_request_meta' ], 9, 1 );

		// Handle URL redirect if set.
		add_action( 'template_redirect', [ $this, 'handle_redirect' ] );

		// Check URL and redirect if 404.
		add_action( 'template_redirect', [ $this, 'permalink_manager_redirect' ] );

		// Remove/add old/new post URL on post save if not applicable.
		add_action( 'save_post', [ $this, 'amend_post_meta' ], 10, 2 );
		add_action( 'acf/save_post', [ $this, 'amend_custom_url' ] );

		// Filter custom_url acf field.
		add_filter( 'acf/load_field/name=custom_url', [ $this, 'filter_custom_url_acf' ] );
	}

	/**
	 * 'page_link' filter hook callback
	 * Rewrites the post permalink if a 'version' is found in self or ancestor meta
	 *
	 * @param string $post_link the current post URL.
	 * @param int    $post_id the current post ID.
	 */
	public function change_page_link( $post_link, $post_id ) {
		// Remove root ancestor slug from URL.
		$ancestors        = get_ancestors( $post_id, 'page' );
		$home_id          = get_option( 'page_on_front' );
		$is_home_ancestor = false;
		if ( ! empty( $ancestors ) && in_array( $home_id, $ancestors ) ) {
			$is_home_ancestor = true;
			$old_link         = $post_link;
			$home_post        = get_post( $home_id );
			$home_slug        = $home_post->post_name;
			$post_link        = str_replace( $home_slug . '/', '', $post_link );
		}

		// Change permalink for 'version' pages.
		$version = $this->check_ancestor_meta( $post_id, 'version' );
		$latest  = $this->check_ancestor_meta( $post_id, 'latest_version' );
		if ( $version ) {
			$old_link = $post_link;
			$version  = str_replace( [ '.', ' ' ], '-', $version );

			// Remove version substring from URL.
			$version_substr = strpos( $post_link, '/' . $version . '-' );
			if ( $version_substr !== false ) {
				$start_at = $version_substr + strlen( '/' . $version . '-' );
				$end_at   = strpos( $post_link, '/', $start_at );
				$replace  = substr( $post_link, $start_at, $end_at - $start_at );
				$version  = $version . '-' . $replace;
			}

			// Generate new post link.
			if ( $latest ) {
				$post_link = str_replace( '/' . $version, '', $post_link ) . 'latest/';
			} else {
				$post_link = str_replace( '/' . $version, '', $post_link ) . $version;
			}

			// Check if home is ancestor and re-add the slug into old url.
			if ( $is_home_ancestor && isset( $home_slug ) ) {
				$home_url = home_url();
				$old_link = str_replace( $home_url, $home_url . '/' . $home_slug, $old_link );
			}
		}

		// Update post meta to reflect new post link and help with rewrites.
		if ( $is_home_ancestor || $version ) {
			if ( strpos( $post_link, '%pagename%' ) === false ) {
				delete_post_meta( $post_id, 'new_url' );
				update_post_meta( $post_id, 'new_url', $post_link );
			}
			
			if ( strpos( $old_link, '%pagename%' ) === false ) {
				delete_post_meta( $post_id, 'old_url' );
				update_post_meta( $post_id, 'old_url', $old_link );
			}
		}

		// Check if custom URL is set.
		$custom_url = get_post_meta( $post_id, 'custom_url', true );
		if ( $custom_url && ! empty( $custom_url ) ) {
			$custom_url = ltrim( $custom_url, '/' );
			$custom_url = rtrim( $custom_url, '/' ) . '/';
			$post_link  = esc_url( home_url( '/' ) . $custom_url );
		}

		return $post_link;
	}

	/**
	 * 'request' filter hook callback
	 * Rewrites the query if a 'version' part of a URL is found
	 *
	 * @param array $query array of requested query variables.
	 */
	public function rewrite_request( $query ) {
		// Check if version page and fix page request.
		$regex = '/(.*)\/(?i)v\d.*-\d*\/?$/';
		$url   = urldecode( $this->current_location() );
		preg_match( $regex, $url, $matches );
		if ( $matches ) {
			$home_url    = home_url();
			$home_slug   = get_post( get_option( 'page_on_front' ) )->post_name;
			$version     = trim( str_replace( $matches[1], '', $matches[0] ), '/' );
			$slug        = $home_slug . '/' . trim( str_replace( $home_url, '', $matches[1] ), '/' );
			$url_parts   = explode( '/', $slug );
			$count_parts = count( $url_parts );
			$url_rebuilt = false;

			for ( $x = 0; $x <= $count_parts; $x++ ) {
				$url_rebuilt = $this->check_page_path( $url_parts, $x, $version );
				if ( $url_rebuilt ) {
					break;
				}
			}

			if ( $url_rebuilt ) {
				$query = [
					'pagename' => $url_rebuilt,
				];
			}
		}

		return $query;
	}

	/**
	 * 'request' filter hook callback
	 * Checks the page URL against post meta and if found rewrites to correct URL.
	 *
	 * @param array $query array of requested query variables.
	 */
	public function rewrite_request_meta( $query ) {
		$url = urldecode( $this->current_location() );
		$url = rtrim( $url, '/' ) . '/';

		if ( isset( $_GET['preview_id'] ) ) {
			$post_id = sanitize_text_field( intval( $_GET['preview_id'] ) );
			$query   = [
				'page_id' => $post_id,
			];
			return $query;
		}

		// Return if home page.
		if ( $url === home_url( '/' ) ) {
			return $query;
		}

		$slug    = trim( str_replace( home_url( '/' ), '', $url ), '/' );
		$post_id = $this->get_post_from_meta( 'custom_url', $slug );
		$post_id = $post_id ? $post_id : $this->get_post_from_meta( 'new_url', $url );

		// Try trimming the last slash.
		if ( ! $post_id ) {
			$post_id = $this->get_post_from_meta( 'new_url', rtrim( $url, '/' ) );
		}

		// If version URL try checking '/latest' instead.
		if ( ! $post_id ) {
			$regex     = '/(.*)\/(?i)v\d.*-\d*\/?$/';
			preg_match( $regex, $url, $matches );
			if ( $matches ) {
				$version = trim( str_replace( $matches[1], '', $matches[0] ), '/' );
				$url     = rtrim( str_replace( $version, 'latest', $url ), '/' ) . '/';
				$post_id = $this->get_post_from_meta( 'new_url', $url );
			}
		}

		if ( $post_id ) {
			$old_url = get_post_meta( $post_id, 'old_url', true );
			if ( $old_url ) {
				$old_url = trim( str_replace( home_url(), '', $old_url ), '/' );
				$query = [
					'pagename' => $old_url,
				];
			} else {
				$query = [
					'page_id' => $post_id,
				];
			}
		}

		return $query;
	}

	/**
	 * 'template_redirect' action hook callback
	 * Handles redirects if one is set in meta
	 */
	public function handle_redirect() {
		$is_cancel = is_search();
		if ( $is_cancel ) {
			return;
		}

		$post_id       = get_the_ID();
		$redirect_meta = get_post_meta( $post_id, 'redirect_to_url', true );
		if ( ! isset( $redirect_meta['url'] ) || ! $redirect_meta['url'] ) {
			return;
		}

		wp_safe_redirect( $redirect_meta['url'] );
		exit;
	}

	/**
	 * 'template_redirect' action hook callback
	 * Reads array of custom permalink changes from the old site and redirects to the correct page if found
	 */
	public function permalink_manager_redirect() {
		if ( ! is_404() ) {
			return;
		}

		$current_url  = rtrim( urldecode( $this->current_location() ), '/' );
		$home_url     = home_url( '/' );
		$relative_url = str_replace( $home_url, '', $current_url );

		include NINE3_VERSIONS_PATH . '/includes/obs-permalinks.php';
		$original_id = array_search( $relative_url, $uris );

		if ( $original_id ) {
			$post_id = $this->get_post_from_meta( 'original_id', $original_id );
			if ( $post_id ) {
				$permalink = get_the_permalink( $post_id );

				// If latest, make sure we go to latest post.
				if ( strpos( $relative_url, 'latest' ) !== false && strpos( $permalink, 'latest' ) == false ) {
					$regex     = '/(.*)\/(?i)v\d.*-\d*\/?$/';
					$permalink = preg_replace( $regex, '/latest', $permalink );
				}

				wp_safe_redirect( $permalink );
				exit;
			}
		}

		// If nothing is still found, try to go to 'latest' version.
		if ( strpos( $relative_url, 'latest' ) === false ) {
			$current_url .= '/latest/';
			$post_id      = $this->get_post_from_meta( 'new_url', $current_url );
			$post_id      = $post_id ? $post_id : $this->get_post_from_meta( 'new_url', rtrim( $current_url, '/' ) );
			if ( $post_id ) {
				$permalink = get_the_permalink( $post_id );
				wp_safe_redirect( $permalink );
				exit;
			}
		}
	}

	/**
	 * Checks if there are other versions of this page.
	 *
	 * @param int $post_id the post ID of page to check.
	 */
	public function check_for_versions( $post_id = false ) {
		$post_id        = $post_id ? $post_id : get_the_ID();
		$home_url       = home_url();
		$version_array  = $this->check_ancestor_meta( $post_id, 'version', 'array' );
		$version_id     = key( $version_array );
		$version        = current( $version_array );
		$version        = $version ? $version : 'N/A';
		$version_parent = get_post_parent( $version_id );
		$version_url    = get_permalink( $post_id );
		$version_slug   = str_replace( '.', '-', $version );
		$version_path   = trim( str_replace( [ '/' . $version_slug, '/latest', $home_url ], '', $version_url ), '/' );
		$all_versions   = [
			$version => $version_url,
		];
		
		if ( $version === 'N/A' ) {
			// Check if child page is version.
			$args = [
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_parent'    => $post_id,
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
			];

			$children = new \WP_Query( $args );
			if ( $children->have_posts() ) {
				foreach ( $children->posts as $post_id ) {
					$version = get_post_meta( $post_id, 'version', true );
					if ( $version ) {
						$all_versions[ $version ] = get_permalink( $post_id );
					}
				}
			}
			wp_reset_postdata();
		} elseif ( $version_parent ) {
			// Get sibling version posts.
			$args = [
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'post_parent'    => $version_parent->ID,
				'post__not_in'   => [ $version_id ],
				'fields'         => 'ids',
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
			];

			$pages = new \WP_Query( $args );
			if ( $pages->have_posts() ) {
				foreach ( $pages->posts as $post_id ) {
					$version   = get_post_meta( $post_id, 'version', true );
					$is_latest = get_post_meta( $post_id, 'latest_version', true );
					if ( ! $version ) {
						continue;
					}

					$version_slug = str_replace( '.', '-', $version );
					$version_url  = trim( home_url( '/' ) . $version_path . '/' . $version_slug, '/' );
					$version_post = $this->get_post_from_meta( 'new_url', $version_url, 'LIKE' );

					if ( $is_latest ) {
						$version_url  = trim( home_url( '/' ) . $version_path . '/latest', '/' );
						$version_post = $this->get_post_from_meta( 'new_url', $version_url, 'LIKE' );
					}

					if ( $version_post ) {
						$all_versions[ $version ] = get_permalink( $version_post );
					}
				}
			}
		}

		$version_keys = array_keys( $all_versions );
		usort( $version_keys, 'version_compare' );
		$this->sort_custom( $all_versions, $version_keys );
		return array_reverse( $all_versions );
	}

	/**
	 * Sort first array keys using second array values
	 *
	 * @param array $array first array to sort and return.
	 * @param array $hierarchy second array vales to use as order.
	 */
	public function sort_custom( &$array, $hierarchy ) {
		$hierarchy = array_flip( $hierarchy );
		uksort( $array, fn( $a, $b ) => $hierarchy[ $a ] <=> $hierarchy[ $b ] );
	}

	/**
	 * Gets meta from current post and all ancestors if current is not set.
	 *
	 * @param int    $post_id post ID (and its ancestors) to check for meta.
	 * @param string $meta_key the meta key to check.
	 * @param string $return the type of var to return (just the meta value or array).
	 */
	public function check_ancestor_meta( $post_id, $meta_key, $return = 'value' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( ! $value ) {
			$ancestors = get_post_ancestors( $post_id );
			foreach ( $ancestors as $ancestor ) {
				$value = get_post_meta( $ancestor, $meta_key, true );
				if ( $value ) {
					$post_id = $ancestor;
					break;
				}
			}
		}

		if ( $return === 'array' ) {
			$value = [
				$post_id => $value,
			];
		}

		return $value;
	}

	/**
	 * Returns full current URL with protocol
	 */
	private function current_location() {
		if (
			isset( $_SERVER['HTTPS'] ) &&
			( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1 ) ||
			isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
		) {
			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}

		return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	/**
	 * Helper function inserts a string at a specified position in array,
	 * Then builds page path and check if page exists, if it does returns correct page path.
	 *
	 * @param array  $array parts of URL string.
	 * @param int    $index which position to insert string into.
	 * @param string $insert the string to insert into array.
	 */
	private function check_page_path( $array, $index, $insert ) {
		array_splice( $array, $index, 0, $insert );
		$url_rebuilt = implode( '/', $array );
		$page_exists = get_page_by_path( $url_rebuilt );
		return $page_exists ? $url_rebuilt : false;
	}

	/**
	 * Returns the post ID of a given meta value/key
	 *
	 * @param string $meta_key the key to lookup.
	 * @param string $meta_value the value to lookup.
	 * @param string $operator default is =.
	 */
	private function get_post_from_meta( $meta_key, $meta_value, $operator = '=' ) {
		$args = [
			'post_type'      => 'page',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => $operator,
				],
			],
		];

		$posts = new \WP_Query( $args );
		return $posts->have_posts() ? $posts->posts[0] : false;
	}

	/**
	 * Remove unneeded meta
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function amend_post_meta( $post_id, $post ) {
		if ( $post && $post->post_type !== 'page' ) {
			return;
		}

		// Delete unnecessary meta.
		$version   = $this->check_ancestor_meta( $post_id, 'version' );
		$ancestors = get_ancestors( $post_id, 'page' );
		$home_id   = get_option( 'page_on_front' );
		if ( ! in_array( $home_id, $ancestors ) || ! $version ) {
			delete_post_meta( $post_id, 'new_url' );
			delete_post_meta( $post_id, 'old_url' );
		}
	}

	/**
	 * Update custom URL meta
	 *
	 * @param int $post_id Post ID.
	 */
	public function amend_custom_url( $post_id ) {
		if ( get_post_type( $post_id ) !== 'page' ) {
			return;
		}

		// Amend custom URL meta.
		if ( isset( $_POST['acf']['field_626a64f43e9c4'] ) && $_POST['acf']['field_626a64f43e9c4'] ) {
			$custom_url = sanitize_text_field( $_POST['acf']['field_626a64f43e9c4'] );
			if ( $custom_url && ! empty( $custom_url ) ) {
				$custom_url = trim( $custom_url, '/' );
				update_post_meta( $post_id, 'custom_url', $custom_url );
			}
		}
	}

	/**
	 * Add placeholder for custom URL field.
	 *
	 * @param array $field array of field params.
	 */
	public function filter_custom_url_acf( $field ) {
		$post_id     = isset( $_REQUEST['post'] ) ? intval( $_REQUEST['post'] ) : get_the_ID();
		$permalink   = get_the_permalink( $post_id );
		$home_url    = home_url( '/' );
		$placeholder = str_replace( $home_url, '', $permalink );
		$field['placeholder'] = $placeholder;
		return $field;
	}
}
