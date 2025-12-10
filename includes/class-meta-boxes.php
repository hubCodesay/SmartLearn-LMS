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
