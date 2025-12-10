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
	}
	
	/**
	 * Шорткод [courses_list] - список всіх курсів
	 *
	 * @param array $atts
	 * @return string
	 */
	public function courses_list( $atts ) {
		$atts = shortcode_atts( array(
			'category' => '',
			'columns' => '3',
			'per_page' => '-1',
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
		
		echo '<div class="-courses-grid ' . esc_attr( $columns_class ) . '">';
		
		while ( $courses->have_posts() ) {
			$courses->the_post();
			$course_id = get_the_ID();
			$user_id = get_current_user_id();
			
			$has_access = SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id );
			$is_free = get_post_meta( $course_id, '_smartlearn_course_is_free', true ) === '1';
			$duration = get_post_meta( $course_id, '_smartlearn_course_duration', true );
			$level = get_post_meta( $course_id, '_smartlearn_course_level', true );
			
			$classes = array( '-course-item' );
			if ( $has_access ) {
				$classes[] = 'has-access';
			}
			if ( $is_free ) {
				$classes[] = 'is-free';
			}
			
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			
			// Зображення
			if ( has_post_thumbnail() ) {
				echo '<div class="-course-thumbnail">';
				echo '<a href="' . esc_url( get_permalink() ) . '">';
				the_post_thumbnail( 'medium' );
				echo '</a>';
				
				// Мітка безкоштовного курсу
				if ( $is_free ) {
					echo '<span class="-course-badge free">' . __( 'Безкоштовно', 'smartlearn-lms' ) . '</span>';
				}
				
				echo '</div>';
			}
			
			echo '<div class="-course-content">';
			
			// Категорії
			$categories = get_the_terms( $course_id, 'course_category' );
			if ( $categories && ! is_wp_error( $categories ) ) {
				echo '<div class="-course-categories">';
				$cat_links = array();
				foreach ( $categories as $category ) {
					$cat_links[] = '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
				}
				echo implode( ', ', $cat_links );
				echo '</div>';
			}
			
			// Назва
			echo '<h3 class="-course-title">';
			echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a>';
			echo '</h3>';
			
			// Мета-інформація
			if ( $level || $duration ) {
				echo '<div class="-course-meta">';
				
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
				echo '<div class="-course-excerpt">';
				the_excerpt();
				echo '</div>';
			}
			
			// Кнопка доступу
			echo '<div class="-course-button-wrap">';
			echo SmartLearn_LMS_Access_Control::get_course_access_button( $course_id );
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
