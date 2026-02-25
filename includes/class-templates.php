<?php
/**
 * Templates - шаблони для відображення курсів та уроків (copy)
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
     * Get login / my-account URL.
     * Prefers plugin option, then WooCommerce My Account, then wp_login_url.
     */
    private static function get_login_url( $redirect_url = '' ) {
        $login_url = trim( (string) get_option( 'smartlearn_lms_login_url', '' ) );
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
    private static function get_course_purchase_url( $course_id ) {
        $product_id = get_post_meta( $course_id, '_smartlearn_course_product_id', true );
        $product_id = $product_id ? absint( $product_id ) : 0;
        if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
            return '';
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '';
        }
        return $product->get_permalink();
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
     */
    public static function get_course_lessons( $course_id, $only_free = false ) {
        $args = array(
            'post_type' => 'smartlearn_lesson',
            'posts_per_page' => -1,
            'meta_key' => '_smartlearn_lesson_course_id',
            'meta_value' => $course_id,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        );

        if ( $only_free ) {
            $args['meta_query'] = array(
                array(
                    'key' => '_smartlearn_lesson_is_free',
                    'value' => '1',
                    'compare' => '=',
                ),
            );
        }

        $lessons = get_posts( $args );
        return $lessons;
    }
    
    /**
     * Відобразити список уроків курсу
     */
    public static function display_course_lessons( $course_id, $only_free = false ) {
        $lessons = self::get_course_lessons( $course_id, $only_free );
        $user_id = get_current_user_id();
        $has_course_access = SmartLearn_LMS_Access_Control::user_has_course_access( $course_id, $user_id );
        
        if ( empty( $lessons ) ) {
            echo '<p>' . __( 'Уроків поки немає.', 'smartlearn-lms' ) . '</p>';
            return;
        }

        // Single CTA block when course is locked (guest -> login; logged-in -> buy)
        if ( ! $has_course_access ) {
            if ( ! $user_id ) {
                $message = __( 'Щоб відкрити уроки, потрібно увійти.', 'smartlearn-lms' );
                $button_text = get_option( 'smartlearn_lms_button_text_login', __( 'Увійти', 'smartlearn-lms' ) );
                $button_url = self::get_login_url( get_permalink( $course_id ) );
            } else {
                $message = __( 'Щоб відкрити уроки, потрібно придбати курс.', 'smartlearn-lms' );
                $button_text = get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) );
                $button_url = self::get_course_purchase_url( $course_id );
            }

            echo '<div class="smartlearn-course-locked">';
            echo '<h3>' . esc_html( $message ) . '</h3>';
            if ( ! empty( $button_url ) ) {
                echo '<p><a class="smartlearn-access-button" href="' . esc_url( $button_url ) . '">' . esc_html( $button_text ) . '</a></p>';
            }
            echo '</div>';
        }
        
        echo '<div class="smartlearn-lessons-list">';
		
        foreach ( $lessons as $lesson ) {
            $has_access = SmartLearn_LMS_Access_Control::user_has_lesson_access( $lesson->ID, $user_id );
            $is_free = get_post_meta( $lesson->ID, '_smartlearn_lesson_is_free', true ) === '1';
            $duration = get_post_meta( $lesson->ID, '_smartlearn_lesson_duration', true );

            $classes = array( 'smartlearn-lesson-item' );
            if ( $has_access ) {
                $classes[] = 'has-access';
            } else {
                $classes[] = 'no-access';
            }
            if ( $is_free ) {
                $classes[] = 'is-free';
            }

            echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
            echo '<div class="smartlearn-lesson-content">';

            // Назва (урок відкривається тільки якщо є доступ)
            $title_classes = 'smartlearn-lesson-title';
            if ( ! $has_access ) {
                $title_classes .= ' smartlearn-lesson-locked';
            }
			if ( $has_access ) {
				echo '<a href="' . esc_url( get_permalink( $lesson->ID ) ) . '" class="' . esc_attr( $title_classes ) . '">';
				echo esc_html( $lesson->post_title );
				echo '</a>';
			} else {
				echo '<span class="' . esc_attr( $title_classes ) . '">' . esc_html( $lesson->post_title ) . '</span>';
			}

            if ( $duration ) {
                echo '<div class="smartlearn-lesson-meta">';
                echo '<span class="smartlearn-lesson-duration">' . esc_html( $duration ) . '</span>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Відобразити відео/медіа уроку (підтримка декількох елементів: URL, Upload, Text)
     * Виводить заголовок та опис, потім кожен елемент у відповідному форматі.
     */
    public static function display_lesson_video( $lesson_id ) {
        $media = get_post_meta( $lesson_id, '_smartlearn_lesson_media', true );
        if ( ! is_array( $media ) || empty( $media ) ) {
            // fallback to legacy URLs
            $video_urls = get_post_meta( $lesson_id, '_smartlearn_lesson_video_urls', true );
            if ( is_array( $video_urls ) && ! empty( $video_urls ) ) {
                $media = array();
                foreach ( $video_urls as $u ) {
                    if ( $u ) {
                        $media[] = array( 'type' => 'url', 'value' => $u );
                    }
                }
            } else {
                $single = get_post_meta( $lesson_id, '_smartlearn_lesson_video_url', true );
                if ( $single ) {
                    $media = array( array( 'type' => 'url', 'value' => $single ) );
                }
            }
        }

        if ( ! is_array( $media ) || empty( $media ) ) {
            return;
        }

        // Wrapper for media items (heading/description removed per request)
        echo '<div class="smartlearn-lesson-videos">';

        foreach ( $media as $item ) {
            if ( ! isset( $item['type'] ) || ! isset( $item['value'] ) ) {
                continue;
            }
            $type = $item['type'];
            $value = $item['value'];

            if ( 'url' === $type && $value ) {
                $video_url = $value;
                if ( preg_match( '/youtube\.com|youtu\.be/', $video_url ) ) {
                    preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $video_url, $matches );
                    if ( isset( $matches[1] ) ) {
                        $video_id = $matches[1];
                        $unique_id = 'yt-' . uniqid();
                        echo '<style>
                            .smartlearn-lesson-video.youtube_' . esc_attr($unique_id) . ' { position:relative; width: 100%; border-radius: 8px; overflow: hidden; background: #000; }
                            @media (min-width: 768px) { .smartlearn-lesson-video.youtube_' . esc_attr($unique_id) . ' { height: 500px; } }
                            @media (max-width: 767px) { .smartlearn-lesson-video.youtube_' . esc_attr($unique_id) . ' { height: 0; padding-bottom: 56.25%; /* 16:9 aspect ratio */ } }
                        </style>';
                        echo '<div class="smartlearn-lesson-video youtube_' . esc_attr($unique_id) . '">';
                        
                        // Iframe with pointer-events:none so user CANNOT interact with Youtube directly (no clicks on logos, titles, etc)
                        // Added 'playlist' parameter pointing to the same video to defeat the standard "More Videos" grid on pause/end
                        echo '<iframe id="' . esc_attr($unique_id) . '" width="100%" height="100%" src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '?rel=0&modestbranding=1&controls=0&showinfo=0&iv_load_policy=3&disablekb=1&fs=0&enablejsapi=1&playsinline=1&playlist=' . esc_attr( $video_id ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:1; pointer-events:none;"></iframe>';
                        
                        // Custom Overlay & Controls
                        echo '<div id="overlay-' . esc_attr($unique_id) . '" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:10; cursor:pointer; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.1); transition: 0.3s;">';
                        echo '<div class="sl-play-btn" style="width:70px; height:70px; background:rgba(0,0,0,0.7); border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.3s;">';
                        echo '<svg width="32" height="32" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M8 5V19L19 12L8 5Z"/></svg>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                        // Script to handle play/pause via YouTube API
                        ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                var iframe = document.getElementById("<?php echo esc_attr($unique_id); ?>");
                                var overlay = document.getElementById("overlay-<?php echo esc_attr($unique_id); ?>");
                                var playBtn = overlay.querySelector('.sl-play-btn');
                                var isPlaying = false;

                                overlay.addEventListener("click", function() {
                                    if (isPlaying) {
                                        iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                                        playBtn.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M8 5V19L19 12L8 5Z"/></svg>';
                                        isPlaying = false;
                                        playBtn.style.opacity = '1';
                                        overlay.style.background = 'rgba(0,0,0,0.1)';
                                    } else {
                                        iframe.contentWindow.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
                                        playBtn.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M6 19H10V5H6V19ZM14 5V19H18V5H14Z"/></svg>';
                                        isPlaying = true;
                                        playBtn.style.opacity = '0'; 
                                        overlay.style.background = 'transparent';
                                    }
                                });
                                
                                overlay.addEventListener("mouseenter", function() {
                                    if(isPlaying) playBtn.style.opacity = '1';
                                });
                                overlay.addEventListener("mouseleave", function() {
                                    if(isPlaying) playBtn.style.opacity = '0';
                                });
                            });
                        </script>
                        <?php
                    }
                } elseif ( preg_match( '/vimeo\.com/', $video_url ) ) {
                    preg_match( '/vimeo\.com\/(\d+)/', $video_url, $matches );
                    if ( isset( $matches[1] ) ) {
                        $video_id = $matches[1];
                        echo '<div class="smartlearn-lesson-video vimeo">';
                        echo '<iframe src="https://player.vimeo.com/video/' . esc_attr( $video_id ) . '" width="100%" height="500" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
                        echo '</div>';
                    }
                } else {
                    // HTML5 fallback
                    echo '<div class="smartlearn-lesson-video html5">';
                    echo '<video width="100%" height="500" controls>';
                    echo '<source src="' . esc_url( $video_url ) . '">';
                    echo esc_html__( 'Ваш браузер не підтримує відео.', 'smartlearn-lms' );
                    echo '</video>';
                    echo '</div>';
                }

            } elseif ( 'upload' === $type && $value ) {
                $attachment_id = intval( $value );
                $src = wp_get_attachment_url( $attachment_id );
                if ( $src ) {
                    echo '<div class="smartlearn-lesson-video upload">';
                    echo '<video width="100%" height="500" controls>';
                    echo '<source src="' . esc_url( $src ) . '">';
                    echo esc_html__( 'Ваш браузер не підтримує відео.', 'smartlearn-lms' );
                    echo '</video>';
                    echo '</div>';
                }

            } elseif ( 'text' === $type && $value ) {
                echo '<div class="smartlearn-lesson-text">' . wp_kses_post( wpautop( $value ) ) . '</div>';
            }
        }

        echo '</div>';
    }
    
    /**
     * Відобразити мета-інформацію курсу
     */
    public static function display_course_meta( $course_id ) {
        $duration = get_post_meta( $course_id, '_smartlearn_course_duration', true );
        $level = get_post_meta( $course_id, '_smartlearn_course_level', true );
        $custom_author_name = trim( (string) get_post_meta( $course_id, '_smartlearn_course_instructor_name', true ) );
        $author_id = (int) get_post_field( 'post_author', $course_id );
        $fallback_author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
        $author_name = '' !== $custom_author_name ? $custom_author_name : $fallback_author_name;
        $lessons = self::get_course_lessons( $course_id );
        $lessons_count = count( $lessons );
        
        if ( ! $duration && ! $level && ! $lessons_count && ! $author_name ) {
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

        if ( $author_name ) {
            echo '<div class="smartlearn-course-meta-item author">';
            echo '<span class="label">' . __( 'Автор курсу:', 'smartlearn-lms' ) . '</span> ';
            echo '<span class="value">' . esc_html( $author_name ) . '</span>';
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
