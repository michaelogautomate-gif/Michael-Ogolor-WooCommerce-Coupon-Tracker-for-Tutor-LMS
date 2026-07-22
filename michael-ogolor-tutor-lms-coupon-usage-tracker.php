<?php
/**
 * Plugin Name:       Michael Ogolor WooCommerce Coupon Tracker for Tutor LMS
 * Plugin URI:        https://github.com/michaelogautomate-gif/tutor-lms-coupon-usage-tracker
 * Description:       Tracks WooCommerce coupon usage for Tutor LMS course purchases with B2B partnership attribution, guest checkout support, KPI metrics, and secure CSV exports.
 * Version:           4.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Michael Ogolor
 * Author URI:        https://shorturl.at/wyUYh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tutor-wc-coupon-tracker
 * Domain Path:       /languages
 *
 * @package           TutorWCCouponTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Declare High-Performance Order Storage (HPOS) Compatibility for WooCommerce.
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Main Singleton Plugin Class for Tutor LMS WooCommerce Coupon Tracker.
 *
 * @since 4.0.0
 */
final class Tutor_WC_Coupon_Tracker {

	/**
	 * Single instance of the class.
	 *
	 * @var Tutor_WC_Coupon_Tracker|null
	 */
	private static $instance = null;

	/**
	 * Database table name with prefix.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Current plugin version string.
	 *
	 * @var string
	 */
	const VERSION = '4.0.0';

