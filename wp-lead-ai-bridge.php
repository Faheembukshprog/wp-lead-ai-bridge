<?php
/**
 * Plugin Name: WP Lead AI Bridge
 * Plugin URI:  https://github.com/Faheembukshprog
 * Description: Captures Contact Form 7 / WPForms submissions (or any custom
 *              form via a public REST endpoint), logs every submission to a
 *              custom database table, and forwards the lead as JSON to an
 *              n8n webhook so it can be classified and routed by an AI
 *              automation pipeline. If n8n is unreachable the plugin fails
 *              gracefully: the form's own email still sends, and the log
 *              simply records that the forward failed.
 * Version:     1.0.0
 * Author:      Muhammad Faheem Khan
 * Author URI:  https://Faheembukshprog.com
 * License:     GPL v2 or later
 * Text Domain: wp-lead-ai-bridge
 */

// Block direct access — this file must only run inside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------
define( 'LAI_VERSION', '1.0.0' );
define( 'LAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LAI_TABLE', 'lai_lead_log' ); // gets $wpdb->prefix added at runtime

// -----------------------------------------------------------------------
// Activation: create the log table
// -----------------------------------------------------------------------
register_activation_hook( __FILE__, 'lai_create_log_table' );

function lai_create_log_table() {
	global $wpdb;
	$table_name      = $wpdb->prefix . LAI_TABLE;
	$charset_collate = $wpdb->get_charset_collate();

	// dbDelta needs each field on its own line, two spaces before the
	// field type, and no semicolons except the final line.
	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at DATETIME NOT NULL,
		source VARCHAR(20) NOT NULL,
		lead_name VARCHAR(190) NULL,
		lead_email VARCHAR(190) NULL,
		lead_message TEXT NULL,
		product_interest VARCHAR(190) NULL,
		payload LONGTEXT NULL,
		n8n_status VARCHAR(20) NOT NULL DEFAULT 'pending',
		n8n_http_code SMALLINT NULL,
		n8n_response LONGTEXT NULL,
		PRIMARY KEY  (id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// -----------------------------------------------------------------------
// Settings: Settings API field for the n8n webhook URL + timeout
// -----------------------------------------------------------------------
add_action( 'admin_init', 'lai_register_settings' );

function lai_register_settings() {
	register_setting( 'lai_settings_group', 'lai_webhook_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	) );
	register_setting( 'lai_settings_group', 'lai_timeout', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 10,
	) );

	add_settings_section( 'lai_main_section', 'n8n Connection', function () {
		echo '<p>Paste the Webhook URL from your n8n "Webhook" trigger node here.</p>';
	}, 'lai-settings' );

	add_settings_field( 'lai_webhook_url', 'n8n Webhook URL', function () {
		$val = esc_attr( get_option( 'lai_webhook_url', '' ) );
		echo "<input type='url' name='lai_webhook_url' value='{$val}' class='regular-text' placeholder='https://your-n8n-instance.com/webhook/lead-ai-bridge' />";
	}, 'lai-settings', 'lai_main_section' );

	add_settings_field( 'lai_timeout', 'Request Timeout (seconds)', function () {
		$val = esc_attr( get_option( 'lai_timeout', 10 ) );
		echo "<input type='number' min='1' max='30' name='lai_timeout' value='{$val}' class='small-text' />";
	}, 'lai-settings', 'lai_main_section' );
}

// -----------------------------------------------------------------------
// Core: sanitize a lead, log it, forward it to n8n
// -----------------------------------------------------------------------
/**
 * @param array $args {
 *   @type string $source            cf7 | wpforms | rest
 *   @type string $name
 *   @type string $email
 *   @type string $message
 *   @type string $product_interest
 *   @type array  $raw               full raw submission, for the log
 * }
 * @return array Result summary, useful as a REST response body.
 */
function lai_process_lead( $args ) {
	global $wpdb;
	$table = $wpdb->prefix . LAI_TABLE;

	$name    = sanitize_text_field( $args['name'] ?? '' );
	$email   = sanitize_email( $args['email'] ?? '' );
	$message = sanitize_textarea_field( $args['message'] ?? '' );
	$product = sanitize_text_field( $args['product_interest'] ?? '' );
	$source  = sanitize_key( $args['source'] ?? 'unknown' );
	$raw     = isset( $args['raw'] ) ? wp_json_encode( $args['raw'] ) : '';

	// Basic validation. We still log invalid attempts (status "invalid")
	// instead of silently dropping them, so nothing goes unrecorded.
	$is_valid = ( $email !== '' && is_email( $email ) && $name !== '' );

	$wpdb->insert( $table, array(
		'created_at'       => current_time( 'mysql' ),
		'source'           => $source,
		'lead_name'        => $name,
		'lead_email'       => $email,
		'lead_message'     => $message,
		'product_interest' => $product,
		'payload'          => $raw,
		'n8n_status'       => $is_valid ? 'pending' : 'invalid',
	) );
	$log_id = $wpdb->insert_id;

	if ( ! $is_valid ) {
		return array( 'ok' => false, 'reason' => 'invalid_lead', 'log_id' => $log_id );
	}

	$webhook_url = get_option( 'lai_webhook_url', '' );

	// Graceful path #1: no webhook configured yet. This is not an error —
	// the site keeps working, the form's own email still sends, we just
	// note in the log that nothing was forwarded.
	if ( empty( $webhook_url ) ) {
		$wpdb->update( $table, array( 'n8n_status' => 'skipped' ), array( 'id' => $log_id ) );
		return array( 'ok' => true, 'reason' => 'no_webhook_configured', 'log_id' => $log_id );
	}

	$payload = array(
		'source'           => $source,
		'name'             => $name,
		'email'            => $email,
		'message'          => $message,
		'product_interest' => $product,
		'site'             => home_url(),
		'submitted_at'     => current_time( 'mysql' ),
	);

	$response = wp_remote_post( $webhook_url, array(
		'timeout' => (int) get_option( 'lai_timeout', 10 ),
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $payload ),
	) );

	// Graceful path #2: n8n is down / unreachable. wp_remote_post never
	// throws — it returns a WP_Error object instead — so we catch that
	// and log it as a failure without breaking the site or the form.
	if ( is_wp_error( $response ) ) {
		$wpdb->update( $table, array(
			'n8n_status'   => 'failed',
			'n8n_response' => $response->get_error_message(),
		), array( 'id' => $log_id ) );
		return array( 'ok' => false, 'reason' => 'n8n_unreachable', 'log_id' => $log_id );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$status = ( $code >= 200 && $code < 300 ) ? 'sent' : 'failed';

	$wpdb->update( $table, array(
		'n8n_status'     => $status,
		'n8n_http_code'  => $code,
		'n8n_response'   => $body,
	), array( 'id' => $log_id ) );

	return array( 'ok' => ( $status === 'sent' ), 'reason' => $status, 'log_id' => $log_id, 'http_code' => $code );
}

// -----------------------------------------------------------------------
// Contact Form 7 hook — fires after CF7 has already sent its own email,
// so we only add a side effect, never block the original form behaviour.
// -----------------------------------------------------------------------
add_action( 'wpcf7_mail_sent', 'lai_handle_cf7_submission' );

function lai_handle_cf7_submission( $contact_form ) {
	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}
	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}
	$data = $submission->get_posted_data();

	// Common CF7 field-name conventions. If a site uses different tag
	// names, adjust this map in SETUP.md's "customize field mapping" step.
	$name    = lai_first_present( $data, array( 'your-name', 'name', 'your-firstname' ) );
	$email   = lai_first_present( $data, array( 'your-email', 'email' ) );
	$message = lai_first_present( $data, array( 'your-message', 'message' ) );
	$product = lai_first_present( $data, array( 'product-interest', 'your-product', 'product' ) );

	lai_process_lead( array(
		'source'           => 'cf7',
		'name'             => $name,
		'email'            => $email,
		'message'          => $message,
		'product_interest' => $product,
		'raw'              => $data,
	) );
}

