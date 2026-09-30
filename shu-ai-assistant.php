<?php
/**
 * Plugin Name:       SHU AI Assistant
 * Plugin URI:        https://safetyhostunit.com
 * Description:       Website chat, consultation lead capture, and conversation reporting for Safety Host Unit.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Safety Host Unit
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shu-ai-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Constants
 */
define( 'SHU_AI_VERSION', '2.1.0' );
define( 'SHU_AI_PLUGIN_FILE', __FILE__ );
define( 'SHU_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHU_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SHU_AI_DB_VERSION', '2.1' );

/*
 * Includes
 */
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-crypto.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-activator.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-lead-fields.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-lead-scoring.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-ai-provider-interface.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-groq-client.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-anthropic-client.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-openai-client.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-relevance-engine.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-knowledge-base.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-leads.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-analytics.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-weekly-digest.php';
require_once SHU_AI_PLUGIN_DIR . 'includes/class-shu-rest-api.php';

if ( is_admin() ) {
	require_once SHU_AI_PLUGIN_DIR . 'admin/class-shu-admin.php';
}

/*
 * Hooks
 */
register_activation_hook( __FILE__, array( 'SHU_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SHU_Activator', 'deactivate' ) );

add_action( 'shu_ai_weekly_digest', array( 'SHU_Weekly_Digest', 'send_digest' ) );
add_action( 'shu_ai_daily_cleanup', array( 'SHU_Analytics', 'purge_old_logs' ) );

/*
 * Core Bootstrap Class
 */
final class SHU_Ai_Assistant {

	private static $instance = null;

	/** @var SHU_Knowledge_Base */
	public $knowledge_base;

	/** @var SHU_Leads */
	public $leads;

	/** @var SHU_REST_API */
	public $rest_api;

	/** @var SHU_Admin|null */
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->knowledge_base = new SHU_Knowledge_Base();
		$this->leads          = new SHU_Leads();
		$this->rest_api       = new SHU_REST_API( $this->knowledge_base, $this->leads );

		if ( is_admin() && class_exists( 'SHU_Admin' ) ) {
			$this->admin = new SHU_Admin( $this->knowledge_base, $this->leads );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_db' ) );
	}

