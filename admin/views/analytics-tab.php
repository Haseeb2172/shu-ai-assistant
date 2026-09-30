<?php
/**
 * Analytics Tab View: Operational visibility dashboard showing conversation volume,
 * lead conversion rates, top asked questions, and low-relevance coverage gaps.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$days = isset( $_GET['days'] ) && 90 === (int) $_GET['days'] ? 90 : 30;

$session_count  = SHU_Analytics::get_session_count( $days );
$lead_count     = SHU_Analytics::get_lead_count( $days );
$conversion_rate = $session_count > 0 ? round( ( $lead_count / $session_count ) * 100, 1 ) : 0;

$daily_volume  = SHU_Analytics::get_daily_volume( $days );
$top_questions = SHU_Analytics::get_top_questions( $days, 10 );
$gaps          = SHU_Analytics::get_coverage_gaps( $days, 10 );
?>
<h2>Operational Visibility & Analytics</h2>

<div class="shu-analytics-filter" style="margin-bottom:20px;">
	<strong>Lookback Window: </strong>
	<a href="?page=shu-ai-assistant&tab=analytics&days=30" class="button <?php echo 30 === $days ? 'button-primary' : ''; ?>">Last 30 Days</a>
	<a href="?page=shu-ai-assistant&tab=analytics&days=90" class="button <?php echo 90 === $days ? 'button-primary' : ''; ?>">Last 90 Days</a>
</div>

<div class="shu-metrics-grid">
	<div class="shu-metric-card">
		<div class="shu-metric-label">Total Conversations</div>
		<div class="shu-metric-val"><?php echo esc_html( number_format( $session_count ) ); ?></div>
		<div class="shu-metric-sub">Distinct visitor sessions</div>
	</div>
	<div class="shu-metric-card">
		<div class="shu-metric-label">Leads Captured</div>
		<div class="shu-metric-val"><?php echo esc_html( number_format( $lead_count ) ); ?></div>
		<div class="shu-metric-sub">Qualified consultations</div>
	</div>
	<div class="shu-metric-card">
		<div class="shu-metric-label">Lead Conversion Rate</div>
		<div class="shu-metric-val"><?php echo esc_html( $conversion_rate ); ?>%</div>
		<div class="shu-metric-sub">Leads &divide; Chat Sessions</div>
	</div>
</div>

<div class="shu-analytics-grid">
	<div class="shu-analytics-card">
		<h3>Top 10 Most-Asked Questions</h3>
		<p class="description">Grouped question themes based on keyword clustering of visitor messages.</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Question Theme</th>
					<th style="width:70px;">Asks</th>
					<th>Representative Sample</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $top_questions ) ) : ?>
					<tr><td colspan="3">No questions logged in this period.</td></tr>
				<?php else : ?>
					<?php foreach ( $top_questions as $q ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $q['cluster'] ); ?></strong></td>
							<td><span class="shu-badge-count"><?php echo esc_html( $q['count'] ); ?></span></td>
							<td><em>&ldquo;<?php echo esc_html( $q['sample'] ); ?>&rdquo;</em></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="shu-analytics-card">
		<h3>Questions We Couldn't Answer Well (Coverage Gaps)</h3>
		<p class="description">Visitor questions where the Knowledge Base search scored near 0. Useful for deciding what site content to write next.</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Visitor Message / Query</th>
					<th style="width:70px;">Count</th>
					<th>Last Asked</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $gaps ) ) : ?>
					<tr><td colspan="3">No coverage gaps detected! Your KB indexed content covers visitor queries well.</td></tr>
				<?php else : ?>
					<?php foreach ( $gaps as $g ) : ?>
						<tr>
							<td><strong>&ldquo;<?php echo esc_html( $g['message'] ); ?>&rdquo;</strong></td>
							<td><span class="shu-badge-gap"><?php echo esc_html( $g['count'] ); ?></span></td>
							<td><small><?php echo esc_html( $g['last_asked'] ); ?></small></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
