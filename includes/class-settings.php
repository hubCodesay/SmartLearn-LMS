<?php
/**
 * Settings Page - сторінка налаштувань плагіна
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartLearn_LMS_Settings {
	
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_styles' ) );
	}
	
	/**
	 * Додати сторінку налаштувань
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=smartlearn_course',
			__( 'Налаштування LMS', 'smartlearn-lms' ),
			__( 'Налаштування', 'smartlearn-lms' ),
			'manage_options',
			'-lms-settings',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Реєструвати налаштування
	 */
	public function register_settings() {
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_login_url' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_courses_per_page' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_default_columns' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_button_text_view' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_button_text_buy' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_button_text_login' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_language' );
	}
	
	/**
	 * Підключити стилі для сторінки налаштувань
	 */
	public function enqueue_settings_styles( $hook ) {
		if ( 'smartlearn_course_page_-lms-settings' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 
			'-lms-settings', 
			SMARTLEARN_LMS_URL . 'assets/css/settings.css', 
			array(), 
			SMARTLEARN_LMS_VERSION 
		);
	}
	
	/**
	 * Відобразити сторінку налаштувань
	 */
	public function render_settings_page() {
		?>
		<div class="wrap -lms-settings-wrap">
			<h1>
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<?php _e( 'SmartLearn LMS', 'smartlearn-lms' ); ?>
			</h1>
			
			<div class="-lms-settings-container">
				
				<!-- Основні налаштування -->
				<div class="-lms-settings-main">
					
					<div class="-lms-card">
						<h2><?php _e( '⚙️ Налаштування', 'smartlearn-lms' ); ?></h2>
						
						<form method="post" action="options.php">
							<?php settings_fields( 'smartlearn_lms_settings' ); ?>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="smartlearn_lms_login_url">
											<?php _e( 'URL сторінки входу', 'smartlearn-lms' ); ?>
										</label>
									</th>
									<td>
										<input 
											type="url" 
											id="smartlearn_lms_login_url" 
											name="smartlearn_lms_login_url" 
											value="<?php echo esc_attr( get_option( 'smartlearn_lms_login_url', 'https://www.smartlearn-shopchik.com/my-account/' ) ); ?>" 
											class="regular-text"
										>
										<p class="description">
											<?php _e( 'URL на який будуть перенаправлятися неавторизовані користувачі', 'smartlearn-lms' ); ?>
										</p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="smartlearn_lms_courses_per_page">
											<?php _e( 'Курсів на сторінку', 'smartlearn-lms' ); ?>
										</label>
									</th>
									<td>
										<input 
											type="number" 
											id="smartlearn_lms_courses_per_page" 
											name="smartlearn_lms_courses_per_page" 
											value="<?php echo esc_attr( get_option( 'smartlearn_lms_courses_per_page', '9' ) ); ?>" 
											min="-1"
											class="small-text"
										>
										<p class="description">
											<?php _e( 'Кількість курсів у шорткоді за замовчуванням (-1 = всі)', 'smartlearn-lms' ); ?>
										</p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="smartlearn_lms_default_columns">
											<?php _e( 'Колонок за замовчуванням', 'smartlearn-lms' ); ?>
										</label>
									</th>
									<td>
										<select id="smartlearn_lms_default_columns" name="smartlearn_lms_default_columns">
											<?php
											$columns = get_option( 'smartlearn_lms_default_columns', '3' );
											for ( $i = 1; $i <= 4; $i++ ) {
												printf(
													'<option value="%d" %s>%d</option>',
													$i,
													selected( $columns, $i, false ),
													$i
												);
											}
											?>
										</select>
										<p class="description">
											<?php _e( 'Кількість колонок у сітці курсів', 'smartlearn-lms' ); ?>
										</p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="smartlearn_lms_language">
											<?php _e( 'Мова плагіна', 'smartlearn-lms' ); ?>
										</label>
									</th>
									<td>
										<select id="smartlearn_lms_language" name="smartlearn_lms_language">
											<?php
											$current_lang = get_option( 'smartlearn_lms_language', 'uk' );
											$languages = array(
												'uk' => 'Українська',
												'ru' => 'Русский',
												'en' => 'English'
											);
											foreach ( $languages as $code => $name ) {
												printf(
													'<option value="%s" %s>%s</option>',
													esc_attr( $code ),
													selected( $current_lang, $code, false ),
													esc_html( $name )
												);
											}
											?>
										</select>
										<p class="description">
											<?php _e( 'Виберіть мову інтерфейсу плагіна', 'smartlearn-lms' ); ?>
										</p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<?php _e( 'Тексти кнопок', 'smartlearn-lms' ); ?>
									</th>
									<td>
										<fieldset>
											<label for="smartlearn_lms_button_text_view">
												<?php _e( 'Переглянути курс:', 'smartlearn-lms' ); ?>
											</label>
											<br>
											<input 
												type="text" 
												id="smartlearn_lms_button_text_view" 
												name="smartlearn_lms_button_text_view" 
												value="<?php echo esc_attr( get_option( 'smartlearn_lms_button_text_view', __( 'Переглянути курс', 'smartlearn-lms' ) ) ); ?>" 
												class="regular-text"
											>
											<br><br>
											
											<label for="smartlearn_lms_button_text_buy">
												<?php _e( 'Купити курс:', 'smartlearn-lms' ); ?>
											</label>
											<br>
											<input 
												type="text" 
												id="smartlearn_lms_button_text_buy" 
												name="smartlearn_lms_button_text_buy" 
												value="<?php echo esc_attr( get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) ) ); ?>" 
												class="regular-text"
											>
											<br><br>
											
											<label for="smartlearn_lms_button_text_login">
												<?php _e( 'Увійти:', 'smartlearn-lms' ); ?>
											</label>
											<br>
											<input 
												type="text" 
												id="smartlearn_lms_button_text_login" 
												name="smartlearn_lms_button_text_login" 
												value="<?php echo esc_attr( get_option( 'smartlearn_lms_button_text_login', __( 'Увійти', 'smartlearn-lms' ) ) ); ?>" 
												class="regular-text"
											>
										</fieldset>
									</td>
								</tr>
							</table>
							
							<?php submit_button(); ?>
						</form>
					</div>
					
					<!-- Інструкції -->
					<div class="-lms-card">
						<h2><?php _e( '📖 Як використовувати', 'smartlearn-lms' ); ?></h2>
						
						<div class="-instructions">
							<h3><?php _e( '1. Створення курсу', 'smartlearn-lms' ); ?></h3>
							<ol>
								<li><?php _e( 'Перейдіть в меню "Курси → Додати новий"', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Введіть назву та опис курсу', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Виберіть товар WooCommerce або зробіть курс безкоштовним', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Додайте зображення курсу (рекомендовано)', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Натисніть "Опублікувати"', 'smartlearn-lms' ); ?></li>
							</ol>
							
							<h3><?php _e( '2. Додавання уроків', 'smartlearn-lms' ); ?></h3>
							<ol>
								<li><?php _e( 'Перейдіть в меню "Уроки → Додати новий"', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Виберіть курс, до якого належить урок', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Додайте відео URL (YouTube, Vimeo) за бажанням', 'smartlearn-lms' ); ?></li>
								<li><?php _e( 'Вкажіть чи урок безкоштовний (для preview)', 'smartlearn-lms' ); ?></li>
							</ol>
							
							<h3><?php _e( '3. Шорткоди', 'smartlearn-lms' ); ?></h3>
							
							<h4><?php _e( 'Список всіх курсів:', 'smartlearn-lms' ); ?></h4>
							<div class="-code-block">
								<code>[courses_list]</code>
								<button class="button button-small copy-shortcode" data-clipboard="[courses_list]">
									<?php _e( 'Копіювати', 'smartlearn-lms' ); ?>
								</button>
							</div>
							
							<h4><?php _e( 'Параметри шорткоду:', 'smartlearn-lms' ); ?></h4>
							<ul class="-params-list">
								<li>
									<strong>columns</strong> - <?php _e( 'кількість колонок (1-4)', 'smartlearn-lms' ); ?>
									<div class="-code-block">
										<code>[courses_list columns="4"]</code>
										<button class="button button-small copy-shortcode" data-clipboard='[courses_list columns="4"]'>
											<?php _e( 'Копіювати', 'smartlearn-lms' ); ?>
										</button>
									</div>
								</li>
								<li>
									<strong>category</strong> - <?php _e( 'slug категорії або декілька через кому', 'smartlearn-lms' ); ?>
									<div class="-code-block">
										<code>[courses_list category="programming"]</code>
										<button class="button button-small copy-shortcode" data-clipboard='[courses_list category="programming"]'>
											<?php _e( 'Копіювати', 'smartlearn-lms' ); ?>
										</button>
									</div>
								</li>
								<li>
									<strong>per_page</strong> - <?php _e( 'кількість курсів (-1 = всі)', 'smartlearn-lms' ); ?>
									<div class="-code-block">
										<code>[courses_list per_page="6"]</code>
										<button class="button button-small copy-shortcode" data-clipboard='[courses_list per_page="6"]'>
											<?php _e( 'Копіювати', 'smartlearn-lms' ); ?>
										</button>
									</div>
								</li>
								<li>
									<strong>orderby</strong> - <?php _e( 'сортування (date, title, menu_order)', 'smartlearn-lms' ); ?>
									<div class="-code-block">
										<code>[courses_list orderby="title" order="ASC"]</code>
										<button class="button button-small copy-shortcode" data-clipboard='[courses_list orderby="title" order="ASC"]'>
											<?php _e( 'Копіювати', 'smartlearn-lms' ); ?>
										</button>
									</div>
								</li>
							</ul>
							
							<h4><?php _e( 'Список уроків курсу:', 'smartlearn-lms' ); ?></h4>
							<div class="-code-block">
								<code>[course_lessons course_id="123"]</code>
								<button class="button button-small copy-shortcode" data-clipboard='[course_lessons course_id="123"]'>
									<?php _e( 'Копіювати', 'smartlearn-lms' ); ?>
								</button>
							</div>
							
							<h3><?php _e( '4. Стилізація', 'smartlearn-lms' ); ?></h3>
							<p><?php _e( 'Плагін включає адаптивні стилі, які автоматично підлаштовуються під вашу тему. Основні CSS класи:', 'smartlearn-lms' ); ?></p>
							<ul class="-css-list">
								<li><code>.smartlearn-courses-grid</code> - <?php _e( 'сітка курсів', 'smartlearn-lms' ); ?></li>
								<li><code>.smartlearn-course-item</code> - <?php _e( 'окремий курс', 'smartlearn-lms' ); ?></li>
								<li><code>.smartlearn-course-button</code> - <?php _e( 'кнопка курсу', 'smartlearn-lms' ); ?></li>
								<li><code>.smartlearn-lessons-list</code> - <?php _e( 'список уроків', 'smartlearn-lms' ); ?></li>
								<li><code>.smartlearn-lesson-item</code> - <?php _e( 'окремий урок', 'smartlearn-lms' ); ?></li>
								<li><code>.smartlearn-access-denied</code> - <?php _e( 'блок відсутності доступу', 'smartlearn-lms' ); ?></li>
							</ul>
							<p><?php _e( 'Ви можете перевизначити стилі у своїй темі для повної кастомізації.', 'smartlearn-lms' ); ?></p>
						</div>
					</div>
				</div>
				
				<!-- Бічна панель -->
				<div class="-lms-settings-sidebar">
					
					<!-- Про плагін -->
					<div class="-lms-card -about">
						<div class="-logo">
							<span class="dashicons dashicons-welcome-learn-more"></span>
						</div>
						<h3>SmartLearn LMS</h3>
						<p class="version"><?php printf( __( 'Версія %s', 'smartlearn-lms' ), SMARTLEARN_LMS_VERSION ); ?></p>
						<p><?php _e( 'Професійна система управління онлайн-курсами з інтеграцією WooCommerce.', 'smartlearn-lms' ); ?></p>
						
						<div class="-stats">
							<?php
							$courses_count = wp_count_posts( 'smartlearn_course' )->publish;
							$lessons_count = wp_count_posts( 'smartlearn_lesson' )->publish;
							?>
							<div class="stat-item">
								<span class="stat-number"><?php echo esc_html( $courses_count ); ?></span>
								<span class="stat-label"><?php _e( 'Курсів', 'smartlearn-lms' ); ?></span>
							</div>
							<div class="stat-item">
								<span class="stat-number"><?php echo esc_html( $lessons_count ); ?></span>
								<span class="stat-label"><?php _e( 'Уроків', 'smartlearn-lms' ); ?></span>
							</div>
						</div>
					</div>
					
					<!-- Підтримка розробки -->
					<div class="-lms-card -donate">
						<h3>
							<span class="dashicons dashicons-heart"></span>
							<?php _e( 'Підтримати розробку', 'smartlearn-lms' ); ?>
						</h3>
						<p><?php _e( 'Якщо вам подобається цей плагін і ви хочете підтримати його розвиток, буду вдячний за вашу допомогу!', 'smartlearn-lms' ); ?></p>
						
						<div class="donate-buttons">
							<p class="donate-note">
								<?php _e( 'Якщо ви хочете підтримати розробку плагіна, будемо дуже вдячні! Контакти для донатів:', 'smartlearn-lms' ); ?>
							</p>
							<p>
								<strong>Email:</strong> <code>donate@stabion.studio</code><br>
								<strong>Website:</strong> <a href="https://stabion.studio/donate/" target="_blank">stabion.studio/donate</a>
							</p>
						</div>
						
						<p class="thank-you">
							<span class="dashicons dashicons-smiley"></span>
							<?php _e( 'Дякую за підтримку!', 'smartlearn-lms' ); ?>
						</p>
					</div>
					
					<!-- Stabion Studio -->
					<div class="-lms-card -stabion">
						<h3><?php _e( 'Про автора', 'smartlearn-lms' ); ?></h3>
						<p><?php _e( 'Розроблено командою', 'smartlearn-lms' ); ?> <strong>Stabion Studio</strong></p>
						<p><?php _e( 'Ми створюємо професійні рішення для WordPress та WooCommerce.', 'smartlearn-lms' ); ?></p>
						
						<div class="stabion-links">
							<a href="https://stabion.studio" target="_blank" class="button">
								<span class="dashicons dashicons-admin-site"></span>
								<?php _e( 'Наш сайт', 'smartlearn-lms' ); ?>
							</a>
							<a href="https://github.com/stabion" target="_blank" class="button">
								<span class="dashicons dashicons-github"></span>
								<?php _e( 'GitHub', 'smartlearn-lms' ); ?>
							</a>
						</div>
					</div>
					
					<!-- Корисні посилання -->
					<div class="-lms-card">
						<h3><?php _e( '🔗 Корисні посилання', 'smartlearn-lms' ); ?></h3>
						<ul class="-links-list">
							<li>
								<a href="edit.php?post_type=smartlearn_course">
									<span class="dashicons dashicons-book"></span>
									<?php _e( 'Всі курси', 'smartlearn-lms' ); ?>
								</a>
							</li>
							<li>
								<a href="post-new.php?post_type=smartlearn_course">
									<span class="dashicons dashicons-plus"></span>
									<?php _e( 'Додати курс', 'smartlearn-lms' ); ?>
								</a>
							</li>
							<li>
								<a href="edit.php?post_type=smartlearn_lesson">
									<span class="dashicons dashicons-media-document"></span>
									<?php _e( 'Всі уроки', 'smartlearn-lms' ); ?>
								</a>
							</li>
							<li>
								<a href="edit-tags.php?taxonomy=course_category&post_type=smartlearn_course">
									<span class="dashicons dashicons-category"></span>
									<?php _e( 'Категорії курсів', 'smartlearn-lms' ); ?>
								</a>
							</li>
							<li>
								<a href="edit.php?post_type=product">
									<span class="dashicons dashicons-products"></span>
									<?php _e( 'Товари WooCommerce', 'smartlearn-lms' ); ?>
								</a>
							</li>
						</ul>
					</div>
					
				</div>
				
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Копіювання в буфер обміну
			$('.copy-shortcode, .copy-crypto').on('click', function(e) {
				e.preventDefault();
				var text = $(this).data('clipboard');
				var $button = $(this);
				
				// Створити тимчасове поле
				var $temp = $('<input>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				
				// Показати повідомлення
				var originalText = $button.text();
				$button.text('<?php _e( 'Скопійовано!', 'smartlearn-lms' ); ?>');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		});
		</script>
		<?php
	}
}
