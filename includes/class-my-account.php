<?php
/**
 * My Account integration (WooCommerce)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SmartLearn_LMS_My_Account' ) ) {
	return;
}

class SmartLearn_LMS_My_Account {
	const ENDPOINT = 'smartlearn-courses';

	public function __construct() {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_wc_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 9999 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
	}

	public function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_query_vars( $vars ) {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	public function add_wc_query_vars( $vars ) {
		if ( ! is_array( $vars ) ) {
			$vars = array();
		}
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public function add_menu_item( $items ) {
		$new_items = array();
		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new_items[ self::ENDPOINT ] = __( 'Курси', 'smartlearn-lms' );
			}
		}

		if ( ! isset( $new_items[ self::ENDPOINT ] ) ) {
			$new_items[ self::ENDPOINT ] = __( 'Курси', 'smartlearn-lms' );
		}

		return $new_items;
	}

	public function render_endpoint() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Будь ласка, увійдіть, щоб переглянути свої курси.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		$user_id     = get_current_user_id();
		$product_ids = $this->get_purchased_product_ids( $user_id );
		$courses     = $this->get_courses_for_products( $product_ids );

		echo '<h2 class="smartlearn-my-courses-title">' . esc_html__( 'Мої курси', 'smartlearn-lms' ) . '</h2>';

		if ( empty( $courses ) ) {
			echo '<p>' . esc_html__( 'У вас ще немає куплених курсів.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		echo '<div class="smartlearn-my-courses-grid">';
		foreach ( $courses as $course ) {
			$course_id  = $course->ID;
			$course_url = $this->get_course_preview_url( $course_id );

			echo '<div class="smartlearn-my-course-item">';
			if ( has_post_thumbnail( $course_id ) ) {
				echo '<a class="smartlearn-my-course-thumb" href="' . esc_url( $course_url ) . '">';
				echo get_the_post_thumbnail( $course_id, 'medium' );
				echo '</a>';
			}

			echo '<div class="smartlearn-my-course-body">';
			echo '<a class="smartlearn-my-course-title" href="' . esc_url( $course_url ) . '">' . esc_html( get_the_title( $course_id ) ) . '</a>';

			$lessons       = class_exists( 'SmartLearn_LMS_Templates' ) ? SmartLearn_LMS_Templates::get_course_lessons( $course_id ) : array();
			$lessons_count = is_array( $lessons ) ? count( $lessons ) : 0;
			echo '<div class="smartlearn-my-course-meta">' . esc_html( sprintf( __( 'Уроків: %d', 'smartlearn-lms' ), $lessons_count ) ) . '</div>';

			$view_label = get_option( 'smartlearn_lms_button_text_view', __( 'Переглянути курс', 'smartlearn-lms' ) );
			echo '<div class="smartlearn-my-course-actions">';
			echo '<a class="button smartlearn-my-course-button" href="' . esc_url( $course_url ) . '">' . esc_html( $view_label ) . '</a>';
			echo '</div>';

			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function get_course_preview_url( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return home_url( '/' );
		}

		$url  = get_permalink( $course_id );
		$home = home_url( '/' );
		if ( empty( $url ) || untrailingslashit( $url ) === untrailingslashit( $home ) ) {
			$url = add_query_arg(
				array(
					'post_type' => 'smartlearn_course',
					'p'         => $course_id,
				),
				home_url( '/' )
			);
		}

		return $url;
	}

	/**
	 * Collect purchased product IDs for the customer.
	 *
	 * @return int[]
	 */
	private function get_purchased_product_ids( $user_id ) {
		$product_ids = array();

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $product_ids;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'status'      => array( 'completed', 'processing' ),
				'return'      => 'objects',
			)
		);

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				if ( $product_id ) {
					$product_ids[ $product_id ] = true;
				}
			}
		}

		return array_map( 'intval', array_keys( $product_ids ) );
	}

	/**
	 * Find courses linked to any of the provided product IDs.
	 *
	 * @param int[] $product_ids
	 * @return WP_Post[]
	 */
	private function get_courses_for_products( $product_ids ) {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$meta_query = array( 'relation' => 'OR' );
		foreach ( $product_ids as $pid ) {
			$meta_query[] = array(
				'key'     => '_smartlearn_course_product_id',
				'value'   => (string) intval( $pid ),
				'compare' => '=',
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'smartlearn_course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'all',
				'meta_query'     => $meta_query,
			)
		);

		return $query->posts;
	}
}
