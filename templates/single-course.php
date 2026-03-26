<?php
/**
 * Template for displaying a single course (copy)
 */

get_header();

$course_id = get_the_ID();
$user_id   = get_current_user_id();
$has_access = SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id );
?>

<div class="smartlearn-course-single">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="course-<?php the_ID(); ?>" <?php post_class( 'smartlearn-course' ); ?>>
			<header class="smartlearn-course-header">
				<h1 class="smartlearn-course-title"><?php the_title(); ?></h1>
				<?php SmartLearn_LMS_Templates::display_course_meta( $course_id ); ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="smartlearn-course-thumbnail">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<div class="smartlearn-course-description">
				<?php the_content(); ?>
			</div>

			<section class="smartlearn-course-lessons-section">
				<h2><?php echo esc_html__( 'Уроки', 'smartlearn-lms' ); ?></h2>
				<?php SmartLearn_LMS_Templates::display_course_lessons( $course_id ); ?>
				<?php
				$lessons = SmartLearn_LMS_Templates::get_course_lessons( $course_id );
				$lessons_count = is_array( $lessons ) ? count( $lessons ) : 0;
				?>
				<div class="smartlearn-course-lessons-count">
					<?php echo esc_html( sprintf( __( 'Уроків у курсі: %d', 'smartlearn-lms' ), $lessons_count ) ); ?>
				</div>
			</section>
		</article>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
