<?php
/**
 * Register Custom Post Types for Courses and Lessons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Post_Types {
	
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}
	
	/**
	 * Register Course and Lesson post types
	 */
	public function register_post_types() {
		
		// Register Course CPT
		$course_labels = array(
			'name'                  => _x( 'Курси', 'Post Type General Name', 'smartlearn-lms' ),
			'singular_name'         => _x( 'Курс', 'Post Type Singular Name', 'smartlearn-lms' ),
			'menu_name'             => __( 'Курси', 'smartlearn-lms' ),
			'name_admin_bar'        => __( 'Курс', 'smartlearn-lms' ),
			'add_new'               => __( 'Додати новий', 'smartlearn-lms' ),
			'add_new_item'          => __( 'Додати новий курс', 'smartlearn-lms' ),
			'new_item'              => __( 'Новий курс', 'smartlearn-lms' ),
			'edit_item'             => __( 'Редагувати курс', 'smartlearn-lms' ),
			'view_item'             => __( 'Переглянути курс', 'smartlearn-lms' ),
			'all_items'             => __( 'Всі курси', 'smartlearn-lms' ),
			'search_items'          => __( 'Шукати курси', 'smartlearn-lms' ),
			'not_found'             => __( 'Курсів не знайдено', 'smartlearn-lms' ),
			'not_found_in_trash'    => __( 'Курсів не знайдено в кошику', 'smartlearn-lms' ),
		);
		
		$course_args = array(
			'labels'                => $course_labels,
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_icon'             => 'dashicons-welcome-learn-more',
			'menu_position'         => 25,
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'course' ),
			'capability_type'       => 'post',
			'has_archive'           => true,
			'hierarchical'          => false,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ),
			'show_in_rest'          => true, // Gutenberg support
			'rest_base'             => 'courses',
		);
		
		register_post_type( 'smartlearn_course', $course_args );
		
		// Register Lesson CPT
		$lesson_labels = array(
			'name'                  => _x( 'Уроки', 'Post Type General Name', 'smartlearn-lms' ),
			'singular_name'         => _x( 'Урок', 'Post Type Singular Name', 'smartlearn-lms' ),
			'menu_name'             => __( 'Уроки', 'smartlearn-lms' ),
			'name_admin_bar'        => __( 'Урок', 'smartlearn-lms' ),
			'add_new'               => __( 'Додати новий', 'smartlearn-lms' ),
			'add_new_item'          => __( 'Додати новий урок', 'smartlearn-lms' ),
			'new_item'              => __( 'Новий урок', 'smartlearn-lms' ),
			'edit_item'             => __( 'Редагувати урок', 'smartlearn-lms' ),
			'view_item'             => __( 'Переглянути урок', 'smartlearn-lms' ),
			'all_items'             => __( 'Всі уроки', 'smartlearn-lms' ),
			'search_items'          => __( 'Шукати уроки', 'smartlearn-lms' ),
			'not_found'             => __( 'Уроків не знайдено', 'smartlearn-lms' ),
			'not_found_in_trash'    => __( 'Уроків не знайдено в кошику', 'smartlearn-lms' ),
		);
		
		$lesson_args = array(
			'labels'                => $lesson_labels,
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => 'edit.php?post_type=smartlearn_course',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'lesson' ),
			'capability_type'       => 'post',
			'has_archive'           => false,
			'hierarchical'          => false,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields', 'page-attributes' ),
			'show_in_rest'          => true, // Gutenberg support
			'rest_base'             => 'lessons',
		);
		
		register_post_type( 'smartlearn_lesson', $lesson_args );
	}
	
	/**
	 * Register taxonomies for courses
	 */
	public function register_taxonomies() {
		
		// Course Category
		$cat_labels = array(
			'name'              => _x( 'Категорії курсів', 'taxonomy general name', 'smartlearn-lms' ),
			'singular_name'     => _x( 'Категорія курсу', 'taxonomy singular name', 'smartlearn-lms' ),
			'search_items'      => __( 'Шукати категорії', 'smartlearn-lms' ),
			'all_items'         => __( 'Всі категорії', 'smartlearn-lms' ),
			'parent_item'       => __( 'Батьківська категорія', 'smartlearn-lms' ),
			'parent_item_colon' => __( 'Батьківська категорія:', 'smartlearn-lms' ),
			'edit_item'         => __( 'Редагувати категорію', 'smartlearn-lms' ),
			'update_item'       => __( 'Оновити категорію', 'smartlearn-lms' ),
			'add_new_item'      => __( 'Додати нову категорію', 'smartlearn-lms' ),
			'new_item_name'     => __( 'Назва нової категорії', 'smartlearn-lms' ),
			'menu_name'         => __( 'Категорії', 'smartlearn-lms' ),
		);
		
		register_taxonomy(
			'course_category',
			array( 'smartlearn_course' ),
			array(
				'hierarchical'      => true,
				'labels'            => $cat_labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'course-category' ),
				'show_in_rest'      => true,
			)
		);
	}
}
