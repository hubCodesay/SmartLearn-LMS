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
		return self::user_has_bought_product( $user_id, $product_id );
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
		<div class="-access-denied">
			<div class="-access-denied-icon">🔒</div>
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
