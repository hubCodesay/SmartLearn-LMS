<?php
/**
 * Admin: Access reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Admin_Access_Reports {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
	}

	public function register_menu() {
		// Attach to SmartLearn Courses CPT menu.
		add_submenu_page(
			'edit.php?post_type=smartlearn_course',
			__( 'Доступи', 'smartlearn-lms' ),
			__( 'Доступи', 'smartlearn-lms' ),
			'manage_options',
			'smartlearn-access-reports',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
		$courses = get_posts(
			array(
				'post_type'      => 'smartlearn_course',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft' ),
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Доступи до курсів', 'smartlearn-lms' ) . '</h1>';

		// Filter
		echo '<form method="get" style="margin: 16px 0;">';
		echo '<input type="hidden" name="post_type" value="smartlearn_course" />';
		echo '<input type="hidden" name="page" value="smartlearn-access-reports" />';
		echo '<label for="smartlearn_course_id"><strong>' . esc_html__( 'Курс:', 'smartlearn-lms' ) . '</strong></label> ';
		echo '<select name="course_id" id="smartlearn_course_id" style="min-width:340px;">';
		echo '<option value="0">' . esc_html__( '— Виберіть курс —', 'smartlearn-lms' ) . '</option>';
		foreach ( $courses as $course ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $course->ID,
				selected( $course_id, $course->ID, false ),
				esc_html( $course->post_title )
			);
		}
		echo '</select> ';
		submit_button( __( 'Показати', 'smartlearn-lms' ), 'primary', '', false );
		echo '</form>';

		if ( ! $course_id ) {
			echo '<p>' . esc_html__( 'Виберіть курс, щоб побачити хто купував і коли закінчується доступ.', 'smartlearn-lms' ) . '</p>';
			echo '</div>';
			return;
		}

		$product_id = absint( get_post_meta( $course_id, '_smartlearn_course_product_id', true ) );
		$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true ) === '1';
		$duration_value = absint( get_post_meta( $course_id, '_smartlearn_course_access_duration_value', true ) );
		$duration_unit = get_post_meta( $course_id, '_smartlearn_course_access_duration_unit', true );
		if ( ! in_array( $duration_unit, array( 'days', 'months' ), true ) ) {
			$duration_unit = 'days';
		}

		echo '<h2 style="margin-top:18px;">' . esc_html( get_the_title( $course_id ) ) . '</h2>';

		if ( $is_free ) {
			echo '<p>' . esc_html__( 'Курс безкоштовний — доступ без обмежень.', 'smartlearn-lms' ) . '</p>';
			echo '</div>';
			return;
		}

		if ( ! $product_id ) {
			echo '<p>' . esc_html__( 'Не прив’язано товар WooCommerce. Доступ буде закритий для всіх.', 'smartlearn-lms' ) . '</p>';
			echo '</div>';
			return;
		}

		$duration_label = $duration_value > 0
			? sprintf( '%d %s', $duration_value, ( 'months' === $duration_unit ? __( 'місяців', 'smartlearn-lms' ) : __( 'днів', 'smartlearn-lms' ) ) )
			: __( 'без обмеження', 'smartlearn-lms' );
		echo '<p><strong>' . esc_html__( 'Термін доступу:', 'smartlearn-lms' ) . '</strong> ' . esc_html( $duration_label ) . '</p>';

		$rows = $this->get_course_access_stats_rows( $course_id, $product_id );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Покупок цього курсу ще не знайдено.', 'smartlearn-lms' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Клієнт', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Остання покупка', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Доступ до', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Статус', 'smartlearn-lms' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$purchase_ts = (int) $row['purchased_at'];
			$expires_at = 0;
			if ( class_exists( 'SmartLearn_LMS_Access_Control' ) && method_exists( 'SmartLearn_LMS_Access_Control', 'calculate_course_access_expires_at' ) ) {
				$expires_at = SmartLearn_LMS_Access_Control::calculate_course_access_expires_at( $course_id, $purchase_ts );
			}
			$is_expired = $expires_at ? ( current_time( 'timestamp' ) >= $expires_at ) : false;
			$status = $is_expired ? __( 'закінчився', 'smartlearn-lms' ) : __( 'активний', 'smartlearn-lms' );

			echo '<tr>';
			echo '<td>' . esc_html( $row['name'] ? $row['name'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $row['email'] ? $row['email'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $purchase_ts ? wp_date( 'd.m.Y H:i', $purchase_ts ) : '—' ) . '</td>';
			echo '<td>' . esc_html( $expires_at ? wp_date( 'd.m.Y H:i', $expires_at ) : '∞' ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Відлік робиться від останньої покупки курсу кожним клієнтом. Після закінчення терміну доступ блокується автоматично.', 'smartlearn-lms' ) . '</p>';
		echo '</div>';
	}

	private function get_course_access_stats_rows( $course_id, $product_id ) {
		$course_id = absint( $course_id );
		$product_id = absint( $product_id );
		if ( ! $course_id || ! $product_id || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$cache_key = 'smartlearn_access_reports_' . $course_id . '_' . $product_id;
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$order_items = $wpdb->prefix . 'woocommerce_order_items';
		$item_meta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$posts = $wpdb->posts;

		$statuses = array( 'wc-completed', 'wc-processing' );
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "
			SELECT DISTINCT oi.order_id
			FROM {$order_items} oi
			INNER JOIN {$item_meta} oim ON oi.order_item_id = oim.order_item_id
			INNER JOIN {$posts} p ON p.ID = oi.order_id
			WHERE oi.order_item_type = 'line_item'
			AND p.post_type = 'shop_order'
			AND p.post_status IN ({$placeholders})
			AND oim.meta_key IN ('_product_id','_variation_id')
			AND oim.meta_value = %d
			ORDER BY oi.order_id DESC
			LIMIT 3000
		";

		$params = array_merge( $statuses, array( $product_id ) );
		$order_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
		if ( empty( $order_ids ) ) {
			set_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
			return array();
		}

		$orders = wc_get_orders(
			array(
				'include' => array_map( 'absint', $order_ids ),
				'limit'   => -1,
				'return'  => 'objects',
			)
		);

		$customers = array();
		foreach ( $orders as $order ) {
			/** @var WC_Order $order */
			$customer_id = (int) $order->get_customer_id();
			$email = sanitize_email( $order->get_billing_email() );
			$key = $customer_id ? 'u:' . $customer_id : ( $email ? 'e:' . $email : '' );
			if ( ! $key ) {
				continue;
			}

			$found = false;
			foreach ( $order->get_items() as $item ) {
				$item_product_id = (int) $item->get_product_id();
				$item_variation_id = (int) $item->get_variation_id();
				if ( $item_product_id === $product_id || $item_variation_id === $product_id ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				continue;
			}

			$date = $order->get_date_paid();
			if ( ! $date ) {
				$date = $order->get_date_completed();
			}
			if ( ! $date ) {
				$date = $order->get_date_created();
			}
			$purchase_ts = $date ? (int) $date->getTimestamp() : 0;
			if ( ! $purchase_ts ) {
				continue;
			}

			if ( ! isset( $customers[ $key ] ) || $purchase_ts > $customers[ $key ]['purchased_at'] ) {
				$name = '';
				if ( $customer_id ) {
					$user = get_user_by( 'id', $customer_id );
					if ( $user ) {
						$name = trim( $user->display_name );
						if ( ! $email ) {
							$email = sanitize_email( $user->user_email );
						}
					}
				}
				if ( ! $name ) {
					$name = trim( $order->get_formatted_billing_full_name() );
				}

				$customers[ $key ] = array(
					'user_id'     => $customer_id,
					'email'       => $email,
					'name'        => $name,
					'purchased_at' => $purchase_ts,
				);
			}
		}

		$rows = array_values( $customers );
		usort(
			$rows,
			function ( $a, $b ) {
				return (int) $b['purchased_at'] <=> (int) $a['purchased_at'];
			}
		);

		set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );
		return $rows;
	}
}
