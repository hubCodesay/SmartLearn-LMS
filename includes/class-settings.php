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
			'smartlearn-lms-settings',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Реєструвати налаштування
	 */
	public function register_settings() {
		// General
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_login_url' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_courses_per_page' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_default_columns' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_language' );

		// Button Texts
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_button_text_view' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_button_text_buy' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_button_text_login' );

		// Card Design Settings
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_card_layout' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_image_aspect' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_card_bg' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_card_radius' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_card_border' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_title_color' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_text_color' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_meta_color' );
		
		// Button Design Settings
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_btn_bg' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_btn_text_color' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_btn_hover_bg' );
		register_setting( 'smartlearn_lms_settings', 'smartlearn_lms_btn_radius' );
	}
	
	/**
	 * Підключити стилі для сторінки налаштувань
	 */
	public function enqueue_settings_styles( $hook ) {
		if ( 'smartlearn_course_page_smartlearn-lms-settings' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 
			'smartlearn-lms-settings', 
			SMARTLEARN_LMS_URL . 'assets/css/settings.css', 
			array(), 
			SMARTLEARN_LMS_VERSION 
		);
		
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
	}
	
	/**
	 * Відобразити сторінку налаштувань
	 */
	public function render_settings_page() {
		// Отримуємо збережені налаштування для прев'ю
		$opts = array(
			'card_layout' => get_option('smartlearn_lms_card_layout', 'thumbnail:1,category:1,title:1,meta:1,excerpt:1,button:1'),
			'image_aspect' => get_option('smartlearn_lms_image_aspect', '16/9'),
			'card_bg' => get_option('smartlearn_lms_card_bg', '#ffffff'),
			'card_radius' => get_option('smartlearn_lms_card_radius', '8'),
			'card_border' => get_option('smartlearn_lms_card_border', '#e0e0e0'),
			'title_color' => get_option('smartlearn_lms_title_color', '#1d2327'),
			'text_color' => get_option('smartlearn_lms_text_color', '#3c434a'),
			'meta_color' => get_option('smartlearn_lms_meta_color', '#646970'),
			'btn_bg' => get_option('smartlearn_lms_btn_bg', '#2271b1'),
			'btn_text_color' => get_option('smartlearn_lms_btn_text_color', '#ffffff'),
			'btn_hover_bg' => get_option('smartlearn_lms_btn_hover_bg', '#135e96'),
			'btn_radius' => get_option('smartlearn_lms_btn_radius', '4'),
			'btn_text_view' => get_option('smartlearn_lms_button_text_view', __('Переглянути курс', 'smartlearn-lms')),
		);
		?>
		<style>
			.nav-tab-wrapper { margin-bottom: 20px; }
			.tab-content { display: none; }
			.tab-content.active { display: block; }
			
			/* Live Preview Styles */
			.live-preview-container {
				position: sticky;
				top: 40px;
				background: #f0f0f1;
				padding: 20px;
				border-radius: 8px;
				border: 1px dashed #ccc;
			}
			#preview-card {
				display: flex;
				flex-direction: column;
				max-width: 350px;
				margin: 0 auto;
				overflow: hidden;
				transition: all 0.3s;
			}
			#preview-card .preview-thumb {
				background-color: #ddd;
				background-image: url('https://via.placeholder.com/600x400?text=Course+Image');
				background-size: cover;
				background-position: center;
				width: 100%;
			}
			#preview-card .preview-content {
				padding: 20px;
				display: flex;
				flex-direction: column;
				gap: 10px;
				/* This will allow items to change order */
				display: contents; 
			}
			/* Elements inside card */
			#preview-card {
				display: flex;
				flex-direction: column;
				max-width: 350px;
				margin: 0 auto;
				overflow: hidden;
				transition: all 0.3s;
				padding: 20px;
				gap: 10px;
			}
			#preview-card > * { width: 100%; }
			#preview-card .preview-thumb-wrap { margin: 0 -20px; width: calc(100% + 40px); }
			
			/* Drag and drop sorting */
			#sl-card-layout-list { list-style: none; padding: 0; margin: 0; max-width: 400px; }
			#sl-card-layout-list li { background: #fff; border: 1px solid #ccd0d4; padding: 10px 15px; margin-bottom: 8px; cursor: move; display: flex; align-items: center; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
			#sl-card-layout-list li:hover { border-color: #999; }
			#sl-card-layout-list li .dashicons { margin-right: 15px; color: #a4a9ad; cursor: grab; }
			#sl-card-layout-list li label { margin: 0; display:flex; align-items:center; cursor:pointer; width: 100%; font-weight: 500; }
			#sl-card-layout-list li input { margin-right: 10px; }
			#preview-title { font-size: 18px; font-weight: bold; margin: 0; }
			#preview-meta { font-size: 13px; display: flex; gap: 10px; }
			#preview-excerpt { font-size: 14px; line-height: 1.5; margin: 0; }
			#preview-btn {
				display: inline-block;
				padding: 10px 20px;
				text-align: center;
				text-decoration: none;
				font-weight: 500;
				margin-top: 10px;
				transition: background 0.2s;
				align-self: flex-start;
			}
			#preview-btn:hover { background-color: var(--hover-bg); }
		</style>
		
		<div class="wrap smartlearn-lms-settings-wrap">
			<h1>
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<?php _e( 'SmartLearn LMS Налаштування', 'smartlearn-lms' ); ?>
			</h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="#tab-general" class="nav-tab nav-tab-active"><?php _e('Основні', 'smartlearn-lms'); ?></a>
				<a href="#tab-texts" class="nav-tab"><?php _e('Тексти кнопок', 'smartlearn-lms'); ?></a>
				<a href="#tab-design" class="nav-tab"><?php _e('Дизайн карток (Прев\'ю)', 'smartlearn-lms'); ?></a>
				<a href="#tab-instructions" class="nav-tab"><?php _e('Інструкції', 'smartlearn-lms'); ?></a>
			</h2>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'smartlearn_lms_settings' ); ?>
				
				<div class="smartlearn-lms-settings-container">
					
					<!-- Ліва колонка (Налаштування) -->
					<div class="smartlearn-lms-settings-main">
						
						<!-- TAB 1: General -->
						<div id="tab-general" class="tab-content active smartlearn-lms-card">
							<h2><?php _e( '⚙️ Основні налаштування', 'smartlearn-lms' ); ?></h2>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="smartlearn_lms_login_url"><?php _e( 'URL сторінки входу', 'smartlearn-lms' ); ?></label></th>
									<td>
										<input type="url" id="smartlearn_lms_login_url" name="smartlearn_lms_login_url" value="<?php echo esc_attr( get_option( 'smartlearn_lms_login_url', 'https://www.smartlearn-shopchik.com/my-account/' ) ); ?>" class="regular-text">
										<p class="description"><?php _e( 'URL на який будуть перенаправлятися неавторизовані користувачі', 'smartlearn-lms' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_courses_per_page"><?php _e( 'Курсів на сторінку', 'smartlearn-lms' ); ?></label></th>
									<td>
										<input type="number" id="smartlearn_lms_courses_per_page" name="smartlearn_lms_courses_per_page" value="<?php echo esc_attr( get_option( 'smartlearn_lms_courses_per_page', '9' ) ); ?>" min="-1" class="small-text">
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_default_columns"><?php _e( 'Колонок за замовчуванням', 'smartlearn-lms' ); ?></label></th>
									<td>
										<select id="smartlearn_lms_default_columns" name="smartlearn_lms_default_columns">
											<?php
											$columns = get_option( 'smartlearn_lms_default_columns', '3' );
											for ( $i = 1; $i <= 4; $i++ ) {
												printf('<option value="%d" %s>%d</option>', $i, selected( $columns, $i, false ), $i);
											}
											?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_language"><?php _e( 'Мова плагіна', 'smartlearn-lms' ); ?></label></th>
									<td>
										<select id="smartlearn_lms_language" name="smartlearn_lms_language">
											<?php
											$current_lang = get_option( 'smartlearn_lms_language', 'uk' );
											$languages = array('uk' => 'Українська', 'ru' => 'Русский', 'en' => 'English');
											foreach ( $languages as $code => $name ) {
												printf('<option value="%s" %s>%s</option>', esc_attr( $code ), selected( $current_lang, $code, false ), esc_html( $name ));
											}
											?>
										</select>
									</td>
								</tr>
							</table>
						</div>

						<!-- TAB 2: Text -->
						<div id="tab-texts" class="tab-content smartlearn-lms-card">
							<h2><?php _e( '📝 Тексти кнопок', 'smartlearn-lms' ); ?></h2>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="smartlearn_lms_button_text_view"><?php _e( 'Переглянути курс', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_button_text_view" name="smartlearn_lms_button_text_view" value="<?php echo esc_attr( $opts['btn_text_view'] ); ?>" class="regular-text sl-live-input" data-target="#preview-btn"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_button_text_buy"><?php _e( 'Купити курс', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_button_text_buy" name="smartlearn_lms_button_text_buy" value="<?php echo esc_attr( get_option( 'smartlearn_lms_button_text_buy', __( 'Купити курс', 'smartlearn-lms' ) ) ); ?>" class="regular-text"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_button_text_login"><?php _e( 'Увійти', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_button_text_login" name="smartlearn_lms_button_text_login" value="<?php echo esc_attr( get_option( 'smartlearn_lms_button_text_login', __( 'Увійти', 'smartlearn-lms' ) ) ); ?>" class="regular-text"></td>
								</tr>
							</table>
						</div>
						
						<!-- TAB 3: Design -->
						<div id="tab-design" class="tab-content smartlearn-lms-card">
							<h2><?php _e( '🎨 Дизайн карток та кнопок', 'smartlearn-lms' ); ?></h2>
							<p><?php _e('Змінюйте налаштування і бачте результат в реальному часі справа.', 'smartlearn-lms'); ?></p>
							
							<h3><?php _e('Елементи (Будова картки)', 'smartlearn-lms'); ?></h3>
							<table class="form-table">
								<tr>
									<td colspan="2" style="padding-left:0; padding-top:0;">
										<?php
										$default_layout = 'thumbnail:1,category:1,title:1,meta:1,excerpt:1,button:1';
										$saved_layout = $opts['card_layout'];
										$layout_items = explode(',', $saved_layout);
										$labels = array(
											'thumbnail' => __('Зображення', 'smartlearn-lms'),
											'category' => __('Категорія', 'smartlearn-lms'),
											'title' => __('Заголовок', 'smartlearn-lms'),
											'meta' => __('Мета-дані (рівень, час)', 'smartlearn-lms'),
											'excerpt' => __('Опис', 'smartlearn-lms'),
											'button' => __('Кнопка', 'smartlearn-lms'),
										);
										
										foreach($labels as $key => $label) {
											$found = false;
											foreach($layout_items as $item) {
												if (strpos($item, $key.':') === 0) { $found = true; break; }
											}
											if (!$found) $layout_items[] = $key.':1';
										}
										?>
										<ul id="sl-card-layout-list">
											<?php foreach($layout_items as $item): 
												$parts = explode(':', $item);
												if (count($parts) !== 2) continue;
												$id = $parts[0];
												$visible = $parts[1] === '1';
												if (!isset($labels[$id])) continue;
											?>
											<li data-id="<?php echo esc_attr($id); ?>">
												<span class="dashicons dashicons-menu"></span>
												<label>
													<input type="checkbox" class="sl-layout-cb" <?php checked($visible, true); ?>> 
													<?php echo esc_html($labels[$id]); ?>
												</label>
											</li>
											<?php endforeach; ?>
										</ul>
										<input type="hidden" id="smartlearn_lms_card_layout" name="smartlearn_lms_card_layout" value="<?php echo esc_attr($saved_layout); ?>">
										<p class="description" style="margin-top:10px;"><?php _e('Перетягніть блоки мишкою ↕, щоб змінити порядок відображення в картці. Зніміть галочку для приховування.', 'smartlearn-lms'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row" style="padding-top:0;"><label for="smartlearn_lms_image_aspect"><?php _e( 'Пропорції зображення', 'smartlearn-lms' ); ?></label></th>
									<td>
										<select id="smartlearn_lms_image_aspect" name="smartlearn_lms_image_aspect" class="sl-live-select">
											<option value="16/9" <?php selected($opts['image_aspect'], '16/9'); ?>><?php _e( '16:9 (Широкий)', 'smartlearn-lms' ); ?></option>
											<option value="4/3" <?php selected($opts['image_aspect'], '4/3'); ?>><?php _e( '4:3 (Стандарт)', 'smartlearn-lms' ); ?></option>
											<option value="1/1" <?php selected($opts['image_aspect'], '1/1'); ?>><?php _e( '1:1 (Квадрат)', 'smartlearn-lms' ); ?></option>
											<option value="auto" <?php selected($opts['image_aspect'], 'auto'); ?>><?php _e( 'Авто (Оригінал)', 'smartlearn-lms' ); ?></option>
										</select>
									</td>
								</tr>
							</table>

							<h3><?php _e('Картка', 'smartlearn-lms'); ?></h3>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="smartlearn_lms_card_bg"><?php _e( 'Фон картки', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_card_bg" name="smartlearn_lms_card_bg" value="<?php echo esc_attr($opts['card_bg']); ?>" class="sl-color-picker"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_card_radius"><?php _e( 'Закруглення кутів (px)', 'smartlearn-lms' ); ?></label></th>
									<td><input type="number" id="smartlearn_lms_card_radius" name="smartlearn_lms_card_radius" value="<?php echo esc_attr($opts['card_radius']); ?>" class="small-text sl-live-input"> px</td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_card_border"><?php _e( 'Колір рамки', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_card_border" name="smartlearn_lms_card_border" value="<?php echo esc_attr($opts['card_border']); ?>" class="sl-color-picker"></td>
								</tr>
							</table>

							<h3><?php _e('Текст', 'smartlearn-lms'); ?></h3>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="smartlearn_lms_title_color"><?php _e( 'Колір заголовка', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_title_color" name="smartlearn_lms_title_color" value="<?php echo esc_attr($opts['title_color']); ?>" class="sl-color-picker"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_text_color"><?php _e( 'Колір тексту/опису', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_text_color" name="smartlearn_lms_text_color" value="<?php echo esc_attr($opts['text_color']); ?>" class="sl-color-picker"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_meta_color"><?php _e( 'Колір мета-даних', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_meta_color" name="smartlearn_lms_meta_color" value="<?php echo esc_attr($opts['meta_color']); ?>" class="sl-color-picker"></td>
								</tr>
							</table>

							<h3><?php _e('Кнопка', 'smartlearn-lms'); ?></h3>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="smartlearn_lms_btn_bg"><?php _e( 'Фон кнопки', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_btn_bg" name="smartlearn_lms_btn_bg" value="<?php echo esc_attr($opts['btn_bg']); ?>" class="sl-color-picker"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_btn_hover_bg"><?php _e( 'Фон кнопки (Наведення)', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_btn_hover_bg" name="smartlearn_lms_btn_hover_bg" value="<?php echo esc_attr($opts['btn_hover_bg']); ?>" class="sl-color-picker"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_btn_text_color"><?php _e( 'Колір тексту кнопки', 'smartlearn-lms' ); ?></label></th>
									<td><input type="text" id="smartlearn_lms_btn_text_color" name="smartlearn_lms_btn_text_color" value="<?php echo esc_attr($opts['btn_text_color']); ?>" class="sl-color-picker"></td>
								</tr>
								<tr>
									<th scope="row"><label for="smartlearn_lms_btn_radius"><?php _e( 'Закруглення кнопки (px)', 'smartlearn-lms' ); ?></label></th>
									<td><input type="number" id="smartlearn_lms_btn_radius" name="smartlearn_lms_btn_radius" value="<?php echo esc_attr($opts['btn_radius']); ?>" class="small-text sl-live-input"> px</td>
								</tr>
							</table>
						</div>

						<!-- TAB 4: Instructions -->
						<div id="tab-instructions" class="tab-content smartlearn-lms-card">
							<h2><?php _e( '📖 Як використовувати', 'smartlearn-lms' ); ?></h2>
							<div class="smartlearn-instructions">
								<h3><?php _e( 'Шорткоди', 'smartlearn-lms' ); ?></h3>
								<h4><?php _e( 'Список всіх курсів:', 'smartlearn-lms' ); ?></h4>
								<div class="smartlearn-code-block">
									<code>[courses_list]</code>
								</div>
								<h4><?php _e( 'Параметри шорткоду:', 'smartlearn-lms' ); ?></h4>
								<ul class="smartlearn-params-list">
									<li><strong>columns</strong> - <?php _e( 'кількість колонок (1-4)', 'smartlearn-lms' ); ?> <code>[courses_list columns="4"]</code></li>
									<li><strong>category</strong> - <?php _e( 'slug категорії', 'smartlearn-lms' ); ?> <code>[courses_list category="programming"]</code></li>
								</ul>
							</div>
						</div>
						
						<p class="submit">
							<?php submit_button( 'Зберегти зміни', 'primary', 'submit', false ); ?>
						</p>

					</div>
					
					<!-- Права колонка (Прев'ю) -->
					<div class="smartlearn-lms-settings-sidebar">
						<div class="live-preview-container">
							<h3 style="margin-top:0"><?php _e('Live Preview', 'smartlearn-lms'); ?></h3>
							
							<div id="preview-card">
								<div class="preview-thumb-wrap" data-id="thumbnail">
									<div class="preview-thumb"></div>
								</div>
								<div class="preview-cat" data-id="category"><span id="preview-meta-cat">Розробка</span></div>
								<div id="preview-title" data-id="title">WordPress Plugin Development</div>
								<div id="preview-meta" data-id="meta">
									<span id="preview-meta-level">Початковий</span>
									<span id="preview-meta-time">2 години</span>
								</div>
								<div id="preview-excerpt" data-id="excerpt">Дізнайтеся як створювати професійні плагіни для WordPress з нуля. В цьому курсі ви пройдете всі етапи...</div>
								<div id="preview-btn-wrap" data-id="button">
									<a href="#" id="preview-btn">Переглянути курс</a>
								</div>
							</div>
						</div>
					</div>
					
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Ініціалізація Color Picker
			$('.sl-color-picker').wpColorPicker({
				change: updateLivePreview
			});
			
			// Tabs
			$('.nav-tab').on('click', function(e) {
				e.preventDefault();
				$('.nav-tab').removeClass('nav-tab-active');
				$(this).addClass('nav-tab-active');
				var target = $(this).attr('href');
				$('.tab-content').removeClass('active');
				$(target).addClass('active');
			});

			// Drag and Drop
			function syncLayout() {
				var arr = [];
				$('#sl-card-layout-list li').each(function() {
					var id = $(this).data('id');
					var vis = $(this).find('input').is(':checked') ? '1' : '0';
					arr.push(id + ':' + vis);
				});
				$('#smartlearn_lms_card_layout').val(arr.join(','));
				updateLivePreview();
			}

			$('#sl-card-layout-list').sortable({
				update: syncLayout,
				handle: '.dashicons-menu',
				axis: 'y'
			});
			$('.sl-layout-cb').on('change', syncLayout);

			// Live Update
			$('.sl-live-input, .sl-live-select').on('input change', updateLivePreview);

			function updateLivePreview() {
				// Отримуємо значення
				var layoutStr = $('#smartlearn_lms_card_layout').val();
				var aspect = $('#smartlearn_lms_image_aspect').val() || '16/9';
				var bg = $('#smartlearn_lms_card_bg').val() || '#fff';
				var radius = $('#smartlearn_lms_card_radius').val() || '8';
				var border = $('#smartlearn_lms_card_border').val() || '#e0e0e0';
				var tColor = $('#smartlearn_lms_title_color').val() || '#000';
				var textColor = $('#smartlearn_lms_text_color').val() || '#333';
				var mColor = $('#smartlearn_lms_meta_color').val() || '#666';
				var btnBg = $('#smartlearn_lms_btn_bg').val() || '#0073aa';
				var btnHbg = $('#smartlearn_lms_btn_hover_bg').val() || '#005177';
				var btnTxtC = $('#smartlearn_lms_btn_text_color').val() || '#fff';
				var btnRd = $('#smartlearn_lms_btn_radius').val() || '4';
				var btnLabel = $('#smartlearn_lms_button_text_view').val() || 'Переглянути курс';

				// Застосовуємо до картки
				var $card = $('#preview-card');
				$card.css({
					'background-color': bg,
					'border-radius': radius + 'px',
					'border': '1px solid ' + border
				});

				// Тексти та кольори
				$('#preview-title').css('color', tColor);
				$('#preview-excerpt').css('color', textColor);
				$('#preview-meta-cat, #preview-meta-level, #preview-meta-time').css('color', mColor);
				$('#preview-btn').text(btnLabel)
					.css({
						'background-color': btnBg,
						'color': btnTxtC,
						'border-radius': btnRd + 'px'
					});
				// Записуємо CSS змінну для hover
				$('#preview-btn')[0].style.setProperty('--hover-bg', btnHbg);

				// Layout & Visibility
				var $card = $('#preview-card');
				var visibleItems = [];
				
				if (layoutStr) {
					var parts = layoutStr.split(',');
					for (var i = 0; i < parts.length; i++) {
						var kv = parts[i].split(':');
						if (kv.length === 2) {
							var id = kv[0];
							var isVisible = kv[1] === '1';
							var $el = $card.find('[data-id="' + id + '"]');
							
							if ($el.length) {
								if (isVisible) {
									$el.show();
									visibleItems.push(id);
									$card.append($el); // Append simply re-orders DOM elements!
								} else {
									$el.hide();
								}
							}
						}
					}
				}
				
				// Fix thumbnail margin based on position
				var $thumbWrap = $card.find('.preview-thumb-wrap');
				if (visibleItems.length > 0) {
					var tIdx = visibleItems.indexOf('thumbnail');
					if (tIdx === 0) {
						$thumbWrap.css('margin', '-20px -20px 10px');
					} else if (tIdx === visibleItems.length - 1) {
						$thumbWrap.css('margin', '10px -20px -20px');
					} else {
						$thumbWrap.css('margin', '10px -20px');
					}
				}
				
				// Пропорції зображення
				var $thumb = $('.preview-thumb');
				if (aspect === 'auto') {
					$thumb.css('aspect-ratio', 'auto').css('height', '180px');
				} else {
					$thumb.css('aspect-ratio', aspect).css('height', 'auto');
				}
			}

			// Ініціалізація
			updateLivePreview();
		});
		</script>
		<?php
	}
}