	/**
	 * Main Instance Gateway.
	 *
	 * Ensures only one instance of the plugin is loaded or can be loaded (Singleton Pattern).
	 *
	 * @return Tutor_WC_Coupon_Tracker Singleton instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private Constructor.
	 *
	 * Initializes properties, defines constants, and attaches primary hooks.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'tutor_coupon_usage';

		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define plugin-wide environment constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'TCM_VERSION', self::VERSION );
		define( 'TCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'TCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'TCM_PLUGIN_FILE', __FILE__ );
	}

	/**
	 * Register WordPress core hooks, actions, and administrative triggers.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Table Installation & Updates
		register_activation_hook( TCM_PLUGIN_FILE, array( $this, 'install_table' ) );

		// WooCommerce Order Processing Hooks
		add_action( 'woocommerce_order_status_completed', array( $this, 'log_coupon_usage_on_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'log_coupon_usage_on_order' ), 10, 1 );

		// Admin Dashboard Hooks
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
	}

	/**
	 * Create or upgrade the custom database table schema using dbDelta.
	 *
	 * @return void
	 */
	public function install_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			coupon_code varchar(100) NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY coupon_code (coupon_code),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY course_id (course_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'tcm_db_version', self::VERSION );
	}

	/**
	 * Log coupon usage when a WooCommerce order transitions to Processing or Completed.
	 *
	 * Cross-references Tutor LMS course relationships and prevents duplicate tracking records.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 * @return void
	 */
	public function log_coupon_usage_on_order( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$coupons = $order->get_coupon_codes();
		if ( empty( $coupons ) || ! is_array( $coupons ) ) {
			return;
		}

		global $wpdb;

		// Extract Tutor LMS Course IDs associated with items in this order
		$course_ids = array();
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			// Check standard post meta relationship key (_tutor_course_id)
			$tutor_course_id = get_post_meta( $product_id, '_tutor_course_id', true );

			if ( ! $tutor_course_id ) {
				// Reverse lookup fallback: check if product ID is linked in postmeta table
				$tutor_course_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tutor_course_product_id' AND meta_value = %d",
						$product_id
					)
				);
			}

			if ( $tutor_course_id ) {
				$course_ids[] = (int) $tutor_course_id;
			} else {
				// Fallback to WooCommerce Product ID if not directly linked to a Tutor course
				$course_ids[] = (int) $product_id;
			}
		}

		$course_ids = ! empty( $course_ids ) ? array_unique( $course_ids ) : array( 0 );
		$user_id    = (int) $order->get_user_id();

		// Iterate through all applied coupons (Multi-coupon support)
		foreach ( $coupons as $raw_coupon ) {
			$coupon_code = strtoupper( sanitize_text_field( $raw_coupon ) );

			foreach ( $course_ids as $course_id ) {
				// Prevent duplicate entry for the exact same order, coupon, and course combination
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$this->table_name} WHERE order_id = %d AND coupon_code = %s AND course_id = %d",
						$order_id,
						$coupon_code,
						$course_id
					)
				);

				if ( ! $existing ) {
					$wpdb->insert(
						$this->table_name,
						array(
							'order_id'    => $order_id,
							'user_id'     => $user_id,
							'coupon_code' => $coupon_code,
							'course_id'   => $course_id,
							'created_at'  => current_time( 'mysql' ),
						),
						array( '%d', '%d', '%s', '%d', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Register the primary admin navigation menu page.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Tutor LMS Coupon Tracker', 'tutor-wc-coupon-tracker' ),
			__( 'Coupon Tracker', 'tutor-wc-coupon-tracker' ),
			'manage_options',
			'tutor-wc-coupon-tracker',
			array( $this, 'render_admin_page' ),
			'dashicons-chart-bar',
			56
		);
	}

	/**
	 * Enqueue Admin Stylesheet on the plugin page specifically.
	 *
	 * @param string $hook Page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_tutor-wc-coupon-tracker' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tcm-admin-css',
			TCM_PLUGIN_URL . 'css/admin-style.css',
			array(),
			TCM_VERSION
		);
	}

	/**
	 * Fetch Summary KPI Analytics Metrics for Dashboard display.
	 *
	 * @return array Metric key-value indicators.
	 */
	private function get_summary_metrics() {
		global $wpdb;

		$total_redemptions = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name}" );
		$unique_coupons    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT coupon_code) FROM {$this->table_name}" );

		$top_coupon_row = $wpdb->get_row(
			"SELECT coupon_code, COUNT(id) as usage_count
			 FROM {$this->table_name}
			 GROUP BY coupon_code
			 ORDER BY usage_count DESC
			 LIMIT 1"
		);

		$top_coupon = $top_coupon_row ? $top_coupon_row->coupon_code . ' (' . number_format_i18n( $top_coupon_row->usage_count ) . ')' : __( 'N/A', 'tutor-wc-coupon-tracker' );

		return array(
			'total_redemptions' => $total_redemptions,
			'unique_coupons'    => $unique_coupons,
			'top_coupon'        => $top_coupon,
		);
	}

	/**
	 * Handle CSV File Generation and Streaming with Nonce & Capability verification.
	 *
	 * @return void
	 */
	public function handle_csv_export() {
		if ( ! isset( $_POST['tcm_export_csv'] ) ) {
			return;
		}

		// Security Check 1: Capability Authorization
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user attempt.', 'tutor-wc-coupon-tracker' ), 403 );
		}

		// Security Check 2: Nonce Verification
		if ( ! isset( $_POST['tcm_export_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tcm_export_nonce'] ), 'tcm_export_csv_action' ) ) {
			wp_die( esc_html__( 'Invalid security token. Please refresh and try again.', 'tutor-wc-coupon-tracker' ), 403 );
		}

		global $wpdb;

		// Fetch filtered or full dataset safely
		$search_where = '';
		$query_args   = array();

		if ( ! empty( $_POST['s'] ) ) {
			$search_term  = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_POST['s'] ) ) ) . '%';
			$search_where = ' WHERE coupon_code LIKE %s ';
			$query_args[] = $search_term;
		}

		$sql     = "SELECT * FROM {$this->table_name} {$search_where} ORDER BY created_at DESC";
		$results = ! empty( $query_args ) ? $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		// Configure CSV Download Stream Headers
		$filename = 'coupon-usage-report-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Header Row
		fputcsv(
			$output,
			array(
				__( 'ID', 'tutor-wc-coupon-tracker' ),
				__( 'Date & Time', 'tutor-wc-coupon-tracker' ),
				__( 'Coupon Code', 'tutor-wc-coupon-tracker' ),
				__( 'Order ID', 'tutor-wc-coupon-tracker' ),
				__( 'Username', 'tutor-wc-coupon-tracker' ),
				__( 'First Name', 'tutor-wc-coupon-tracker' ),
				__( 'Last Name', 'tutor-wc-coupon-tracker' ),
				__( 'Email', 'tutor-wc-coupon-tracker' ),
				__( 'Course Title', 'tutor-wc-coupon-tracker' ),
			)
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$order = wc_get_order( $row['order_id'] );
				$user  = get_userdata( $row['user_id'] );

				$first_name = $order ? $order->get_billing_first_name() : ( $user ? $user->first_name : '' );
				$last_name  = $order ? $order->get_billing_last_name() : ( $user ? $user->last_name : '' );
				$email      = $order ? $order->get_billing_email() : ( $user ? $user->user_email : '' );
				$username   = $user ? $user->user_login : __( 'Guest', 'tutor-wc-coupon-tracker' );
				$course     = get_the_title( $row['course_id'] );

				fputcsv(
					$output,
					array(
						$row['id'],
						$row['created_at'],
						$row['coupon_code'],
						$row['order_id'],
						$username,
						$first_name,
						$last_name,
						$email,
						$course ? $course : __( 'N/A', 'tutor-wc-coupon-tracker' ),
					)
				);
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Render Administrative Interface, Analytics Grid, and WP_List_Table UI.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$list_table = new Tutor_WC_Coupon_List_Table( $this->table_name );
		$list_table->prepare_items();

		$metrics = $this->get_summary_metrics();
		?>
		<div class="wrap tcm-admin-wrap">
			<div class="tcm-header-banner">
				<div class="tcm-header-content">
					<h1><?php esc_html_e( 'WooCommerce Coupon Usage Tracker', 'tutor-wc-coupon-tracker' ); ?></h1>
					<p><?php esc_html_e( 'Real-time B2B partnership attribution, coupon metrics, and course enrollment logging for Tutor LMS.', 'tutor-wc-coupon-tracker' ); ?></p>
				</div>
			</div>

			<div class="tcm-stats-grid">
				<div class="tcm-stat-card">
					<div class="tcm-stat-icon dashicons dashicons-tickets-alt"></div>
					<div class="tcm-stat-data">
						<span class="tcm-stat-value"><?php echo esc_html( number_format_i18n( $metrics['total_redemptions'] ) ); ?></span>
						<span class="tcm-stat-label"><?php esc_html_e( 'Total Redemptions', 'tutor-wc-coupon-tracker' ); ?></span>
					</div>
				</div>

				<div class="tcm-stat-card">
					<div class="tcm-stat-icon dashicons dashicons-tag"></div>
					<div class="tcm-stat-data">
						<span class="tcm-stat-value"><?php echo esc_html( number_format_i18n( $metrics['unique_coupons'] ) ); ?></span>
						<span class="tcm-stat-label"><?php esc_html_e( 'Active Coupons', 'tutor-wc-coupon-tracker' ); ?></span>
					</div>
				</div>

				<div class="tcm-stat-card">
					<div class="tcm-stat-icon dashicons dashicons-star-filled"></div>
					<div class="tcm-stat-data">
						<span class="tcm-stat-value"><?php echo esc_html( $metrics['top_coupon'] ); ?></span>
						<span class="tcm-stat-label"><?php esc_html_e( 'Top Coupon Code', 'tutor-wc-coupon-tracker' ); ?></span>
					</div>
				</div>
			</div>

			<div class="tcm-card">
				<div class="tcm-card-header">
					<h2><?php esc_html_e( 'Coupon Usage Audit Trail', 'tutor-wc-coupon-tracker' ); ?></h2>

					<form method="post" action="" class="tcm-export-form">
						<?php wp_nonce_field( 'tcm_export_csv_action', 'tcm_export_nonce' ); ?>
						<?php if ( ! empty( $_REQUEST['s'] ) ) : ?>
							<input type="hidden" name="s" value="<?php echo esc_attr( wp_unslash( $_REQUEST['s'] ) ); ?>" />
						<?php endif; ?>
						<button type="submit" name="tcm_export_csv" class="button button-primary tcm-btn-export">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export to CSV', 'tutor-wc-coupon-tracker' ); ?>
						</button>
					</form>
				</div>

				<form method="get">
					<input type="hidden" name="page" value="tutor-wc-coupon-tracker" />
					<?php
					$list_table->search_box( __( 'Search Coupons', 'tutor-wc-coupon-tracker' ), 'tcm-search' );
					$list_table->display();
					?>
				</form>
			</div>
		</div>
		<?php
	}
}

