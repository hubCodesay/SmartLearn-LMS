<?php
/**
 * Template для відображення одного уроку
 */

get_header();

$lesson_id = get_the_ID();
$user_id = get_current_user_id();
$has_access = SmartLearn_LMS_Access_Control::user_has_lesson_access( $lesson_id, $user_id );
$course_id = get_post_meta( $lesson_id, '_smartlearn_lesson_course_id', true );
$video_url = get_post_meta( $lesson_id, '_smartlearn_lesson_video_url', true );
$duration = get_post_meta( $lesson_id, '_smartlearn_lesson_duration', true );
?>

<div class="-lesson-single">
	
	<?php while ( have_posts() ) : the_post(); ?>
		
		<article id="lesson-<?php the_ID(); ?>" <?php post_class( '-lesson' ); ?>>
			
			<?php if ( $course_id ) : ?>
				<div class="-lesson-breadcrumb">
					<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="back-to-course">
						← <?php echo esc_html( get_the_title( $course_id ) ); ?>
					</a>
				</div>
			<?php endif; ?>
			
			<header class="-lesson-header">
				<h1 class="-lesson-title"><?php the_title(); ?></h1>
				
				<?php if ( $duration ) : ?>
					<div class="-lesson-meta">
						<span class="lesson-duration"><?php echo esc_html( $duration ); ?></span>
					</div>
				<?php endif; ?>
			</header>
			
			<?php if ( $has_access ) : ?>
				
				<?php if ( $video_url ) : ?>
					<div class="-lesson-video-wrap">
						<?php SmartLearn_LMS_Templates::display_lesson_video( $lesson_id ); ?>
					</div>
				<?php endif; ?>
				
				<div class="-lesson-content">
					<?php the_content(); ?>
				</div>
				
				<?php if ( $course_id ) : ?>
					<div class="-lesson-navigation">
						<?php
						// Отримати всі уроки курсу
						$lessons = SmartLearn_LMS_Templates::get_course_lessons( $course_id );
						$current_index = -1;
						
						// Знайти індекс поточного уроку
						foreach ( $lessons as $index => $lesson ) {
							if ( $lesson->ID == $lesson_id ) {
								$current_index = $index;
								break;
							}
						}
						
						// Попередній урок
						if ( $current_index > 0 ) {
							$prev_lesson = $lessons[ $current_index - 1 ];
							echo '<a href="' . esc_url( get_permalink( $prev_lesson->ID ) ) . '" class="prev-lesson button">';
							echo '← ' . esc_html( $prev_lesson->post_title );
							echo '</a>';
						}
						
						// Наступний урок
						if ( $current_index >= 0 && $current_index < count( $lessons ) - 1 ) {
							$next_lesson = $lessons[ $current_index + 1 ];
							echo '<a href="' . esc_url( get_permalink( $next_lesson->ID ) ) . '" class="next-lesson button">';
							echo esc_html( $next_lesson->post_title ) . ' →';
							echo '</a>';
						}
						?>
					</div>
				<?php endif; ?>
				
			<?php else : ?>
				
				<div class="-lesson-locked">
					<?php echo SmartLearn_LMS_Access_Control::get_access_denied_message( $course_id ); ?>
				</div>
				
			<?php endif; ?>
			
		</article>
		
	<?php endwhile; ?>
	
</div>

<?php get_footer(); ?>
