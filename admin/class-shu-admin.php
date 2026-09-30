<?php
/**
 * Admin UI handler: manage settings tabs (General, Knowledge Base, Leads, Notifications, Analytics),
 * provider selection, key encryption, scoring settings, TXT/CSV exports, and review request actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Admin {

	/** @var SHU_Knowledge_Base */
	private $kb;

	/** @var SHU_Leads */
	private $leads;

	public function __construct( SHU_Knowledge_Base $kb, SHU_Leads $leads ) {
		$this->kb    = $kb;
		$this->leads = $leads;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_menu() {
		add_options_page(
			'SHU AI Assistant',
			'SHU AI Assistant',
			'manage_options',
			'shu-ai-assistant',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_shu-ai-assistant' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'shu-admin', SHU_AI_PLUGIN_URL . 'assets/css/admin.css', array(), SHU_AI_VERSION );
	}

	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['page'], $_GET['shu_export'] ) && 'shu-ai-assistant' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			check_admin_referer( 'shu_export_nonce', 'shu_nonce' );
			$format = sanitize_key( wp_unslash( $_GET['shu_export'] ) );
			if ( 'csv' === $format ) {
				$this->export_leads_csv();
			} elseif ( 'txt' === $format && isset( $_GET['lead_id'] ) ) {
				$this->export_lead_txt( absint( $_GET['lead_id'] ) );
			} elseif ( 'conversation' === $format && isset( $_GET['session_id'] ) ) {
				$this->export_conversation_txt( sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) );
			}
		}

		if ( isset( $_POST['shu_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['shu_action'] ) );

			switch ( $action ) {
				case 'save_general':
					check_admin_referer( 'shu_save_general' );
					$this->save_general();
					break;

				case 'save_kb':
					check_admin_referer( 'shu_save_kb' );
					$this->save_kb_facts();
					break;

				case 'save_scoring':
					check_admin_referer( 'shu_save_scoring' );
					$this->save_scoring();
					break;

				case 'rebuild_kb':
					check_admin_referer( 'shu_rebuild_kb' );
					$count = $this->kb->rebuild_full_index();
					add_settings_error( 'shu_ai', 'shu_kb_rebuilt', sprintf( 'Knowledge base rebuilt: %d chunks indexed with vectors.', $count ), 'success' );
					break;

				case 'save_notifications':
					check_admin_referer( 'shu_save_notifications' );
					$this->save_notifications();
					break;

				case 'update_lead_status':
					check_admin_referer( 'shu_update_lead_status' );
					$lead_id = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
					$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
					$result = $this->leads->update_status( $lead_id, $status );
					add_settings_error( 'shu_ai', 'shu_lead_updated', false === $result ? 'Unable to update lead status.' : 'Lead status updated.', false === $result ? 'error' : 'success' );
					break;

				case 'send_review_request':
					check_admin_referer( 'shu_send_review_request' );
					$lead_id = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
					$res     = $this->leads->send_review_request( $lead_id );
					if ( is_wp_error( $res ) ) {
						add_settings_error( 'shu_ai', 'shu_review_error', $res->get_error_message(), 'error' );
					} else {
						add_settings_error( 'shu_ai', 'shu_review_sent', 'Google review request email sent successfully.', 'success' );
					}
					break;
			}
		}
	}

	private function save_general() {
		$options = shu_ai_get_options();

		// Provider selection
		$provider = isset( $_POST['ai_provider'] ) ? sanitize_key( wp_unslash( $_POST['ai_provider'] ) ) : 'groq';
		$options['ai_provider'] = in_array( $provider, array( 'groq', 'anthropic', 'openai' ), true ) ? $provider : 'groq';

		// Encrypt API keys if non-empty input is provided
		if ( isset( $_POST['groq_api_key'] ) && '' !== trim( $_POST['groq_api_key'] ) ) {
			$options['groq_api_key'] = SHU_Crypto::encrypt( sanitize_textarea_field( wp_unslash( $_POST['groq_api_key'] ) ) );
		}
		if ( isset( $_POST['anthropic_api_key'] ) && '' !== trim( $_POST['anthropic_api_key'] ) ) {
			$options['anthropic_api_key'] = SHU_Crypto::encrypt( sanitize_textarea_field( wp_unslash( $_POST['anthropic_api_key'] ) ) );
		}
		if ( isset( $_POST['openai_api_key'] ) && '' !== trim( $_POST['openai_api_key'] ) ) {
			$options['openai_api_key'] = SHU_Crypto::encrypt( sanitize_textarea_field( wp_unslash( $_POST['openai_api_key'] ) ) );
		}
		foreach ( array( 'groq_api_key', 'anthropic_api_key', 'openai_api_key' ) as $key_field ) {
			if ( false === $options[ $key_field ] ) {
				add_settings_error( 'shu_ai', 'shu_crypto_error', 'API keys could not be encrypted. Check PHP OpenSSL and WordPress auth salts; settings were not saved.', 'error' );
				return;
			}
		}

		$options['groq_model']      = isset( $_POST['groq_model'] ) ? sanitize_text_field( wp_unslash( $_POST['groq_model'] ) ) : $options['groq_model'];
		$options['anthropic_model'] = isset( $_POST['anthropic_model'] ) ? sanitize_text_field( wp_unslash( $_POST['anthropic_model'] ) ) : $options['anthropic_model'];
		$options['openai_model']    = isset( $_POST['openai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_model'] ) ) : $options['openai_model'];

		// Basic Widget Settings
		$options['widget_enabled']  = isset( $_POST['widget_enabled'] ) ? 1 : 0;
		$options['widget_position'] = isset( $_POST['widget_position'] ) && 'bottom-right' === $_POST['widget_position'] ? 'bottom-right' : 'bottom-left';
		$options['header_title']    = isset( $_POST['header_title'] ) ? sanitize_text_field( wp_unslash( $_POST['header_title'] ) ) : $options['header_title'];
		$options['header_subtitle'] = isset( $_POST['header_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['header_subtitle'] ) ) : $options['header_subtitle'];
		$options['greeting_message'] = isset( $_POST['greeting_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['greeting_message'] ) ) : $options['greeting_message'];

		// Spanish Header & Greeting Defaults
		$options['header_title_es']    = isset( $_POST['header_title_es'] ) ? sanitize_text_field( wp_unslash( $_POST['header_title_es'] ) ) : $options['header_title_es'];
		$options['header_subtitle_es'] = isset( $_POST['header_subtitle_es'] ) ? sanitize_text_field( wp_unslash( $_POST['header_subtitle_es'] ) ) : $options['header_subtitle_es'];
		$options['greeting_message_es'] = isset( $_POST['greeting_message_es'] ) ? sanitize_textarea_field( wp_unslash( $_POST['greeting_message_es'] ) ) : $options['greeting_message_es'];

		// Appearance Settings
		$options['trigger_bg']          = isset( $_POST['trigger_bg'] ) ? sanitize_hex_color( wp_unslash( $_POST['trigger_bg'] ) ) : $options['trigger_bg'];
		$options['trigger_icon_color'] = isset( $_POST['trigger_icon_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['trigger_icon_color'] ) ) : $options['trigger_icon_color'];
		$options['header_bg']           = isset( $_POST['header_bg'] ) ? sanitize_hex_color( wp_unslash( $_POST['header_bg'] ) ) : $options['header_bg'];
		$options['header_text_color']   = isset( $_POST['header_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['header_text_color'] ) ) : $options['header_text_color'];
		$options['user_bubble_bg']      = isset( $_POST['user_bubble_bg'] ) ? sanitize_hex_color( wp_unslash( $_POST['user_bubble_bg'] ) ) : $options['user_bubble_bg'];
		$options['user_bubble_text']    = isset( $_POST['user_bubble_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['user_bubble_text'] ) ) : $options['user_bubble_text'];
		$options['bot_bubble_bg']       = isset( $_POST['bot_bubble_bg'] ) ? sanitize_hex_color( wp_unslash( $_POST['bot_bubble_bg'] ) ) : $options['bot_bubble_bg'];
		$options['bot_bubble_text']     = isset( $_POST['bot_bubble_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['bot_bubble_text'] ) ) : $options['bot_bubble_text'];
		$options['send_button_color']   = isset( $_POST['send_button_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['send_button_color'] ) ) : $options['send_button_color'];
		$options['font_family']         = isset( $_POST['font_family'] ) ? preg_replace( '/[^a-zA-Z0-9 ,\x27\x22-]/', '', sanitize_text_field( wp_unslash( $_POST['font_family'] ) ) ) : $options['font_family'];
		$options['corner_radius']       = isset( $_POST['corner_radius'] ) ? min( 30, max( 0, (int) $_POST['corner_radius'] ) ) : $options['corner_radius'];

		$options['rate_limit_count']    = isset( $_POST['rate_limit_count'] ) ? min( 100, max( 1, (int) $_POST['rate_limit_count'] ) ) : $options['rate_limit_count'];
		$options['rate_limit_minutes']  = isset( $_POST['rate_limit_minutes'] ) ? min( 60, max( 1, (int) $_POST['rate_limit_minutes'] ) ) : $options['rate_limit_minutes'];
		$options['conversation_retention_days'] = isset( $_POST['conversation_retention_days'] ) ? min( 3650, max( 0, (int) $_POST['conversation_retention_days'] ) ) : $options['conversation_retention_days'];
		$options['delete_on_uninstall'] = isset( $_POST['delete_on_uninstall'] ) ? 1 : 0;

		update_option( 'shu_ai_options', $options );
		update_option( 'shu_ai_delete_on_uninstall', $options['delete_on_uninstall'] );
		add_settings_error( 'shu_ai', 'shu_general_saved', 'General & Appearance settings saved.', 'success' );
	}

	/** The scoring form is independent of the widget/provider form. */
	private function save_scoring() {
		$options = shu_ai_get_options();
		foreach ( array( 'scoring_hot_keywords', 'scoring_cold_keywords', 'scoring_high_value_services' ) as $field ) {
			$options[ $field ] = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		update_option( 'shu_ai_options', $options );
		add_settings_error( 'shu_ai', 'shu_scoring_saved', 'Lead scoring rules saved.', 'success' );
	}

	private function save_kb_facts() {
		$options = shu_ai_get_options();
		$options['key_facts']    = isset( $_POST['key_facts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['key_facts'] ) ) : '';
		$options['key_facts_es'] = isset( $_POST['key_facts_es'] ) ? sanitize_textarea_field( wp_unslash( $_POST['key_facts_es'] ) ) : '';
		update_option( 'shu_ai_options', $options );
		add_settings_error( 'shu_ai', 'shu_kb_saved', 'Key Facts & FAQs (English & Spanish) saved.', 'success' );
	}

	private function save_notifications() {
		$options = shu_ai_get_options();
		$options['notify_emails']         = isset( $_POST['notify_emails'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_emails'] ) ) : $options['notify_emails'];
		$options['notify_enabled']        = isset( $_POST['notify_enabled'] ) ? 1 : 0;
		$options['notify_weekly_digest']  = isset( $_POST['notify_weekly_digest'] ) ? 1 : 0;
		$options['google_review_url']     = isset( $_POST['google_review_url'] ) ? esc_url_raw( wp_unslash( $_POST['google_review_url'] ) ) : $options['google_review_url'];
		update_option( 'shu_ai_options', $options );
		add_settings_error( 'shu_ai', 'shu_notify_saved', 'Notification & Digest settings saved.', 'success' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$options = shu_ai_get_options();
		?>
		<div class="wrap shu-admin-wrap">
			<h1>SHU AI Assistant <span class="shu-badge-version">v<?php echo esc_html( SHU_AI_VERSION ); ?></span></h1>
			<?php settings_errors( 'shu_ai' ); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=shu-ai-assistant&tab=general" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>">General & Appearance</a>
				<a href="?page=shu-ai-assistant&tab=knowledge" class="nav-tab <?php echo 'knowledge' === $tab ? 'nav-tab-active' : ''; ?>">Knowledge Base</a>
				<a href="?page=shu-ai-assistant&tab=leads" class="nav-tab <?php echo 'leads' === $tab ? 'nav-tab-active' : ''; ?>">Leads & Scoring</a>
				<a href="?page=shu-ai-assistant&tab=conversations" class="nav-tab <?php echo 'conversations' === $tab ? 'nav-tab-active' : ''; ?>">Conversations</a>
				<a href="?page=shu-ai-assistant&tab=analytics" class="nav-tab <?php echo 'analytics' === $tab ? 'nav-tab-active' : ''; ?>">Analytics</a>
				<a href="?page=shu-ai-assistant&tab=notifications" class="nav-tab <?php echo 'notifications' === $tab ? 'nav-tab-active' : ''; ?>">Notifications & Reviews</a>
			</h2>

			<div class="shu-tab-content">
				<?php
				switch ( $tab ) {
					case 'knowledge':
						include SHU_AI_PLUGIN_DIR . 'admin/views/knowledge-base-tab.php';
						break;
					case 'leads':
						include SHU_AI_PLUGIN_DIR . 'admin/views/leads-tab.php';
						break;
					case 'analytics':
						include SHU_AI_PLUGIN_DIR . 'admin/views/analytics-tab.php';
						break;
					case 'conversations':
						include SHU_AI_PLUGIN_DIR . 'admin/views/conversations-tab.php';
						break;
					case 'notifications':
						include SHU_AI_PLUGIN_DIR . 'admin/views/notifications-tab.php';
						break;
					default:
						include SHU_AI_PLUGIN_DIR . 'admin/views/general-tab.php';
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function export_leads_csv() {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=shu-ai-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Priority', 'Name', 'Phone', 'Email', 'Service', 'Location', 'Urgency', 'Service Details', 'Status', 'Created At' ) );

		for ( $page = 1; ; $page++ ) {
			$leads = $this->leads->get_leads( array( 'per_page' => 500, 'paged' => $page, 'orderby' => 'id', 'order' => 'ASC' ) );
			if ( ! $leads ) {
				break;
			}
		foreach ( $leads as $lead ) {
			$extra_str = '';
			if ( ! empty( $lead['extra_fields'] ) ) {
				$extra = json_decode( $lead['extra_fields'], true );
				if ( is_array( $extra ) ) {
					$pairs = array();
					foreach ( $extra as $k => $v ) {
						$pairs[] = SHU_Lead_Fields::get_field_label( $lead['service'], $k ) . ': ' . $v;
					}
					$extra_str = implode( ' | ', $pairs );
				}
			}

			fputcsv(
				$out,
				array_map( array( $this, 'csv_cell' ), array(
					$lead['id'],
					strtoupper( $lead['priority'] ),
					$lead['name'],
					$lead['phone'],
					$lead['email'],
					$lead['service'],
					$lead['location'],
					$lead['urgency'],
					$extra_str,
					$lead['status'],
					$lead['created_at'],
				) )
			);
		}
			if ( count( $leads ) < 500 ) {
				break;
			}
		}

		fclose( $out );
		exit;
	}

	/** Prevent spreadsheet applications from executing user-supplied cell values. */
	private function csv_cell( $value ) {
		$value = (string) $value;
		return preg_match( '/^[\s]*[=+@-]/u', $value ) ? "'" . $value : $value;
	}

	/** Download a complete retained session from the chat log. */
	private function export_conversation_txt( $session_id ) {
		if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $session_id ) ) {
			wp_die( 'Invalid conversation.' );
		}
		$turns = SHU_Analytics::get_transcript( $session_id );
		if ( ! $turns ) {
			wp_die( 'Conversation not found or no longer retained.' );
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=shu-conversation-' . substr( $session_id, 0, 12 ) . '.txt' );
		foreach ( $turns as $turn ) {
			echo '[' . ( 'user' === $turn['role'] ? 'Visitor' : 'Assistant' ) . ' | ' . $turn['created_at'] . "]\n" . $turn['message'] . "\n\n";
		}
		exit;
	}

	private function export_lead_txt( $lead_id ) {
		$lead = $this->leads->get_lead( $lead_id );
		if ( empty( $lead ) ) {
			wp_die( 'Lead not found.' );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=shu-lead-' . $lead['id'] . '-transcript.txt' );

		echo "========================================================================\n";
		echo "SAFETY HOST UNIT - AI ASSISTANT CHAT TRANSCRIPT\n";
		echo "========================================================================\n";
		echo "Lead ID      : #" . $lead['id'] . "\n";
		echo "Priority     : " . strtoupper( $lead['priority'] ) . "\n";
		echo "Captured At  : " . $lead['created_at'] . "\n";
		echo "Visitor Name : " . $lead['name'] . "\n";
		echo "Phone        : " . $lead['phone'] . "\n";
		echo "Email        : " . $lead['email'] . "\n";
		echo "Service      : " . $lead['service'] . "\n";
		echo "Location     : " . $lead['location'] . "\n";
		echo "Urgency      : " . $lead['urgency'] . "\n";

		if ( ! empty( $lead['extra_fields'] ) ) {
			$extra = json_decode( $lead['extra_fields'], true );
			if ( is_array( $extra ) ) {
				echo "------------------------------------------------------------------------\n";
				echo "SERVICE QUALIFYING DETAILS:\n";
				foreach ( $extra as $k => $v ) {
					echo "  - " . SHU_Lead_Fields::get_field_label( $lead['service'], $k ) . ": " . $v . "\n";
				}
			}
		}

		echo "========================================================================\n";
		echo "CONVERSATION DIALOGUE:\n";
		echo "========================================================================\n\n";

		$transcript = json_decode( $lead['conversation_transcript'], true );
		if ( is_array( $transcript ) ) {
			foreach ( $transcript as $turn ) {
				$role    = isset( $turn['role'] ) && 'user' === $turn['role'] ? '[Visitor]' : '[Assistant]';
				$content = isset( $turn['content'] ) ? $turn['content'] : '';
				echo "{$role}\n{$content}\n\n";
			}
		} else {
			echo "No conversation turns recorded.\n";
		}

		exit;
	}
}
