<?php
/**
 * Leads tab view: priority badges (Hot/Warm/Cold), expandable service details,
 * redesigned transcript modal, TXT export, review request action, and lead scoring settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$leads_service = SHU_Ai_Assistant::instance()->leads;

$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$priority = isset( $_GET['priority'] ) ? sanitize_text_field( wp_unslash( $_GET['priority'] ) ) : '';
$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
$order    = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc';
$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page = 25;

$args = array(
	'search'   => $search,
	'status'   => $status,
	'priority' => $priority,
	'orderby'  => $orderby,
	'order'    => $order,
	'per_page' => $per_page,
	'paged'    => $paged,
);

$leads       = $leads_service->get_leads( $args );
$total_leads = $leads_service->count_leads( $args );
$total_pages = max( 1, (int) ceil( $total_leads / $per_page ) );

$nonce = wp_create_nonce( 'shu_export_nonce' );

$csv_export_url = add_query_arg(
	array(
		'page'       => 'shu-ai-assistant',
		'tab'        => 'leads',
		'shu_export' => 'csv',
		'shu_nonce'  => $nonce,
	),
	admin_url( 'options-general.php' )
);

function shu_sort_link_v2( $label, $field, $current_orderby, $current_order, $extra_args ) {
	$new_order = ( $field === $current_orderby && 'asc' === $current_order ) ? 'desc' : 'asc';
	$url       = add_query_arg( array_merge( $extra_args, array( 'orderby' => $field, 'order' => $new_order ) ) );
	$arrow     = $field === $current_orderby ? ( 'asc' === $current_order ? ' &uarr;' : ' &darr;' ) : '';
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
}

$base_args = array( 'page' => 'shu-ai-assistant', 'tab' => 'leads', 's' => $search, 'status' => $status, 'priority' => $priority );
$options   = shu_ai_get_options();
?>
<h2>Captured Consultation Leads</h2>

<form method="get" class="shu-leads-filters" style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
	<input type="hidden" name="page" value="shu-ai-assistant" />
	<input type="hidden" name="tab" value="leads" />
	<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, phone, email, location..." class="regular-text" />
	
	<select name="priority">
		<option value="">All Priorities</option>
		<option value="hot" <?php selected( $priority, 'hot' ); ?>>🔥 Hot</option>
		<option value="warm" <?php selected( $priority, 'warm' ); ?>>⚡ Warm</option>
		<option value="cold" <?php selected( $priority, 'cold' ); ?>>❄️ Cold</option>
	</select>

	<select name="status">
		<option value="">All Statuses</option>
		<option value="new" <?php selected( $status, 'new' ); ?>>New</option>
		<option value="contacted" <?php selected( $status, 'contacted' ); ?>>Contacted</option>
		<option value="closed" <?php selected( $status, 'closed' ); ?>>Closed</option>
	</select>

	<?php submit_button( 'Filter Leads', 'secondary', '', false ); ?>
	<a href="<?php echo esc_url( $csv_export_url ); ?>" class="button button-primary">Export All CSV</a>
</form>

<table class="wp-list-table widefat fixed striped shu-leads-table">
	<thead>
		<tr>
			<th style="width:90px;"><?php echo shu_sort_link_v2( 'Priority', 'priority', $orderby, $order, $base_args ); ?></th>
			<th><?php echo shu_sort_link_v2( 'Name', 'name', $orderby, $order, $base_args ); ?></th>
			<th>Contact Info</th>
			<th><?php echo shu_sort_link_v2( 'Service', 'service', $orderby, $order, $base_args ); ?></th>
			<th>Location & Urgency</th>
			<th>Details</th>
			<th><?php echo shu_sort_link_v2( 'Status', 'status', $orderby, $order, $base_args ); ?></th>
			<th><?php echo shu_sort_link_v2( 'Received', 'created_at', $orderby, $order, $base_args ); ?></th>
			<th style="width:160px;">Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $leads ) ) : ?>
			<tr><td colspan="9">No leads matching filter criteria.</td></tr>
		<?php else : ?>
			<?php foreach ( $leads as $lead ) : ?>
				<?php
				$p_badge_class = 'shu-badge-warm';
				$p_label       = 'WARM';
				if ( 'hot' === $lead['priority'] ) {
					$p_badge_class = 'shu-badge-hot';
					$p_label       = 'HOT';
				} elseif ( 'cold' === $lead['priority'] ) {
					$p_badge_class = 'shu-badge-cold';
					$p_label       = 'COLD';
				}

				$txt_url = add_query_arg(
					array(
						'page'       => 'shu-ai-assistant',
						'tab'        => 'leads',
						'shu_export' => 'txt',
						'lead_id'    => $lead['id'],
						'shu_nonce'  => $nonce,
					),
					admin_url( 'options-general.php' )
				);
				?>
				<tr>
					<td><span class="shu-priority-badge <?php echo $p_badge_class; ?>"><?php echo $p_label; ?></span></td>
					<td><strong><?php echo esc_html( $lead['name'] ); ?></strong></td>
					<td>
						<?php echo esc_html( $lead['phone'] ); ?><br/>
						<small><?php echo esc_html( $lead['email'] ); ?></small>
					</td>
					<td><?php echo esc_html( $lead['service'] ); ?></td>
					<td>
						<strong><?php echo esc_html( $lead['location'] ); ?></strong><br/>
						<small><?php echo esc_html( $lead['urgency'] ); ?></small>
					</td>
					<td>
						<?php
						if ( ! empty( $lead['extra_fields'] ) ) {
							$extra = json_decode( $lead['extra_fields'], true );
							if ( is_array( $extra ) && ! empty( $extra ) ) {
								echo '<details><summary>View Details (' . count( $extra ) . ')</summary><div class="shu-extra-details">';
								foreach ( $extra as $k => $v ) {
									echo '<strong>' . esc_html( SHU_Lead_Fields::get_field_label( $lead['service'], $k ) ) . ':</strong> ' . esc_html( $v ) . '<br/>';
								}
								echo '</div></details>';
							} else {
								echo '<span class="description">Core fields</span>';
							}
						} else {
							echo '<span class="description">Core fields</span>';
						}
						?>
					</td>
					<td>
						<form method="post" class="shu-status-form">
							<?php wp_nonce_field( 'shu_update_lead_status' ); ?>
							<input type="hidden" name="shu_action" value="update_lead_status" />
							<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead['id'] ); ?>" />
							<select name="status" onchange="this.form.submit()">
								<option value="new" <?php selected( $lead['status'], 'new' ); ?>>New</option>
								<option value="contacted" <?php selected( $lead['status'], 'contacted' ); ?>>Contacted</option>
								<option value="closed" <?php selected( $lead['status'], 'closed' ); ?>>Closed</option>
							</select>
						</form>
					</td>
					<td><small><?php echo esc_html( $lead['created_at'] ); ?></small></td>
					<td>
						<button type="button" class="button button-small" onclick="shuOpenTranscriptModal(<?php echo (int) $lead['id']; ?>)">Transcript</button>
						<a href="<?php echo esc_url( $txt_url ); ?>" class="button button-small">.TXT</a>

						<?php if ( 'closed' === $lead['status'] ) : ?>
							<form method="post" style="display:inline-block; margin-top:4px;">
								<?php wp_nonce_field( 'shu_send_review_request' ); ?>
								<input type="hidden" name="shu_action" value="send_review_request" />
								<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead['id'] ); ?>" />
								<?php if ( ! empty( $lead['review_requested_at'] ) ) : ?>
									<button type="submit" class="button button-small" title="Sent at <?php echo esc_attr( $lead['review_requested_at'] ); ?>">Resend Review</button>
								<?php else : ?>
									<button type="submit" class="button button-small button-primary">Review Request</button>
								<?php endif; ?>
							</form>
						<?php endif; ?>

						<!-- Hidden transcript template for modal -->
						<div id="shu_transcript_data_<?php echo (int) $lead['id']; ?>" style="display:none;">
							<div class="shu-modal-header-info">
								<h3>Conversation Transcript - Lead #<?php echo (int) $lead['id']; ?> (<?php echo esc_html( $lead['name'] ); ?>)</h3>
								<p><strong>Service:</strong> <?php echo esc_html( $lead['service'] ); ?> | <strong>Urgency:</strong> <?php echo esc_html( $lead['urgency'] ); ?></p>
							</div>
							<div class="shu-chat-transcript-view">
								<?php
								$transcript = json_decode( $lead['conversation_transcript'], true );
								if ( is_array( $transcript ) ) {
									foreach ( $transcript as $turn ) {
										$is_user  = isset( $turn['role'] ) && 'user' === $turn['role'];
										$cls      = $is_user ? 'visitor' : 'assistant';
										$sender   = $is_user ? esc_html( $lead['name'] ) : 'SHU Assistant';
										$content  = isset( $turn['content'] ) ? $turn['content'] : '';
										echo '<div class="shu-modal-turn ' . $cls . '">';
										$when = ! empty( $turn['created_at'] ) ? ' · ' . esc_html( $turn['created_at'] ) : '';
										echo '<div class="shu-turn-sender">' . $sender . $when . '</div>';
										echo '<div class="shu-turn-bubble">' . esc_html( $content ) . '</div>';
										echo '</div>';
									}
								} else {
									echo '<p><em>No detailed transcript turns available.</em></p>';
								}
								?>
							</div>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav">
		<div class="tablenav-pages">
			<?php
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$page_url = add_query_arg( array_merge( $base_args, array( 'paged' => $i, 'orderby' => $orderby, 'order' => $order ) ) );
				$class    = $i === $paged ? 'class="button button-primary"' : 'class="button"';
				echo '<a ' . $class . ' href="' . esc_url( $page_url ) . '">' . esc_html( $i ) . '</a> ';
			}
			?>
		</div>
	</div>
<?php endif; ?>

<hr style="margin-top:30px;"/>

<h2>Lead Scoring Rule Configuration</h2>
<form method="post" action="">
	<?php wp_nonce_field( 'shu_save_scoring' ); ?>
	<input type="hidden" name="shu_action" value="save_scoring" />

	<table class="form-table">
		<tr>
			<th scope="row"><label for="scoring_hot_keywords">Hot Urgency Keywords</label></th>
			<td>
				<textarea name="scoring_hot_keywords" id="scoring_hot_keywords" rows="3" class="large-text code"><?php echo esc_textarea( isset( $options['scoring_hot_keywords'] ) ? $options['scoring_hot_keywords'] : "today\nasap\nimmediately\nthis week\nurgent\nright now\nnext 24 hours" ); ?></textarea>
				<p class="description">One keyword/phrase per line. Leads matching these urgency phrases automatically score <strong>HOT</strong>.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="scoring_high_value_services">High-Value Services (Hot)</label></th>
			<td>
				<textarea name="scoring_high_value_services" id="scoring_high_value_services" rows="2" class="large-text code"><?php echo esc_textarea( isset( $options['scoring_high_value_services'] ) ? $options['scoring_high_value_services'] : "Executive & Personal Protection" ); ?></textarea>
				<p class="description">One service line per line. Leads requesting these services automatically score <strong>HOT</strong>.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="scoring_cold_keywords">Cold Urgency Keywords</label></th>
			<td>
				<textarea name="scoring_cold_keywords" id="scoring_cold_keywords" rows="3" class="large-text code"><?php echo esc_textarea( isset( $options['scoring_cold_keywords'] ) ? $options['scoring_cold_keywords'] : "just researching\nnot sure yet\na few months\nno timeline\nexploratory\nsometime this year\nnext year\nnot urgent\nno rush" ); ?></textarea>
				<p class="description">One keyword/phrase per line. Leads matching these phrases automatically score <strong>COLD</strong>.</p>
			</td>
		</tr>
	</table>
	<?php submit_button( 'Save Lead Scoring Rules' ); ?>
</form>

<!-- Modal Container for Transcript Viewer -->
<div id="shu_modal_overlay" class="shu-modal-overlay" aria-hidden="true" onclick="shuCloseTranscriptModal(event)">
	<div class="shu-modal-content" role="dialog" aria-modal="true" aria-label="Lead conversation transcript" onclick="event.stopPropagation()">
		<button type="button" class="shu-modal-close" aria-label="Close transcript" onclick="shuCloseTranscriptModal()">&times;</button>
		<div id="shu_modal_body"></div>
	</div>
</div>

<script>
var shuTranscriptTrigger = null;
function shuOpenTranscriptModal(leadId) {
	var src = document.getElementById('shu_transcript_data_' + leadId);
	if (!src) return;
	shuTranscriptTrigger = document.activeElement;
	document.getElementById('shu_modal_body').innerHTML = src.innerHTML;
	var overlay = document.getElementById('shu_modal_overlay');
	overlay.style.display = 'flex';
	overlay.setAttribute('aria-hidden', 'false');
	overlay.querySelector('.shu-modal-close').focus();
}

function shuCloseTranscriptModal(e) {
	if (!e || e.target.id === 'shu_modal_overlay' || e.target.className === 'shu-modal-close') {
		var overlay = document.getElementById('shu_modal_overlay');
		overlay.style.display = 'none';
		overlay.setAttribute('aria-hidden', 'true');
		if (shuTranscriptTrigger) shuTranscriptTrigger.focus();
	}
}
document.addEventListener('keydown', function(e) {
	var overlay = document.getElementById('shu_modal_overlay');
	if (overlay.style.display !== 'flex') return;
	if (e.key === 'Escape') { shuCloseTranscriptModal(); return; }
	if (e.key === 'Tab') {
		e.preventDefault();
		overlay.querySelector('.shu-modal-close').focus();
	}
});
</script>
