<?php
/**
 * CRM Center - єдина адмін-сторінка з даними та експортом/імпортом
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_CRM {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_smartlearn_lms_crm_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_smartlearn_lms_crm_import_access', array( $this, 'handle_import_access' ) );
	}

	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=smartlearn_course',
			__( 'CRM Центр', 'smartlearn-lms' ),
			__( 'CRM', 'smartlearn-lms' ),
			'manage_options',
			'smartlearn-lms-crm',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! in_array( $tab, array( 'overview', 'users', 'orders', 'access' ), true ) ) {
			$tab = 'overview';
		}

		$base_url = add_query_arg(
			array(
				'post_type' => 'smartlearn_course',
				'page' => 'smartlearn-lms-crm',
			),
			admin_url( 'edit.php' )
		);

		$notice = isset( $_GET['crm_notice'] ) ? sanitize_key( wp_unslash( $_GET['crm_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CRM Центр', 'smartlearn-lms' ); ?></h1>
			<p><?php esc_html_e( 'Єдина панель для управління користувачами, замовленнями та доступами.', 'smartlearn-lms' ); ?></p>

			<?php if ( 'import_done' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						$ok = isset( $_GET['import_ok'] ) ? absint( $_GET['import_ok'] ) : 0;
						$fail = isset( $_GET['import_fail'] ) ? absint( $_GET['import_fail'] ) : 0;
						echo esc_html(
							sprintf(
								/* translators: 1: imported count, 2: failed count */
								__( 'Імпорт завершено. Додано: %1$d, пропущено: %2$d.', 'smartlearn-lms' ),
								$ok,
								$fail
							)
						);
						?>
					</p>
				</div>
			<?php elseif ( 'import_error' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Імпорт не вдався. Перевірте CSV файл.', 'smartlearn-lms' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'overview' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'overview', $base_url ) ); ?>"><?php esc_html_e( 'Огляд', 'smartlearn-lms' ); ?></a>
				<a class="nav-tab <?php echo 'users' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'users', $base_url ) ); ?>"><?php esc_html_e( 'Користувачі', 'smartlearn-lms' ); ?></a>
				<a class="nav-tab <?php echo 'orders' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'orders', $base_url ) ); ?>"><?php esc_html_e( 'Замовлення', 'smartlearn-lms' ); ?></a>
				<a class="nav-tab <?php echo 'access' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'access', $base_url ) ); ?>"><?php esc_html_e( 'Доступи', 'smartlearn-lms' ); ?></a>
			</h2>

			<?php
			if ( 'overview' === $tab ) {
				$this->render_overview_tab();
			} elseif ( 'users' === $tab ) {
				$this->render_users_tab();
			} elseif ( 'orders' === $tab ) {
				$this->render_orders_tab();
			} elseif ( 'access' === $tab ) {
				$this->render_access_tab();
			}
			?>
		</div>
		<?php
	}

	private function render_overview_tab() {
		global $wpdb;

		$total_users = count_users();
		$all_users = isset( $total_users['total_users'] ) ? (int) $total_users['total_users'] : 0;

		$wc_orders = 0;
		$wc_customers = 0;
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_customers = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta}
				 WHERE meta_key = '_customer_user' AND meta_value <> '0'"
			);
			$wc_orders = (int) wp_count_posts( 'shop_order' )->publish;
		}

		$active_access = 0;
		$expired_access = 0;
		if ( class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			$table = SmartLearn_LMS_Manual_Access::get_table_name();
			$now = current_time( 'mysql' );
			$active_access = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE expires_at IS NULL OR expires_at >= %s",
					$now
				)
			);
			$expired_access = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < %s",
					$now
				)
			);
		}

		$post_type_stats = $wpdb->get_results(
			"SELECT post_type, COUNT(*) AS total
			 FROM {$wpdb->posts}
			 WHERE post_status IN ('publish', 'private')
			 GROUP BY post_type
			 ORDER BY total DESC
			 LIMIT 12"
		);
		?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:16px;">
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;">
				<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Всього користувачів', 'smartlearn-lms' ); ?></h3>
				<div style="font-size:28px;font-weight:700;"><?php echo esc_html( $all_users ); ?></div>
			</div>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;">
				<h3 style="margin:0 0 8px;"><?php esc_html_e( 'WooCommerce клієнти', 'smartlearn-lms' ); ?></h3>
				<div style="font-size:28px;font-weight:700;"><?php echo esc_html( $wc_customers ); ?></div>
			</div>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;">
				<h3 style="margin:0 0 8px;"><?php esc_html_e( 'WooCommerce замовлення', 'smartlearn-lms' ); ?></h3>
				<div style="font-size:28px;font-weight:700;"><?php echo esc_html( $wc_orders ); ?></div>
			</div>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;">
				<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Ручні доступи (активні)', 'smartlearn-lms' ); ?></h3>
				<div style="font-size:28px;font-weight:700;"><?php echo esc_html( $active_access ); ?></div>
			</div>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;">
				<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Ручні доступи (завершені)', 'smartlearn-lms' ); ?></h3>
				<div style="font-size:28px;font-weight:700;"><?php echo esc_html( $expired_access ); ?></div>
			</div>
		</div>

		<h2 style="margin-top:24px;"><?php esc_html_e( 'Контент по розділах (post types)', 'smartlearn-lms' ); ?></h2>
		<table class="widefat striped" style="max-width:700px;">
			<thead><tr><th><?php esc_html_e( 'Розділ', 'smartlearn-lms' ); ?></th><th><?php esc_html_e( 'Кількість', 'smartlearn-lms' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $post_type_stats as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->post_type ); ?></td>
						<td><?php echo esc_html( (int) $row->total ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_users_tab() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';

		$args = array(
			'number' => 200,
			'orderby' => 'registered',
			'order' => 'DESC',
			'fields' => array( 'ID', 'display_name', 'user_email', 'user_registered', 'roles' ),
		);
		if ( ! empty( $search ) ) {
			$args['search'] = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		if ( ! empty( $role ) ) {
			$args['role'] = $role;
		}

		$users_query = new WP_User_Query( $args );
		$users = $users_query->get_results();
		$editable_roles = wp_roles()->get_names();
		?>
		<form method="get" style="margin-top:16px;">
			<input type="hidden" name="post_type" value="smartlearn_course">
			<input type="hidden" name="page" value="smartlearn-lms-crm">
			<input type="hidden" name="tab" value="users">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Пошук (ім\'я/email)', 'smartlearn-lms' ); ?>">
			<select name="role">
				<option value=""><?php esc_html_e( 'Всі ролі', 'smartlearn-lms' ); ?></option>
				<?php foreach ( $editable_roles as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $role, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button button-secondary"><?php esc_html_e( 'Фільтрувати', 'smartlearn-lms' ); ?></button>
		</form>

		<?php $this->render_export_form( 'users', array( 'id', 'name', 'email', 'registered', 'roles', 'orders_count', 'total_spent' ), array( 's' => $search, 'role' => $role ) ); ?>

		<table class="widefat striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php esc_html_e( 'Ім\'я', 'smartlearn-lms' ); ?></th>
					<th>Email</th>
					<th><?php esc_html_e( 'Дата реєстрації', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Ролі', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Замовлень', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Витрачено', 'smartlearn-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $users ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Немає даних.', 'smartlearn-lms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $users as $user ) : ?>
						<?php
						$orders_count = ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_customer_order_count' ) ) ? (int) wc_get_customer_order_count( $user->ID ) : 0;
						$total_spent = ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_customer_total_spent' ) ) ? (float) wc_get_customer_total_spent( $user->ID ) : 0;
						$total_spent_label = ( class_exists( 'WooCommerce' ) && function_exists( 'wc_price' ) ) ? wp_strip_all_tags( wc_price( $total_spent ) ) : (string) $total_spent;
						?>
						<tr>
							<td><?php echo esc_html( $user->ID ); ?></td>
							<td><?php echo esc_html( $user->display_name ); ?></td>
							<td><?php echo esc_html( $user->user_email ); ?></td>
							<td><?php echo esc_html( $user->user_registered ); ?></td>
							<td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
							<td><?php echo esc_html( $orders_count ); ?></td>
							<td><?php echo esc_html( $total_spent_label ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_orders_tab() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_orders' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce не активний.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		$query_args = array(
			'limit' => 200,
			'orderby' => 'date',
			'order' => 'DESC',
			'return' => 'objects',
		);
		if ( ! empty( $status ) ) {
			$query_args['status'] = array( $status );
		}
		if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
			$date_query = '';
			if ( ! empty( $date_from ) ) {
				$date_query .= $date_from;
			}
			$date_query .= '...';
			if ( ! empty( $date_to ) ) {
				$date_query .= $date_to;
			}
			$query_args['date_created'] = $date_query;
		}

		$orders = wc_get_orders( $query_args );
		$statuses = wc_get_order_statuses();
		?>
		<form method="get" style="margin-top:16px;">
			<input type="hidden" name="post_type" value="smartlearn_course">
			<input type="hidden" name="page" value="smartlearn-lms-crm">
			<input type="hidden" name="tab" value="orders">
			<select name="status">
				<option value=""><?php esc_html_e( 'Всі статуси', 'smartlearn-lms' ); ?></option>
				<?php foreach ( $statuses as $key => $label ) : ?>
					<?php $clean = str_replace( 'wc-', '', $key ); ?>
					<option value="<?php echo esc_attr( $clean ); ?>" <?php selected( $status, $clean ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
			<button class="button button-secondary"><?php esc_html_e( 'Фільтрувати', 'smartlearn-lms' ); ?></button>
		</form>

		<?php $this->render_export_form( 'orders', array( 'id', 'date', 'status', 'customer', 'email', 'total', 'items' ), array( 'status' => $status, 'date_from' => $date_from, 'date_to' => $date_to ) ); ?>

		<table class="widefat striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Замовлення', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Дата', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Статус', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Клієнт', 'smartlearn-lms' ); ?></th>
					<th>Email</th>
					<th><?php esc_html_e( 'Сума', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Позицій', 'smartlearn-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $orders ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Немає даних.', 'smartlearn-lms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $orders as $order ) : ?>
						<tr>
							<td>#<?php echo esc_html( $order->get_id() ); ?></td>
							<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '' ); ?></td>
							<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
							<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
							<td><?php echo esc_html( $order->get_billing_email() ); ?></td>
							<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
							<td><?php echo esc_html( count( $order->get_items() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_access_tab() {
		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			echo '<p>' . esc_html__( 'Модуль ручних доступів недоступний.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		global $wpdb;
		$table = SmartLearn_LMS_Manual_Access::get_table_name();

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$now = current_time( 'mysql' );

		$where = '1=1';
		if ( 'active' === $status ) {
			$where .= $wpdb->prepare( ' AND (a.expires_at IS NULL OR a.expires_at >= %s)', $now );
		} elseif ( 'expired' === $status ) {
			$where .= $wpdb->prepare( ' AND a.expires_at IS NOT NULL AND a.expires_at < %s', $now );
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR p.post_title LIKE %s)', $like, $like, $like );
		}

		$rows = $wpdb->get_results(
			"SELECT a.*, u.display_name AS user_name, u.user_email AS user_email, p.post_title AS course_title
			 FROM {$table} a
			 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
			 LEFT JOIN {$wpdb->posts} p ON p.ID = a.course_id
			 WHERE {$where}
			 ORDER BY a.created_at DESC
			 LIMIT 500"
		);
		?>
		<form method="get" style="margin-top:16px;">
			<input type="hidden" name="post_type" value="smartlearn_course">
			<input type="hidden" name="page" value="smartlearn-lms-crm">
			<input type="hidden" name="tab" value="access">
			<select name="status">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Активні', 'smartlearn-lms' ); ?></option>
				<option value="expired" <?php selected( $status, 'expired' ); ?>><?php esc_html_e( 'Протерміновані', 'smartlearn-lms' ); ?></option>
				<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'Всі', 'smartlearn-lms' ); ?></option>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Пошук користувача/курсу', 'smartlearn-lms' ); ?>">
			<button class="button button-secondary"><?php esc_html_e( 'Фільтрувати', 'smartlearn-lms' ); ?></button>
		</form>

		<?php $this->render_export_form( 'access', array( 'id', 'user', 'email', 'course', 'created_at', 'expires_at', 'status', 'note' ), array( 'status' => $status, 's' => $search ) ); ?>

		<h3><?php esc_html_e( 'Імпорт доступів з CSV', 'smartlearn-lms' ); ?></h3>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:900px;">
			<?php wp_nonce_field( 'smartlearn_lms_crm_import_access' ); ?>
			<input type="hidden" name="action" value="smartlearn_lms_crm_import_access">
			<input type="file" name="csv_file" accept=".csv,text/csv" required>
			<p class="description"><?php esc_html_e( 'Поля CSV: user_email або user_id, course_id або course_title, expires_at, note', 'smartlearn-lms' ); ?></p>
			<?php submit_button( __( 'Імпортувати доступи', 'smartlearn-lms' ), 'secondary', 'submit', false ); ?>
		</form>

		<table class="widefat striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php esc_html_e( 'Користувач', 'smartlearn-lms' ); ?></th>
					<th>Email</th>
					<th><?php esc_html_e( 'Курс', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Надано', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Дійсний до', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Статус', 'smartlearn-lms' ); ?></th>
					<th><?php esc_html_e( 'Нотатка', 'smartlearn-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Немає даних.', 'smartlearn-lms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php $is_active = empty( $row->expires_at ) || $row->expires_at >= $now; ?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td><?php echo esc_html( $row->user_name ); ?></td>
							<td><?php echo esc_html( $row->user_email ); ?></td>
							<td><?php echo esc_html( $row->course_title ); ?></td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( $row->expires_at ? $row->expires_at : __( 'Безстроково', 'smartlearn-lms' ) ); ?></td>
							<td><?php echo esc_html( $is_active ? __( 'Активний', 'smartlearn-lms' ) : __( 'Завершений', 'smartlearn-lms' ) ); ?></td>
							<td><?php echo esc_html( $row->note ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_export_form( $dataset, $columns, $filters ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;background:#fff;border:1px solid #dcdcde;padding:12px;">
			<?php wp_nonce_field( 'smartlearn_lms_crm_export' ); ?>
			<input type="hidden" name="action" value="smartlearn_lms_crm_export">
			<input type="hidden" name="dataset" value="<?php echo esc_attr( $dataset ); ?>">
			<?php foreach ( $filters as $key => $value ) : ?>
				<input type="hidden" name="filter_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
			<strong><?php esc_html_e( 'Експорт полів:', 'smartlearn-lms' ); ?></strong><br>
			<?php foreach ( $columns as $col ) : ?>
				<label style="display:inline-block;margin-right:10px;margin-top:6px;">
					<input type="checkbox" name="columns[]" value="<?php echo esc_attr( $col ); ?>" checked> <?php echo esc_html( $col ); ?>
				</label>
			<?php endforeach; ?>
			<div style="margin-top:8px;">
				<button class="button button-primary"><?php esc_html_e( 'Експортувати CSV', 'smartlearn-lms' ); ?></button>
			</div>
		</form>
		<?php
	}

	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}
		check_admin_referer( 'smartlearn_lms_crm_export' );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( wp_unslash( $_POST['dataset'] ) ) : '';
		$columns = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ) ) : array();
		if ( empty( $dataset ) || empty( $columns ) ) {
			wp_die( esc_html__( 'Невірні параметри експорту.', 'smartlearn-lms' ) );
		}

		$rows = array();
		if ( 'users' === $dataset ) {
			$rows = $this->get_users_export_rows();
		} elseif ( 'orders' === $dataset ) {
			$rows = $this->get_orders_export_rows();
		} elseif ( 'access' === $dataset ) {
			$rows = $this->get_access_export_rows();
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=smartlearn-crm-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $columns );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $column ) {
				$line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
			}
			fputcsv( $output, $line );
		}
		fclose( $output );
		exit;
	}

	private function get_users_export_rows() {
		$search = isset( $_POST['filter_s'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_s'] ) ) : '';
		$role = isset( $_POST['filter_role'] ) ? sanitize_key( wp_unslash( $_POST['filter_role'] ) ) : '';

		$args = array(
			'number' => 2000,
			'orderby' => 'registered',
			'order' => 'DESC',
			'fields' => array( 'ID', 'display_name', 'user_email', 'user_registered', 'roles' ),
		);
		if ( '' !== $search ) {
			$args['search'] = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		if ( '' !== $role ) {
			$args['role'] = $role;
		}

		$users = ( new WP_User_Query( $args ) )->get_results();
		$rows = array();
		foreach ( $users as $user ) {
			$orders_count = ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_customer_order_count' ) ) ? (int) wc_get_customer_order_count( $user->ID ) : 0;
			$total_spent = ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_customer_total_spent' ) ) ? (float) wc_get_customer_total_spent( $user->ID ) : 0;
			$rows[] = array(
				'id' => $user->ID,
				'name' => $user->display_name,
				'email' => $user->user_email,
				'registered' => $user->user_registered,
				'roles' => implode( ', ', $user->roles ),
				'orders_count' => $orders_count,
				'total_spent' => $total_spent,
			);
		}
		return $rows;
	}

	private function get_orders_export_rows() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$status = isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '';
		$date_from = isset( $_POST['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_date_from'] ) ) : '';
		$date_to = isset( $_POST['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_date_to'] ) ) : '';

		$args = array(
			'limit' => 2000,
			'orderby' => 'date',
			'order' => 'DESC',
			'return' => 'objects',
		);
		if ( '' !== $status ) {
			$args['status'] = array( $status );
		}
		if ( '' !== $date_from || '' !== $date_to ) {
			$args['date_created'] = $date_from . '...' . $date_to;
		}

		$orders = wc_get_orders( $args );
		$rows = array();
		foreach ( $orders as $order ) {
			$rows[] = array(
				'id' => $order->get_id(),
				'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '',
				'status' => $order->get_status(),
				'customer' => $order->get_formatted_billing_full_name(),
				'email' => $order->get_billing_email(),
				'total' => $order->get_total(),
				'items' => count( $order->get_items() ),
			);
		}
		return $rows;
	}

	private function get_access_export_rows() {
		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			return array();
		}

		global $wpdb;
		$table = SmartLearn_LMS_Manual_Access::get_table_name();
		$status = isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : 'active';
		$search = isset( $_POST['filter_s'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_s'] ) ) : '';
		$now = current_time( 'mysql' );

		$where = '1=1';
		if ( 'active' === $status ) {
			$where .= $wpdb->prepare( ' AND (a.expires_at IS NULL OR a.expires_at >= %s)', $now );
		} elseif ( 'expired' === $status ) {
			$where .= $wpdb->prepare( ' AND a.expires_at IS NOT NULL AND a.expires_at < %s', $now );
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR p.post_title LIKE %s)', $like, $like, $like );
		}

		$data = $wpdb->get_results(
			"SELECT a.*, u.display_name AS user_name, u.user_email AS user_email, p.post_title AS course_title
			 FROM {$table} a
			 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
			 LEFT JOIN {$wpdb->posts} p ON p.ID = a.course_id
			 WHERE {$where}
			 ORDER BY a.created_at DESC
			 LIMIT 5000"
		);

		$rows = array();
		foreach ( $data as $row ) {
			$is_active = empty( $row->expires_at ) || $row->expires_at >= $now;
			$rows[] = array(
				'id' => $row->id,
				'user' => $row->user_name,
				'email' => $row->user_email,
				'course' => $row->course_title,
				'created_at' => $row->created_at,
				'expires_at' => $row->expires_at,
				'status' => $is_active ? 'active' : 'expired',
				'note' => $row->note,
			);
		}
		return $rows;
	}

	public function handle_import_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'smartlearn-lms' ) );
		}
		check_admin_referer( 'smartlearn_lms_crm_import_access' );

		$redirect = add_query_arg(
			array(
				'post_type' => 'smartlearn_course',
				'page' => 'smartlearn-lms-crm',
				'tab' => 'access',
			),
			admin_url( 'edit.php' )
		);

		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'crm_notice', 'import_error', $redirect ) );
			exit;
		}

		$file = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
		if ( ! $file ) {
			wp_safe_redirect( add_query_arg( 'crm_notice', 'import_error', $redirect ) );
			exit;
		}

		$header = fgetcsv( $file );
		if ( empty( $header ) ) {
			fclose( $file );
			wp_safe_redirect( add_query_arg( 'crm_notice', 'import_error', $redirect ) );
			exit;
		}

		$header = array_map(
			static function( $h ) {
				return sanitize_key( strtolower( trim( (string) $h ) ) );
			},
			$header
		);

		global $wpdb;
		$table = SmartLearn_LMS_Manual_Access::get_table_name();
		$ok = 0;
		$fail = 0;

		while ( ( $row = fgetcsv( $file ) ) !== false ) {
			$data = array();
			foreach ( $header as $i => $key ) {
				$data[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
			}

			$user_id = 0;
			if ( ! empty( $data['user_id'] ) ) {
				$user_id = absint( $data['user_id'] );
			} elseif ( ! empty( $data['user_email'] ) ) {
				$user = get_user_by( 'email', sanitize_email( $data['user_email'] ) );
				$user_id = $user ? (int) $user->ID : 0;
			}

			$course_id = 0;
			if ( ! empty( $data['course_id'] ) ) {
				$course_id = absint( $data['course_id'] );
			} elseif ( ! empty( $data['course_title'] ) ) {
				$course_query = get_posts(
					array(
						'post_type' => 'smartlearn_course',
						'post_status' => array( 'publish', 'draft', 'private' ),
						'title' => sanitize_text_field( $data['course_title'] ),
						'posts_per_page' => 1,
						'fields' => 'ids',
					)
				);
				$course_id = ! empty( $course_query ) ? (int) $course_query[0] : 0;
			}

			if ( ! $user_id || ! $course_id ) {
				$fail++;
				continue;
			}

			$expires_at = null;
			if ( ! empty( $data['expires_at'] ) ) {
				$dt = date_create( $data['expires_at'], wp_timezone() );
				if ( ! $dt ) {
					$fail++;
					continue;
				}
				$expires_at = $dt->format( 'Y-m-d H:i:s' );
			}

			$note = ! empty( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '';
			$inserted = $wpdb->insert(
				$table,
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

			if ( $inserted ) {
				$ok++;
			} else {
				$fail++;
			}
		}

		fclose( $file );
		wp_safe_redirect(
			add_query_arg(
				array(
					'crm_notice' => 'import_done',
					'import_ok' => $ok,
					'import_fail' => $fail,
				),
				$redirect
			)
		);
		exit;
	}
}
