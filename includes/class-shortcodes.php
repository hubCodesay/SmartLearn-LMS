<?php
/**
 * Shortcodes - шорткоди для відображення курсів
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Shortcodes {
	private $settings_cache = null;
	private $button_labels_cache = null;
	
	public function __construct() {
		add_shortcode( 'courses_list', array( $this, 'courses_list' ) );
		add_shortcode( 'course_lessons', array( $this, 'course_lessons' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	private function get_settings() {
		if ( null !== $this->settings_cache ) {
			return $this->settings_cache;
		}

		$this->settings_cache = array(
			'login_url' => trim( (string) get_option( 'smartlearn_lms_login_url', '' ) ),
			'image_aspect' => get_option( 'smartlearn_lms_image_aspect', '16/9' ),
			'card_bg' => get_option( 'smartlearn_lms_card_bg', '#ffffff' ),
			'card_radius' => get_option( 'smartlearn_lms_card_radius', '8' ),
			'card_border' => get_option( 'smartlearn_lms_card_border', '#e0e0e0' ),
			'title_color' => get_option( 'smartlearn_lms_title_color', '#1d2327' ),
			'text_color' => get_option( 'smartlearn_lms_text_color', '#3c434a' ),
			'meta_color' => get_option( 'smartlearn_lms_meta_color', '#646970' ),
			'btn_bg' => get_option( 'smartlearn_lms_btn_bg', '#2271b1' ),
			'btn_txt_color' => get_option( 'smartlearn_lms_btn_text_color', '#ffffff' ),
			'btn_hover_bg' => get_option( 'smartlearn_lms_btn_hover_bg', '#135e96' ),
			'btn_radius' => get_option( 'smartlearn_lms_btn_radius', '4' ),
			'default_columns' => get_option( 'smartlearn_lms_default_columns', '3' ),
			'default_per_page' => get_option( 'smartlearn_lms_courses_per_page', '-1' ),
			'card_layout' => get_option( 'smartlearn_lms_card_layout', 'thumbnail:1,category:1,title:1,author:1,meta:1,price:1,excerpt:1,button:1' ),
		);

		return $this->settings_cache;
	}

	private function get_button_labels() {
		if ( null !== $this->button_labels_cache ) {
			return $this->button_labels_cache;
		}

		$this->button_labels_cache = array(
			'view' => get_option( 'smartlearn_lms_button_text_view', __( 'Переглянути', 'smartlearn-lms' ) ),
			'buy' => get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) ),
			'login' => get_option( 'smartlearn_lms_button_text_login', __( 'Увійти', 'smartlearn-lms' ) ),
		);

		return $this->button_labels_cache;
	}

	/**
	 * Get login / my-account URL.
	 */
	private function get_login_url( $redirect_url = '' ) {
		$settings = $this->get_settings();
		$login_url = $settings['login_url'];
		if ( empty( $login_url ) && function_exists( 'wc_get_page_permalink' ) ) {
			$login_url = wc_get_page_permalink( 'myaccount' );
		}
		if ( empty( $login_url ) ) {
			$login_url = wp_login_url( $redirect_url ?: home_url( '/' ) );
		}
		return $login_url;
	}

	/**
	 * Get WooCommerce product permalink linked to the course.
	 */
	private function get_course_purchase_url( $course_id ) {
		$product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
		$product_id = $product_id ? absint( $product_id ) : 0;
		if ( ! $product_id ) {
			return '';
		}

		if ( function_exists( 'wc_get_checkout_url' ) ) {
			return add_query_arg(
				array(
					'add-to-cart' => $product_id,
				),
				wc_get_checkout_url()
			);
		}

		$product_url = get_permalink( $product_id );
		return $product_url ? $product_url : '';
	}

	/**
	 * Get WooCommerce product price HTML linked to the course.
	 */
	private function get_course_price_html( $course_id ) {
		$product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
		$product_id = $product_id ? absint( $product_id ) : 0;
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		return (string) $product->get_price_html();
	}

	/**
	 * Get a reliable course URL.
	 */
	private function get_course_preview_url( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return home_url( '/' );
		}

		$url = get_permalink( $course_id );
		$home = home_url( '/' );
		if ( empty( $url ) || untrailingslashit( $url ) === untrailingslashit( $home ) ) {
			$url = add_query_arg(
				array(
					'post_type' => 'smartlearn_course',
					'p' => $course_id,
				),
				home_url( '/' )
			);
		}

		return $url;
	}
	
	/**
	 * Підключити стилі для шорткодів
	 */
	public function enqueue_styles() {
		$opts = $this->get_settings();
		wp_enqueue_style(
			'smartlearn-lms-frontend',
			SMARTLEARN_LMS_URL . 'assets/css/frontend.css',
			array(),
			SMARTLEARN_LMS_VERSION
		);

		$inline_css = "
			.smartlearn-courses-grid { 
				display: grid !important; 
				gap: 30px !important; 
				margin: 30px 0 !important; 
				width: 100% !important;
			}
			@media(min-width: 992px) {
				.smartlearn-courses-grid.columns-1 { grid-template-columns: 1fr !important; }
				.smartlearn-courses-grid.columns-2 { grid-template-columns: repeat(2, 1fr) !important; }
				.smartlearn-courses-grid.columns-3 { grid-template-columns: repeat(3, 1fr) !important; }
				.smartlearn-courses-grid.columns-4 { grid-template-columns: repeat(4, 1fr) !important; }
			}
			@media(min-width: 768px) and (max-width: 991px) {
				.smartlearn-courses-grid.columns-3, .smartlearn-courses-grid.columns-4 { grid-template-columns: repeat(2, 1fr) !important; }
			}
			@media(max-width: 767px) {
				.smartlearn-courses-grid { grid-template-columns: 1fr !important; }
			}
			.smartlearn-course-item { 
				display: flex !important; 
				flex-direction: column !important; 
				background: {$opts['card_bg']} !important; 
				border: 1px solid {$opts['card_border']} !important; 
				border-radius: {$opts['card_radius']}px !important; 
				overflow: hidden;
				padding: 20px;
				gap: 10px;
			}
			.smartlearn-course-item > * { width: 100%; margin: 0; }
			.smartlearn-course-thumbnail { margin: -20px -20px 0 !important; width: calc(100% + 40px) !important; }
			.smartlearn-course-thumbnail img {
				width: 100%;
				display: block;
				object-fit: cover;
				aspect-ratio: " . ($opts['image_aspect'] === 'auto' ? 'auto' : $opts['image_aspect']) . ";
			}
			.smartlearn-course-title a { color: {$opts['title_color']} !important; text-decoration: none; }
			.smartlearn-course-excerpt { color: {$opts['text_color']} !important; }
			.smartlearn-course-categories a, .smartlearn-course-meta span, .smartlearn-course-author, .smartlearn-course-price { color: {$opts['meta_color']} !important; }
			.smartlearn-course-button {
				background-color: {$opts['btn_bg']} !important;
				color: {$opts['btn_txt_color']} !important;
				border-radius: {$opts['btn_radius']}px !important;
				text-decoration: none;
				padding: 10px 20px;
				display: inline-block;
				text-align: center;
				transition: background 0.3s ease;
			}
			.smartlearn-course-button:hover { background-color: {$opts['btn_hover_bg']} !important; }
			/* For elements that are supposed to stick to the bottom */
			.smartlearn-course-button-wrap { margin-top: auto !important; }
		";
		wp_add_inline_style( 'smartlearn-lms-frontend', $inline_css );
	}

	/**
	 * Шорткод [courses_list] - список всіх курсів
	 */
	public function courses_list( $atts ) {
		$settings = $this->get_settings();
		$labels = $this->get_button_labels();
		$default_columns = $settings['default_columns'];
		$default_per_page = $settings['default_per_page'];
		$user_id = get_current_user_id();
		
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
		
		$layout_str = $settings['card_layout'];
		$layout_items = explode(',', $layout_str);
		$ordered_blocks = array();
		foreach($layout_items as $item) {
			$p = explode(':', $item);
			if(count($p) == 2 && $p[1] === '1') {
				$ordered_blocks[] = $p[0];
			}
		}

		ob_start();
		
		echo '<div class="smartlearn-courses-grid ' . esc_attr( $columns_class ) . '">';
		
		while ( $courses->have_posts() ) {
			$courses->the_post();
			$course_id = get_the_ID();
			$course_url = $this->get_course_preview_url( $course_id );
			
			$has_access = SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id );
			$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true ) === '1';
			$duration = get_post_meta( $course_id, '_smartlearn_course_duration', true );
			$level = get_post_meta( $course_id, '_smartlearn_course_level', true );
			$custom_author_name = trim( (string) get_post_meta( $course_id, '_smartlearn_course_instructor_name', true ) );
			$author_id = (int) get_post_field( 'post_author', $course_id );
			$fallback_author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
			$author_name = '' !== $custom_author_name ? $custom_author_name : $fallback_author_name;
			$course_price_html = $this->get_course_price_html( $course_id );
			
			$classes = array( 'smartlearn-course-item' );
			if ( $has_access ) $classes[] = 'has-access';
			if ( $is_free ) $classes[] = 'is-free';
			
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			
			$block_count = count($ordered_blocks);
			foreach($ordered_blocks as $idx => $block_id) {
				switch($block_id) {
					case 'thumbnail':
						if ( has_post_thumbnail() ) {
							$thumb_margin = '0 -20px';
							if ($idx === 0) $thumb_margin = '-20px -20px 10px';
							elseif ($idx === $block_count - 1) $thumb_margin = '10px -20px -20px';
							
							echo '<div class="smartlearn-course-thumbnail" style="margin: ' . $thumb_margin . '; width: calc(100% + 40px);">';
							echo '<a href="' . esc_url( $course_url ) . '" data-smartlearn-course-id="' . esc_attr( $course_id ) . '" onclick="event.stopPropagation();">';
							the_post_thumbnail( 'medium' );
							echo '</a>';
							if ( $is_free ) {
								echo '<span class="smartlearn-course-badge free" style="position:absolute; top:10px; right:10px; background:#4CAF50; color:#fff; padding:4px 8px; border-radius:4px; font-size:12px;">' . __( 'Безкоштовно', 'smartlearn-lms' ) . '</span>';
							}
							echo '</div>';
						}
						break;
						
					case 'category':
						$categories = get_the_terms( $course_id, 'course_category' );
						if ( $categories && ! is_wp_error( $categories ) ) {
							echo '<div class="smartlearn-course-categories" style="font-size: 13px;">';
							$cat_links = array();
							foreach ( $categories as $category ) {
								$cat_links[] = '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
							}
							echo implode( ', ', $cat_links );
							echo '</div>';
						}
						break;
						
					case 'title':
						echo '<h4 class="smartlearn-course-title" style="margin: 0 0 5px;">';
						echo '<a href="' . esc_url( $course_url ) . '" data-smartlearn-course-id="' . esc_attr( $course_id ) . '" onclick="event.stopPropagation();">' . get_the_title() . '</a>';
						echo '</h4>';
						break;

					case 'author':
						if ( $author_name ) {
							echo '<div class="smartlearn-course-author" style="font-size: 13px;">';
							echo '<span class="meta-author">' . sprintf( esc_html__( 'Автор: %s', 'smartlearn-lms' ), esc_html( $author_name ) ) . '</span>';
							echo '</div>';
						}
						break;
						
					case 'meta':
						if ( $level || $duration ) {
							echo '<div class="smartlearn-course-meta" style="font-size: 13px; display: flex; gap: 15px;">';
							if ( $level ) {
								$level_labels = array('beginner' => __('Початковий', 'smartlearn-lms'), 'intermediate' => __('Середній', 'smartlearn-lms'), 'advanced' => __('Просунутий', 'smartlearn-lms'));
								echo '<span class="meta-level">' . esc_html( isset($level_labels[$level]) ? $level_labels[$level] : $level ) . '</span>';
							}
							if ( $duration ) echo '<span class="meta-duration">' . esc_html( $duration ) . '</span>';
							echo '</div>';
						}
						break;

					case 'price':
						if ( $course_price_html ) {
							echo '<div class="smartlearn-course-price" style="font-size: 14px; font-weight: 600;">';
							echo wp_kses_post( $course_price_html );
							echo '</div>';
						}
						break;
						
					case 'excerpt':
						$excerpt_text = wp_trim_words( empty( get_the_excerpt() ) ? wp_strip_all_tags( get_the_content() ) : get_the_excerpt(), 20, '...' );
						echo '<div class="smartlearn-course-excerpt" style="line-height: 1.5; font-size: 14px;">' . esc_html( $excerpt_text ) . '</div>';
						break;
						
					case 'button':
						echo '<div class="smartlearn-course-button-wrap" style="margin-top:auto;">';
						$btn_class_base = 'smartlearn-course-button';

						if ( ! is_user_logged_in() ) {
							$target_url = $course_url;
							$btn_label = $labels['view'];
							$btn_class = $btn_class_base . ' view-course';
						} elseif ( $has_access ) {
							$target_url = $course_url;
							$btn_label = $labels['view'];
							$btn_class = $btn_class_base . ' has-access';
						} else {
							$purchase_url = $this->get_course_purchase_url( $course_id );
							$target_url = $purchase_url ? $purchase_url : $course_url;
							$btn_label = $purchase_url ? $labels['buy'] : $labels['view'];
							$btn_class = $btn_class_base . ( $purchase_url ? ' need-purchase' : '' );
						}

						echo sprintf(
							'<a href="%s" class="%s" data-smartlearn-course-id="%s" onclick="event.stopPropagation();">%s</a>',
							esc_url( $target_url ),
							esc_attr( $btn_class ),
							esc_attr( $course_id ),
							esc_html( $btn_label )
						);
						echo '</div>';
						break;
				}
			}
			
			echo '</div>'; // .smartlearn-course-item
		}
		
		echo '</div>'; // .smartlearn-courses-grid
		
		wp_reset_postdata();
		
		return ob_get_clean();
	}

	/**
	 * Шорткод [course_lessons] - список уроків поточного курсу
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
