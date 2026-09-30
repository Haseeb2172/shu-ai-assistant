<?php
/**
 * Fired automatically by WordPress when the plugin is deleted from the
 * Plugins screen (not on mere deactivation). By default this does NOT
 * delete any data — leads and the knowledge base index are kept in case
 * the plugin is reinstalled. Data is removed only when the administrator
 * enables "Delete all data on uninstall" in General settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'shu_ai_delete_on_uninstall', false );

if ( $delete_data ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-shu-activator.php';
	SHU_Activator::drop_tables();

	delete_option( 'shu_ai_options' );
	delete_option( 'shu_ai_db_version' );
	delete_option( 'shu_ai_kb_last_indexed' );
	delete_option( 'shu_ai_kb_chunk_count' );
	delete_option( 'shu_ai_delete_on_uninstall' );
	foreach ( array( 'groq', 'anthropic', 'openai' ) as $provider ) {
		delete_option( 'shu_ai_' . $provider . '_key_pointer' );
	}
}
