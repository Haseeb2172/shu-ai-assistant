<?php
/**
 * General & Appearance Settings Tab View with Provider Selection and Live Widget Preview.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options      = shu_ai_get_options();
$current_prov = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'groq';

$groq_has_key      = ! empty( $options['groq_api_key'] );
$anthropic_has_key = ! empty( $options['anthropic_api_key'] );
$openai_has_key    = ! empty( $options['openai_api_key'] );
?>
<form method="post" action="" class="shu-settings-form">
	<?php wp_nonce_field( 'shu_save_general' ); ?>
	<input type="hidden" name="shu_action" value="save_general" />

	<div class="shu-settings-grid">
		<div class="shu-settings-main">
			<h2>AI Provider & Core API Configuration</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="ai_provider">Active AI Provider</label></th>
					<td>
						<select name="ai_provider" id="ai_provider" onchange="shuToggleProviderFields(this.value)">
							<option value="groq" <?php selected( $current_prov, 'groq' ); ?>>Groq (Fast, Llama 3.3 70B)</option>
							<option value="anthropic" <?php selected( $current_prov, 'anthropic' ); ?>>Anthropic Claude (Sonnet / Haiku)</option>
							<option value="openai" <?php selected( $current_prov, 'openai' ); ?>>OpenAI (GPT-4o / GPT-4o-mini)</option>
						</select>
						<p class="description">Select the model provider backend. Accepts multi-key failover list (one key per line).</p>
					</td>
				</tr>

				<!-- GROQ FIELDS -->
				<tr class="shu-provider-row shu-prov-groq" style="<?php echo 'groq' === $current_prov ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="groq_api_key">Groq API Keys</label></th>
					<td>
						<textarea name="groq_api_key" id="groq_api_key" rows="3" class="large-text code" placeholder="<?php echo $groq_has_key ? '●●●●●●●● (Keys stored encrypted — enter new key(s) per line to overwrite)' : 'gsk_... (one key per line)'; ?>"></textarea>
						<p class="description">Enter Groq API keys (one per line) for round-robin rotation and failover.</p>
					</td>
				</tr>
				<tr class="shu-provider-row shu-prov-groq" style="<?php echo 'groq' === $current_prov ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="groq_model">Groq Model</label></th>
					<td>
						<input type="text" name="groq_model" id="groq_model" value="<?php echo esc_attr( $options['groq_model'] ); ?>" class="regular-text" />
						<p class="description">Default: <code>llama-3.3-70b-versatile</code></p>
					</td>
				</tr>

				<!-- ANTHROPIC FIELDS -->
				<tr class="shu-provider-row shu-prov-anthropic" style="<?php echo 'anthropic' === $current_prov ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="anthropic_api_key">Anthropic API Keys</label></th>
					<td>
						<textarea name="anthropic_api_key" id="anthropic_api_key" rows="3" class="large-text code" placeholder="<?php echo $anthropic_has_key ? '●●●●●●●● (Keys stored encrypted — enter new key(s) per line to overwrite)' : 'sk-ant-api03-... (one key per line)'; ?>"></textarea>
						<p class="description">Enter Anthropic API keys (one per line) for round-robin failover.</p>
					</td>
				</tr>
				<tr class="shu-provider-row shu-prov-anthropic" style="<?php echo 'anthropic' === $current_prov ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="anthropic_model">Anthropic Model</label></th>
					<td>
						<input type="text" name="anthropic_model" id="anthropic_model" value="<?php echo esc_attr( isset( $options['anthropic_model'] ) ? $options['anthropic_model'] : 'claude-haiku-4-5-20251001' ); ?>" class="regular-text" />
						<p class="description">Default: <code>claude-haiku-4-5-20251001</code></p>
					</td>
				</tr>

				<!-- OPENAI FIELDS -->
				<tr class="shu-provider-row shu-prov-openai" style="<?php echo 'openai' === $current_prov ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="openai_api_key">OpenAI API Keys</label></th>
					<td>
						<textarea name="openai_api_key" id="openai_api_key" rows="3" class="large-text code" placeholder="<?php echo $openai_has_key ? '●●●●●●●● (Keys stored encrypted — enter new key(s) per line to overwrite)' : 'sk-... (one key per line)'; ?>"></textarea>
						<p class="description">Enter OpenAI API keys (one per line) for round-robin failover.</p>
					</td>
				</tr>
				<tr class="shu-provider-row shu-prov-openai" style="<?php echo 'openai' === $current_prov ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="openai_model">OpenAI Model</label></th>
					<td>
						<input type="text" name="openai_model" id="openai_model" value="<?php echo esc_attr( isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-4o-mini' ); ?>" class="regular-text" />
						<p class="description">Default: <code>gpt-4o-mini</code></p>
					</td>
				</tr>
			</table>

			<hr />

			<h2>Widget Appearance & Customization</h2>
			<table class="form-table">
				<tr>
					<th scope="row">Widget Status</th>
					<td>
						<label><input type="checkbox" name="widget_enabled" value="1" <?php checked( $options['widget_enabled'], 1 ); ?> /> Enable chat widget on frontend</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="widget_position">Position</label></th>
					<td>
						<select name="widget_position" id="widget_position">
							<option value="bottom-left" <?php selected( $options['widget_position'], 'bottom-left' ); ?>>Bottom Left</option>
							<option value="bottom-right" <?php selected( $options['widget_position'], 'bottom-right' ); ?>>Bottom Right</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="trigger_bg">Trigger Button BG</label></th>
					<td>
						<input type="color" name="trigger_bg" id="trigger_bg" value="<?php echo esc_attr( isset( $options['trigger_bg'] ) ? $options['trigger_bg'] : '#111214' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="trigger_icon_color">Trigger Icon Color</label></th>
					<td>
						<input type="color" name="trigger_icon_color" id="trigger_icon_color" value="<?php echo esc_attr( isset( $options['trigger_icon_color'] ) ? $options['trigger_icon_color'] : '#E7E6E2' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="header_bg">Header Background</label></th>
					<td>
						<input type="color" name="header_bg" id="header_bg" value="<?php echo esc_attr( isset( $options['header_bg'] ) ? $options['header_bg'] : '#111214' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="header_text_color">Header Text Color</label></th>
					<td>
						<input type="color" name="header_text_color" id="header_text_color" value="<?php echo esc_attr( isset( $options['header_text_color'] ) ? $options['header_text_color'] : '#FFFFFF' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bot_bubble_bg">Assistant Bubble BG</label></th>
					<td>
						<input type="color" name="bot_bubble_bg" id="bot_bubble_bg" value="<?php echo esc_attr( isset( $options['bot_bubble_bg'] ) ? $options['bot_bubble_bg'] : '#F3F4F6' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bot_bubble_text">Assistant Bubble Text</label></th>
					<td>
						<input type="color" name="bot_bubble_text" id="bot_bubble_text" value="<?php echo esc_attr( isset( $options['bot_bubble_text'] ) ? $options['bot_bubble_text'] : '#111827' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="user_bubble_bg">Visitor Bubble BG</label></th>
					<td>
						<input type="color" name="user_bubble_bg" id="user_bubble_bg" value="<?php echo esc_attr( isset( $options['user_bubble_bg'] ) ? $options['user_bubble_bg'] : '#111214' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="user_bubble_text">Visitor Bubble Text</label></th>
					<td>
						<input type="color" name="user_bubble_text" id="user_bubble_text" value="<?php echo esc_attr( isset( $options['user_bubble_text'] ) ? $options['user_bubble_text'] : '#FFFFFF' ); ?>" onchange="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="font_family">Font Family Override</label></th>
					<td>
						<input type="text" name="font_family" id="font_family" value="<?php echo esc_attr( isset( $options['font_family'] ) ? $options['font_family'] : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" ); ?>" class="regular-text" onchange="shuUpdatePreview()" />
						<p class="description">Defaults to the visitor’s system font stack; set this to match your theme.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="corner_radius">Corner Radius (px)</label></th>
					<td>
						<input type="number" name="corner_radius" id="corner_radius" value="<?php echo esc_attr( isset( $options['corner_radius'] ) ? $options['corner_radius'] : 12 ); ?>" min="0" max="30" class="small-text" onchange="shuUpdatePreview()" /> px
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="header_title">Header Title (English)</label></th>
					<td>
						<input type="text" name="header_title" id="header_title" value="<?php echo esc_attr( $options['header_title'] ); ?>" class="regular-text" oninput="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="header_subtitle">Header Subtitle (English)</label></th>
					<td>
						<input type="text" name="header_subtitle" id="header_subtitle" value="<?php echo esc_attr( $options['header_subtitle'] ); ?>" class="regular-text" oninput="shuUpdatePreview()" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="greeting_message">Opening Greeting (English)</label></th>
					<td>
						<textarea name="greeting_message" id="greeting_message" rows="3" class="large-text" oninput="shuUpdatePreview()"><?php echo esc_textarea( $options['greeting_message'] ); ?></textarea>
					</td>
				</tr>
				<tr><th scope="row"><label for="send_button_color">Send Button Color</label></th>
					<td><input type="color" name="send_button_color" id="send_button_color" value="<?php echo esc_attr( $options['send_button_color'] ); ?>" onchange="shuUpdatePreview()" /></td></tr>
				<tr><th scope="row"><label for="header_title_es">Header Title (Spanish)</label></th>
					<td><input type="text" name="header_title_es" id="header_title_es" value="<?php echo esc_attr( $options['header_title_es'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="header_subtitle_es">Header Subtitle (Spanish)</label></th>
					<td><input type="text" name="header_subtitle_es" id="header_subtitle_es" value="<?php echo esc_attr( $options['header_subtitle_es'] ); ?>" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="greeting_message_es">Opening Greeting (Spanish)</label></th>
					<td><textarea name="greeting_message_es" id="greeting_message_es" rows="3" class="large-text"><?php echo esc_textarea( $options['greeting_message_es'] ); ?></textarea></td></tr>
			</table>
			<h2>Traffic &amp; Data</h2>
			<table class="form-table">
				<tr><th scope="row"><label for="rate_limit_count">Requests per visitor</label></th>
					<td><input type="number" min="1" max="100" name="rate_limit_count" id="rate_limit_count" class="small-text" value="<?php echo esc_attr( $options['rate_limit_count'] ); ?>" /> per <input type="number" min="1" max="60" name="rate_limit_minutes" class="small-text" value="<?php echo esc_attr( $options['rate_limit_minutes'] ); ?>" /> minutes</td></tr>
				<tr><th scope="row"><label for="conversation_retention_days">Conversation retention</label></th>
					<td><input type="number" min="0" max="3650" name="conversation_retention_days" id="conversation_retention_days" class="small-text" value="<?php echo esc_attr( $options['conversation_retention_days'] ); ?>" /> days <p class="description">180 by default; 0 keeps chat logs until manually removed or data is deleted on uninstall. Lead transcripts remain with their leads.</p></td></tr>
				<tr><th scope="row">Uninstall</th>
					<td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $options['delete_on_uninstall'], 1 ); ?> /> Delete plugin settings, leads, index, and chat logs when the plugin is deleted</label></td></tr>
			</table>

			<?php submit_button( 'Save General & Appearance Settings' ); ?>
		</div>

		<div class="shu-settings-sidebar">
			<div class="shu-preview-box">
				<h3>Live Widget Preview</h3>
				<div class="shu-preview-trigger" id="preview_trigger" aria-hidden="true">✦</div>
				<div class="shu-preview-widget" id="shu_preview_widget">
					<div class="shu-preview-header" id="preview_header">
						<div class="shu-preview-title" id="preview_title"><?php echo esc_html( $options['header_title'] ); ?></div>
						<div class="shu-preview-subtitle" id="preview_subtitle"><?php echo esc_html( $options['header_subtitle'] ); ?></div>
					</div>
					<div class="shu-preview-body">
						<div class="shu-preview-msg bot" id="preview_bot_msg"><?php echo esc_html( $options['greeting_message'] ); ?></div>
						<div class="shu-preview-msg user" id="preview_user_msg">I need event security for a gala in Bel Air this weekend.</div>
					</div>
					<div class="shu-preview-footer">
						<input type="text" disabled placeholder="Type a message..." class="shu-preview-input" />
						<button type="button" class="shu-preview-send" id="preview_send_btn">&#10148;</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</form>

<script>
function shuToggleProviderFields(val) {
	document.querySelectorAll('.shu-provider-row').forEach(el => el.style.display = 'none');
	document.querySelectorAll('.shu-prov-' + val).forEach(el => el.style.display = '');
}

function shuUpdatePreview() {
	var hBg = document.getElementById('header_bg').value;
	var hTxt = document.getElementById('header_text_color').value;
	var bBg = document.getElementById('bot_bubble_bg').value;
	var bTxt = document.getElementById('bot_bubble_text').value;
	var uBg = document.getElementById('user_bubble_bg').value;
	var uTxt = document.getElementById('user_bubble_text').value;
	var font = document.getElementById('font_family').value;
	var radius = document.getElementById('corner_radius').value + 'px';

	var title = document.getElementById('header_title').value;
	var sub = document.getElementById('header_subtitle').value;
	var greet = document.getElementById('greeting_message').value;

	var w = document.getElementById('shu_preview_widget');
	var trigger = document.getElementById('preview_trigger');
	trigger.style.backgroundColor = document.getElementById('trigger_bg').value;
	trigger.style.color = document.getElementById('trigger_icon_color').value;
	w.style.fontFamily = font;
	w.style.borderRadius = radius;

	var header = document.getElementById('preview_header');
	header.style.backgroundColor = hBg;
	header.style.color = hTxt;

	document.getElementById('preview_title').innerText = title;
	document.getElementById('preview_subtitle').innerText = sub;

	var botMsg = document.getElementById('preview_bot_msg');
	botMsg.style.backgroundColor = bBg;
	botMsg.style.color = bTxt;
	botMsg.style.borderRadius = radius;
	botMsg.innerText = greet;

	var userMsg = document.getElementById('preview_user_msg');
	userMsg.style.backgroundColor = uBg;
	userMsg.style.color = uTxt;
	userMsg.style.borderRadius = radius;
	document.getElementById('preview_send_btn').style.backgroundColor = document.getElementById('send_button_color').value;
}
window.addEventListener('DOMContentLoaded', shuUpdatePreview);
</script>
