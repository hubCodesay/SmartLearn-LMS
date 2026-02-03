<?php
/**
 * Shortcodes - шорткоди для відображення курсів
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Shortcodes {
	
	public function __construct() {
		add_shortcode( 'courses_list', array( $this, 'courses_list' ) );
		add_shortcode( 'course_lessons', array( $this, 'course_lessons' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// Admin settings for styling
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}
	
	/**
	 * Підключити стилі для шорткодів
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'smartlearn-lms-frontend',
			SMARTLEARN_LMS_URL . 'assets/css/frontend.css',
			array(),
			SMARTLEARN_LMS_VERSION . '-' . time() // Додаємо timestamp щоб оминути кеш
		);
		
		// Додаємо inline критичні стилі для гарантії
		$inline_css = '
			.smartlearn-courses-grid { 
				display: grid !important; 
				gap: 30px !important; 
				margin: 30px 0 !important; 
				width: 100% !important;
			}
			.smartlearn-courses-grid.columns-1 { grid-template-columns: 1fr !important; }
			.smartlearn-courses-grid.columns-2 { grid-template-columns: repeat(2, 1fr) !important; }
			.smartlearn-courses-grid.columns-3 { grid-template-columns: repeat(3, 1fr) !important; }
			.smartlearn-courses-grid.columns-4 { grid-template-columns: repeat(4, 1fr) !important; }
			.smartlearn-course-item { 
				display: flex !important; 
				flex-direction: column !important; 
				background: #fff; 
				border: 1px solid #e0e0e0; 
				border-radius: 8px; 
				overflow: hidden;
			}
		';
		wp_add_inline_style( 'smartlearn-lms-frontend', $inline_css );

		// Inject CSS variables from options (allow admin to control button background)
		$btn_bg = get_option( 'smartlearn_lms_button_bg', '#B8B7FD' );
		$btn_text = get_option( 'smartlearn_lms_button_text_color', '#ffffff' );
		$btn_hover = get_option( 'smartlearn_lms_button_hover_bg', '' );
		$btn_font = get_option( 'smartlearn_lms_button_font_family', '' );
		$btn_size = get_option( 'smartlearn_lms_button_font_size', '' );
		$css_vars = "
.smartlearn-courses-grid, .smartlearn-course-single, .smartlearn-lesson-navigation, .smartlearn-access-denied, .smartlearn-course-locked {\n"
			. "  --btn-accented-bgcolor: {$btn_bg};\n"
			. "  --btn-accented-color: {$btn_text};\n";
		if ( $btn_hover ) {
			$css_vars .= "  --btn-accented-bg-hover: " . esc_attr( $btn_hover ) . ";\n";
		}
		if ( $btn_font ) {
			$css_vars .= "  --btn-accented-font-family: " . esc_attr( $btn_font ) . ";\n";
		}
		if ( $btn_size ) {
			$css_vars .= "  --btn-accented-font-size: " . esc_attr( intval( $btn_size ) ) . "px;\n";
		}
		$css_vars .= "}\n";

		wp_add_inline_style( 'smartlearn-lms-frontend', $css_vars );
	}

	/**
	 * Register admin settings
	 */
	public function register_settings() {
		register_setting( 'smartlearn_lms_styles', 'smartlearn_lms_button_bg', 'sanitize_hex_color' );
		register_setting( 'smartlearn_lms_styles', 'smartlearn_lms_button_text_color', 'sanitize_hex_color' );
		register_setting( 'smartlearn_lms_styles', 'smartlearn_lms_button_hover_bg', 'sanitize_hex_color' );
		register_setting( 'smartlearn_lms_styles', 'smartlearn_lms_button_font_family', 'sanitize_text_field' );
		register_setting( 'smartlearn_lms_styles', 'smartlearn_lms_button_font_size', 'absint' );
	}

	/**
	 * Add settings page under Settings
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'SmartLearn Styles', 'smartlearn-lms' ),
			__( 'SmartLearn Styles', 'smartlearn-lms' ),
			'manage_options',
			'smartlearn-lms-styles',
			array( $this, 'settings_page' ),
		);
	}

	/**
	 * Render settings page
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$btn_bg = get_option( 'smartlearn_lms_button_bg', '#B8B7FD' );
		?>
		<div class="wrap">
			<h1><?php _e( 'SmartLearn Styles', 'smartlearn-lms' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'smartlearn_lms_styles' ); ?>
				<?php do_settings_sections( 'smartlearn_lms_styles' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="smartlearn_lms_button_bg"><?php _e( 'Button background', 'smartlearn-lms' ); ?></label></th>
						<td>
							<input type="text" id="smartlearn_lms_button_bg" name="smartlearn_lms_button_bg" value="<?php echo esc_attr( $btn_bg ); ?>" class="regular-text" />
							<input type="color" id="smartlearn_lms_button_bg_color" value="<?php echo esc_attr( $btn_bg ); ?>" style="margin-left:8px;vertical-align:middle;" onchange="document.getElementById('smartlearn_lms_button_bg').value = this.value;" />
							<p class="description"><?php _e( 'Set the background color used for plugin buttons.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="smartlearn_lms_button_hover_bg"><?php _e( 'Button hover background', 'smartlearn-lms' ); ?></label></th>
						<td>
							<?php $hover = get_option( 'smartlearn_lms_button_hover_bg', '' ); ?>
							<input type="text" id="smartlearn_lms_button_hover_bg" name="smartlearn_lms_button_hover_bg" value="<?php echo esc_attr( $hover ); ?>" class="regular-text" />
							<input type="color" id="smartlearn_lms_button_hover_bg_color" value="<?php echo esc_attr( $hover ?: '#000000' ); ?>" style="margin-left:8px;vertical-align:middle;" onchange="document.getElementById('smartlearn_lms_button_hover_bg').value = this.value;" />
							<p class="description"><?php _e( 'Optional hover color for plugin buttons.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="smartlearn_lms_button_text_color"><?php _e( 'Button text color', 'smartlearn-lms' ); ?></label></th>
						<td>
							<?php $txt = get_option( 'smartlearn_lms_button_text_color', '#ffffff' ); ?>
							<input type="text" id="smartlearn_lms_button_text_color" name="smartlearn_lms_button_text_color" value="<?php echo esc_attr( $txt ); ?>" class="regular-text" />
							<input type="color" id="smartlearn_lms_button_text_color_color" value="<?php echo esc_attr( $txt ); ?>" style="margin-left:8px;vertical-align:middle;" onchange="document.getElementById('smartlearn_lms_button_text_color').value = this.value;" />
							<p class="description"><?php _e( 'Set the text color used inside plugin buttons.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="smartlearn_lms_button_font_family"><?php _e( 'Button font family', 'smartlearn-lms' ); ?></label></th>
						<td>
							<?php $font = get_option( 'smartlearn_lms_button_font_family', '' ); ?>
							<input type="text" id="smartlearn_lms_button_font_family" name="smartlearn_lms_button_font_family" value="<?php echo esc_attr( $font ); ?>" class="regular-text" placeholder="e.g. 'Inter, Arial, sans-serif'" />
							<p class="description"><?php _e( 'Font family for plugin buttons. Provide CSS font-family string.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="smartlearn_lms_button_font_size"><?php _e( 'Button font size (px)', 'smartlearn-lms' ); ?></label></th>
						<td>
							<?php $size = get_option( 'smartlearn_lms_button_font_size', 14 ); ?>
							<input type="number" id="smartlearn_lms_button_font_size" name="smartlearn_lms_button_font_size" value="<?php echo esc_attr( $size ); ?>" class="small-text" min="8" max="72" /> px
							<p class="description"><?php _e( 'Font size for plugin buttons in pixels.', 'smartlearn-lms' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Шорткод [courses_list] - список всіх курсів
	 *
	 * @param array $atts
	 * @return string
	 */
	public function courses_list( $atts ) {
		// Отримуємо налаштування за замовчуванням
		$default_columns = get_option( 'smartlearn_lms_default_columns', '3' );
		$default_per_page = get_option( 'smartlearn_lms_courses_per_page', '-1' );
		
		$atts = shortcode_atts( array(
			'category' => '',
			'columns' => $default_columns,
			'per_page' => $default_per_page,
			'orderby' => 'date',
			'order' => 'DESC',
		), $atts );
		
		$args = array(
			'post_type' => 'smartlearn_course',
			'posts_per_page' => intval( $atts['per_page'] ),
			'orderby' => $atts['orderby'],
			'order' => $atts['order'],
		);
		
		// Фільтр по категорії
		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'course_category',
					'field' => 'slug',
					'terms' => explode( ',', $atts['category'] ),
				),
			);
		}
		
		$courses = new WP_Query( $args );
		
		if ( ! $courses->have_posts() ) {
			return '<p>' . __( 'Курсів не знайдено.', 'smartlearn-lms' ) . '</p>';
		}
		
		$columns = intval( $atts['columns'] );
		$columns_class = 'columns-' . $columns;
		
		ob_start();
		
		echo '<div class="smartlearn-courses-grid ' . esc_attr( $columns_class ) . '">';
		
		while ( $courses->have_posts() ) {
			$courses->the_post();
			$course_id = get_the_ID();
			$user_id = get_current_user_id();
			
			$has_access = SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id );
			$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true ) === '1';
			$duration = get_post_meta( $course_id, '_smartlearn_course_duration', true );
			$level = get_post_meta( $course_id, '_smartlearn_course_level', true );
			
			$classes = array( 'smartlearn-course-item' );
			if ( $has_access ) {
				$classes[] = 'has-access';
			}
			if ( $is_free ) {
				$classes[] = 'is-free';
			}
			
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			
			// Зображення
			if ( has_post_thumbnail() ) {
				echo '<div class="smartlearn-course-thumbnail">';
				echo '<a href="' . esc_url( get_permalink() ) . '">';
				the_post_thumbnail( 'medium' );
				echo '</a>';
				
				// Мітка безкоштовного курсу
				if ( $is_free ) {
					echo '<span class="smartlearn-course-badge free">' . __( 'Безкоштовно', 'smartlearn-lms' ) . '</span>';
				}
				
				echo '</div>';
			}
			
			echo '<div class="smartlearn-course-content">';
			
			// Категорії
			$categories = get_the_terms( $course_id, 'course_category' );
			if ( $categories && ! is_wp_error( $categories ) ) {
				echo '<div class="smartlearn-course-categories">';
				$cat_links = array();
				foreach ( $categories as $category ) {
					$cat_links[] = '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
				}
				echo implode( ', ', $cat_links );
				echo '</div>';
			}
			
			// Назва
			echo '<h3 class="smartlearn-course-title">';
			echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a>';
			echo '</h3>';
			
			// Мета-інформація
			if ( $level || $duration ) {
				echo '<div class="smartlearn-course-meta">';
				
				if ( $level ) {
					$level_labels = array(
						'beginner' => __( 'Початковий', 'smartlearn-lms' ),
						'intermediate' => __( 'Середній', 'smartlearn-lms' ),
						'advanced' => __( 'Просунутий', 'smartlearn-lms' ),
					);
					$level_label = isset( $level_labels[ $level ] ) ? $level_labels[ $level ] : $level;
					echo '<span class="meta-level">' . esc_html( $level_label ) . '</span>';
				}
				
				if ( $duration ) {
					echo '<span class="meta-duration">' . esc_html( $duration ) . '</span>';
				}
				
				echo '</div>';
			}
			
			// Опис
			if ( has_excerpt() ) {
				echo '<div class="smartlearn-course-excerpt">';
				the_excerpt();
				echo '</div>';
			}
			
			// Клас обгортки та стандартна кнопка (повернення до оригінальної розмітки)
			$wrapper_classes = 'smartlearn-course-button-wrap';
			echo '<div class="' . esc_attr( $wrapper_classes ) . '">';
			$view_label = get_option( 'smartlearn_lms_button_text_view', __( 'Переглянути', 'smartlearn-lms' ) );
			$btn_class = 'button smartlearn-course-button login-button';
			echo sprintf(
				'<a href="%s" class="%s"><span class="smartlearn-button-label">%s</span></a>',
				esc_url( get_permalink( $course_id ) ),
				esc_attr( $btn_class ),
				esc_html( $view_label )
			);
			echo '</div>'; 
			
			echo '</div>'; // .smartlearn-course-content
			
			echo '</div>'; // .smartlearn-course-item
		}
		
		echo '</div>'; // .smartlearn-courses-grid
		
		wp_reset_postdata();
		
		return ob_get_clean();
	}
	
	/**
	 * Шорткод [course_lessons] - список уроків поточного курсу
	 *
	 * @param array $atts
	 * @return string
	 */
	public function course_lessons( $atts ) {
		$atts = shortcode_atts( array(
			'course_id' => get_the_ID(),
		), $atts );
		
		$course_id = intval( $atts['course_id'] );
		
		if ( ! $course_id ) {
			return '<p>' . __( 'ID курсу не вказано.', 'smartlearn-lms' ) . '</p>';
		}
		
		ob_start();
		SmartLearn_LMS_Templates::display_course_lessons( $course_id );
		return ob_get_clean();
	}
}