// -----------------------------------------------------------------------
// WPForms hook — fires after WPForms has processed the entry (which
// includes sending its own notification emails), same non-blocking idea.
// -----------------------------------------------------------------------
add_action( 'wpforms_process_complete', 'lai_handle_wpforms_submission', 10, 4 );

function lai_handle_wpforms_submission( $fields, $entry, $form_data, $entry_id ) {
	$name = $email = $message = $product = '';

	foreach ( (array) $fields as $field ) {
		$label = strtolower( $field['name'] ?? ( $field['label'] ?? '' ) );
		$value = $field['value'] ?? '';
		$type  = $field['type'] ?? '';

		if ( $type === 'email' || strpos( $label, 'email' ) !== false ) {
			$email = $value;
		} elseif ( $type === 'textarea' || strpos( $label, 'message' ) !== false ) {
			$message = $value;
		} elseif ( strpos( $label, 'product' ) !== false || strpos( $label, 'interest' ) !== false ) {
			$product = $value;
		} elseif ( strpos( $label, 'name' ) !== false && $name === '' ) {
			$name = $value;
		}
	}

	lai_process_lead( array(
		'source'           => 'wpforms',
		'name'             => $name,
		'email'            => $email,
		'message'          => $message,
		'product_interest' => $product,
		'raw'              => $fields,
	) );
}

