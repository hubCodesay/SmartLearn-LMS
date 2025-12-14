<?php
/**
 * Templates - шаблони для відображення курсів та уроків
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Templates {
	
	public function __construct() {
		add_filter( 'template_include', array( $this, 'load_course_template' ) );
		add_filter( 'template_include', array( $this, 'load_lesson_template' ) );
	}
	
	/**
	 * Завантажити шаблон для курсу
	 */
	public function load_course_template( $template ) {
		if ( is_singular( 'smartlearn_course' ) ) {
			$plugin_template = SMARTLEARN_LMS_PATH . 'templates/single-course.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}
	
	/**
	 * Завантажити шаблон для уроку
	 */
	public function load_lesson_template( $template ) {
		if ( is_singular( 'smartlearn_lesson' ) ) {
			$plugin_template = SMARTLEARN_LMS_PATH . 'templates/single-lesson.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}
	
	/**
	 * Отримати список уроків курсу
	 *
	 * @param int $course_id
	 * @return array
	 */
	public static function get_course_lessons( $course_id ) {
		$lessons = get_posts( array(
			'post_type' => 'smartlearn_lesson',
			'posts_per_page' => -1,
			'meta_key' => '_smartlearn_lesson_course_id',
			'meta_value' => $course_id,
			'orderby' => 'menu_order',
			'order' => 'ASC',
		) );
		
		return $lessons;
	}
	
	/**
	 * Відобразити список уроків курсу
	 *
	 * @param int $course_id
	 */
	public static function display_course_lessons( $course_id ) {
		$lessons = self::get_course_lessons( $course_id );
		$user_id = get_current_user_id();
		
		if ( empty( $lessons ) ) {
			echo '<p>' . __( 'Уроків поки немає.', 'smartlearn-lms' ) . '</p>';
			return;
		}
		
		echo '<div class="smartlearn-lessons-list">';
		
		foreach ( $lessons as $lesson ) {
			$has_access = SmartLearn_LMS_Access_Control::user_has_lesson_access( $lesson->ID, $user_id );
			$is_free = get_post_meta( $lesson->ID, '_smartlearn_lesson_is_free', true ) === '1';
			$duration = get_post_meta( $lesson->ID, '_smartlearn_lesson_duration', true );
			
			$classes = array( '-lesson-item' );
			if ( $has_access ) {
				$classes[] = 'has-access';
			} else {
				$classes[] = 'no-access';
			}
			if ( $is_free ) {
				$classes[] = 'is-free';
			}
			
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			
			// Іконка
			echo '<span class="smartlearn-lesson-icon">';
			if ( $has_access ) {
				echo '✓'; // Відкритий урок
			} else {
				echo '🔒'; // Закритий урок
			}
			echo '</span>';
			
			// Назва
			if ( $has_access ) {
				echo '<a href="' . esc_url( get_permalink( $lesson->ID ) ) . '" class="smartlearn-lesson-title">';
				echo esc_html( $lesson->post_title );
				echo '</a>';
			} else {
				echo '<span class="smartlearn-lesson-title">';
				echo esc_html( $lesson->post_title );
				echo '</span>';
			}
			
			// Мітки
			echo '<div class="smartlearn-lesson-meta">';
			if ( $is_free ) {
				echo '<span class="smartlearn-lesson-badge free">' . __( 'Безкоштовно', 'smartlearn-lms' ) . '</span>';
			}
			if ( $duration ) {
				echo '<span class="smartlearn-lesson-duration">' . esc_html( $duration ) . '</span>';
			}
			echo '</div>';
			
			echo '</div>';
		}
		
		echo '</div>';
	}
	
	/**
	 * Відобразити відео урок
	 *
	 * @param int $lesson_id
	 */
	public static function display_lesson_video( $lesson_id ) {
		$video_url = get_post_meta( $lesson_id, '_smartlearn_lesson_video_url', true );
		
		if ( ! $video_url ) {
			return;
		}
		
		// Перевірити чи це YouTube або Vimeo
		if ( preg_match( '/youtube\.com|youtu\.be/', $video_url ) ) {
			// YouTube
			preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $video_url, $matches );
			if ( isset( $matches[1] ) ) {
				$video_id = $matches[1];
				echo '<div class="smartlearn-lesson-video youtube">';
				echo '<iframe width="100%" height="500" src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
				echo '</div>';
			}
		} elseif ( preg_match( '/vimeo\.com/', $video_url ) ) {
			// Vimeo
			preg_match( '/vimeo\.com\/(\d+)/', $video_url, $matches );
			if ( isset( $matches[1] ) ) {
				$video_id = $matches[1];
				echo '<div class="smartlearn-lesson-video vimeo">';
				echo '<iframe src="https://player.vimeo.com/video/' . esc_attr( $video_id ) . '" width="100%" height="500" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
				echo '</div>';
			}
		} else {
			// HTML5 video
			echo '<div class="smartlearn-lesson-video html5">';
			echo '<video width="100%" height="500" controls>';
			echo '<source src="' . esc_url( $video_url ) . '">';
			echo __( 'Ваш браузер не підтримує відео.', 'smartlearn-lms' );
			echo '</video>';
			echo '</div>';
		}
	}
	
	/**
	 * Відобразити мета-інформацію курсу
	 *
	 * @param int $course_id
	 */
	public static function display_course_meta( $course_id ) {
		$duration = get_post_meta( $course_id, '_smartlearn_course_duration', true );
		$level = get_post_meta( $course_id, '_smartlearn_course_level', true );
		$lessons = self::get_course_lessons( $course_id );
		$lessons_count = count( $lessons );
		
		if ( ! $duration && ! $level && ! $lessons_count ) {
			return;
		}
		
		echo '<div class="smartlearn-course-meta">';
		
		if ( $level ) {
			$level_labels = array(
				'beginner' => __( 'Початковий', 'smartlearn-lms' ),
				'intermediate' => __( 'Середній', 'smartlearn-lms' ),
				'advanced' => __( 'Просунутий', 'smartlearn-lms' ),
			);
			$level_label = isset( $level_labels[ $level ] ) ? $level_labels[ $level ] : $level;
			
			echo '<div class="smartlearn-course-meta-item level">';
			echo '<span class="label">' . __( 'Рівень:', 'smartlearn-lms' ) . '</span> ';
			echo '<span class="value">' . esc_html( $level_label ) . '</span>';
			echo '</div>';
		}
		
		if ( $duration ) {
			echo '<div class="smartlearn-course-meta-item duration">';
			echo '<span class="label">' . __( 'Тривалість:', 'smartlearn-lms' ) . '</span> ';
			echo '<span class="value">' . esc_html( $duration ) . '</span>';
			echo '</div>';
		}
		
		if ( $lessons_count ) {
			echo '<div class="smartlearn-course-meta-item lessons">';
			echo '<span class="label">' . __( 'Уроків:', 'smartlearn-lms' ) . '</span> ';
			echo '<span class="value">' . esc_html( $lessons_count ) . '</span>';
			echo '</div>';
		}
		
		echo '</div>';
	}
}
