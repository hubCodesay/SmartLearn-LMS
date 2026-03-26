<?php
/**
 * Access Control - перевірка доступу користувачів до курсів та уроків
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Access_Control {
	private static $course_access_cache = array();
	
	public function __construct() {
		// Hooks are handled in Templates class
	}

	/**
	 * Ensure a purchase-backed access row exists for a user/course pair.
	 *
	 * @param int $user_id
	 * @param int $course_id
	 * @param int $product_id
	 * @return void
	 */
	private static function ensure_purchase_access_record( $user_id, $course_id, $product_id ) {
		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders( array(
			'customer' => $user_id,
			'limit'    => 10,
			'status'   => array( 'completed', 'processing', 'on-hold' ),
		) );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === (int) $product_id ) {
					$purchase_ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : current_time( 'timestamp' );
					$duration_raw = get_post_meta( $course_id, '_smartlearn_course_duration', true );
					$seconds = (int) SmartLearn_LMS_Manual_Access::parse_duration_to_seconds( (string) $duration_raw );
					$expires_at = '';
					if ( $seconds > 0 ) {
						$expires_at = gmdate( 'Y-m-d H:i:s', $purchase_ts + $seconds );
					}
					SmartLearn_LMS_Manual_Access::grant_access( $user_id, $course_id, $expires_at, __( 'Автоматичне надання після покупки (міграція)', 'smartlearn-lms' ), 0 );
					return;
				}
			}
		}
	}
	
	/**
	 * Перевірити чи користувач має доступ до курсу
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @return bool
	 */
	public static function user_has_course_access( $course_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$cache_key = absint( $course_id ) . ':' . absint( $user_id );
		if ( isset( self::$course_access_cache[ $cache_key ] ) ) {
			return self::$course_access_cache[ $cache_key ];
		}
		
		// Якщо користувач адмін - завжди дозволяємо
		if ( user_can( $user_id, 'manage_options' ) ) {
			self::$course_access_cache[ $cache_key ] = true;
			return true;
		}
		
		// Перевірити чи курс безкоштовний
		$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true );
		if ( $is_free === '1' ) {
			self::$course_access_cache[ $cache_key ] = true;
			return true;
		}
		
		// Перевірити чи користувач авторизований
		if ( ! $user_id ) {
			self::$course_access_cache[ $cache_key ] = false;
			return false;
		}

		$has_manual_access = class_exists( 'SmartLearn_LMS_Manual_Access' ) && SmartLearn_LMS_Manual_Access::user_has_active_access( $user_id, $course_id );
		if ( $has_manual_access ) {
			self::$course_access_cache[ $cache_key ] = true;
			return true;
		}
		
		// Отримати прив'язаний товар
		$product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
		if ( ! $product_id ) {
			self::$course_access_cache[ $cache_key ] = false;
			return false;
		}

		// Перевірити чи користувач купив товар (через WooCommerce)
		if ( function_exists( 'wc_customer_bought_product' ) && wc_customer_bought_product( get_userdata( $user_id )->user_email, $user_id, $product_id ) ) {
			if ( class_exists( 'SmartLearn_LMS_Manual_Access' ) && ! $has_manual_access ) {
				self::ensure_purchase_access_record( $user_id, $course_id, $product_id );
				self::$course_access_cache[ $cache_key ] = class_exists( 'SmartLearn_LMS_Manual_Access' ) && SmartLearn_LMS_Manual_Access::user_has_active_access( $user_id, $course_id );
				return self::$course_access_cache[ $cache_key ];
			}
			self::$course_access_cache[ $cache_key ] = $has_manual_access;
			return self::$course_access_cache[ $cache_key ];
		}

		self::$course_access_cache[ $cache_key ] = false;
		return false;
	}
	
	/**
	 * Перевірити чи користувач має доступ до уроку
	 *
	 * @param int $lesson_id
	 * @param int $user_id
	 * @return bool
	 */
	public static function user_has_lesson_access( $lesson_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		
		// Якщо користувач адмін - завжди дозволяємо
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		
		// Перевірити чи урок безкоштовний
		$is_free = get_post_meta( $lesson_id, '_smartlearn_lesson_is_free', true );
		if ( $is_free === '1' ) {
			return true;
		}
		
		// Отримати курс до якого належить урок
		$course_id = get_post_meta( $lesson_id, '_smartlearn_lesson_course_id', true );
		if ( ! $course_id ) {
			// Якщо курс не прив'язаний - дозволяємо доступ
			return true;
		}
		
		// Перевірити доступ до курсу
		return self::user_has_course_access( $course_id, $user_id );
	}
	
	/**
	 * Перевірити чи користувач купив товар WooCommerce
	 *
	 * @param int $user_id
	 * @param int $product_id
	 * @return bool
	 */
	public static function user_has_bought_product( $user_id, $product_id ) {
		if ( ! function_exists( 'wc_customer_bought_product' ) ) {
			return false;
		}
		
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		
		return wc_customer_bought_product( $user->user_email, $user_id, $product_id );
	}
	
	/**
	 * Отримати URL для купівлі курсу
	 *
	 * @param int $course_id
	 * @return string
	 */
	public static function get_course_purchase_url( $course_id ) {
		$product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
		
		if ( ! $product_id ) {
			return '';
		}

		if ( function_exists( 'wc_get_checkout_url' ) ) {
			return add_query_arg(
				array(
					'add-to-cart' => absint( $product_id ),
				),
				wc_get_checkout_url()
			);
		}

		$product_url = get_permalink( $product_id );
		return $product_url ? $product_url : '';
	}
	
	/**
	 * Отримати кнопку доступу до курсу
	 *
	 * @param int $course_id
	 * @param array $args
	 * @return string HTML
	 */
	public static function get_course_access_button( $course_id, $args = array() ) {
		$defaults = array(
			'class' => 'button -course-button',
			'text_view' => __( 'Переглянути курс', 'smartlearn-lms' ),
			'text_buy' => __( 'Купити курс', 'smartlearn-lms' ),
			'text_login' => __( 'Увійти', 'smartlearn-lms' ),
		);
		
		$args = wp_parse_args( $args, $defaults );
		$user_id = get_current_user_id();
		
		// Якщо користувач має доступ
		if ( self::user_has_course_access( $course_id, $user_id ) ) {
			return sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( get_permalink( $course_id ) ),
				esc_attr( $args['class'] . ' has-access' ),
				esc_html( $args['text_view'] )
			);
		}
		
		// Якщо користувач не авторизований
		if ( ! $user_id ) {
			return sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( 'https://www.smartlearn-shopchik.com/my-account/' ),
				esc_attr( $args['class'] . ' need-login' ),
				esc_html( $args['text_login'] )
			);
		}
		
		// Якщо потрібно купити
		$purchase_url = self::get_course_purchase_url( $course_id );
		if ( $purchase_url ) {
			return sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $purchase_url ),
				esc_attr( $args['class'] . ' need-purchase' ),
				esc_html( $args['text_buy'] )
			);
		}
		
		return '';
	}
	
	/**
	 * Відобразити повідомлення про відсутність доступу
	 *
	 * @param int $course_id
	 * @return string HTML
	 */
	public static function get_access_denied_message( $course_id = 0 ) {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			$message = __( 'Для перегляду цього контенту необхідно авторизуватися.', 'smartlearn-lms' );
			$button_text = __( 'Увійти', 'smartlearn-lms' );
			$button_url = 'https://www.smartlearn-shopchik.com/my-account/';
		} else {
			$message = __( 'Для перегляду цього контенту необхідно придбати курс.', 'smartlearn-lms' );
			$button_text = __( 'Купити курс', 'smartlearn-lms' );
			$button_url = $course_id ? self::get_course_purchase_url( $course_id ) : '';
		}
		
		ob_start();
		?>
		<div class="smartlearn-access-denied">
			<div class="smartlearn-access-denied-icon">🔒</div>
			<h3><?php echo esc_html( $message ); ?></h3>
			<?php if ( $button_url ) : ?>
				<p>
					<a href="<?php echo esc_url( $button_url ); ?>" class="button -access-button">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