/** Small helper: return the first non-empty value found for a list of keys. */
function lai_first_present( $data, $keys ) {
	foreach ( $keys as $key ) {
		if ( ! empty( $data[ $key ] ) ) {
			return $data[ $key ];
		}
	}
	return '';
}

// -----------------------------------------------------------------------
// Public REST endpoint — lets any custom form (or a curl/Postman test)
// submit a lead without depending on CF7 or WPForms at all.
// -----------------------------------------------------------------------
add_action( 'rest_api_init', function () {
	register_rest_route( 'lead-ai-bridge/v1', '/submit', array(
		'methods'             => 'POST',
		'callback'            => 'lai_handle_rest_submission',
		'permission_callback' => '__return_true', // public: this is a lead-capture form endpoint
	) );
} );

function lai_handle_rest_submission( WP_REST_Request $request ) {
	$data = $request->get_json_params();
	if ( empty( $data ) ) {
		$data = $request->get_params();
	}

	// Honeypot: a hidden field named "website" that a real visitor would
	// never fill in. If it has a value, treat it as spam and bail early.
	if ( ! empty( $data['website'] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'reason' => 'spam_detected' ), 200 );
	}

	$result = lai_process_lead( array(
		'source'           => 'rest',
		'name'             => $data['name'] ?? '',
		'email'            => $data['email'] ?? '',
		'message'          => $data['message'] ?? '',
		'product_interest' => $data['product_interest'] ?? '',
		'raw'              => $data,
	) );

	$status_code = $result['ok'] ? 200 : 200; // always 200 so the caller can read the JSON body itself
	return new WP_REST_Response( $result, $status_code );
}

// -----------------------------------------------------------------------
// Admin menu: Settings page + Lead Log page
// -----------------------------------------------------------------------
add_action( 'admin_menu', 'lai_register_admin_menu' );

function lai_register_admin_menu() {
	add_menu_page(
		'Lead AI Bridge',
		'Lead AI Bridge',
		'manage_options',
		'lai-settings',
		'lai_render_settings_page',
		'dashicons-randomize',
		58
	);
	add_submenu_page( 'lai-settings', 'Settings', 'Settings', 'manage_options', 'lai-settings', 'lai_render_settings_page' );
	add_submenu_page( 'lai-settings', 'Lead Log', 'Lead Log', 'manage_options', 'lai-lead-log', 'lai_render_log_page' );
}

function lai_render_settings_page() {
	?>
	<div class="wrap">
		<h1>Lead AI Bridge — Settings</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'lai_settings_group' );
			do_settings_sections( 'lai-settings' );
			submit_button( 'Save Settings' );
			?>
		</form>
		<p>REST endpoint for custom forms: <code><?php echo esc_url( rest_url( 'lead-ai-bridge/v1/submit' ) ); ?></code></p>
	</div>
	<?php
}

function lai_render_log_page() {
	if ( is_admin() ) {
		require_once LAI_PLUGIN_DIR . 'includes/class-lead-log-list-table.php';
	}
	$list_table = new LAI_Lead_Log_List_Table();
	$list_table->prepare_items();
	?>
	<div class="wrap">
		<h1>Lead AI Bridge — Lead Log</h1>
		<p>Every form submission that passed through this plugin, and whether it reached n8n.</p>
		<?php $list_table->display(); ?>
	</div>
	<?php
}