	public function enqueue_frontend_assets() {
		if ( is_admin() ) {
			return;
		}

		$options = shu_ai_get_options();

		if ( empty( $options['widget_enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'shu-chat-widget',
			SHU_AI_PLUGIN_URL . 'assets/css/chat-widget.css',
			array(),
			SHU_AI_VERSION
		);

		wp_enqueue_script(
			'shu-chat-widget',
			SHU_AI_PLUGIN_URL . 'assets/js/chat-widget.js',
			array(),
			SHU_AI_VERSION,
			true
		);

		wp_localize_script(
			'shu-chat-widget',
			'SHU_CHAT_CONFIG',
			array(
				'restUrl'           => esc_url_raw( rest_url( 'shu-chat/v1/message' ) ),
				'privacyUrl'        => get_privacy_policy_url(),
				'position'          => isset( $options['widget_position'] ) ? $options['widget_position'] : 'bottom-left',
				'triggerBg'         => isset( $options['trigger_bg'] ) ? $options['trigger_bg'] : '#111214',
				'triggerIconColor'  => isset( $options['trigger_icon_color'] ) ? $options['trigger_icon_color'] : '#E7E6E2',
				'headerBg'          => isset( $options['header_bg'] ) ? $options['header_bg'] : '#111214',
				'headerTextColor'    => isset( $options['header_text_color'] ) ? $options['header_text_color'] : '#FFFFFF',
				'userBubbleBg'      => isset( $options['user_bubble_bg'] ) ? $options['user_bubble_bg'] : '#111214',
				'userBubbleText'    => isset( $options['user_bubble_text'] ) ? $options['user_bubble_text'] : '#FFFFFF',
				'botBubbleBg'       => isset( $options['bot_bubble_bg'] ) ? $options['bot_bubble_bg'] : '#F3F4F6',
				'botBubbleText'     => isset( $options['bot_bubble_text'] ) ? $options['bot_bubble_text'] : '#111827',
				'sendButtonColor'   => isset( $options['send_button_color'] ) ? $options['send_button_color'] : '#111214',
				'fontFamily'        => isset( $options['font_family'] ) ? $options['font_family'] : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
				'cornerRadius'      => isset( $options['corner_radius'] ) ? (int) $options['corner_radius'] : 12,
				'headerTitle'       => isset( $options['header_title'] ) ? $options['header_title'] : 'Safety Host Unit',
				'headerSubtitle'    => isset( $options['header_subtitle'] ) ? $options['header_subtitle'] : 'Virtual assistant',
				'greeting'          => isset( $options['greeting_message'] ) ? $options['greeting_message'] : 'Good day. I\'m the virtual assistant for Safety Host Unit — how may I help you today?',
				'headerTitleEs'     => isset( $options['header_title_es'] ) ? $options['header_title_es'] : 'Safety Host Unit',
				'headerSubtitleEs'  => isset( $options['header_subtitle_es'] ) ? $options['header_subtitle_es'] : 'Asistente virtual',
				'greetingEs'        => isset( $options['greeting_message_es'] ) ? $options['greeting_message_es'] : 'Buenos días. Soy el asistente virtual de Safety Host Unit — ¿en qué puedo ayudarle hoy?',
			)
		);
	}

	public function maybe_upgrade_db() {
		SHU_Crypto::migrate_api_keys();
		$installed = get_option( 'shu_ai_db_version', '' );
		if ( $installed !== SHU_AI_DB_VERSION ) {
			$saved = get_option( 'shu_ai_options', array() );
			if ( is_array( $saved ) && isset( $saved['anthropic_model'] ) && 'claude-3-5-sonnet-20241022' === $saved['anthropic_model'] ) {
				$saved['anthropic_model'] = 'claude-haiku-4-5-20251001';
				update_option( 'shu_ai_options', $saved );
			}
			SHU_Activator::create_tables();
			update_option( 'shu_ai_db_version', SHU_AI_DB_VERSION );
			// v2 used a fixed weekly interval, which can drift after DST changes.
			wp_clear_scheduled_hook( 'shu_ai_weekly_digest' );
		}
		SHU_Activator::schedule_events();
	}
}

/**
 * Return default options merged with saved options.
 *
 * @return array
 */
function shu_ai_get_options() {
	$defaults = array(
		'ai_provider'               => 'groq',
		'groq_api_key'              => '',
		'groq_model'                => 'llama-3.3-70b-versatile',
		'anthropic_api_key'         => '',
		'anthropic_model'           => 'claude-haiku-4-5-20251001',
		'openai_api_key'            => '',
		'openai_model'              => 'gpt-4o-mini',
		'widget_enabled'            => 1,
		'widget_position'           => 'bottom-left',
		'trigger_bg'                => '#111214',
		'trigger_icon_color'        => '#E7E6E2',
		'header_bg'                 => '#111214',
		'header_text_color'         => '#FFFFFF',
		'bot_bubble_bg'              => '#F3F4F6',
		'bot_bubble_text'            => '#111827',
		'user_bubble_bg'             => '#111214',
		'user_bubble_text'           => '#FFFFFF',
		'send_button_color'          => '#111214',
		'font_family'               => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
		'corner_radius'             => 12,
		'header_title'              => 'Safety Host Unit',
		'header_subtitle'           => 'Virtual assistant',
		'greeting_message'          => 'Good day. I\'m the virtual assistant for Safety Host Unit — how may I help you today?',
		'header_title_es'           => 'Safety Host Unit',
		'header_subtitle_es'        => 'Asistente virtual',
		'greeting_message_es'       => 'Buenos días. Soy el asistente virtual de Safety Host Unit — ¿en qué puedo ayudarle hoy?',
		'key_facts'                 => '',
		'key_facts_es'              => '',
		'notify_emails'             => get_option( 'admin_email' ),
		'notify_enabled'            => 1,
		'notify_weekly_digest'       => 1,
		'google_review_url'         => '',
		'scoring_hot_keywords'       => "today\nasap\nimmediately\nthis week\nurgent\nright now\nnext 24 hours",
		'scoring_cold_keywords'      => "just researching\nnot sure yet\na few months\nno timeline\nexploratory\nsometime this year\nnext year\nnot urgent\nno rush",
		'scoring_high_value_services' => "Executive & Personal Protection",
		'rate_limit_count'          => 20,
		'rate_limit_minutes'        => 10,
		'conversation_retention_days' => 180,
		'delete_on_uninstall'       => 0,
	);

	$saved = get_option( 'shu_ai_options', array() );

	return wp_parse_args( $saved, $defaults );
}

add_action( 'plugins_loaded', array( 'SHU_Ai_Assistant', 'instance' ) );
