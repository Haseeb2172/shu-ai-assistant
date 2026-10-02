<?php
/**
 * Knowledge Base settings tab: English & Spanish Key Facts textareas and re-index action.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options      = shu_ai_get_options();
$last_indexed = get_option( 'shu_ai_kb_last_indexed', 'Never' );
$chunk_count  = get_option( 'shu_ai_kb_chunk_count', 0 );
?>
<h2>Knowledge Base Indexing & Manual Facts</h2>

<div class="card shu-kb-status-card">
	<h3>Auto-Indexed Content Status</h3>
	<p><strong>Last Full Index:</strong> <?php echo esc_html( $last_indexed ); ?></p>
	<p><strong>Total Chunks Indexed (with term-frequency vectors):</strong> <?php echo esc_html( $chunk_count ); ?></p>
	<form method="post" action="">
		<?php wp_nonce_field( 'shu_rebuild_kb' ); ?>
		<input type="hidden" name="shu_action" value="rebuild_kb" />
		<?php submit_button( 'Rebuild Knowledge Base Now', 'secondary', '', false ); ?>
	</form>
</div>

<form method="post" action="" class="shu-kb-facts-form">
	<?php wp_nonce_field( 'shu_save_kb' ); ?>
	<input type="hidden" name="shu_action" value="save_kb" />

	<h3>Key Facts & FAQs (English)</h3>
	<p class="description">Add firm details, licensing specs, phone numbers, or special instructions. These always take top priority when answering queries.</p>
	<textarea name="key_facts" rows="8" class="large-text code"><?php echo esc_textarea( isset( $options['key_facts'] ) ? $options['key_facts'] : '' ); ?></textarea>

	<br/><br/>
	<h3>Key Facts & FAQs (Spanish)</h3>
	<p class="description">Optional Spanish translation of key facts and instructions. Injected automatically when a Spanish query or browser language is detected.</p>
	<textarea name="key_facts_es" rows="8" class="large-text code"><?php echo esc_textarea( isset( $options['key_facts_es'] ) ? $options['key_facts_es'] : '' ); ?></textarea>

	<?php submit_button( 'Save Key Facts' ); ?>
</form>
