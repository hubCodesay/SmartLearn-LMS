<?php
/**
 * Template для відображення одного курсу
 */

get_header();

$course_id = get_the_ID();
$user_id = get_current_user_id();
$has_access = SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id );
?>

<div class="-course-single">
	
	<?php while ( have_posts() ) : the_post(); ?>
		
		<article id="course-<?php the_ID(); ?>" <?php post_class( '-course' ); ?>>
			
			<header class="-course-header">
				<h1 class="-course-title"><?php the_title(); ?></h1>
				
				<?php SmartLearn_LMS_Templates::display_course_meta( $course_id ); ?>
				
				<?php
				// Кнопка доступу
				if ( ! $has_access ) {
					echo '<div class="-course-access-button">';
					echo SmartLearn_LMS_Access_Control::get_course_access_button( $course_id );
					echo '</div>';
				}
				?>
			</header>
			
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="-course-thumbnail">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>
			
			<div class="-course-description">
				<?php the_content(); ?>
			</div>
			
			<?php if ( $has_access ) : ?>
				
				<div class="-course-lessons-section">
					<h2><?php _e( 'Уроки курсу', 'smartlearn-lms' ); ?></h2>
					<?php SmartLearn_LMS_Templates::display_course_lessons( $course_id ); ?>
				</div>
				
			<?php else : ?>
				
				<div class="-course-locked">
					<?php echo SmartLearn_LMS_Access_Control::get_access_denied_message( $course_id ); ?>
				</div>
				
			<?php endif; ?>
			
		</article>
		
	<?php endwhile; ?>
	
</div>

<?php get_footer(); ?>
