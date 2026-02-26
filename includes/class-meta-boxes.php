<?php
/**
 * Meta Boxes for Courses and Lessons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Meta_Boxes {
	
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_course_meta' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_lesson_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}
	
	public function enqueue_admin_scripts( $hook ) {
		global $post_type;
		
		if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && in_array( $post_type, array( 'smartlearn_course', 'smartlearn_lesson' ) ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_style( '-lms-admin', SMARTLEARN_LMS_URL . 'assets/css/admin.css', array(), SMARTLEARN_LMS_VERSION );
		}
	}
	
	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		
		// Course meta box
		add_meta_box(
			'smartlearn_course_settings',
			__( 'Налаштування курсу', 'smartlearn-lms' ),
			array( $this, 'render_course_meta_box' ),
			'smartlearn_course',
			'normal',
			'high'
		);
		
		// Lesson meta box
		add_meta_box(
			'smartlearn_lesson_settings',
			__( 'Налаштування уроку', 'smartlearn-lms' ),
			array( $this, 'render_lesson_meta_box' ),
			'smartlearn_lesson',
			'normal',
			'high'
		);
		
		// Course lessons meta box
		add_meta_box(
			'smartlearn_course_lessons',
			__( 'Уроки курсу', 'smartlearn-lms' ),
			array( $this, 'render_course_lessons_meta_box' ),
			'smartlearn_course',
			'side',
			'default'
		);
	}
	
	/**
	 * Render course meta box
	 */
	public function render_course_meta_box( $post ) {
		wp_nonce_field( 'smartlearn_course_meta', 'smartlearn_course_meta_nonce' );
		
		$product_id = get_post_meta( $post->ID, '_smartlearn_course_product_id', true );
		$is_free = get_post_meta( $post->ID, '_smartlearn_course_is_free', true );
		$instructor_name = get_post_meta( $post->ID, '_smartlearn_course_instructor_name', true );
		$notify_on_start = get_post_meta( $post->ID, '_smartlearn_course_notify_on_start', true );
		$start_ts = (int) get_post_meta( $post->ID, '_smartlearn_course_start_ts', true );
		$start_value = $start_ts ? wp_date( 'Y-m-d\TH:i', $start_ts, wp_timezone() ) : '';
		$start_sms = get_post_meta( $post->ID, '_smartlearn_course_start_sms', true );
		$current_user = wp_get_current_user();
		$test_email = ( $current_user instanceof WP_User ) ? $current_user->user_email : '';
		$now_ts = time();
		$now_display = wp_date( 'Y-m-d H:i:s', $now_ts, wp_timezone() );
		$start_in_past = $start_ts && $start_ts <= $now_ts;
		$start_invalid = get_post_meta( $post->ID, '_smartlearn_course_start_invalid', true );
		if ( $start_invalid ) {
			delete_post_meta( $post->ID, '_smartlearn_course_start_invalid' );
		}
		$logs_all = isset( $_GET['sl_logs'] ) && 'all' === sanitize_key( wp_unslash( $_GET['sl_logs'] ) );
		
		?>
		<div class="-meta-box">
			<p>
				<label>
					<input type="checkbox" name="smartlearn_course_is_free" value="1" <?php checked( $is_free, '1' ); ?> />
					<?php esc_html_e( 'Безкоштовний курс (доступний всім користувачам)', 'smartlearn-lms' ); ?>
				</label>
			</p>
			
			<p class="course-product-field" style="<?php echo $is_free ? 'display:none;' : ''; ?>">
				<label for="smartlearn_course_product_id">
					<strong><?php esc_html_e( 'Товар WooCommerce для доступу:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<select name="smartlearn_course_product_id" id="smartlearn_course_product_id" style="width:100%;max-width:400px;">
					<option value=""><?php esc_html_e( '— Виберіть товар —', 'smartlearn-lms' ); ?></option>
					<?php
					$products = wc_get_products( array(
						'limit' => -1,
						'status' => 'publish',
						'orderby' => 'title',
						'order' => 'ASC',
					) );
					
					foreach ( $products as $product ) {
						printf(
							'<option value="%d" %s>%s (#%d)</option>',
							$product->get_id(),
							selected( $product_id, $product->get_id(), false ),
							esc_html( $product->get_name() ),
							$product->get_id()
						);
					}
					?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Користувачі, які купили цей товар, отримають доступ до курсу.', 'smartlearn-lms' ); ?>
				</p>
			</p>
			
			<p>
				<label for="smartlearn_course_duration">
					<strong><?php esc_html_e( 'Тривалість курсу:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="text" name="smartlearn_course_duration" id="smartlearn_course_duration" value="<?php echo esc_attr( get_post_meta( $post->ID, '_smartlearn_course_duration', true ) ); ?>" style="width:100%;max-width:400px;" placeholder="<?php esc_attr_e( 'наприклад: 4 тижні, 12 годин', 'smartlearn-lms' ); ?>" />
			</p>
			
			<p>
				<label for="smartlearn_course_level">
					<strong><?php esc_html_e( 'Рівень складності:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<select name="smartlearn_course_level" id="smartlearn_course_level" style="width:100%;max-width:400px;">
					<?php
					$level = get_post_meta( $post->ID, '_smartlearn_course_level', true );
					$levels = array(
						'beginner' => __( 'Початковий', 'smartlearn-lms' ),
						'intermediate' => __( 'Середній', 'smartlearn-lms' ),
						'advanced' => __( 'Просунутий', 'smartlearn-lms' ),
					);
					foreach ( $levels as $key => $label ) {
						printf( '<option value="%s" %s>%s</option>', $key, selected( $level, $key, false ), esc_html( $label ) );
					}
					?>
				</select>
			</p>

			<p>
				<label for="smartlearn_course_instructor_name">
					<strong><?php esc_html_e( 'Викладач (ім\'я та прізвище):', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="text" name="smartlearn_course_instructor_name" id="smartlearn_course_instructor_name" value="<?php echo esc_attr( $instructor_name ); ?>" style="width:100%;max-width:400px;" placeholder="<?php esc_attr_e( 'наприклад: Іван Петренко', 'smartlearn-lms' ); ?>" />
				<p class="description">
					<?php esc_html_e( 'Якщо поле порожнє, автоматично використовується автор запису курсу.', 'smartlearn-lms' ); ?>
				</p>
			</p>

			<hr style="margin:20px 0;">

			<h3 style="margin:0 0 12px;"><?php esc_html_e( 'Сповіщення про початок курсу (SMS на email)', 'smartlearn-lms' ); ?></h3>
			<p>
				<label>
					<input type="checkbox" name="smartlearn_course_notify_on_start" value="1" <?php checked( $notify_on_start, '1' ); ?> />
					<?php esc_html_e( 'Сповістити користувачів про початок курсу/стріму', 'smartlearn-lms' ); ?>
				</label>
			</p>
			<p>
				<label for="smartlearn_course_start_at">
					<strong><?php esc_html_e( 'Дата і час початку:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="datetime-local" name="smartlearn_course_start_at" id="smartlearn_course_start_at" value="<?php echo esc_attr( $start_value ); ?>" style="width:260px;">
				<p class="description">
					<?php esc_html_e( 'Використовується час сайту (налаштування WordPress).', 'smartlearn-lms' ); ?>
					<br/>
					<?php echo esc_html( sprintf( __( 'Поточний час: %s', 'smartlearn-lms' ), $now_display ) ); ?>
				</p>
				<?php if ( $start_invalid ) : ?>
					<p class="description" style="color:#b32d2e;">
						<?php esc_html_e( 'Збережено некоректний час (у минулому). Вкажіть майбутню дату.', 'smartlearn-lms' ); ?>
					</p>
				<?php elseif ( $start_in_past ) : ?>
					<p class="description" style="color:#b32d2e;">
						<?php esc_html_e( 'Обраний час уже в минулому. Вкажіть майбутню дату.', 'smartlearn-lms' ); ?>
					</p>
				<?php endif; ?>
			</p>
			<p>
				<label for="smartlearn_course_start_sms">
					<strong><?php esc_html_e( 'Текст SMS (на email):', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<textarea name="smartlearn_course_start_sms" id="smartlearn_course_start_sms" rows="5" style="width:100%;max-width:600px;"><?php echo esc_textarea( $start_sms ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Цей текст буде відправлено всім користувачам курсу.', 'smartlearn-lms' ); ?></p>
			</p>
			<p>
				<button type="button" class="button button-primary" id="smartlearn_course_send_now_sms_button"><?php esc_html_e( 'Надіслати всім зараз', 'smartlearn-lms' ); ?></button>
				<span id="smartlearn_course_send_now_sms_status" style="margin-left:8px;"></span>
			</p>
			<p>
				<label for="smartlearn_course_test_sms_email">
					<strong><?php esc_html_e( 'Тестове відправлення (email):', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="email" id="smartlearn_course_test_sms_email" value="<?php echo esc_attr( $test_email ); ?>" style="width:260px;">
				<button type="button" class="button" id="smartlearn_course_test_sms_button"><?php esc_html_e( 'Надіслати тест', 'smartlearn-lms' ); ?></button>
				<span id="smartlearn_course_test_sms_status" style="margin-left:8px;"></span>
			</p>

			<?php if ( class_exists( 'SmartLearn_LMS_Notifications' ) ) : ?>
				<?php $logs = SmartLearn_LMS_Notifications::get_course_logs( $post->ID, $logs_all ? 0 : 30 ); ?>
				<?php if ( ! empty( $logs ) ) : ?>
					<h4 style="margin:16px 0 8px;"><?php echo esc_html( $logs_all ? __( 'Статус відправлень (всі)', 'smartlearn-lms' ) : __( 'Статус відправлень (останні 30)', 'smartlearn-lms' ) ); ?></h4>
					<p style="margin:0 0 8px;">
						<?php if ( $logs_all ) : ?>
							<a href="<?php echo esc_url( remove_query_arg( 'sl_logs' ) ); ?>"><?php esc_html_e( 'Показати останні 30', 'smartlearn-lms' ); ?></a>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'sl_logs', 'all' ) ); ?>"><?php esc_html_e( 'Показати всі логи', 'smartlearn-lms' ); ?></a>
						<?php endif; ?>
					</p>
					<table class="widefat striped" style="max-width:900px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Email', 'smartlearn-lms' ); ?></th>
								<th><?php esc_html_e( 'Статус', 'smartlearn-lms' ); ?></th>
								<th><?php esc_html_e( 'Час', 'smartlearn-lms' ); ?></th>
								<th><?php esc_html_e( 'Помилка', 'smartlearn-lms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log->user_email ); ?></td>
									<td><?php echo esc_html( $log->status ); ?></td>
									<td><?php echo esc_html( $log->sent_at ); ?></td>
									<td><?php echo esc_html( $log->error ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('input[name="smartlearn_course_is_free"]').on('change', function() {
				if ( $(this).is(':checked') ) {
					$('.course-product-field').hide();
				} else {
					$('.course-product-field').show();
				}
			});

			$('#smartlearn_course_test_sms_button').on('click', function() {
				var $status = $('#smartlearn_course_test_sms_status');
				var email = $('#smartlearn_course_test_sms_email').val();
				var message = $('#smartlearn_course_start_sms').val();
				$status.text('');
				$.post(ajaxurl, {
					action: 'smartlearn_lms_send_test_sms',
					nonce: '<?php echo esc_js( wp_create_nonce( 'smartlearn_lms_send_test_sms' ) ); ?>',
					course_id: '<?php echo esc_js( $post->ID ); ?>',
					email: email,
					message: message
				}).done(function(resp) {
					if (resp && resp.success) {
						$status.css('color', '#0a7a00').text(resp.data.message);
					} else {
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : '<?php echo esc_js( __( 'Помилка.', 'smartlearn-lms' ) ); ?>';
						$status.css('color', '#b32d2e').text(msg);
					}
				}).fail(function() {
					$status.css('color', '#b32d2e').text('<?php echo esc_js( __( 'Помилка запиту.', 'smartlearn-lms' ) ); ?>');
				});
			});

			$('#smartlearn_course_send_now_sms_button').on('click', function() {
				var $status = $('#smartlearn_course_send_now_sms_status');
				var message = $('#smartlearn_course_start_sms').val();
				$status.text('');
				$.post(ajaxurl, {
					action: 'smartlearn_lms_send_now_sms',
					nonce: '<?php echo esc_js( wp_create_nonce( 'smartlearn_lms_send_now_sms' ) ); ?>',
					course_id: '<?php echo esc_js( $post->ID ); ?>',
					message: message
				}).done(function(resp) {
					if (resp && resp.success) {
						$status.css('color', '#0a7a00').text(resp.data.message);
					} else {
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : '<?php echo esc_js( __( 'Помилка.', 'smartlearn-lms' ) ); ?>';
						$status.css('color', '#b32d2e').text(msg);
					}
				}).fail(function() {
					$status.css('color', '#b32d2e').text('<?php echo esc_js( __( 'Помилка запиту.', 'smartlearn-lms' ) ); ?>');
				});
			});

			function pad2(n) { return (n < 10 ? '0' : '') + n; }
			function toLocalDatetimeValue(date) {
				return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate()) + 'T' + pad2(date.getHours()) + ':' + pad2(date.getMinutes());
			}
			var $startInput = $('#smartlearn_course_start_at');
			if ($startInput.length) {
				var now = new Date();
				now.setMinutes(now.getMinutes() + 1);
				$startInput.attr('min', toLocalDatetimeValue(now));
			}
		});
		</script>
		<?php
	}
	
	/**
	 * Render lesson meta box
	 */
	public function render_lesson_meta_box( $post ) {
		wp_nonce_field( 'smartlearn_lesson_meta', 'smartlearn_lesson_meta_nonce' );
		
		$course_id = get_post_meta( $post->ID, '_smartlearn_lesson_course_id', true );
		$is_free = get_post_meta( $post->ID, '_smartlearn_lesson_is_free', true );
		$video_url = get_post_meta( $post->ID, '_smartlearn_lesson_video_url', true );
		
		?>
		<div class="-meta-box">
			<p>
				<label for="smartlearn_lesson_course_id">
					<strong><?php esc_html_e( 'Курс:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<select name="smartlearn_lesson_course_id" id="smartlearn_lesson_course_id" style="width:100%;max-width:400px;">
					<option value=""><?php esc_html_e( '— Виберіть курс —', 'smartlearn-lms' ); ?></option>
					<?php
					$courses = get_posts( array(
						'post_type' => 'smartlearn_course',
						'posts_per_page' => -1,
						'orderby' => 'title',
						'order' => 'ASC',
						'post_status' => array( 'publish', 'draft' ),
					) );
					
					foreach ( $courses as $course ) {
						printf(
							'<option value="%d" %s>%s</option>',
							$course->ID,
							selected( $course_id, $course->ID, false ),
							esc_html( $course->post_title )
						);
					}
					?>
				</select>
			</p>
			
			<p>
				<label>
					<input type="checkbox" name="smartlearn_lesson_is_free" value="1" <?php checked( $is_free, '1' ); ?> />
					<?php esc_html_e( 'Безкоштовний урок (доступний без покупки курсу)', 'smartlearn-lms' ); ?>
				</label>
			</p>
			
			<p>
				<label for="smartlearn_lesson_video_url">
					<strong><?php esc_html_e( 'Відео URL (YouTube, Vimeo):', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="url" name="smartlearn_lesson_video_url" id="smartlearn_lesson_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;" placeholder="https://www.youtube.com/watch?v=..." />
				<p class="description">
					<?php esc_html_e( 'Опціонально: додайте посилання на відео з YouTube або Vimeo.', 'smartlearn-lms' ); ?>
				</p>
			</p>
			
			<p>
				<label for="smartlearn_lesson_duration">
					<strong><?php esc_html_e( 'Тривалість уроку:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="text" name="smartlearn_lesson_duration" id="smartlearn_lesson_duration" value="<?php echo esc_attr( get_post_meta( $post->ID, '_smartlearn_lesson_duration', true ) ); ?>" style="width:100%;max-width:200px;" placeholder="<?php esc_attr_e( '15 хв', 'smartlearn-lms' ); ?>" />
			</p>
		</div>
		<?php
	}
	
	/**
	 * Render course lessons list meta box
	 */
	public function render_course_lessons_meta_box( $post ) {
		$lessons = get_posts( array(
			'post_type' => 'smartlearn_lesson',
			'posts_per_page' => -1,
			'meta_key' => '_smartlearn_lesson_course_id',
			'meta_value' => $post->ID,
			'orderby' => 'menu_order',
			'order' => 'ASC',
		) );
		
		if ( ! empty( $lessons ) ) {
			echo '<ul style="margin:0;padding:0;list-style:none;">';
			foreach ( $lessons as $lesson ) {
				$is_free = get_post_meta( $lesson->ID, '_smartlearn_lesson_is_free', true );
				$icon = $is_free ? '🔓' : '🔒';
				printf(
					'<li style="padding:8px 0;border-bottom:1px solid #ddd;">%s <a href="%s">%s</a></li>',
					$icon,
					get_edit_post_link( $lesson->ID ),
					esc_html( $lesson->post_title )
				);
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'Уроків поки немає.', 'smartlearn-lms' ) . '</p>';
		}
		
		echo '<p style="margin-top:15px;"><a href="' . admin_url( 'post-new.php?post_type=smartlearn_lesson&course_id=' . $post->ID ) . '" class="button">' . esc_html__( 'Додати урок', 'smartlearn-lms' ) . '</a></p>';
	}
	
	/**
	 * Save course meta
	 */
	public function save_course_meta( $post_id, $post ) {
		if ( $post->post_type !== 'smartlearn_course' ) {
			return;
		}
		
		if ( ! isset( $_POST['smartlearn_course_meta_nonce'] ) || ! wp_verify_nonce( $_POST['smartlearn_course_meta_nonce'], 'smartlearn_course_meta' ) ) {
			return;
		}
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Save is_free
		$is_free = isset( $_POST['smartlearn_course_is_free'] ) ? '1' : '';
		update_post_meta( $post_id, '_smartlearn_course_is_free', $is_free );
		
		// Save product ID
		$product_id = isset( $_POST['smartlearn_course_product_id'] ) ? absint( $_POST['smartlearn_course_product_id'] ) : '';
		update_post_meta( $post_id, '_smartlearn_course_product_id', $product_id );
		
		// Save duration
		$duration = isset( $_POST['smartlearn_course_duration'] ) ? sanitize_text_field( $_POST['smartlearn_course_duration'] ) : '';
		update_post_meta( $post_id, '_smartlearn_course_duration', $duration );
		
		// Save level
		$level = isset( $_POST['smartlearn_course_level'] ) ? sanitize_text_field( $_POST['smartlearn_course_level'] ) : 'beginner';
		update_post_meta( $post_id, '_smartlearn_course_level', $level );

		// Save instructor name override
		$instructor_name = isset( $_POST['smartlearn_course_instructor_name'] ) ? sanitize_text_field( $_POST['smartlearn_course_instructor_name'] ) : '';
		update_post_meta( $post_id, '_smartlearn_course_instructor_name', $instructor_name );

		// Save notifications settings
		$notify_on_start = isset( $_POST['smartlearn_course_notify_on_start'] ) ? '1' : '';
		update_post_meta( $post_id, '_smartlearn_course_notify_on_start', $notify_on_start );

		$start_at_raw = isset( $_POST['smartlearn_course_start_at'] ) ? sanitize_text_field( $_POST['smartlearn_course_start_at'] ) : '';
		$start_ts = 0;
		$start_at = '';
		if ( '' !== $start_at_raw ) {
			$dt = date_create_from_format( 'Y-m-d\TH:i', $start_at_raw, wp_timezone() );
			if ( $dt ) {
				$start_ts = (int) $dt->getTimestamp();
				if ( $start_ts <= time() ) {
					$start_ts = 0;
					$start_at = '';
					update_post_meta( $post_id, '_smartlearn_course_start_invalid', '1' );
				} else {
					$start_at = $dt->format( 'Y-m-d H:i:s' );
					update_post_meta( $post_id, '_smartlearn_course_start_invalid', '' );
				}
			}
		}
		update_post_meta( $post_id, '_smartlearn_course_start_at', $start_at );
		update_post_meta( $post_id, '_smartlearn_course_start_ts', $start_ts );

		$start_sms = isset( $_POST['smartlearn_course_start_sms'] ) ? sanitize_textarea_field( $_POST['smartlearn_course_start_sms'] ) : '';
		update_post_meta( $post_id, '_smartlearn_course_start_sms', $start_sms );

		if ( class_exists( 'SmartLearn_LMS_Notifications' ) ) {
			if ( '1' === $notify_on_start && $start_ts ) {
				SmartLearn_LMS_Notifications::schedule_course_notification( $post_id, $start_ts );
			} else {
				SmartLearn_LMS_Notifications::schedule_course_notification( $post_id, 0 );
			}
		}
	}
	
	/**
	 * Save lesson meta
	 */
	public function save_lesson_meta( $post_id, $post ) {
		if ( $post->post_type !== 'smartlearn_lesson' ) {
			return;
		}
		
		if ( ! isset( $_POST['smartlearn_lesson_meta_nonce'] ) || ! wp_verify_nonce( $_POST['smartlearn_lesson_meta_nonce'], 'smartlearn_lesson_meta' ) ) {
			return;
		}
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Save course ID
		$course_id = isset( $_POST['smartlearn_lesson_course_id'] ) ? absint( $_POST['smartlearn_lesson_course_id'] ) : '';
		update_post_meta( $post_id, '_smartlearn_lesson_course_id', $course_id );
		
		// Save is_free
		$is_free = isset( $_POST['smartlearn_lesson_is_free'] ) ? '1' : '';
		update_post_meta( $post_id, '_smartlearn_lesson_is_free', $is_free );
		
		// Save video URL
		$video_url = isset( $_POST['smartlearn_lesson_video_url'] ) ? esc_url_raw( $_POST['smartlearn_lesson_video_url'] ) : '';
		update_post_meta( $post_id, '_smartlearn_lesson_video_url', $video_url );
		
		// Save duration
		$duration = isset( $_POST['smartlearn_lesson_duration'] ) ? sanitize_text_field( $_POST['smartlearn_lesson_duration'] ) : '';
		update_post_meta( $post_id, '_smartlearn_lesson_duration', $duration );
	}
}
