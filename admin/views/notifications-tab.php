<?php
/**
 * Notifications & Review Settings Tab View.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = shu_ai_get_options();
?>
<h2>Notifications & Google Review Settings</h2>

<form method="post" action="">
	<?php wp_nonce_field( 'shu_save_notifications' ); ?>
	<input type="hidden" name="shu_action" value="save_notifications" />

	<table class="form-table">
		<tr>
			<th scope="row"><label for="notify_emails">Notification Recipients</label></th>
			<td>
				<input type="text" name="notify_emails" id="notify_emails" value="<?php echo esc_attr( $options['notify_emails'] ); ?>" class="large-text" />
				<p class="description">Comma-separated email addresses to receive lead alerts and weekly performance digests.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">New Lead Alerts</th>
			<td>
				<label><input type="checkbox" name="notify_enabled" value="1" <?php checked( $options['notify_enabled'], 1 ); ?> /> Email notification immediately when a new lead is captured</label>
			</td>
		</tr>
		<tr>
			<th scope="row">Weekly Performance Digest</th>
			<td>
				<label><input type="checkbox" name="notify_weekly_digest" value="1" <?php checked( isset( $options['notify_weekly_digest'] ) ? $options['notify_weekly_digest'] : 1, 1 ); ?> /> Send weekly summary email every Monday morning (Total chats, Hot/Warm/Cold leads, Conversion rate, Top questions & gaps)</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="google_review_url">Google Review Link URL</label></th>
			<td>
				<input type="url" name="google_review_url" id="google_review_url" value="<?php echo esc_attr( isset( $options['google_review_url'] ) ? $options['google_review_url'] : '' ); ?>" class="large-text" placeholder="https://g.page/r/your-business/review" />
				<p class="description">The URL used when clicking "Review Request" on closed leads in the Leads tab.</p>
			</td>
		</tr>
	</table>

	<?php submit_button( 'Save Notification & Review Settings' ); ?>
</form>
