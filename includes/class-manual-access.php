<?php
/**
 * Manual Access Management - ручна видача доступу до курсів
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Manual_Access {
	const LIFETIME_EXPIRES_AT = '2099-12-31 23:59:59';

	/**
	 * DB table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'smartlearn_lms_manual_access';
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
		add_action( 'admin_post_smartlearn_lms_grant_access', array( $this, 'handle_grant_access' ) );
		add_action( 'admin_post_smartlearn_lms_delete_access', array( $this, 'handle_delete_access' ) );
		add_action( 'admin_post_smartlearn_lms_extend_user_accesses', array( $this, 'handle_extend_user_accesses' ) );
		add_action( 'admin_post_smartlearn_lms_extend_all_accesses', array( $this, 'handle_extend_all_accesses' ) );
	}

	/**
	 * Ensure table exists on admin requests after plugin updates.
	 */
	public function maybe_create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $exists !== $table_name ) {
			self::create_table();
		}
		// Normalize legacy empty DATETIME values and keep one record per user/course.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table_name} SET expires_at = %s WHERE expires_at = ''", self::LIFETIME_EXPIRES_AT ) );
		$this->deduplicate_user_course_rows();
	}

	/**
	 * Notify user about access changes.
	 *
	 * @param int    $user_id
	 * @param int    $course_id
	 * @param string $event
	 * @param string $expires_at
	 * @return void
	 */
	private function notify_access_change( $user_id, $course_id, $event, $expires_at = '' ) {
		$user_id = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id ) {
			return;
		}

		do_action( 'smartlearn_lms_access_changed', $user_id, $course_id, sanitize_key( $event ), (string) $expires_at );
	}

	/**
	 * Encode debug payload for redirect URL.
	 *
	 * @param array $payload
	 * @return string
	 */
	private static function encode_debug_payload( array $payload ) {
		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}
		return rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode debug payload from URL.
	 *
	 * @param string $encoded
	 * @return array
	 */
	private static function decode_debug_payload( $encoded ) {
		$encoded = sanitize_text_field( (string) $encoded );
		if ( '' === $encoded ) {
			return array();
		}
		$raw = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Normalize lifetime/empty expiration value.
	 *
	 * @param string|null $expires_at
	 * @return string
	 */
	private static function normalize_expires_at( $expires_at ) {
		$expires_at = (string) $expires_at;
		return '' === $expires_at ? self::LIFETIME_EXPIRES_AT : $expires_at;
	}

	/**
	 * Get user-facing expiry label.
	 *
	 * @param string $expires_at
	 * @return string
	 */
	private static function get_expiry_label( $expires_at ) {
		$expires_at = self::normalize_expires_at( $expires_at );
		if ( self::LIFETIME_EXPIRES_AT === $expires_at ) {
			return __( 'безстроковий', 'smartlearn-lms' );
		}
		$ts = strtotime( $expires_at );
		return $ts ? wp_date( 'Y-m-d H:i', $ts ) : $expires_at;
	}

	/**
	 * Remove duplicate access rows, keeping newest one per user/course.
	 *
	 * @return void
	 */
	private function deduplicate_user_course_rows() {
		global $wpdb;
		$table_name = self::get_table_name();
		$rows = $wpdb->get_results(
			"SELECT user_id, course_id, MAX(id) AS keep_id
			 FROM {$table_name}
			 GROUP BY user_id, course_id
			 HAVING COUNT(*) > 1"
		);
		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name}
					 WHERE user_id = %d
					   AND course_id = %d
					   AND id <> %d",
					absint( $row->user_id ),
					absint( $row->course_id ),
					absint( $row->keep_id )
				)
			);
		}
	}

	/**
	 * Create / update access table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			granted_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NULL,
			note text NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY expires_at (expires_at),
			KEY user_course (user_id,course_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Get user IDs who have access to a course (manual + purchases + free courses).
	 *
	 * @param int  $course_id
	 * @param bool $include_free_all_users
	 * @return array
	 */
	public static function get_course_user_ids( $course_id, $include_free_all_users = true ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return array();
		}

		$user_ids = array();

		// Manual access users.
		$table_name = self::get_table_name();
		$now = current_time( 'mysql' );
		$manual_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$table_name}
				 WHERE course_id = %d
				   AND (expires_at IS NULL OR expires_at = %s OR expires_at >= %s)",
				$course_id,
				self::LIFETIME_EXPIRES_AT,
				$now
			)
		);
		if ( ! empty( $manual_ids ) ) {
			$user_ids = array_merge( $user_ids, array_map( 'absint', $manual_ids ) );
		}

		// Purchases via WooCommerce.
		$product_id = absint( get_post_meta( $course_id, '_smartlearn_course_product_id', true ) );
		if ( $product_id && class_exists( 'WooCommerce' ) ) {
			$purchased_ids = self::get_user_ids_by_product_purchase( $product_id );
			if ( ! empty( $purchased_ids ) ) {
				$user_ids = array_merge( $user_ids, array_map( 'absint', $purchased_ids ) );
			}
		}

		// Free course: include all users if requested.
		$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true );
		if ( $include_free_all_users && '1' === $is_free ) {
			$all_users = get_users(
				array(
					'fields' => 'ID',
					'number' => 2000,
				)
			);
			if ( ! empty( $all_users ) ) {
				$user_ids = array_merge( $user_ids, array_map( 'absint', $all_users ) );
			}
		}

		$user_ids = array_filter( array_unique( $user_ids ) );
		sort( $user_ids );

		return $user_ids;
	}

	/**
	 * Get user IDs that purchased a WooCommerce product.
	 *
	 * @param int $product_id
	 * @return array
	 */
	private static function get_user_ids_by_product_purchase( $product_id ) {
		global $wpdb;

		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return array();
		}

		$order_statuses = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );
		$status_placeholders = implode( ',', array_fill( 0, count( $order_statuses ), '%s' ) );

		$sql = "
			SELECT DISTINCT pm.meta_value AS user_id
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
				ON oi.order_item_id = oim.order_item_id
			INNER JOIN {$wpdb->posts} p
				ON p.ID = oi.order_id
			INNER JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_customer_user'
			WHERE oi.order_item_type = 'line_item'
			  AND oim.meta_key = '_product_id'
			  AND oim.meta_value = %d
			  AND p.post_type = 'shop_order'
			  AND p.post_status IN ({$status_placeholders})
			  AND pm.meta_value > 0
		";

		$params = array_merge( array( $product_id ), $order_statuses );
		$prepared = $wpdb->prepare( $sql, $params );

		$rows = $wpdb->get_col( $prepared );
		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( 'absint', $rows );
	}

	/**
	 * Check if user has active manual access to course.
	 *
	 * @param int $user_id
	 * @param int $course_id
	 * @return bool
	 */
	public static function user_has_active_access( $user_id, $course_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$table_name = self::get_table_name();
		$now = current_time( 'mysql' );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name}
					WHERE user_id = %d
					  AND course_id = %d
					  AND (expires_at IS NULL OR expires_at = %s OR expires_at >= %s)",
					$user_id,
					$course_id,
					self::LIFETIME_EXPIRES_AT,
					$now
				)
			);

		return $count > 0;
	}

	/**
	 * Add submenu page for manual access.
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=smartlearn_course',
			__( 'Доступи користувачів', 'smartlearn-lms' ),
			__( 'Користувачі', 'smartlearn-lms' ),
			'manage_options',
			'smartlearn-lms-access',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Handle grant access request.
	 */
	public function handle_grant_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}

		check_admin_referer( 'smartlearn_lms_grant_access' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$expires_at_raw = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		$is_lifetime = isset( $_POST['is_lifetime'] ) ? '1' : '';
		$redirect = admin_url( 'edit.php?post_type=smartlearn_course&page=smartlearn-lms-access&ui_tab=list&status=active' );

		if ( ! $user_id || ! $course_id ) {
			$debug = self::encode_debug_payload(
				array(
					'step' => 'validate_ids',
					'user_id' => $user_id,
					'course_id' => $course_id,
				)
			);
			wp_safe_redirect( add_query_arg( array( 'sl_notice' => 'invalid', 'sl_debug' => $debug ), $redirect ) );
			exit;
		}

		if ( ! get_user_by( 'id', $user_id ) || 'smartlearn_course' !== get_post_type( $course_id ) ) {
			$debug = self::encode_debug_payload(
				array(
					'step' => 'validate_entities',
					'user_exists' => (bool) get_user_by( 'id', $user_id ),
					'course_type' => (string) get_post_type( $course_id ),
					'user_id' => $user_id,
					'course_id' => $course_id,
				)
			);
			wp_safe_redirect( add_query_arg( array( 'sl_notice' => 'invalid', 'sl_debug' => $debug ), $redirect ) );
			exit;
		}

		$expires_at = '';
		if ( '1' !== $is_lifetime && ! empty( $expires_at_raw ) ) {
			$dt = date_create_from_format( 'Y-m-d\TH:i', $expires_at_raw, wp_timezone() );
			if ( ! $dt ) {
				$debug = self::encode_debug_payload(
					array(
						'step' => 'validate_date',
						'expires_at_raw' => $expires_at_raw,
					)
				);
				wp_safe_redirect( add_query_arg( array( 'sl_notice' => 'invalid_date', 'sl_debug' => $debug ), $redirect ) );
				exit;
			}
			$expires_at = $dt->format( 'Y-m-d H:i:s' );
		}
		$expires_at = self::normalize_expires_at( $expires_at );

		global $wpdb;
		$table_name = self::get_table_name();
		$now = current_time( 'mysql' );

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name}
				 WHERE user_id = %d AND course_id = %d
				 ORDER BY id DESC
				 LIMIT 1",
				$user_id,
				$course_id
			)
		);

		if ( $existing_id > 0 ) {
			$current_expires_at = (string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT expires_at FROM {$table_name} WHERE id = %d",
					$existing_id
				)
			);
			$current_expires_at = self::normalize_expires_at( $current_expires_at );

			// If same access already exists, do not duplicate and do not create a new row.
			if ( $current_expires_at === $expires_at ) {
				$this->deduplicate_user_course_rows();
				wp_safe_redirect(
					add_query_arg(
						array(
							'sl_notice' => 'already_has_access',
							'sl_access_until' => rawurlencode( self::get_expiry_label( $current_expires_at ) ),
						),
						$redirect
					)
				);
				exit;
			}

			$written = $wpdb->update(
				$table_name,
				array(
					'granted_by' => get_current_user_id(),
					'created_at' => $now,
					'expires_at' => $expires_at,
					'note' => $note,
				),
				array( 'id' => $existing_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$write_ok = ( false !== $written );

			// Keep one row per user/course.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name}
					 WHERE user_id = %d
					   AND course_id = %d
					   AND id <> %d",
					$user_id,
					$course_id,
					$existing_id
				)
			);
		} else {
			$inserted = $wpdb->insert(
				$table_name,
				array(
					'user_id' => $user_id,
					'course_id' => $course_id,
					'granted_by' => get_current_user_id(),
					'created_at' => $now,
					'expires_at' => $expires_at,
					'note' => $note,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s' )
			);
			$write_ok = ( false !== $inserted );
		}

		$active_saved = false;
		if ( $write_ok ) {
			$active_saved = self::user_has_active_access( $user_id, $course_id );
		}

		if ( $active_saved ) {
			$event = $existing_id > 0 ? 'extended' : 'granted';
			$this->notify_access_change( $user_id, $course_id, $event, $expires_at );
		}

		if ( ! $active_saved ) {
			$debug = self::encode_debug_payload(
				array(
					'step' => 'persist_access',
					'user_id' => $user_id,
					'course_id' => $course_id,
					'expires_at' => $expires_at,
					'existing_id' => $existing_id,
					'write_ok' => $write_ok,
					'wpdb_error' => (string) $wpdb->last_error,
				)
			);
			wp_safe_redirect( add_query_arg( array( 'sl_notice' => 'error', 'sl_debug' => $debug ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'sl_notice', 'granted', $redirect ) );
		exit;
	}

	/**
	 * Handle delete access request.
	 */
	public function handle_delete_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'smartlearn_lms_delete_access_' . $id );

		$redirect = admin_url( 'edit.php?post_type=smartlearn_course&page=smartlearn-lms-access' );
		if ( ! $id ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}

		global $wpdb;
		$deleted = $wpdb->delete( self::get_table_name(), array( 'id' => $id ), array( '%d' ) );
		wp_safe_redirect( add_query_arg( 'sl_notice', $deleted ? 'deleted' : 'error', $redirect ) );
		exit;
	}

	/**
	 * Handle mass extension for all courses of one user.
	 */
	public function handle_extend_user_accesses() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}

		check_admin_referer( 'smartlearn_lms_extend_user_accesses' );

		$user_id = isset( $_POST['bulk_user_id'] ) ? absint( $_POST['bulk_user_id'] ) : 0;
		$days = isset( $_POST['extend_days'] ) ? absint( $_POST['extend_days'] ) : 0;
		$set_expires_raw = isset( $_POST['bulk_expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_expires_at'] ) ) : '';
		$is_lifetime = isset( $_POST['bulk_is_lifetime'] ) ? '1' : '';
		$redirect = admin_url( 'edit.php?post_type=smartlearn_course&page=smartlearn-lms-access' );

		if ( ! $user_id ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}

		global $wpdb;
		$table_name = self::get_table_name();
		$course_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT course_id FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		$course_ids = array_map( 'absint', (array) $course_ids );

		$where = array( 'user_id' => $user_id );
		$where_format = array( '%d' );

		if ( '1' === $is_lifetime ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name}
					 SET expires_at = %s
					 WHERE user_id = %d",
					self::LIFETIME_EXPIRES_AT,
					$user_id
				)
			);
		} elseif ( ! empty( $set_expires_raw ) ) {
			$dt = date_create_from_format( 'Y-m-d\TH:i', $set_expires_raw, wp_timezone() );
			if ( ! $dt ) {
				wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid_date', $redirect ) );
				exit;
			}
			$updated = $wpdb->update(
				$table_name,
				array( 'expires_at' => $dt->format( 'Y-m-d H:i:s' ) ),
				$where,
				array( '%s' ),
				$where_format
			);
		} else {
			if ( $days <= 0 ) {
				wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
				exit;
			}
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name}
					 SET expires_at = DATE_ADD(
					 	CASE
					 		WHEN expires_at IS NULL OR expires_at < %s THEN %s
					 		ELSE expires_at
					 	END,
					 	INTERVAL %d DAY
					 )
					 WHERE user_id = %d",
					current_time( 'mysql' ),
					current_time( 'mysql' ),
					$days,
					$user_id
				)
			);
		}

		if ( false !== $updated && ! empty( $course_ids ) ) {
			foreach ( $course_ids as $course_id ) {
				$this->notify_access_change( $user_id, $course_id, 'extended', '' );
			}
		}

		wp_safe_redirect( add_query_arg( 'sl_notice', ( false === $updated ? 'error' : 'extended' ), $redirect ) );
		exit;
	}

	/**
	 * Handle global extension for all users with optional exclusions.
	 */
	public function handle_extend_all_accesses() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}

		check_admin_referer( 'smartlearn_lms_extend_all_accesses' );

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$days = isset( $_POST['all_extend_days'] ) ? absint( $_POST['all_extend_days'] ) : 0;
		$exclude_user_ids = isset( $_POST['exclude_user_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['exclude_user_ids'] ) ) : array();
		$exclude_user_ids = array_values( array_filter( $exclude_user_ids ) );
		$redirect = admin_url( 'edit.php?post_type=smartlearn_course&page=smartlearn-lms-access' );

		if ( ! $course_id || $days <= 0 ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}
		$redirect = add_query_arg( 'course_id', $course_id, $redirect );

		$course_user_ids = self::get_course_user_ids( $course_id, true );
		if ( empty( $course_user_ids ) ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}

		if ( ! empty( $exclude_user_ids ) ) {
			$course_user_ids = array_values( array_diff( $course_user_ids, $exclude_user_ids ) );
		}

		if ( empty( $course_user_ids ) ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}

		global $wpdb;
		$table_name = self::get_table_name();
		$now = current_time( 'mysql' );

		$updated = 0;
		$chunk_size = 200;
		$chunks = array_chunk( $course_user_ids, $chunk_size );

		foreach ( $chunks as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$params = array_merge(
				array( $now, $now, $days, $course_id ),
				$chunk,
				array( self::LIFETIME_EXPIRES_AT )
			);

				$sql = "UPDATE {$table_name}
						SET expires_at = DATE_ADD(
							CASE
								WHEN expires_at IS NULL OR expires_at < %s THEN %s
								ELSE expires_at
							END,
						INTERVAL %d DAY
					)
					WHERE course_id = %d
					  AND user_id IN ({$placeholders})
					  AND expires_at IS NOT NULL
					  AND expires_at <> %s";

			$updated += (int) $wpdb->query( $wpdb->prepare( $sql, $params ) );
		}

		$existing_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$table_name} WHERE course_id = %d AND user_id IN (" . implode( ',', array_fill( 0, count( $course_user_ids ), '%d' ) ) . ")",
				array_merge( array( $course_id ), $course_user_ids )
			)
		);
		$existing_ids = array_map( 'absint', (array) $existing_ids );
		$missing_ids = array_values( array_diff( $course_user_ids, $existing_ids ) );

		if ( ! empty( $missing_ids ) ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
			foreach ( $missing_ids as $user_id ) {
				$wpdb->insert(
					$table_name,
					array(
						'user_id' => $user_id,
						'course_id' => $course_id,
						'granted_by' => get_current_user_id(),
						'created_at' => $now,
						'expires_at' => $expires_at,
						'note' => __( 'Масове продовження доступу', 'smartlearn-lms' ),
					),
					array( '%d', '%d', '%d', '%s', '%s', '%s' )
				);
			}
		}

		if ( false !== $updated ) {
			foreach ( $course_user_ids as $target_user_id ) {
				$this->notify_access_change( $target_user_id, $course_id, 'extended', '' );
			}
		}

		wp_safe_redirect( add_query_arg( 'sl_notice', ( false === $updated ? 'error' : 'extended_all' ), $redirect ) );
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}

		$ui_tab = isset( $_GET['ui_tab'] ) ? sanitize_key( wp_unslash( $_GET['ui_tab'] ) ) : 'list';
		if ( ! in_array( $ui_tab, array( 'add', 'extend_user', 'extend_all', 'list' ), true ) ) {
			$ui_tab = 'list';
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
		if ( ! in_array( $status, array( 'active', 'expired', 'all' ), true ) ) {
			$status = 'active';
		}

		global $wpdb;
		$table_name = self::get_table_name();
		$now = current_time( 'mysql' );

		$where = '1=1';
		if ( 'active' === $status ) {
			$where .= $wpdb->prepare( " AND (a.expires_at IS NULL OR a.expires_at = %s OR a.expires_at >= %s)", self::LIFETIME_EXPIRES_AT, $now );
		} elseif ( 'expired' === $status ) {
			$where .= $wpdb->prepare( " AND a.expires_at IS NOT NULL AND a.expires_at <> %s AND a.expires_at < %s", self::LIFETIME_EXPIRES_AT, $now );
		}
		$rows = $wpdb->get_results(
			"SELECT a.*,
			        u.display_name AS user_name,
			        u.user_email AS user_email,
			        p.post_title AS course_title,
			        g.display_name AS granted_by_name
			 FROM {$table_name} a
			 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
			 LEFT JOIN {$wpdb->posts} p ON p.ID = a.course_id
			 LEFT JOIN {$wpdb->users} g ON g.ID = a.granted_by
			 WHERE {$where}
			 ORDER BY a.created_at DESC
			 LIMIT 500"
		);

		$courses = get_posts(
			array(
				'post_type' => 'smartlearn_course',
				'posts_per_page' => -1,
				'post_status' => array( 'publish', 'draft' ),
				'orderby' => 'title',
				'order' => 'ASC',
			)
		);
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order' => 'ASC',
				'number' => 1000,
			)
		);

		$notice = isset( $_GET['sl_notice'] ) ? sanitize_key( wp_unslash( $_GET['sl_notice'] ) ) : '';
		$debug_payload = isset( $_GET['sl_debug'] ) ? self::decode_debug_payload( wp_unslash( $_GET['sl_debug'] ) ) : array();
		$debug_text = ! empty( $debug_payload ) ? wp_json_encode( $debug_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) : '';
		$selected_course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;
		$selected_course_id = $selected_course_id ? $selected_course_id : 0;
		$course_user_ids = $selected_course_id ? self::get_course_user_ids( $selected_course_id, true ) : array();
		$course_users = ! empty( $course_user_ids )
			? get_users(
				array(
					'include' => $course_user_ids,
					'orderby' => 'display_name',
					'order' => 'ASC',
					'number' => 2000,
				)
			)
			: array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Доступи', 'smartlearn-lms' ); ?></h1>
			<p><?php esc_html_e( 'Видавайте доступ вручну та задавайте індивідуальний термін дії.', 'smartlearn-lms' ); ?></p>

			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'add' === $ui_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'add' ), admin_url( 'edit.php' ) ) ); ?>">
					<?php esc_html_e( 'Додати доступ', 'smartlearn-lms' ); ?>
				</a>
				<a class="nav-tab <?php echo 'extend_user' === $ui_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'extend_user' ), admin_url( 'edit.php' ) ) ); ?>">
					<?php esc_html_e( 'Продовжити користувачу', 'smartlearn-lms' ); ?>
				</a>
				<a class="nav-tab <?php echo 'extend_all' === $ui_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'extend_all' ), admin_url( 'edit.php' ) ) ); ?>">
					<?php esc_html_e( 'Продовжити всім', 'smartlearn-lms' ); ?>
				</a>
				<a class="nav-tab <?php echo 'list' === $ui_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'list', 'status' => $status ), admin_url( 'edit.php' ) ) ); ?>">
					<?php esc_html_e( 'Список', 'smartlearn-lms' ); ?>
				</a>
			</h2>

			<?php if ( 'granted' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Доступ надано.', 'smartlearn-lms' ); ?></p></div>
			<?php elseif ( 'extended' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Доступи користувача оновлено.', 'smartlearn-lms' ); ?></p></div>
			<?php elseif ( 'extended_all' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Доступи всіх користувачів оновлено.', 'smartlearn-lms' ); ?></p></div>
			<?php elseif ( 'already_has_access' === $notice ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<?php
						$access_until = isset( $_GET['sl_access_until'] ) ? sanitize_text_field( wp_unslash( $_GET['sl_access_until'] ) ) : '';
						if ( '' !== $access_until ) {
							echo esc_html( sprintf( __( 'У користувача вже є доступ до курсу (%s). Існуючий запис збережено без дублювання.', 'smartlearn-lms' ), $access_until ) );
						} else {
							esc_html_e( 'У користувача вже є доступ до курсу. Існуючий запис збережено без дублювання.', 'smartlearn-lms' );
						}
						?>
					</p>
				</div>
			<?php elseif ( 'deleted' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Доступ видалено.', 'smartlearn-lms' ); ?></p></div>
			<?php elseif ( in_array( $notice, array( 'invalid', 'invalid_date', 'error' ), true ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Не вдалося виконати дію. Перевірте дані.', 'smartlearn-lms' ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'add' === $ui_tab ) : ?>
			<h2><?php esc_html_e( 'Додати доступ користувачу', 'smartlearn-lms' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:1000px;">
				<?php wp_nonce_field( 'smartlearn_lms_grant_access' ); ?>
				<input type="hidden" name="action" value="smartlearn_lms_grant_access">
				<table class="form-table">
					<tr>
						<th><label for="user_id"><?php esc_html_e( 'Користувач', 'smartlearn-lms' ); ?></label></th>
						<td>
							<select name="user_id" id="user_id" required style="min-width:320px;">
								<option value=""><?php esc_html_e( '— Виберіть користувача —', 'smartlearn-lms' ); ?></option>
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>">
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="course_id"><?php esc_html_e( 'Курс', 'smartlearn-lms' ); ?></label></th>
						<td>
							<select name="course_id" id="course_id" required style="min-width:320px;">
								<option value=""><?php esc_html_e( '— Виберіть курс —', 'smartlearn-lms' ); ?></option>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="expires_at"><?php esc_html_e( 'Дійсний до', 'smartlearn-lms' ); ?></label></th>
						<td>
							<input type="datetime-local" name="expires_at" id="expires_at">
							<p class="description"><?php esc_html_e( 'Залиште порожнім для безстрокового ручного доступу.', 'smartlearn-lms' ); ?></p>
							<label style="display:inline-block;margin-top:8px;">
								<input type="checkbox" name="is_lifetime" value="1"> <?php esc_html_e( 'Безстроковий доступ', 'smartlearn-lms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="note"><?php esc_html_e( 'Нотатка', 'smartlearn-lms' ); ?></label></th>
						<td><textarea name="note" id="note" rows="3" style="width:100%;max-width:600px;"></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Надати доступ', 'smartlearn-lms' ) ); ?>
			</form>
			<?php endif; ?>

			<?php if ( 'extend_user' === $ui_tab ) : ?>
			<h2 style="margin-top:24px;"><?php esc_html_e( 'Продовжити всі курси користувачу', 'smartlearn-lms' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:1000px;">
				<?php wp_nonce_field( 'smartlearn_lms_extend_user_accesses' ); ?>
				<input type="hidden" name="action" value="smartlearn_lms_extend_user_accesses">
				<table class="form-table">
					<tr>
						<th><label for="bulk_user_id"><?php esc_html_e( 'Користувач', 'smartlearn-lms' ); ?></label></th>
						<td>
							<select name="bulk_user_id" id="bulk_user_id" required style="min-width:320px;">
								<option value=""><?php esc_html_e( '— Виберіть користувача —', 'smartlearn-lms' ); ?></option>
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>">
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="extend_days"><?php esc_html_e( 'Продовжити на (днів)', 'smartlearn-lms' ); ?></label></th>
						<td>
							<input type="number" min="1" step="1" id="extend_days" name="extend_days" value="30" class="small-text">
							<p class="description"><?php esc_html_e( 'Працює для всіх ручних доступів обраного користувача.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bulk_expires_at"><?php esc_html_e( 'Або виставити дату', 'smartlearn-lms' ); ?></label></th>
						<td>
							<input type="datetime-local" id="bulk_expires_at" name="bulk_expires_at">
							<p class="description"><?php esc_html_e( 'Якщо вказано, ця дата замінить строк для всіх ручних доступів користувача.', 'smartlearn-lms' ); ?></p>
							<label style="display:inline-block;margin-top:8px;">
								<input type="checkbox" name="bulk_is_lifetime" value="1"> <?php esc_html_e( 'Зробити всі доступи безстроковими', 'smartlearn-lms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Продовжити всі курси', 'smartlearn-lms' ), 'secondary' ); ?>
			</form>
			<?php endif; ?>

			<?php if ( 'extend_all' === $ui_tab ) : ?>
			<h2 style="margin-top:24px;"><?php esc_html_e( 'Продовжити всім користувачам', 'smartlearn-lms' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:1000px;margin-bottom:16px;">
				<input type="hidden" name="post_type" value="smartlearn_course">
				<input type="hidden" name="page" value="smartlearn-lms-access">
				<input type="hidden" name="ui_tab" value="extend_all">
				<table class="form-table">
					<tr>
						<th><label for="course_id_select"><?php esc_html_e( 'Курс', 'smartlearn-lms' ); ?></label></th>
						<td>
							<select name="course_id" id="course_id_select" required style="min-width:320px;" onchange="this.form.submit();">
								<option value=""><?php esc_html_e( '— Виберіть курс —', 'smartlearn-lms' ); ?></option>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $selected_course_id, $course->ID ); ?>>
										<?php echo esc_html( $course->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:1000px;">
				<?php wp_nonce_field( 'smartlearn_lms_extend_all_accesses' ); ?>
				<input type="hidden" name="action" value="smartlearn_lms_extend_all_accesses">
				<input type="hidden" name="course_id" value="<?php echo esc_attr( $selected_course_id ); ?>">
				<table class="form-table">
					<tr>
						<th><label for="all_extend_days"><?php esc_html_e( 'Продовжити на (днів)', 'smartlearn-lms' ); ?></label></th>
						<td>
							<input type="number" min="1" step="1" id="all_extend_days" name="all_extend_days" value="5" class="small-text" required>
							<p class="description"><?php esc_html_e( 'Подовжує строкові доступи для всіх користувачів курсу.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="exclude_user_ids"><?php esc_html_e( 'Виключення (не продовжувати)', 'smartlearn-lms' ); ?></label></th>
						<td>
							<?php if ( ! $selected_course_id ) : ?>
								<p class="description"><?php esc_html_e( 'Спочатку оберіть курс вище.', 'smartlearn-lms' ); ?></p>
							<?php elseif ( empty( $course_users ) ) : ?>
								<p class="description"><?php esc_html_e( 'Немає користувачів з доступом до цього курсу.', 'smartlearn-lms' ); ?></p>
							<?php else : ?>
								<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
									<div>
										<div style="font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Користувачі курсу', 'smartlearn-lms' ); ?></div>
										<select id="course_user_ids" multiple size="10" style="min-width:420px;max-width:100%;">
											<?php foreach ( $course_users as $user ) : ?>
												<option value="<?php echo esc_attr( $user->ID ); ?>">
													<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div style="display:flex;flex-direction:column;gap:8px;">
										<button type="button" class="button" id="sl-exclude-add">+</button>
										<button type="button" class="button" id="sl-exclude-remove">-</button>
									</div>
									<div>
										<div style="font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Виключення', 'smartlearn-lms' ); ?></div>
										<select id="exclude_user_ids" name="exclude_user_ids[]" multiple size="10" style="min-width:420px;max-width:100%;"></select>
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'Виберіть користувачів зліва і натисніть + щоб додати у виключення.', 'smartlearn-lms' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Продовжити всім', 'smartlearn-lms' ), 'secondary', 'submit', true, $selected_course_id ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
			<script>
			(function() {
				var addBtn = document.getElementById('sl-exclude-add');
				var removeBtn = document.getElementById('sl-exclude-remove');
				var source = document.getElementById('course_user_ids');
				var target = document.getElementById('exclude_user_ids');
				if (!addBtn || !removeBtn || !source || !target) { return; }

				function moveSelected(from, to) {
					var selected = Array.prototype.slice.call(from.options).filter(function(opt) { return opt.selected; });
					selected.forEach(function(opt) {
						opt.selected = false;
						to.appendChild(opt);
					});
				}

				addBtn.addEventListener('click', function() {
					moveSelected(source, target);
				});

				removeBtn.addEventListener('click', function() {
					moveSelected(target, source);
				});

				var form = target.closest('form');
				if (form) {
					form.addEventListener('submit', function() {
						Array.prototype.slice.call(target.options).forEach(function(opt) { opt.selected = true; });
					});
				}
			})();
			</script>
			<?php endif; ?>

			<?php if ( 'list' === $ui_tab ) : ?>
			<div style="margin-top:24px;">
			<ul class="subsubsub">
				<li>
							<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'list', 'status' => 'active' ), admin_url( 'edit.php' ) ) ); ?>" class="<?php echo 'active' === $status ? 'current' : ''; ?>">
							<?php esc_html_e( 'Активні', 'smartlearn-lms' ); ?>
						</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'list', 'status' => 'expired' ), admin_url( 'edit.php' ) ) ); ?>" class="<?php echo 'expired' === $status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Протерміновані', 'smartlearn-lms' ); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'smartlearn_course', 'page' => 'smartlearn-lms-access', 'ui_tab' => 'list', 'status' => 'all' ), admin_url( 'edit.php' ) ) ); ?>" class="<?php echo 'all' === $status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Всі', 'smartlearn-lms' ); ?>
					</a>
				</li>
			</ul>
			<table class="widefat striped" style="margin-top:12px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Користувач', 'smartlearn-lms' ); ?></th>
						<th><?php esc_html_e( 'Курс', 'smartlearn-lms' ); ?></th>
						<th><?php esc_html_e( 'Надано', 'smartlearn-lms' ); ?></th>
						<th><?php esc_html_e( 'Дійсний до', 'smartlearn-lms' ); ?></th>
						<th><?php esc_html_e( 'Статус', 'smartlearn-lms' ); ?></th>
						<th><?php esc_html_e( 'Адмін', 'smartlearn-lms' ); ?></th>
						<th><?php esc_html_e( 'Дія', 'smartlearn-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Записів немає.', 'smartlearn-lms' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$is_active = empty( $row->expires_at ) || $row->expires_at === self::LIFETIME_EXPIRES_AT || $row->expires_at >= $now;
							$delete_url = wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'smartlearn_lms_delete_access',
										'id' => absint( $row->id ),
									),
									admin_url( 'admin-post.php' )
								),
								'smartlearn_lms_delete_access_' . absint( $row->id )
							);
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $row->user_name ? $row->user_name : ( '#' . absint( $row->user_id ) ) ); ?></strong><br>
									<small><?php echo esc_html( $row->user_email ); ?></small>
								</td>
								<td><?php echo esc_html( $row->course_title ? $row->course_title : ( '#' . absint( $row->course_id ) ) ); ?></td>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><?php echo esc_html( ( empty( $row->expires_at ) || $row->expires_at === self::LIFETIME_EXPIRES_AT ) ? __( 'Безстроково', 'smartlearn-lms' ) : $row->expires_at ); ?></td>
								<td>
									<?php if ( $is_active ) : ?>
										<span style="color:#0a7a00;font-weight:600;"><?php esc_html_e( 'Активний', 'smartlearn-lms' ); ?></span>
									<?php else : ?>
										<span style="color:#b32d2e;font-weight:600;"><?php esc_html_e( 'Завершений', 'smartlearn-lms' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row->granted_by_name ? $row->granted_by_name : ( '#' . absint( $row->granted_by ) ) ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Видалити цей доступ?', 'smartlearn-lms' ) ); ?>');"><?php esc_html_e( 'Видалити', 'smartlearn-lms' ); ?></a></td>
							</tr>
							<?php if ( ! empty( $row->note ) ) : ?>
								<tr>
									<td colspan="7"><em><?php echo esc_html( $row->note ); ?></em></td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $debug_text ) ) : ?>
				<div style="margin-top:28px;">
					<details style="background:#fff;border:1px solid #dcdcde;padding:10px 12px;">
						<summary style="cursor:pointer;font-weight:600;">
							<?php esc_html_e( 'Для розробника: технічний debug log (натисніть, щоб відкрити)', 'smartlearn-lms' ); ?>
						</summary>
						<pre style="white-space:pre-wrap;background:#f6f7f7;border:1px solid #dcdcde;padding:8px;max-height:260px;overflow:auto;margin-top:10px;"><?php echo esc_html( $debug_text ); ?></pre>
					</details>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
