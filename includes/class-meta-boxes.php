<?php
/**
 * Meta Boxes for Courses and Lessons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Meta_Boxes {
	
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_course_meta' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_lesson_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}
	
	public function enqueue_admin_scripts( $hook ) {
		global $post_type;
		
		if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && in_array( $post_type, array( 'smartlearn_course', 'smartlearn_lesson' ) ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_style( '-lms-admin', SMARTLEARN_LMS_URL . 'assets/css/admin.css', array(), SMARTLEARN_LMS_VERSION );
		}
	}
	
	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		
		// Course meta box
		add_meta_box(
			'smartlearn_course_settings',
			__( 'Налаштування курсу', 'smartlearn-lms' ),
			array( $this, 'render_course_meta_box' ),
			'smartlearn_course',
			'normal',
			'high'
		);
		
		// Lesson meta box
		add_meta_box(
			'smartlearn_lesson_settings',
			__( 'Налаштування уроку', 'smartlearn-lms' ),
			array( $this, 'render_lesson_meta_box' ),
			'smartlearn_lesson',
			'normal',
			'high'
		);
		
		// Course lessons meta box
		add_meta_box(
			'smartlearn_course_lessons',
			__( 'Уроки курсу', 'smartlearn-lms' ),
			array( $this, 'render_course_lessons_meta_box' ),
			'smartlearn_course',
			'side',
			'default'
		);

		// Course access stats meta box
		add_meta_box(
			'smartlearn_course_access_stats',
			__( 'Доступ: покупки та закінчення', 'smartlearn-lms' ),
			array( $this, 'render_course_access_stats_meta_box' ),
			'smartlearn_course',
			'side',
			'default'
		);
	}

	/**
	 * Render course access stats meta box.
	 */
	public function render_course_access_stats_meta_box( $post ) {
		if ( ! function_exists( 'WC' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce не активний.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		$product_id = absint( get_post_meta( $post->ID, '_smartlearn_course_product_id', true ) );
		$is_free = get_post_meta( $post->ID, '_smartlearn_course_is_free', true ) === '1';

		if ( $is_free ) {
			echo '<p>' . esc_html__( 'Курс безкоштовний — доступ без обмежень.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		if ( ! $product_id ) {
			echo '<p>' . esc_html__( 'Не прив’язано товар WooCommerce. Доступ буде закритий для всіх.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		$duration_value = absint( get_post_meta( $post->ID, '_smartlearn_course_access_duration_value', true ) );
		$duration_unit = get_post_meta( $post->ID, '_smartlearn_course_access_duration_unit', true );
		if ( ! in_array( $duration_unit, array( 'days', 'months' ), true ) ) {
			$duration_unit = 'days';
		}
		$duration_label = $duration_value > 0
			? sprintf( '%d %s', $duration_value, ( 'months' === $duration_unit ? __( 'місяців', 'smartlearn-lms' ) : __( 'днів', 'smartlearn-lms' ) ) )
			: __( 'без обмеження', 'smartlearn-lms' );
		echo '<p><strong>' . esc_html__( 'Термін доступу:', 'smartlearn-lms' ) . '</strong> ' . esc_html( $duration_label ) . '</p>';

		$rows = $this->get_course_access_stats_rows( $post->ID, $product_id, $duration_value, $duration_unit );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Покупок цього курсу ще не знайдено.', 'smartlearn-lms' ) . '</p>';
			return;
		}

		echo '<div style="max-height:280px;overflow:auto;border:1px solid #e5e5e5;border-radius:6px;">';
		echo '<table class="widefat striped" style="margin:0;border:0;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Клієнт', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Купив', 'smartlearn-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'До', 'smartlearn-lms' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$name = $row['name'] ? $row['name'] : $row['email'];
			$purchased_at = $row['purchased_at'] ? wp_date( 'd.m.Y', $row['purchased_at'] ) : '—';
			$expires_at = $row['expires_at'] ? wp_date( 'd.m.Y', $row['expires_at'] ) : '∞';
			$status = $row['is_expired'] ? __( 'закінчився', 'smartlearn-lms' ) : __( 'активний', 'smartlearn-lms' );
			$status_color = $row['is_expired'] ? '#b32d2e' : '#1d7f2f';
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '<br/><small style="color:#666;">' . esc_html( $status ) . '</small></td>';
			echo '<td><span style="white-space:nowrap;">' . esc_html( $purchased_at ) . '</span></td>';
			echo '<td><span style="white-space:nowrap;color:' . esc_attr( $status_color ) . ';">' . esc_html( $expires_at ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Показано останню дату покупки по кожному клієнту. Після закінчення терміну доступ блокується автоматично.', 'smartlearn-lms' ) . '</p>';
	}

	private function get_course_access_stats_rows( $course_id, $product_id, $duration_value, $duration_unit ) {
		$cache_key = 'smartlearn_access_stats_' . absint( $course_id ) . '_' . absint( $product_id ) . '_' . absint( $duration_value ) . '_' . sanitize_key( $duration_unit );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$order_items = $wpdb->prefix . 'woocommerce_order_items';
		$item_meta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$posts = $wpdb->posts;

		// Only completed/processing orders.
		$statuses = array( 'wc-completed', 'wc-processing' );
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "
			SELECT DISTINCT oi.order_id
			FROM {$order_items} oi
			INNER JOIN {$item_meta} oim ON oi.order_item_id = oim.order_item_id
			INNER JOIN {$posts} p ON p.ID = oi.order_id
			WHERE oi.order_item_type = 'line_item'
			AND p.post_type = 'shop_order'
			AND p.post_status IN ({$placeholders})
			AND oim.meta_key IN ('_product_id','_variation_id')
			AND oim.meta_value = %d
			ORDER BY oi.order_id DESC
			LIMIT 2000
		";

		$params = array_merge( $statuses, array( absint( $product_id ) ) );
		$order_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

		if ( empty( $order_ids ) ) {
			set_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
			return array();
		}

		$orders = wc_get_orders(
			array(
				'include' => array_map( 'absint', $order_ids ),
				'limit'   => -1,
				'return'  => 'objects',
			)
		);

		$customers = array();
		foreach ( $orders as $order ) {
			/** @var WC_Order $order */
			$customer_id = (int) $order->get_customer_id();
			$email = sanitize_email( $order->get_billing_email() );
			$key = $customer_id ? 'u:' . $customer_id : ( $email ? 'e:' . $email : '' );
			if ( ! $key ) {
				continue;
			}

			$found = false;
			foreach ( $order->get_items() as $item ) {
				$item_product_id = (int) $item->get_product_id();
				$item_variation_id = (int) $item->get_variation_id();
				if ( $item_product_id === (int) $product_id || $item_variation_id === (int) $product_id ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				continue;
			}

			$date = $order->get_date_paid();
			if ( ! $date ) {
				$date = $order->get_date_completed();
			}
			if ( ! $date ) {
				$date = $order->get_date_created();
			}
			$purchase_ts = $date ? (int) $date->getTimestamp() : 0;
			if ( ! $purchase_ts ) {
				continue;
			}

			if ( ! isset( $customers[ $key ] ) || $purchase_ts > $customers[ $key ]['purchased_at'] ) {
				$name = '';
				if ( $customer_id ) {
					$user = get_user_by( 'id', $customer_id );
					if ( $user ) {
						$name = trim( $user->display_name );
						if ( ! $email ) {
							$email = sanitize_email( $user->user_email );
						}
					}
				}
				if ( ! $name ) {
					$name = trim( $order->get_formatted_billing_full_name() );
				}

				$customers[ $key ] = array(
					'user_id'     => $customer_id,
					'email'       => $email,
					'name'        => $name,
					'purchased_at' => $purchase_ts,
				);
			}
		}

		$rows = array();
		foreach ( $customers as $customer ) {
			$expires_at = 0;
			if ( class_exists( 'SmartLearn_LMS_Access_Control' ) ) {
				$expires_at = SmartLearn_LMS_Access_Control::calculate_course_access_expires_at( $course_id, $customer['purchased_at'] );
			}
			$is_expired = $expires_at ? ( current_time( 'timestamp' ) >= $expires_at ) : false;
			$rows[] = array(
				'user_id'     => $customer['user_id'],
				'email'       => $customer['email'],
				'name'        => $customer['name'],
				'purchased_at' => $customer['purchased_at'],
				'expires_at'   => $expires_at,
				'is_expired'   => $is_expired,
			);
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return (int) $b['purchased_at'] <=> (int) $a['purchased_at'];
			}
		);

		set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );
		return $rows;
	}
	
	/**
	 * Render course meta box
	 */
	public function render_course_meta_box( $post ) {
		wp_nonce_field( 'smartlearn_course_meta', 'smartlearn_course_meta_nonce' );
		
		$product_id = get_post_meta( $post->ID, '_smartlearn_course_product_id', true );
		$is_free = get_post_meta( $post->ID, '_smartlearn_course_is_free', true );
		$access_duration_value = absint( get_post_meta( $post->ID, '_smartlearn_course_access_duration_value', true ) );
		$access_duration_unit = get_post_meta( $post->ID, '_smartlearn_course_access_duration_unit', true );
		if ( ! in_array( $access_duration_unit, array( 'days', 'months' ), true ) ) {
			$access_duration_unit = 'days';
		}
		
		?>
		<div class="-meta-box">
			<p>
				<label>
					<input type="checkbox" name="smartlearn_course_is_free" value="1" <?php checked( $is_free, '1' ); ?> />
					<?php esc_html_e( 'Безкоштовний курс (доступний всім користувачам)', 'smartlearn-lms' ); ?>
				</label>
			</p>
			
			<p class="course-product-field" style="<?php echo $is_free ? 'display:none;' : ''; ?>">
				<label for="smartlearn_course_product_id">
					<strong><?php esc_html_e( 'Товар WooCommerce для доступу:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<select name="smartlearn_course_product_id" id="smartlearn_course_product_id" style="width:100%;max-width:400px;">
					<option value=""><?php esc_html_e( '— Виберіть товар —', 'smartlearn-lms' ); ?></option>
					<?php
					$products = wc_get_products( array(
						'limit' => -1,
						'status' => 'publish',
						'orderby' => 'title',
						'order' => 'ASC',
					) );
					
					foreach ( $products as $product ) {
						printf(
							'<option value="%d" %s>%s (#%d)</option>',
							$product->get_id(),
							selected( $product_id, $product->get_id(), false ),
							esc_html( $product->get_name() ),
							$product->get_id()
						);
					}
					?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Користувачі, які купили цей товар, отримають доступ до курсу.', 'smartlearn-lms' ); ?>
				</p>
			</p>

			<p>
				<label for="smartlearn_course_access_duration_value">
					<strong><?php esc_html_e( 'Обмеження доступу після покупки:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<span style="display:flex;gap:10px;align-items:center;max-width:400px;">
					<input
						type="number"
						min="0"
						step="1"
						name="smartlearn_course_access_duration_value"
						id="smartlearn_course_access_duration_value"
						value="<?php echo esc_attr( $access_duration_value ); ?>"
						style="width:120px;"
						placeholder="0"
					/>
					<select name="smartlearn_course_access_duration_unit" id="smartlearn_course_access_duration_unit" style="flex:1;">
						<option value="days" <?php selected( $access_duration_unit, 'days' ); ?>><?php esc_html_e( 'днів', 'smartlearn-lms' ); ?></option>
						<option value="months" <?php selected( $access_duration_unit, 'months' ); ?>><?php esc_html_e( 'місяців', 'smartlearn-lms' ); ?></option>
					</select>
				</span>
				<p class="description">
					<?php esc_html_e( '0 або порожньо = доступ без обмеження у часі. Якщо вказано, доступ закінчиться через N днів/місяців після останньої покупки курсу.', 'smartlearn-lms' ); ?>
				</p>
			</p>
			
			<p>
				<label for="smartlearn_course_duration">
					<strong><?php esc_html_e( 'Тривалість курсу (місяці):', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="number" min="0" step="1" name="smartlearn_course_duration" id="smartlearn_course_duration" value="<?php echo esc_attr( absint( get_post_meta( $post->ID, '_smartlearn_course_duration', true ) ) ); ?>" style="width:100%;max-width:400px;" placeholder="<?php esc_attr_e( 'наприклад: 2', 'smartlearn-lms' ); ?>" />
			</p>
			
			<p>
				<label for="smartlearn_course_level">
					<strong><?php esc_html_e( 'Рівень складності:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<select name="smartlearn_course_level" id="smartlearn_course_level" style="width:100%;max-width:400px;">
					<?php
					$level = get_post_meta( $post->ID, '_smartlearn_course_level', true );
					$levels = array(
						'beginner' => __( 'Початковий', 'smartlearn-lms' ),
						'intermediate' => __( 'Середній', 'smartlearn-lms' ),
						'advanced' => __( 'Просунутий', 'smartlearn-lms' ),
					);
					foreach ( $levels as $key => $label ) {
						printf( '<option value="%s" %s>%s</option>', $key, selected( $level, $key, false ), esc_html( $label ) );
					}
					?>
				</select>
			</p>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('input[name="smartlearn_course_is_free"]').on('change', function() {
				if ( $(this).is(':checked') ) {
					$('.course-product-field').hide();
				} else {
					$('.course-product-field').show();
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Render lesson meta box
	 */
	public function render_lesson_meta_box( $post ) {
		wp_nonce_field( 'smartlearn_lesson_meta', 'smartlearn_lesson_meta_nonce' );
		
		$course_id = get_post_meta( $post->ID, '_smartlearn_lesson_course_id', true );
		$is_free = get_post_meta( $post->ID, '_smartlearn_lesson_is_free', true );
		$video_urls = get_post_meta( $post->ID, '_smartlearn_lesson_video_urls', true );
		if ( ! is_array( $video_urls ) || empty( $video_urls ) ) {
			// Migrate old single URL if exists
			$old_url = get_post_meta( $post->ID, '_smartlearn_lesson_video_url', true );
			$video_urls = $old_url ? array( $old_url ) : array( '' );
		}
		
		?>
		<div class="-meta-box">
			<p>
				<label for="smartlearn_lesson_course_id">
					<strong><?php esc_html_e( 'Курс:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<select name="smartlearn_lesson_course_id" id="smartlearn_lesson_course_id" style="width:100%;max-width:400px;">
					<option value=""><?php esc_html_e( '— Виберіть курс —', 'smartlearn-lms' ); ?></option>
					<?php
					$courses = get_posts( array(
						'post_type' => 'smartlearn_course',
						'posts_per_page' => -1,
						'orderby' => 'title',
						'order' => 'ASC',
						'post_status' => array( 'publish', 'draft' ),
					) );
					
					foreach ( $courses as $course ) {
						printf(
							'<option value="%d" %s>%s</option>',
							$course->ID,
							selected( $course_id, $course->ID, false ),
							esc_html( $course->post_title )
						);
					}
					?>
				</select>
			</p>
			
			<p>
				<label>
					<input type="checkbox" name="smartlearn_lesson_is_free" value="1" <?php checked( $is_free, '1' ); ?> />
					<?php esc_html_e( 'Безкоштовний урок (доступний без покупки курсу)', 'smartlearn-lms' ); ?>
				</label>
			</p>
			
			<div>
				<label>
					<strong><?php esc_html_e( 'Відео URL (YouTube, Vimeo):', 'smartlearn-lms' ); ?></strong>
				</label>
				<p class="description" style="margin-top:5px;margin-bottom:10px;">
					<?php esc_html_e( 'Додайте посилання на відео з YouTube або Vimeo. Можна додати декілька відео.', 'smartlearn-lms' ); ?>
				</p>
				<div id="smartlearn-video-urls-container">
					<?php foreach ( $video_urls as $index => $url ) : ?>
						<div class="smartlearn-video-url-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
							<input type="url" name="smartlearn_lesson_video_urls[]" value="<?php echo esc_attr( $url ); ?>" style="flex:1;" placeholder="https://www.youtube.com/watch?v=..." />
							<?php if ( $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button" style="padding:0 10px;" title="<?php esc_attr_e( 'Відкрити посилання', 'smartlearn-lms' ); ?>">
									<span class="dashicons dashicons-external" style="margin-top:4px;"></span>
								</a>
							<?php endif; ?>
							<button type="button" class="button smartlearn-remove-video-url" style="padding:0 10px;color:#b32d2e;" title="<?php esc_attr_e( 'Видалити', 'smartlearn-lms' ); ?>">
								<span class="dashicons dashicons-trash" style="margin-top:4px;"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" id="smartlearn-add-video-url" class="button" style="margin-top:5px;">
					<span class="dashicons dashicons-plus-alt" style="margin-top:4px;margin-right:3px;"></span>
					<?php esc_html_e( 'Додати відео', 'smartlearn-lms' ); ?>
				</button>
				<style>
					.smartlearn-video-url-row input[type="url"] { min-width: 0; }
					.smartlearn-video-url-row .button { flex-shrink: 0; height: 30px; line-height: 28px; }
				</style>
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						// Add new video URL field
						$('#smartlearn-add-video-url').on('click', function() {
							var row = $('<div class="smartlearn-video-url-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">' +
								'<input type="url" name="smartlearn_lesson_video_urls[]" value="" style="flex:1;" placeholder="https://www.youtube.com/watch?v=..." />' +
								'<button type="button" class="button smartlearn-remove-video-url" style="padding:0 10px;color:#b32d2e;" title="<?php esc_attr_e( 'Видалити', 'smartlearn-lms' ); ?>">' +
								'<span class="dashicons dashicons-trash" style="margin-top:4px;"></span>' +
								'</button>' +
								'</div>');
							$('#smartlearn-video-urls-container').append(row);
						});
						
						// Remove video URL field
						$(document).on('click', '.smartlearn-remove-video-url', function() {
							if ($('.smartlearn-video-url-row').length > 1) {
								$(this).closest('.smartlearn-video-url-row').remove();
							} else {
								$(this).closest('.smartlearn-video-url-row').find('input').val('');
							}
						});
						
						// Update open link button when URL changes
						$(document).on('input', '.smartlearn-video-url-row input[type="url"]', function() {
							var $row = $(this).closest('.smartlearn-video-url-row');
							var url = $(this).val().trim();
							var $openBtn = $row.find('a.button');
							
							if (url) {
								if ($openBtn.length === 0) {
									$openBtn = $('<a href="" target="_blank" class="button" style="padding:0 10px;" title="<?php esc_attr_e( 'Відкрити посилання', 'smartlearn-lms' ); ?>">' +
										'<span class="dashicons dashicons-external" style="margin-top:4px;"></span>' +
										'</a>');
									$row.find('.smartlearn-remove-video-url').before($openBtn);
								}
								$openBtn.attr('href', url);
							} else {
								$openBtn.remove();
							}
						});
					});
				</script>
			</div>
			
			<p>
				<label for="smartlearn_lesson_duration">
					<strong><?php esc_html_e( 'Тривалість уроку:', 'smartlearn-lms' ); ?></strong>
				</label><br/>
				<input type="text" name="smartlearn_lesson_duration" id="smartlearn_lesson_duration" value="<?php echo esc_attr( get_post_meta( $post->ID, '_smartlearn_lesson_duration', true ) ); ?>" style="width:100%;max-width:200px;" placeholder="<?php esc_attr_e( '15 хв', 'smartlearn-lms' ); ?>" />
			</p>
		</div>
		<?php
	}
	
	/**
	 * Render course lessons list meta box
	 */
	public function render_course_lessons_meta_box( $post ) {
		$lessons = get_posts( array(
			'post_type' => 'smartlearn_lesson',
			'posts_per_page' => -1,
			'meta_key' => '_smartlearn_lesson_course_id',
			'meta_value' => $post->ID,
			'orderby' => 'menu_order',
			'order' => 'ASC',
		) );
		
		if ( ! empty( $lessons ) ) {
			echo '<ul style="margin:0;padding:0;list-style:none;">';
			foreach ( $lessons as $lesson ) {
				$is_free = get_post_meta( $lesson->ID, '_smartlearn_lesson_is_free', true );
				$icon = $is_free ? '🔓' : '🔒';
				printf(
					'<li style="padding:8px 0;border-bottom:1px solid #ddd;">%s <a href="%s">%s</a></li>',
					$icon,
					get_edit_post_link( $lesson->ID ),
					esc_html( $lesson->post_title )
				);
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'Уроків поки немає.', 'smartlearn-lms' ) . '</p>';
		}
		
		echo '<p style="margin-top:15px;"><a href="' . admin_url( 'post-new.php?post_type=smartlearn_lesson&course_id=' . $post->ID ) . '" class="button">' . esc_html__( 'Додати урок', 'smartlearn-lms' ) . '</a></p>';
	}
	
	/**
	 * Save course meta
	 */
	public function save_course_meta( $post_id, $post ) {
		if ( $post->post_type !== 'smartlearn_course' ) {
			return;
		}
		
		if ( ! isset( $_POST['smartlearn_course_meta_nonce'] ) || ! wp_verify_nonce( $_POST['smartlearn_course_meta_nonce'], 'smartlearn_course_meta' ) ) {
			return;
		}
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Save is_free
		$is_free = isset( $_POST['smartlearn_course_is_free'] ) ? '1' : '';
		update_post_meta( $post_id, '_smartlearn_course_is_free', $is_free );
		
		// Save product ID
		$product_id = isset( $_POST['smartlearn_course_product_id'] ) ? absint( $_POST['smartlearn_course_product_id'] ) : '';
		update_post_meta( $post_id, '_smartlearn_course_product_id', $product_id );

		// Save access duration settings (0 = unlimited)
		$access_value = isset( $_POST['smartlearn_course_access_duration_value'] ) ? absint( $_POST['smartlearn_course_access_duration_value'] ) : 0;
		$access_unit = isset( $_POST['smartlearn_course_access_duration_unit'] ) ? sanitize_text_field( $_POST['smartlearn_course_access_duration_unit'] ) : 'days';
		if ( ! in_array( $access_unit, array( 'days', 'months' ), true ) ) {
			$access_unit = 'days';
		}
		if ( $access_value > 0 ) {
			update_post_meta( $post_id, '_smartlearn_course_access_duration_value', $access_value );
			update_post_meta( $post_id, '_smartlearn_course_access_duration_unit', $access_unit );
		} else {
			delete_post_meta( $post_id, '_smartlearn_course_access_duration_value' );
			delete_post_meta( $post_id, '_smartlearn_course_access_duration_unit' );
		}
		
		// Save duration (months as integer)
		$duration = isset( $_POST['smartlearn_course_duration'] ) ? absint( $_POST['smartlearn_course_duration'] ) : 0;
		if ( $duration > 0 ) {
			update_post_meta( $post_id, '_smartlearn_course_duration', $duration );
		} else {
			delete_post_meta( $post_id, '_smartlearn_course_duration' );
		}
		
		// Save level
		$level = isset( $_POST['smartlearn_course_level'] ) ? sanitize_text_field( $_POST['smartlearn_course_level'] ) : 'beginner';
		update_post_meta( $post_id, '_smartlearn_course_level', $level );
	}
	
	/**
	 * Save lesson meta
	 */
	public function save_lesson_meta( $post_id, $post ) {
		if ( $post->post_type !== 'smartlearn_lesson' ) {
			return;
		}
		
		if ( ! isset( $_POST['smartlearn_lesson_meta_nonce'] ) || ! wp_verify_nonce( $_POST['smartlearn_lesson_meta_nonce'], 'smartlearn_lesson_meta' ) ) {
			return;
		}
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Save course ID
		$course_id = isset( $_POST['smartlearn_lesson_course_id'] ) ? absint( $_POST['smartlearn_lesson_course_id'] ) : '';
		update_post_meta( $post_id, '_smartlearn_lesson_course_id', $course_id );
		
		// Save is_free
		$is_free = isset( $_POST['smartlearn_lesson_is_free'] ) ? '1' : '';
		update_post_meta( $post_id, '_smartlearn_lesson_is_free', $is_free );
		
		// Save video URLs (multiple)
		$video_urls = array();
		if ( isset( $_POST['smartlearn_lesson_video_urls'] ) && is_array( $_POST['smartlearn_lesson_video_urls'] ) ) {
			foreach ( $_POST['smartlearn_lesson_video_urls'] as $url ) {
				$url = esc_url_raw( trim( $url ) );
				if ( ! empty( $url ) ) {
					$video_urls[] = $url;
				}
			}
		}
		update_post_meta( $post_id, '_smartlearn_lesson_video_urls', $video_urls );
		// Keep old meta for backward compatibility (use first URL if exists)
		if ( ! empty( $video_urls ) ) {
			update_post_meta( $post_id, '_smartlearn_lesson_video_url', $video_urls[0] );
		} else {
			delete_post_meta( $post_id, '_smartlearn_lesson_video_url' );
		}
		
		// Save duration
		$duration = isset( $_POST['smartlearn_lesson_duration'] ) ? sanitize_text_field( $_POST['smartlearn_lesson_duration'] ) : '';
		update_post_meta( $post_id, '_smartlearn_lesson_duration', $duration );
	}
}
