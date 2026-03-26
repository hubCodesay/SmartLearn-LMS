<?php
/**
 * Course notifications (SMS via email).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	class SmartLearn_LMS_Notifications {
	const LOG_TABLE_SUFFIX = 'smartlearn_lms_sms_logs';

	public function __construct() {
		add_action( 'smartlearn_lms_course_start_notify', array( $this, 'handle_course_start_notify' ), 10, 1 );
		add_action( 'smartlearn_lms_access_changed', array( $this, 'handle_access_changed' ), 10, 4 );
		add_action( 'wp_ajax_smartlearn_lms_send_test_sms', array( $this, 'handle_send_test_sms' ) );
		add_action( 'wp_ajax_smartlearn_lms_send_now_sms', array( $this, 'handle_send_now_sms' ) );
	}

	/**
	 * Notify user when access is granted or extended.
	 *
	 * @param int    $user_id
	 * @param int    $course_id
	 * @param string $event
	 * @param string $expires_at
	 * @return void
	 */
	public function handle_access_changed( $user_id, $course_id, $event, $expires_at = '' ) {
		$user_id = absint( $user_id );
		$course_id = absint( $course_id );
		$event = sanitize_key( (string) $event );
		$expires_at = (string) $expires_at;

		if ( ! $user_id || ! $course_id ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$email = sanitize_email( $user->user_email );
		if ( '' === $email ) {
			return;
		}

		$course_title = get_the_title( $course_id );
		$course_title = $course_title ? $course_title : ( '#' . $course_id );
		$course_url = get_permalink( $course_id );

		if ( 'granted' === $event ) {
			$message = sprintf(
				/* translators: %s: course title */
				__( 'Вам надано доступ до курсу "%s".', 'smartlearn-lms' ),
				$course_title
			);
		} else {
			$message = sprintf(
				/* translators: %s: course title */
				__( 'Ваш доступ до курсу "%s" продовжено.', 'smartlearn-lms' ),
				$course_title
			);
		}

		$is_lifetime = ( '' === $expires_at || '2099-12-31 23:59:59' === $expires_at );
		if ( ! $is_lifetime ) {
			$message .= "\n\n" . sprintf(
				/* translators: %s: expiration datetime */
				__( 'Доступ дійсний до: %s', 'smartlearn-lms' ),
				$expires_at
			);
		} else {
			$message .= "\n\n" . __( 'Доступ безстроковий.', 'smartlearn-lms' );
		}

		if ( $course_url ) {
			$message .= "\n\n" . sprintf(
				/* translators: %s: course URL */
				__( 'Посилання на курс: %s', 'smartlearn-lms' ),
				$course_url
			);
		}

		$this->send_sms_email( $email, $message, $course_id, $user_id );
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::LOG_TABLE_SUFFIX;
	}

	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_email varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			message text NULL,
			error text NULL,
			sent_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY sent_at (sent_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function schedule_course_notification( $course_id, $timestamp ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return;
		}

		wp_clear_scheduled_hook( 'smartlearn_lms_course_start_notify', array( $course_id ) );

		if ( $timestamp && $timestamp > time() ) {
			wp_schedule_single_event( $timestamp, 'smartlearn_lms_course_start_notify', array( $course_id ) );
		}
	}

	public static function get_course_logs( $course_id, $limit = 50 ) {
		global $wpdb;
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return array();
		}

		$table_name = self::get_table_name();
		$limit = absint( $limit );
		if ( 0 === $limit ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE course_id = %d ORDER BY sent_at DESC, id DESC",
					$course_id
				)
			);
		}
		$limit = max( 1, $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE course_id = %d ORDER BY sent_at DESC, id DESC LIMIT %d",
				$course_id,
				$limit
			)
		);
	}

	public function handle_course_start_notify( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return;
		}

		$enabled = get_post_meta( $course_id, '_smartlearn_course_notify_on_start', true );
		if ( '1' !== $enabled ) {
			return;
		}

		$message = (string) get_post_meta( $course_id, '_smartlearn_course_start_sms', true );
		$scheduled_ts = (int) get_post_meta( $course_id, '_smartlearn_course_start_ts', true );
		$last_sent_ts = (int) get_post_meta( $course_id, '_smartlearn_course_last_sent_ts', true );
		if ( $scheduled_ts && $last_sent_ts === $scheduled_ts ) {
			return;
		}

		$sent = $this->send_course_sms_now( $course_id, $message );
		if ( $sent && $scheduled_ts ) {
			update_post_meta( $course_id, '_smartlearn_course_last_sent_ts', $scheduled_ts );
		}
	}

	private function send_course_sms_now( $course_id, $message ) {
		$course_id = absint( $course_id );
		$message = trim( (string) $message );
		if ( ! $course_id || '' === $message ) {
			return false;
		}

		if ( ! class_exists( 'SmartLearn_LMS_Manual_Access' ) ) {
			return false;
		}

		$user_ids = SmartLearn_LMS_Manual_Access::get_course_user_ids( $course_id, true );
		if ( empty( $user_ids ) ) {
			return false;
		}

		foreach ( $user_ids as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}
			$email = sanitize_email( $user->user_email );
			if ( '' === $email ) {
				continue;
			}
			$this->send_sms_email( $email, $message, $course_id, $user_id );
		}

		return true;
	}

	private function send_sms_email( $email, $message, $course_id = 0, $user_id = 0 ) {
		$course_id = absint( $course_id );
		$user_id = absint( $user_id );
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return false;
		}

		$subject = __( 'SMS повідомлення', 'smartlearn-lms' );
		if ( $course_id ) {
			$course_title = get_the_title( $course_id );
			if ( $course_title ) {
				$subject = sprintf( __( 'SMS: %s', 'smartlearn-lms' ), $course_title );
			}
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent = wp_mail( $email, $subject, $message, $headers );

		$this->log_sms( $course_id, $user_id, $email, $sent ? 'sent' : 'failed', $message, $sent ? '' : __( 'Помилка відправки.', 'smartlearn-lms' ) );

		return $sent;
	}

	private function log_sms( $course_id, $user_id, $email, $status, $message, $error = '' ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$wpdb->insert(
			$table_name,
			array(
				'course_id' => absint( $course_id ),
				'user_id' => absint( $user_id ),
				'user_email' => sanitize_email( $email ),
				'status' => sanitize_key( $status ),
				'message' => sanitize_textarea_field( $message ),
				'error' => sanitize_textarea_field( $error ),
				'sent_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function handle_send_test_sms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'smartlearn-lms' ) ) );
		}

		check_ajax_referer( 'smartlearn_lms_send_test_sms', 'nonce' );

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === $email || '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Вкажіть email і текст повідомлення.', 'smartlearn-lms' ) ) );
		}

		$sent = $this->send_sms_email( $email, $message, $course_id, 0 );
		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Не вдалося відправити тест.', 'smartlearn-lms' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Тестове повідомлення відправлено.', 'smartlearn-lms' ) ) );
	}

	public function handle_send_now_sms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'smartlearn-lms' ) ) );
		}

		check_ajax_referer( 'smartlearn_lms_send_now_sms', 'nonce' );

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $course_id || '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Вкажіть текст повідомлення.', 'smartlearn-lms' ) ) );
		}

		$sent = $this->send_course_sms_now( $course_id, $message );
		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Немає користувачів для відправки.', 'smartlearn-lms' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Повідомлення відправлено всім користувачам курсу.', 'smartlearn-lms' ) ) );
	}
}
