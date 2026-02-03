<?php
/**
 * Access Control - перевірка доступу користувачів до курсів та уроків
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Access_Control {
	
	public function __construct() {
		// Hooks are handled in Templates class
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
		
		// Якщо користувач адмін - завжди дозволяємо
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		
		// Перевірити чи курс безкоштовний
		$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true );
		if ( $is_free === '1' ) {
			return true;
		}
		
		// Перевірити чи користувач авторизований
		if ( ! $user_id ) {
			return false;
		}
		
		// Отримати прив'язаний товар
		$product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
		if ( ! $product_id ) {
			// Якщо товар не прив'язаний і курс не безкоштовний - забороняємо доступ
			return false;
		}
		
		// Перевірити чи користувач купив товар
		if ( ! self::user_has_bought_product( $user_id, $product_id ) ) {
			return false;
		}

		// Якщо для курсу налаштовано обмеження доступу — перевіряємо термін
		$expires_at = self::get_course_access_expires_at( $course_id, $user_id, $product_id );
		if ( $expires_at && current_time( 'timestamp' ) >= $expires_at ) {
			return false;
		}

		return true;
	}

	private static function get_course_access_duration_settings( $course_id ) {
		$value = absint( get_post_meta( $course_id, '_smartlearn_course_access_duration_value', true ) );
		$unit = get_post_meta( $course_id, '_smartlearn_course_access_duration_unit', true );
		if ( ! in_array( $unit, array( 'days', 'months' ), true ) ) {
			$unit = 'days';
		}
		return array( $value, $unit );
	}

	private static function get_latest_purchase_timestamp_for_product( $user_id, $product_id ) {
		$user_id = absint( $user_id );
		$product_id = absint( $product_id );
		if ( ! $user_id || ! $product_id ) {
			return 0;
		}

		$cache_key = 'latest_purchase_' . $user_id . '_' . $product_id;
		$cached = wp_cache_get( $cache_key, 'smartlearn_lms' );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			wp_cache_set( $cache_key, 0, 'smartlearn_lms', 5 * MINUTE_IN_SECONDS );
			return 0;
		}

		$latest = 0;
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
				$item_product_id = (int) $item->get_product_id();
				$item_variation_id = (int) $item->get_variation_id();
				if ( $item_product_id !== $product_id && $item_variation_id !== $product_id ) {
					continue;
				}

				$date = $order->get_date_paid();
				if ( ! $date ) {
					$date = $order->get_date_completed();
				}
				if ( ! $date ) {
					$date = $order->get_date_created();
				}

				if ( $date ) {
					$ts = (int) $date->getTimestamp();
					if ( $ts > $latest ) {
						$latest = $ts;
					}
				}

				break;
			}
		}

		wp_cache_set( $cache_key, $latest, 'smartlearn_lms', 5 * MINUTE_IN_SECONDS );
		return $latest;
	}

	private static function get_course_access_expires_at( $course_id, $user_id, $product_id ) {
		list( $value, $unit ) = self::get_course_access_duration_settings( $course_id );
		if ( $value <= 0 ) {
			return 0;
		}

		$purchase_ts = self::get_latest_purchase_timestamp_for_product( $user_id, $product_id );
		if ( ! $purchase_ts ) {
			// Якщо не можемо визначити дату покупки — не блокуємо доступ.
			return 0;
		}

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$dt = ( new DateTimeImmutable( '@' . $purchase_ts ) )->setTimezone( $timezone );
		$modifier = sprintf( '+%d %s', $value, ( 'months' === $unit ? 'months' : 'days' ) );
		$expires = $dt->modify( $modifier );
		return $expires ? (int) $expires->getTimestamp() : 0;
	}

	public static function is_course_access_expired( $course_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}
		$product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
		if ( ! $product_id ) {
			return false;
		}
		if ( ! self::user_has_bought_product( $user_id, $product_id ) ) {
			return false;
		}
		$expires_at = self::get_course_access_expires_at( $course_id, $user_id, $product_id );
		return ( $expires_at && current_time( 'timestamp' ) >= $expires_at );
	}

	/**
	 * Calculate expiry timestamp for a course based on a known purchase timestamp.
	 * Returns 0 for unlimited access or if settings invalid.
	 *
	 * @param int $course_id
	 * @param int $purchase_ts Unix timestamp
	 * @return int
	 */
	public static function calculate_course_access_expires_at( $course_id, $purchase_ts ) {
		$course_id = absint( $course_id );
		$purchase_ts = absint( $purchase_ts );
		if ( ! $course_id || ! $purchase_ts ) {
			return 0;
		}
		list( $value, $unit ) = self::get_course_access_duration_settings( $course_id );
		if ( $value <= 0 ) {
			return 0;
		}

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$dt = ( new DateTimeImmutable( '@' . $purchase_ts ) )->setTimezone( $timezone );
		$modifier = sprintf( '+%d %s', $value, ( 'months' === $unit ? 'months' : 'days' ) );
		$expires = $dt->modify( $modifier );
		return $expires ? (int) $expires->getTimestamp() : 0;
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
		
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}
		
		return $product->get_permalink();
	}
	
	/**
	 * Отримати кнопку доступу до курсу
	 *
	 * @param int $course_id
	 * @param array $args
	 * @return string HTML
	 */
	public static function get_course_access_button( $course_id, $args = array() ) {
		// Pull button labels from plugin settings (admin). Provide localized fallbacks.
		$defaults = array(
			'class' => 'button smartlearn-course-button',
			'text_view' => get_option( 'smartlearn_lms_button_text_view', __( 'Переглянути', 'smartlearn-lms' ) ),
			'text_buy' => get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) ),
			'text_login' => get_option( 'smartlearn_lms_button_text_login', __( 'Увійти', 'smartlearn-lms' ) ),
		);

		$args = wp_parse_args( $args, $defaults );
		$btn_class = $args['class'];

		// На картках курсів завжди показуємо "Переглянути"
		return sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( get_permalink( $course_id ) ),
			esc_attr( $btn_class ),
			esc_html( $args['text_view'] )
		);
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
			$button_text = get_option( 'smartlearn_lms_button_text_view', __( 'Переглянути', 'smartlearn-lms' ) );
			$button_url = $course_id ? get_permalink( $course_id ) : get_option( 'smartlearn_lms_login_url', 'https://www.smartlearn-shopchik.com/my-account/' );
		} else {
			if ( $course_id && self::is_course_access_expired( $course_id, $user_id ) ) {
				$message = __( 'Термін доступу до курсу закінчився. Щоб поновити доступ, придбайте курс знову.', 'smartlearn-lms' );
			} else {
				$message = __( 'Для перегляду цього контенту необхідно придбати курс.', 'smartlearn-lms' );
			}
			$button_text = get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) );
			$button_url = $course_id ? self::get_course_purchase_url( $course_id ) : '';
		}
		
		ob_start();
		?>
		<div class="smartlearn-access-denied">
			<div class="smartlearn-access-denied-icon">🔒</div>
			<h3><?php echo esc_html( $message ); ?></h3>
			<?php if ( $button_url ) : ?>
				<p>
					<?php
					$btn_class = 'button smartlearn-access-button';
					?>
					<a href="<?php echo esc_url( $button_url ); ?>" class="<?php echo esc_attr( $btn_class ); ?>">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
