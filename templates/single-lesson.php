<?php
/**
 * Template для відображення одного уроку (copy)
 */

get_header();

$lesson_id = get_the_ID();
$user_id = get_current_user_id();
$has_access = SmartLearn_LMS_Access_Control::user_has_lesson_access( $lesson_id, $user_id );
$course_id = get_post_meta( $lesson_id, '_smartlearn_lesson_course_id', true );
$duration = get_post_meta( $lesson_id, '_smartlearn_lesson_duration', true );
?>

<div class="smartlearn-lesson-single">
    
    <?php while ( have_posts() ) : the_post(); ?>
        
        <article id="lesson-<?php the_ID(); ?>" <?php post_class( '-lesson' ); ?>>
            
            <?php if ( $course_id ) : ?>
                <div class="smartlearn-lesson-breadcrumb">
                    <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="back-to-course">
                        ← <?php echo esc_html( get_the_title( $course_id ) ); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <header class="smartlearn-lesson-header">
                <h1 class="smartlearn-lesson-title"><?php the_title(); ?></h1>
                
                <?php if ( $duration ) : ?>
                    <div class="smartlearn-lesson-meta">
                        <span class="lesson-duration"><?php echo esc_html( $duration ); ?></span>
                    </div>
                <?php endif; ?>
            </header>
            
            <?php if ( $has_access ) : ?>
                
                <?php SmartLearn_LMS_Templates::display_lesson_video( $lesson_id ); ?>
                
                <div class="smartlearn-lesson-content">
                    <?php the_content(); ?>
                </div>
                
                <?php if ( $course_id ) : ?>
                    <div class="smartlearn-lesson-navigation">
                        <?php
                        $lessons = SmartLearn_LMS_Templates::get_course_lessons( $course_id );
                        $current_index = -1;
                        foreach ( $lessons as $index => $lesson ) {
                            if ( $lesson->ID == $lesson_id ) {
                                $current_index = $index;
                                break;
                            }
                        }
                        if ( $current_index > 0 ) {
                            $prev_lesson = $lessons[ $current_index - 1 ];
                            echo '<a href="' . esc_url( get_permalink( $prev_lesson->ID ) ) . '" class="prev-lesson button">';
                            echo '← ' . esc_html( $prev_lesson->post_title );
                            echo '</a>';
                        }
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
                
                <div class="smartlearn-lesson-locked">
                    <?php
                    $logged_in = is_user_logged_in();
                    if ( ! $logged_in ) {
                        $message = __( 'Для перегляду цього уроку необхідно авторизуватися.', 'smartlearn-lms' );
                        $button_text = get_option( 'smartlearn_lms_button_text_login', __( 'Увійти', 'smartlearn-lms' ) );
                        $login_url = trim( (string) get_option( 'smartlearn_lms_login_url', '' ) );
                        if ( empty( $login_url ) && function_exists( 'wc_get_page_permalink' ) ) {
                            $login_url = wc_get_page_permalink( 'myaccount' );
                        }
                        if ( empty( $login_url ) ) {
                            $login_url = wp_login_url( get_permalink( $lesson_id ) );
                        }
                        $button_url = $login_url;
                    } else {
                        $message = __( 'Для перегляду цього уроку необхідно придбати курс.', 'smartlearn-lms' );
                        $button_text = get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) );
                        $button_url = '';
                        if ( $course_id ) {
                            $button_url = SmartLearn_LMS_Access_Control::get_course_purchase_url( $course_id );
                        }
                    }
                    ?>

                    <div class="smartlearn-access-denied">
                        <h3><?php echo esc_html( $message ); ?></h3>
                        <?php if ( ! empty( $button_url ) ) : ?>
                            <p><a class="smartlearn-access-button" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </article>
        
    <?php endwhile; ?>
    
</div>

<?php get_footer(); ?>
