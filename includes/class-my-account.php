<?php
/**
 * WooCommerce My Account endpoint for user's courses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_My_Account {
	const ENDPOINT = 'my-courses';
	const LIFETIME_EXPIRES_AT = '2099-12-31 23:59:59';

	public function __construct() {
		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
	}

	public function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		$this->maybe_flush_rewrite_rules();
	}

	public function add_query_vars( $vars ) {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	public function add_menu_item( $items ) {
		$new_items = array();
		$inserted = false;

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				$new_items[ self::ENDPOINT ] = __( 'Мої курси', 'smartlearn-lms' );
				$inserted = true;
			}
		}

		if ( ! $inserted ) {
			$new_items[ self::ENDPOINT ] = __( 'Мої курси', 'smartlearn-lms' );
		}

		return $new_items;
	}

	public function render_endpoint() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'Увійдіть, щоб переглянути свої курси.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		$courses = $this->get_user_courses( $user_id );
		$active_courses = array();
		$history_courses = array();

		foreach ( $courses as $course ) {
			if ( ! empty( $course['is_active'] ) ) {
				$active_courses[] = $course;
			} else {
				$history_courses[] = $course;
			}
		}

		usort(
			$active_courses,
			function( $a, $b ) {
				return strcmp( $b['purchase_date_raw'], $a['purchase_date_raw'] );
			}
		);
		usort(
			$history_courses,
			function( $a, $b ) {
				return strcmp( $b['purchase_date_raw'], $a['purchase_date_raw'] );
			}
		);

		$this->render_styles();

		echo '<div class="smartlearn-my-courses">';
		echo '<h2>' . esc_html__( 'Мої курси', 'smartlearn-lms' ) . '</h2>';
		$this->render_courses_table( $active_courses, __( 'Активні курси', 'smartlearn-lms' ) );
		$this->render_courses_table( $history_courses, __( 'Історія курсів', 'smartlearn-lms' ) );
		$this->render_access_history_table( $this->get_access_history_rows( $user_id ) );
		echo '</div>';
	}

	private function maybe_flush_rewrite_rules() {
		$flag = (string) get_option( 'smartlearn_lms_my_courses_endpoint_flushed', '' );
		if ( SMARTLEARN_LMS_VERSION === $flag ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'smartlearn_lms_my_courses_endpoint_flushed', SMARTLEARN_LMS_VERSION );
	}

	private function render_styles() {
		?>
		<style>
			.smartlearn-my-courses-table{width:100%;border-collapse:collapse;margin:0 0 24px}
			.smartlearn-my-courses-table th,.smartlearn-my-courses-table td{border:1px solid #e2e8f0;padding:10px;vertical-align:top}
			.smartlearn-my-courses-table th{background:#f8fafc;text-align:left}
			.smartlearn-access-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;line-height:1.7}
			.smartlearn-access-pill.active{background:#dcfce7;color:#166534}
			.smartlearn-access-pill.history{background:#fee2e2;color:#991b1b}
			@media (max-width: 782px){
				.smartlearn-my-courses-table,.smartlearn-my-courses-table tbody,.smartlearn-my-courses-table tr,.smartlearn-my-courses-table td{display:block;width:100%}
				.smartlearn-my-courses-table thead{display:none}
				.smartlearn-my-courses-table tr{margin-bottom:12px;border:1px solid #e2e8f0}
				.smartlearn-my-courses-table td{border:0;border-bottom:1px solid #e2e8f0}
				.smartlearn-my-courses-table td:last-child{border-bottom:0}
			}
		</style>
		<?php
	}

	private function render_courses_table( $courses, $title ) {
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( empty( $courses ) ) {
			echo '<p>' . esc_html__( 'Немає курсів для відображення.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		echo '<table class="smartlearn-my-courses-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Курс', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Дата покупки', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Дата закінчення', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Статус', 'smartlearn-lms' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $courses as $course ) {
			$status_class = ! empty( $course['is_active'] ) ? 'active' : 'history';
			$status_label = ! empty( $course['is_active'] ) ? __( 'Доступний', 'smartlearn-lms' ) : __( 'Завершений', 'smartlearn-lms' );

			echo '<tr>';
			if ( ! empty( $course['permalink'] ) ) {
				echo '<td><a href="' . esc_url( $course['permalink'] ) . '">' . esc_html( $course['title'] ) . '</a></td>';
			} else {
				echo '<td>' . esc_html( $course['title'] ) . '</td>';
			}
			echo '<td>' . esc_html( $course['purchase_date'] ) . '</td>';
			echo '<td>' . esc_html( $course['expires_date'] ) . '</td>';
			echo '<td><span class="smartlearn-access-pill ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function render_access_history_table( $rows ) {
		echo '<h3>' . esc_html__( 'Історія змін доступу', 'smartlearn-lms' ) . '</h3>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Змін доступу поки немає.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		echo '<table class="smartlearn-my-courses-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Дата', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Курс', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Подія', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Дійсний до', 'smartlearn-lms' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( $row['course_title'] ) . '</td>';
			echo '<td>' . esc_html( $row['event_label'] ) . '</td>';
			echo '<td>' . esc_html( $this->format_expires( $row['expires_at'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function get_user_courses( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$manual_courses = $this->get_manual_courses_map( $user_id );
		$purchased_courses = $this->get_purchased_courses_map( $user_id );
		$history_courses = $this->get_history_courses_map( $user_id );
		$all_course_ids = array_unique( array_merge( array_keys( $manual_courses ), array_keys( $purchased_courses ), array_keys( $history_courses ) ) );
		$all_course_ids = array_map( 'absint', $all_course_ids );

		$rows = array();
		foreach ( $all_course_ids as $course_id ) {
			$has_course_post = ( 'smartlearn_course' === get_post_type( $course_id ) );
			$title = $has_course_post ? get_the_title( $course_id ) : '';
			$permalink = $has_course_post ? get_permalink( $course_id ) : '';
			if ( ! $title && ! empty( $history_courses[ $course_id ]['course_title'] ) ) {
				$title = $history_courses[ $course_id ]['course_title'];
			}
			if ( ! $title ) {
				$title = '#' . $course_id;
			}
			if ( ! $has_course_post ) {
				$permalink = '';
			}

			$manual = isset( $manual_courses[ $course_id ] ) ? $manual_courses[ $course_id ] : array();
			$purchase_date_raw = '';
			if ( isset( $purchased_courses[ $course_id ]['purchase_date_raw'] ) ) {
				$purchase_date_raw = $purchased_courses[ $course_id ]['purchase_date_raw'];
			} elseif ( isset( $manual['purchase_date_raw'] ) ) {
				$purchase_date_raw = $manual['purchase_date_raw'];
			} elseif ( isset( $history_courses[ $course_id ]['purchase_date_raw'] ) ) {
				$purchase_date_raw = $history_courses[ $course_id ]['purchase_date_raw'];
			}

			$expires_raw = isset( $manual['expires_raw'] ) ? $manual['expires_raw'] : self::LIFETIME_EXPIRES_AT;
			if ( ! isset( $manual['expires_raw'] ) && isset( $history_courses[ $course_id ]['expires_raw'] ) ) {
				$expires_raw = $history_courses[ $course_id ]['expires_raw'];
			}
			$is_active = $has_course_post ? SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id ) : false;

			$rows[] = array(
				'course_id' => $course_id,
				'title' => $title,
				'permalink' => $permalink,
				'purchase_date_raw' => $purchase_date_raw,
				'purchase_date' => $this->format_datetime( $purchase_date_raw ),
				'expires_date' => $this->format_expires( $expires_raw ),
				'is_active' => $is_active,
			);
		}

		return $rows;
	}

	private function get_history_courses_map( $user_id ) {
		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			return array();
		}

		$rows = SmartLearn_LMS_Manual_Access::get_access_history(
			array(
				'user_id' => $user_id,
				'limit' => 500,
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$course_id = absint( $row->course_id );
			if ( ! $course_id || isset( $map[ $course_id ] ) ) {
				continue;
			}
			$expires_raw = (string) $row->expires_at;
			if ( '' === $expires_raw ) {
				$expires_raw = self::LIFETIME_EXPIRES_AT;
			}
			$map[ $course_id ] = array(
				'course_title' => (string) $row->course_title,
				'purchase_date_raw' => (string) $row->created_at,
				'expires_raw' => $expires_raw,
			);
		}

		return $map;
	}

	private function get_access_history_rows( $user_id ) {
		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			return array();
		}

		$raw_rows = SmartLearn_LMS_Manual_Access::get_access_history(
			array(
				'user_id' => $user_id,
				'limit' => 100,
			)
		);

		$rows = array();
		foreach ( (array) $raw_rows as $row ) {
			$label = __( 'Оновлено', 'smartlearn-lms' );
			if ( 'granted' === $row->action ) {
				$label = __( 'Надано доступ', 'smartlearn-lms' );
			} elseif ( 'extended' === $row->action ) {
				$label = __( 'Продовжено доступ', 'smartlearn-lms' );
			} elseif ( 'revoked' === $row->action ) {
				$label = __( 'Видалено доступ', 'smartlearn-lms' );
			}

			$rows[] = array(
				'created_at' => (string) $row->created_at,
				'course_title' => '' !== (string) $row->course_title ? (string) $row->course_title : ( '#' . absint( $row->course_id ) ),
				'event_label' => $label,
				'expires_at' => (string) $row->expires_at,
			);
		}

		return $rows;
	}

	private function get_manual_courses_map( $user_id ) {
		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			return array();
		}

		global $wpdb;
		$table_name = SmartLearn_LMS_Manual_Access::get_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.course_id, a.created_at, a.expires_at
				 FROM {$table_name} a
				 INNER JOIN (
				 	SELECT course_id, MAX(id) AS max_id
				 	FROM {$table_name}
				 	WHERE user_id = %d
				 	GROUP BY course_id
				 ) latest ON latest.max_id = a.id
				 WHERE a.user_id = %d",
				$user_id,
				$user_id
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$course_id = absint( $row->course_id );
			if ( ! $course_id ) {
				continue;
			}
			$expires_raw = (string) $row->expires_at;
			if ( '' === $expires_raw ) {
				$expires_raw = self::LIFETIME_EXPIRES_AT;
			}
			$map[ $course_id ] = array(
				'purchase_date_raw' => (string) $row->created_at,
				'expires_raw' => $expires_raw,
			);
		}

		return $map;
	}

	private function get_purchased_courses_map( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$product_to_course = $this->get_product_course_map();
		if ( empty( $product_to_course ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status' => array( 'completed', 'processing', 'on-hold' ),
				'limit' => -1,
				'orderby' => 'date',
				'order' => 'ASC',
				'return' => 'ids',
			)
		);

		$map = array();
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$date_created = $order->get_date_created();
			$purchase_raw = $date_created ? $date_created->date( 'Y-m-d H:i:s' ) : '';
			foreach ( $order->get_items() as $item ) {
				$product_id = absint( $item->get_product_id() );
				if ( ! $product_id || empty( $product_to_course[ $product_id ] ) ) {
					continue;
				}
				$course_id = $product_to_course[ $product_id ];
				if ( empty( $map[ $course_id ] ) || ( $purchase_raw && strcmp( $purchase_raw, $map[ $course_id ]['purchase_date_raw'] ) < 0 ) ) {
					$map[ $course_id ] = array(
						'purchase_date_raw' => $purchase_raw,
					);
				}
			}
		}

		return $map;
	}

	private function get_product_course_map() {
		global $wpdb;
		$postmeta = $wpdb->postmeta;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value
				 FROM {$postmeta}
				 WHERE meta_key = %s
				   AND meta_value <> ''",
				'_smartlearn_course_product_id'
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$course_id = absint( $row->post_id );
			$product_id = absint( $row->meta_value );
			if ( $course_id && $product_id ) {
				$map[ $product_id ] = $course_id;
			}
		}

		return $map;
	}

	private function format_datetime( $raw ) {
		$raw = (string) $raw;
		$ts = strtotime( $raw );
		if ( ! $ts ) {
			return __( 'Невідомо', 'smartlearn-lms' );
		}
		return wp_date( 'Y-m-d H:i', $ts );
	}

	private function format_expires( $raw ) {
		$raw = (string) $raw;
		if ( '' === $raw || self::LIFETIME_EXPIRES_AT === $raw ) {
			return __( 'Безкінечний доступ', 'smartlearn-lms' );
		}
		$ts = strtotime( $raw );
		if ( ! $ts ) {
			return __( 'Невідомо', 'smartlearn-lms' );
		}
		return wp_date( 'Y-m-d H:i', $ts );
	}
}
