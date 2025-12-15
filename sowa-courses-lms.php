<?php
/**
 * Plugin Name: SmartLearn LMS
 * Plugin URI: https://stabion.studio/plugins/smartlearn-lms
 * Description: Professional Learning Management System with full WooCommerce integration for selling online courses. Create courses, add lessons, embed videos, control access and monetize your educational content.
 * Version: 1.0.2
 * Author: Stabion Studio
 * Author URI: https://stabion.studio
 * Text Domain: smartlearn-lms
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SMARTLEARN_LMS_VERSION', '1.0.2' );
define( 'SMARTLEARN_LMS_FILE', __FILE__ );
define( 'SMARTLEARN_LMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMARTLEARN_LMS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class SmartLearn_Courses_LMS {
	
	private static $instance = null;
	
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$this->init_hooks();
		$this->includes();
	}
	
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}
	
	public function check_dependencies() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return false;
		}
		return true;
	}
	
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'SmartLearn LMS потребує активації WooCommerce для роботи.', 'smartlearn-lms' ); ?></p>
		</div>
		<?php
	}
	
	public function load_textdomain() {
		// Get selected language from settings
		$selected_lang = get_option( 'smartlearn_lms_language', 'uk' );
		
		// Load the selected language file
		$locale = $selected_lang . '_' . strtoupper( $selected_lang );
		$mofile = dirname( plugin_basename( __FILE__ ) ) . '/languages/smartlearn-lms-' . $selected_lang . '.mo';
		
		// Try to load specific language file
		if ( file_exists( WP_PLUGIN_DIR . '/' . $mofile ) ) {
			load_textdomain( 'smartlearn-lms', WP_PLUGIN_DIR . '/' . $mofile );
		} else {
			// Fallback to default WordPress language loading
			load_plugin_textdomain( 'smartlearn-lms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}
	}
	
	private function includes() {
		require_once SMARTLEARN_LMS_PATH . 'includes/class-post-types.php';
		require_once SMARTLEARN_LMS_PATH . 'includes/class-meta-boxes.php';
		require_once SMARTLEARN_LMS_PATH . 'includes/class-access-control.php';
		require_once SMARTLEARN_LMS_PATH . 'includes/class-templates.php';
		require_once SMARTLEARN_LMS_PATH . 'includes/class-shortcodes.php';
		require_once SMARTLEARN_LMS_PATH . 'includes/class-settings.php';
		
		// Initialize components
		new SmartLearn_LMS_Post_Types();
		new SmartLearn_LMS_Meta_Boxes();
		// Access_Control має тільки статичні методи - не потрібно ініціалізувати
		new SmartLearn_LMS_Templates();
		new SmartLearn_LMS_Shortcodes();
		new SmartLearn_LMS_Settings();
	}
	
	public function activate() {
		// Register post types before flushing rewrite rules
		$post_types = new SmartLearn_LMS_Post_Types();
		$post_types->register_post_types();
		
		flush_rewrite_rules();
		
		// Create default options
		add_option( 'smartlearn_lms_version', SMARTLEARN_LMS_VERSION );
	}
	
	public function deactivate() {
		flush_rewrite_rules();
	}
}

// Initialize plugin
function smartlearn_lms() {
	return SmartLearn_Courses_LMS::instance();
}

smartlearn_lms();
