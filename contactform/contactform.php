<?php
/* Plugin Name: Contact Form Custom
Plugin URL : testt.loc
Author : Dev
Version: 1.0
Description: Custom contact form with database storage
*/

add_action('wpcf7_before_send_mail', 'save_cf7_data_to_db');

function save_cf7_data_to_db($contact_form)
{
	$submission = WPCF7_Submission::get_instance();
	if ($submission) {
		$posted_data = $submission->get_posted_data();

		$name = isset($posted_data['your-name']) ? sanitize_text_field($posted_data['your-name']) : '';
		$email = isset($posted_data['your-email']) ? sanitize_email($posted_data['your-email']) : '';
		$message = isset($posted_data['your-message']) ? sanitize_textarea_field($posted_data['your-message']) : '';

		global $wpdb;
		$table_name = $wpdb->prefix . 'cf7_data';

		$wpdb->insert(
			$table_name,
			array(
				'name' => $name,
				'email' => $email,
				'message' => $message,
				'submitted_at' => current_time('mysql')
			)
		);
	}
}

register_activation_hook(__FILE__, 'create_cf7_data_table');

function create_cf7_data_table()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'cf7_data';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        message text NOT NULL,
        submitted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

add_action('admin_menu', 'cf7_data_menu');

function cf7_data_menu()
{
	add_menu_page(
		'CF7 Data',
		'CF7 Data',
		'manage_options',
		'cf7-data',
		'display_cf7_data'
	);
}

function display_cf7_data()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'cf7_data';

	if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
		$id = intval($_GET['delete']);
		$wpdb->delete($table_name, array('id' => $id));
		echo '<div class="updated"><p>Record deleted.</p></div>';
	}

	if (isset($_POST['update_record']) && isset($_POST['edit_id']) && is_numeric($_POST['edit_id'])) {
		$id = intval($_POST['edit_id']);
		$name = sanitize_text_field($_POST['name']);
		$email = sanitize_email($_POST['email']);
		$message = sanitize_textarea_field($_POST['message']);

		$update_result = $wpdb->update(
			$table_name,
			array(
				'name' => $name,
				'email' => $email,
				'message' => $message,
			),
			array('id' => $id)
		);

		if ($update_result !== false) {
			echo '<div class="updated"><p>Record updated successfully.</p></div>';
		} else {
			echo '<div class="error"><p>Error updating record. Please try again.</p></div>';
		}
	}

	if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
		$id = intval($_GET['edit']);
		$record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

		if ($record) {
			echo '<div class="wrap"><h2>Edit Submission</h2>';
			echo '<form method="post" action="">';
			echo '<input type="hidden" name="edit_id" value="' . $record->id . '">';
			echo '<p><label for="name">Name:</label>';
			echo '<input type="text" id="name" name="name" value="' . esc_attr($record->name) . '"></p>';
			echo '<p><label for="email">Email:</label>';
			echo '<input type="email" id="email" name="email" value="' . esc_attr($record->email) . '"></p>';
			echo '<p><label for="message">Message:</label>';
			echo '<textarea id="message" name="message">' . esc_textarea($record->message) . '</textarea></p>';
			echo '<p><input type="submit" name="update_record" value="Update"></p>';
			echo '</form></div>';
		}
	} else {

		$results = $wpdb->get_results("SELECT * FROM $table_name");

		echo '<div class="wrap"><h2>Contact Form 7 Submissions</h2>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th><th>Actions</th></tr></thead>';
		echo '<tbody>';

		foreach ($results as $row) {
			echo '<tr>';
			echo '<td>' . esc_html($row->name) . '</td>';
			echo '<td>' . esc_html($row->email) . '</td>';
			echo '<td>' . esc_html($row->message) . '</td>';
			echo '<td>' . esc_html($row->submitted_at) . '</td>';
			echo '<td>
                    <a href="?page=cf7-data&edit=' . $row->id . '">Edit</a> | 
                    <a href="?page=cf7-data&delete=' . $row->id . '" onclick="return confirm(\'Are you sure you want to delete this item?\')">Delete</a>
                  </td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}

add_shortcode('custom_contact_form', 'render_custom_contact_form');

function render_custom_contact_form()
{
	ob_start();
	?>
	<form id="custom-contact-form" method="post" action="">
		<p>
			<label for="custom-name">Name:</label>
			<input type="text" id="custom-name" name="custom_name" required>
		</p>
		<p>
			<label for="custom-email">Email:</label>
			<input type="email" id="custom-email" name="custom_email" required>
		</p>
		<p>
			<label for="custom-message">Message:</label>
			<textarea id="custom-message" name="custom_message" required></textarea>
		</p>
		<p>
			<input type="submit" name="custom_contact_form_submit" value="Send">
		</p>
	</form>
	<?php
	return ob_get_clean();
}

add_action('init', 'handle_custom_contact_form_submission');

function handle_custom_contact_form_submission()
{
	if (isset($_POST['custom_contact_form_submit'])) {
		$name = sanitize_text_field($_POST['custom_name']);
		$email = sanitize_email($_POST['custom_email']);
		$message = sanitize_textarea_field($_POST['custom_message']);

		global $wpdb;
		$table_name = $wpdb->prefix . 'cf7_data';

		$wpdb->insert(
			$table_name,
			array(
				'name' => $name,
				'email' => $email,
				'message' => $message,
				'submitted_at' => current_time('mysql')
			)
		);

		wp_redirect(add_query_arg('form_submitted', 'true', $_SERVER['REQUEST_URI']));
		exit;
	}
}


add_action('wp_footer', 'display_custom_form_success_message');

function display_custom_form_success_message()
{
	if (isset($_GET['form_submitted']) && $_GET['form_submitted'] == 'true') {
		echo '<p>Thank you for your message. We will get back to you shortly.</p>';
	}
}
