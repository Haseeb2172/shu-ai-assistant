<?php
/** Admin-only browser for retained conversations, including chats without a lead. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$sessions = SHU_Analytics::get_conversations( $page );
$total    = SHU_Analytics::count_conversations();
$selected = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
$turns    = preg_match( '/^[a-f0-9]{32,64}$/', $selected ) ? SHU_Analytics::get_transcript( $selected ) : array();
$base_url = admin_url( 'options-general.php?page=shu-ai-assistant&tab=conversations' );
$options  = shu_ai_get_options();
?>
<h2>Conversations</h2>
<p>Visitor and assistant turns follow the configured retention period (<?php echo (int) $options['conversation_retention_days']; ?> days; 0 means no automatic cleanup). Captured lead snapshots remain on the Leads tab. Only site administrators can see these records.</p>
<?php if ( $turns ) : ?>
	<section class="shu-conversation-detail" aria-label="Selected conversation">
		<h3>Conversation <?php echo esc_html( substr( $selected, 0, 12 ) ); ?>…</h3>
		<p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'shu_export' => 'conversation', 'session_id' => $selected, 'shu_nonce' => wp_create_nonce( 'shu_export_nonce' ) ), $base_url ) ); ?>">Download transcript (.txt)</a></p>
		<div class="shu-chat-transcript-view">
			<?php foreach ( $turns as $turn ) : ?>
				<div class="shu-modal-turn <?php echo 'user' === $turn['role'] ? 'visitor' : 'assistant'; ?>">
					<div class="shu-turn-sender"><?php echo 'user' === $turn['role'] ? 'Visitor' : 'Assistant'; ?> · <?php echo esc_html( $turn['created_at'] ); ?></div>
					<div class="shu-turn-bubble"><?php echo esc_html( $turn['message'] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>
<table class="wp-list-table widefat fixed striped">
	<thead><tr><th>Session</th><th>Started (site time)</th><th>Last activity</th><th>Turns</th><th>Transcript</th></tr></thead>
	<tbody>
	<?php if ( ! $sessions ) : ?>
		<tr><td colspan="5">No conversations in the retained window.</td></tr>
	<?php else : ?>
		<?php foreach ( $sessions as $session ) : ?>
			<tr>
				<td><code><?php echo esc_html( substr( $session['session_id'], 0, 12 ) ); ?>…</code></td>
				<td><?php echo esc_html( $session['started_at'] ); ?></td>
				<td><?php echo esc_html( $session['last_at'] ); ?></td>
				<td><?php echo (int) $session['turns']; ?></td>
				<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'session_id' => $session['session_id'], 'paged' => $page ), $base_url ) ); ?>">View</a></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>
<?php if ( $total > 20 ) : ?>
	<p class="shu-pagination">
		<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base_url ) ); ?>">Previous</a><?php endif; ?>
		<span>Page <?php echo (int) $page; ?> of <?php echo (int) ceil( $total / 20 ); ?></span>
		<?php if ( $page * 20 < $total ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base_url ) ); ?>">Next</a><?php endif; ?>
	</p>
<?php endif; ?>