/**
 * Custom WP_List_Table Subclass for rendering paginated and formatted coupon usage items.
 */
if ( class_exists( 'WP_List_Table' ) || require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ) {

	class Tutor_WC_Coupon_List_Table extends WP_List_Table {

		/**
		 * Database Table Name.
		 *
		 * @var string
		 */
		private $table_name;

		/**
		 * Constructor.
		 *
		 * @param string $table_name Target DB table name.
		 */
		public function __construct( $table_name ) {
			$this->table_name = $table_name;
			parent::__construct(
				array(
					'singular' => 'coupon_log',
					'plural'   => 'coupon_logs',
					'ajax'     => false,
				)
			);
		}

		/**
		 * Define List Table Columns.
		 *
		 * @return array Column definitions.
		 */
		public function get_columns() {
			return array(
				'cb'          => '<input type="checkbox" />',
				'created_at'  => __( 'Date & Time', 'tutor-wc-coupon-tracker' ),
				'coupon_code' => __( 'Coupon Code', 'tutor-wc-coupon-tracker' ),
				'order_id'    => __( 'Order ID', 'tutor-wc-coupon-tracker' ),
				'username'    => __( 'Username', 'tutor-wc-coupon-tracker' ),
				'first_name'  => __( 'First Name', 'tutor-wc-coupon-tracker' ),
				'last_name'   => __( 'Last Name', 'tutor-wc-coupon-tracker' ),
				'email'       => __( 'Email Address', 'tutor-wc-coupon-tracker' ),
				'course_id'   => __( 'Enrolled Course', 'tutor-wc-coupon-tracker' ),
			);
		}

		/**
		 * Define Sortable Columns.
		 *
		 * @return array Sortable column keys.
		 */
		public function get_sortable_columns() {
			return array(
				'created_at'  => array( 'created_at', true ),
				'coupon_code' => array( 'coupon_code', false ),
				'order_id'    => array( 'order_id', false ),
			);
		}

		/**
		 * Render Default Column Value Markup with explicit CSS badge classes.
		 *
		 * @param array  $item        Row data array.
		 * @param string $column_name Current column name.
		 * @return string Formatted column HTML.
		 */
		public function column_default( $item, $column_name ) {
			$order = wc_get_order( $item['order_id'] );
			$user  = get_userdata( $item['user_id'] );

			switch ( $column_name ) {
				case 'created_at':
					return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) );

				case 'coupon_code':
					return '<span class="tcm-badge-code">' . esc_html( strtoupper( $item['coupon_code'] ) ) . '</span>';

				case 'order_id':
					if ( $order ) {
						$url = method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : get_edit_post_link( $item['order_id'] );
						return '<a href="' . esc_url( $url ) . '" class="tcm-order-link">#' . esc_html( $item['order_id'] ) . '</a>';
					}
					return '#' . esc_html( $item['order_id'] );

				case 'username':
					return $user ? esc_html( $user->user_login ) : '<i>' . esc_html__( 'Guest', 'tutor-wc-coupon-tracker' ) . '</i>';

				case 'first_name':
					return esc_html( $order ? $order->get_billing_first_name() : ( $user ? $user->first_name : '-' ) );

				case 'last_name':
					return esc_html( $order ? $order->get_billing_last_name() : ( $user ? $user->last_name : '-' ) );

				case 'email':
					$email = $order ? $order->get_billing_email() : ( $user ? $user->user_email : '' );
					if ( ! empty( $email ) ) {
						return '<a href="' . esc_url( 'mailto:' . sanitize_email( $email ) ) . '">' . esc_html( $email ) . '</a>';
					}
					return '-';

				case 'course_id':
					$title = get_the_title( $item['course_id'] );
					return esc_html( $title ? $title : __( 'Course #' . $item['course_id'], 'tutor-wc-coupon-tracker' ) );

				default:
					return '';
			}
		}

		/**
		 * Render Bulk Action Checkbox Column.
		 *
		 * @param array $item Row data array.
		 * @return string Checkbox HTML.
		 */
		public function column_cb( $item ) {
			return sprintf( '<input type="checkbox" name="id[]" value="%s" />', esc_attr( $item['id'] ) );
		}

		/**
		 * Prepare table items, handle search filtering, column sorting, and pagination.
		 *
		 * @return void
		 */
		public function prepare_items() {
			global $wpdb;

			$per_page     = 20;
			$current_page = $this->get_pagenum();
			$offset       = ( $current_page - 1 ) * $per_page;

			$columns               = $this->get_columns();
			$hidden                = array();
			$sortable              = $this->get_sortable_columns();
			$this->_column_headers = array( $columns, $hidden, $sortable );

			// Build Search Condition
			$search_where = '';
			$query_args   = array();

			if ( ! empty( $_REQUEST['s'] ) ) {
				$search_term  = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) . '%';
				$search_where = ' WHERE coupon_code LIKE %s ';
				$query_args[] = $search_term;
			}

			// Whitelist Allowed Sorting Columns to prevent SQL Injection
			$allowed_orderby = array( 'created_at', 'coupon_code', 'order_id' );
			$orderby_input   = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
			$orderby         = in_array( $orderby_input, $allowed_orderby, true ) ? $orderby_input : 'created_at';

			$order_input     = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) : 'DESC';
			$order           = ( 'ASC' === $order_input ) ? 'ASC' : 'DESC';

			// Execute Total Count Query safely
			if ( ! empty( $search_where ) ) {
				$total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$this->table_name} {$search_where}", $query_args ) );
			} else {
				$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$this->table_name}" );
			}

			// Execute Data Fetch Query with Pagination Parameters
			if ( ! empty( $search_where ) ) {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$this->table_name} {$search_where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					array_merge( $query_args, array( $per_page, $offset ) )
				);
			} else {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$this->table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$per_page,
					$offset
				);
			}

			$this->items = $wpdb->get_results( $sql, ARRAY_A );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total_items / $per_page ),
				)
			);
		}
	}
}

/**
 * Bootstrap the Plugin Singleton Instance.
 *
 * @return Tutor_WC_Coupon_Tracker
 */
function run_tutor_wc_coupon_tracker() {
	return Tutor_WC_Coupon_Tracker::get_instance();
}
run_tutor_wc_coupon_tracker();
