<?php
/**
 * Public chat widget markup.
 *
 * Printed in the footer by Fsweb_Ai_Automated_Contact_Public::render_widget().
 * Styling lives in public/css and behaviour in public/js — no inline
 * <style> or <script> tags here.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/public/partials
 *
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$fsweb_aic_position = ( 'left' === $settings['position'] ) ? 'fsweb-aic-widget--left' : 'fsweb-aic-widget--right';
$fsweb_aic_initial  = function_exists( 'mb_substr' ) ? mb_substr( $settings['agent_name'], 0, 1 ) : substr( $settings['agent_name'], 0, 1 );
?>
<div class="fsweb-aic-widget <?php echo esc_attr( $fsweb_aic_position ); ?>" id="fsweb-aic-widget" hidden>
	<button type="button" class="fsweb-aic-launcher" id="fsweb-aic-launcher" aria-expanded="false" aria-controls="fsweb-aic-panel">
		<span class="screen-reader-text"><?php esc_html_e( 'Abrir atendimento', 'fsweb-ai-automated-contact' ); ?></span>
		<svg class="fsweb-aic-launcher__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4V6a2 2 0 0 1 2-2Z" fill="currentColor"></path>
			<circle cx="8.5" cy="10.5" r="1.3" fill="#fff"></circle>
			<circle cx="12" cy="10.5" r="1.3" fill="#fff"></circle>
			<circle cx="15.5" cy="10.5" r="1.3" fill="#fff"></circle>
		</svg>
		<span class="fsweb-aic-launcher__badge" id="fsweb-aic-launcher-badge" hidden></span>
	</button>

	<section class="fsweb-aic-panel" id="fsweb-aic-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr( $settings['agent_name'] ); ?>" hidden>
		<header class="fsweb-aic-header">
			<div class="fsweb-aic-header__id">
				<span class="fsweb-aic-header__avatar" aria-hidden="true"><?php echo esc_html( $fsweb_aic_initial ); ?></span>
				<div>
					<strong class="fsweb-aic-header__name"><?php echo esc_html( $settings['agent_name'] ); ?></strong>
					<span class="fsweb-aic-header__status"><?php esc_html_e( 'Atendimento online', 'fsweb-ai-automated-contact' ); ?></span>
				</div>
			</div>
			<div class="fsweb-aic-header__actions">
				<button type="button" class="fsweb-aic-icon-button" id="fsweb-aic-reset" aria-label="<?php esc_attr_e( 'Nova conversa', 'fsweb-ai-automated-contact' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5V2L7 6l5 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z" fill="currentColor"></path></svg>
				</button>
				<button type="button" class="fsweb-aic-icon-button" id="fsweb-aic-close" aria-label="<?php esc_attr_e( 'Fechar', 'fsweb-ai-automated-contact' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"></path></svg>
				</button>
			</div>
		</header>

		<div class="fsweb-aic-messages" id="fsweb-aic-messages" role="log" aria-live="polite"></div>

		<form class="fsweb-aic-composer" id="fsweb-aic-composer">
			<label class="screen-reader-text" for="fsweb-aic-input"><?php esc_html_e( 'Escreva sua mensagem', 'fsweb-ai-automated-contact' ); ?></label>
			<textarea id="fsweb-aic-input" class="fsweb-aic-input" rows="1" maxlength="2000"
				placeholder="<?php esc_attr_e( 'Escreva sua mensagem…', 'fsweb-ai-automated-contact' ); ?>"></textarea>
			<button type="submit" class="fsweb-aic-send" id="fsweb-aic-send" aria-label="<?php esc_attr_e( 'Enviar', 'fsweb-ai-automated-contact' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 20l18-8L3 4v6l12 2-12 2v6Z" fill="currentColor"></path></svg>
			</button>
		</form>

		<p class="fsweb-aic-disclaimer"><?php echo esc_html( $settings['privacy_note'] ); ?></p>
	</section>
</div>
