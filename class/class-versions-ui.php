<?php
/**
 * This class handles any user interface elements for the plugin
 *
 * @package nine3versions
 */

namespace nine3versions;

/**
 * Class definition
 */
final class Versions_Ui {
	/**
	 * Key name for our transient
	 *
	 * @var string $array_trans_key
	 */
	public $array_trans_key = 'nine3v_array_trans';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Setup page screen interface.
		add_action( 'admin_footer', [ $this, 'display_pages_sidebar' ] );

		// Delete transient on page save.
		add_action( 'save_post', [ $this, 'delete_array_transient' ], 10, 2 );

		// Add row quick link buttons.
		add_filter( 'page_row_actions', [ $this, 'add_quick_links' ], 10, 2 );

		// Add menu order column.
		add_filter( 'manage_pages_columns', [ $this, 'page_columns' ] );
		add_action( 'manage_pages_custom_column', [ $this, 'order_page_columns' ] );
	}

	/**
	 * 'admin_footer' action hook callback
	 * Creates UI for navigating/cloning pages.
	 */
	public function display_pages_sidebar() {
		global $nine3v;

		$current = get_current_screen();

		if ( ! $current || $current->id !== 'edit-page' ) {
			return;
		}

		// Get array tree transient or set one.
		$array_tree = $this->get_array_tree();

		$this->render_sidebar( $array_tree );
	}

	/**
	 * Gets or sets transient of our main sidebar array tree
	 */
	public function get_array_tree() {
		global $nine3v;

		$array_tree = get_transient( $this->array_trans_key );
		if ( ! $array_tree || empty( $array_tree ) ) {
			$page_args = [
				'post_type'   => 'page',
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ],
				'parent'      => 0,
			];

			$pages      = get_pages( $page_args );
			$array_tree = [];

			foreach ( $pages as $page ) {
				$array_tree = array_merge( $array_tree, $nine3v->helpers->get_page_tree( $page->ID ) );
			}

			set_transient( $this->array_trans_key, $array_tree );
		}

		return $array_tree;
	}

	/**
	 * Delete transient
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function delete_array_transient( $post_id, $post ) {
		if ( $post && $post->post_type !== 'page' ) {
			return;
		}

		delete_transient( $this->array_trans_key );
	}

	/**
	 * Invalidate array tree transient when a page is added/updated
	 */

	/**
	 * 'page_row_actions' filter hook cllback
	 * Adds quick links to page edit rows
	 *
	 * @param array   $actions array of row action links.
	 * @param WP_Post $post The post object.
	 */
	public function add_quick_links( $actions, $post ) {
		if ( $post->post_type === 'page' ) {
			$actions['clone-page'] = '<a href="#" rel="bookmark" aria-label="Clone page">Clone page</a>';
			$actions['clone']      = '<a href="#" rel="bookmark" aria-label="Clone page tree of ' . $post->post_title . '">Clone page tree</a>';
		}

		return $actions;
	}

	/**
	 * Renders sidebar
	 *
	 * @param array $array_tree the array of the page hierarchy tree to display.
	 */
	public function render_sidebar( array $array_tree ) {
		// Start buffer.
		ob_start();
		?>
		<div class="nine3v-page-sidebar">
			<div class="nine3v-page-sidebar__resizer"></div>
			<div class="nine3v-page-sidebar__content">
				<h2 class="nine3v-page-sidebar__title wp-heading-inline"><?php esc_html_e( 'Page Hierarchy', 'nine3versions' ); ?></h2>

				<div class="nine3v-page-sidebar__toolbar-wrap">
					<ul class="nine3v-page-sidebar__toolbar">
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-edit-page"></span>
							<span class="page-action" data-page-action="nine3v-edit"><?php esc_html_e( 'Edit page', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-admin-page"></span>
							<span class="page-action" data-page-action="nine3v-clone-page"><?php esc_html_e( 'Clone page', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-welcome-add-page"></span>
							<span class="page-action" data-page-action="nine3v-add"><?php esc_html_e( 'Add child page', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-networking"></span>
							<span class="page-action" data-page-action="nine3v-clone"><?php esc_html_e( 'Clone page tree', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-move"></span>
							<span class="page-action" data-page-action="nine3v-move"><?php esc_html_e( 'Move page tree', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-trash"></span>
							<span class="page-action" data-page-action="nine3v-delete"><?php esc_html_e( 'Delete page tree', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-search"></span>
							<span class="page-action" data-page-action="nine3v-replace"><?php esc_html_e( 'Search/replace tree', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-yes"></span>
							<span class="page-action" data-page-action="nine3v-publish"><?php esc_html_e( 'Publish tree', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
							<span class="dashicons dashicons-no-alt"></span>
							<span class="page-action" data-page-action="nine3v-clear"><?php esc_html_e( 'Clear selection', 'nine3versions' ); ?></span>
						</li>
						<li class="nine3v-page-sidebar__toolbar-item">
						</li>
					</ul>
				</div>

				<div class="nine3v-page-sidebar__pages-wrap">
					<div class="nine3v-page-sidebar__buttons">
						<p class="nine3v-page-sidebar__blurb"><?php esc_html_e( 'Please choose a new parent for the current tree.', 'luna' ); ?></p>
						<button class="nine3v-page-sidebar__accept" disabled><?php esc_html_e( 'Accept', 'luna' ); ?></button>
						<button class="nine3v-page-sidebar__cancel"><?php esc_html_e( 'Cancel', 'luna' ); ?></button>
						<button class="nine3v-page-sidebar__root"><?php esc_html_e( 'Root Level', 'luna' ); ?></button>
					</div>

					<ul class="nine3v-page-sidebar__pages">
						<?php
						foreach ( $array_tree as $branch ) {
							echo wp_kses_post( $this->render_list( $branch ) );
						}
						?>
					</ul>
				</div>
			</div>
		</div>
		<?php
		echo wp_kses_post( ob_get_clean() );
	}

	/**
	 * Recursively renders list items and their children
	 *
	 * @param array $branch the current list branch array.
	 */
	private function render_list( $branch ) {
		$status    = isset( $branch['status'] ) && $branch['status'] !== 'publish' ? '(' . ucfirst( $branch['status'] ) . ')' : '';
		$is_latest = get_post_meta( $branch['ID'], 'latest_version', true );
		$latest    = $is_latest ? __( 'Latest', 'luna' ) : '';

		if ( isset( $branch['children'] ) ) {
			$html = '<li>
			<span class="page-toggle"></span>
			<span class="page-item page-item-has-children" data-page-id="' . $branch['ID'] . '" data-parent-id="' . $branch['parent'] . '">
				<span class="page-title">' . $branch['title'] . '</span>
				<span class="page-latest">' . $latest . '</span>
				<span class="page-status">' . $status . '</span>
				<span class="page-count">' . count( $branch['children'] ) . '</span>
			</span>';
		} else {
			$html = '<li>
			<span class="page-item" data-page-id="' . $branch['ID'] . '" data-parent-id="' . $branch['parent'] . '">
				<span class="page-title">' . $branch['title'] . '</span>
				<span class="page-latest">' . $latest . '</span>
				<span class="page-status">' . $status . '</span>' .
			"</span>\n";
		}

		if ( isset( $branch['children'] ) ) {
			$html .= '<ul class="nested">';
			foreach ( $branch['children'] as $child ) {
				$html .= $this->render_list( $child );
			}
			$html .= '</ul>';
		}

		$html .= "</li>\n";
		return $html;
	}

	/**
	 * Add Order column to page edit screen
	 *
	 * @param array $columns the array of columns.
	 */
	public function page_columns( $columns ) {
		unset( $columns['comments'] );
		$columns['order'] = __( 'Order', 'nine3versions' );
		return $columns;
	}

	/**
	 * Render markup for custom column
	 *
	 * @param string $name the name of the column.
	 */
	public function order_page_columns( $name ) {
		global $post;

		switch ( $name ) {
			case 'order':
				$menu_order = $post->menu_order;
				ob_start();
				$markup = ob_get_clean();
				?>
				<input type="number" class="nine3v__order" value="<?php echo $menu_order; ?>">
				<?php
				echo wp_kses_post( $markup );
				break;
		}
	}
}
