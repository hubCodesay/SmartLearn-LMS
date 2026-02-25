<?php
/**
 * Manual Access Management - ручна видача доступу до курсів
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Manual_Access {

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
				  AND (expires_at IS NULL OR expires_at >= %s)",
				$user_id,
				$course_id,
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
		$redirect = admin_url( 'edit.php?post_type=smartlearn_course&page=smartlearn-lms-access' );

		if ( ! $user_id || ! $course_id ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}

		$expires_at = null;
		if ( '1' !== $is_lifetime && ! empty( $expires_at_raw ) ) {
			$dt = date_create_from_format( 'Y-m-d\TH:i', $expires_at_raw, wp_timezone() );
			if ( ! $dt ) {
				wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid_date', $redirect ) );
				exit;
			}
			$expires_at = $dt->format( 'Y-m-d H:i:s' );
		}

		global $wpdb;
		$table_name = self::get_table_name();

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id' => $user_id,
				'course_id' => $course_id,
				'granted_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
				'expires_at' => $expires_at,
				'note' => $note,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( 'sl_notice', $inserted ? 'granted' : 'error', $redirect ) );
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

		$where = array( 'user_id' => $user_id );
		$where_format = array( '%d' );

		if ( '1' === $is_lifetime ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name}
					 SET expires_at = NULL
					 WHERE user_id = %d",
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

		$days = isset( $_POST['all_extend_days'] ) ? absint( $_POST['all_extend_days'] ) : 0;
		$exclude_user_ids = isset( $_POST['exclude_user_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['exclude_user_ids'] ) ) : array();
		$exclude_user_ids = array_values( array_filter( $exclude_user_ids ) );
		$redirect = admin_url( 'edit.php?post_type=smartlearn_course&page=smartlearn-lms-access' );

		if ( $days <= 0 ) {
			wp_safe_redirect( add_query_arg( 'sl_notice', 'invalid', $redirect ) );
			exit;
		}

		global $wpdb;
		$table_name = self::get_table_name();

		$sql = "UPDATE {$table_name}
			SET expires_at = DATE_ADD(
				CASE
					WHEN expires_at < %s THEN %s
					ELSE expires_at
				END,
				INTERVAL %d DAY
			)
			WHERE expires_at IS NOT NULL";

		$params = array(
			current_time( 'mysql' ),
			current_time( 'mysql' ),
			$days,
		);

		if ( ! empty( $exclude_user_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $exclude_user_ids ), '%d' ) );
			$sql .= " AND user_id NOT IN ({$placeholders})";
			$params = array_merge( $params, $exclude_user_ids );
		}

		$updated = $wpdb->query( $wpdb->prepare( $sql, $params ) );

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
			$where .= $wpdb->prepare( ' AND (a.expires_at IS NULL OR a.expires_at >= %s)', $now );
		} elseif ( 'expired' === $status ) {
			$where .= $wpdb->prepare( ' AND a.expires_at IS NOT NULL AND a.expires_at < %s', $now );
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
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:1000px;">
				<?php wp_nonce_field( 'smartlearn_lms_extend_all_accesses' ); ?>
				<input type="hidden" name="action" value="smartlearn_lms_extend_all_accesses">
				<table class="form-table">
					<tr>
						<th><label for="all_extend_days"><?php esc_html_e( 'Продовжити на (днів)', 'smartlearn-lms' ); ?></label></th>
						<td>
							<input type="number" min="1" step="1" id="all_extend_days" name="all_extend_days" value="5" class="small-text" required>
							<p class="description"><?php esc_html_e( 'Подовжує всі строкові (не безстрокові) ручні доступи.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="exclude_user_ids"><?php esc_html_e( 'Виключення (не продовжувати)', 'smartlearn-lms' ); ?></label></th>
						<td>
							<select id="exclude_user_ids" name="exclude_user_ids[]" multiple size="8" style="min-width:420px;max-width:100%;">
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>">
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Утримуйте Ctrl/Cmd для вибору кількох користувачів.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Продовжити всім', 'smartlearn-lms' ), 'secondary' ); ?>
			</form>
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
							$is_active = empty( $row->expires_at ) || $row->expires_at >= $now;
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
								<td><?php echo esc_html( $row->expires_at ? $row->expires_at : __( 'Безстроково', 'smartlearn-lms' ) ); ?></td>
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
		</div>
		<?php
	}
}
