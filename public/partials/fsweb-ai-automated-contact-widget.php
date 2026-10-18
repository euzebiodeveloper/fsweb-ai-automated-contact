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
$fsweb_aic_avatar   = Fsweb_Ai_Automated_Contact_Public::avatar_markup( $settings );

if ( 'initials' === $settings['avatar_type'] ) {
	$fsweb_aic_launcher_icon = '<span class="fsweb-aic-initial">' . esc_html( $fsweb_aic_initial ) . '</span>';
} else {
	$fsweb_aic_launcher_icon = Fsweb_Ai_Automated_Contact_Public::icon_svg( $settings['avatar_type'] );
}

/**
 * Filter the icon shown on the floating launcher.
 *
 * @since 1.0.0
 * @param string $fsweb_aic_launcher_icon Launcher icon HTML.
 * @param array  $settings                Current settings.
 */
$fsweb_aic_launcher_icon = (string) apply_filters( 'fsweb_aic_launcher_icon', $fsweb_aic_launcher_icon, $settings );
?>
<div class="fsweb-aic-widget <?php echo esc_attr( $fsweb_aic_position ); ?>" id="fsweb-aic-widget" hidden>
	<button type="button" class="fsweb-aic-launcher" id="fsweb-aic-launcher" aria-expanded="false" aria-controls="fsweb-aic-panel">
		<span class="screen-reader-text"><?php esc_html_e( 'Open chat', 'fsweb-ai-automated-contact' ); ?></span>
		<span class="fsweb-aic-launcher__icon"><?php echo wp_kses_post( $fsweb_aic_launcher_icon ); ?></span>
		<span class="fsweb-aic-launcher__badge" id="fsweb-aic-launcher-badge" hidden></span>
	</button>

	<section class="fsweb-aic-panel" id="fsweb-aic-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr( $settings['agent_name'] ); ?>" hidden>
		<header class="fsweb-aic-header">
			<div class="fsweb-aic-header__id">
				<span class="fsweb-aic-header__avatar"><?php echo wp_kses_post( $fsweb_aic_avatar ); ?></span>
				<span class="fsweb-aic-header__text">
					<strong class="fsweb-aic-header__name"><?php echo esc_html( $settings['agent_name'] ); ?></strong>
					<span class="fsweb-aic-header__status"><?php esc_html_e( 'Online support', 'fsweb-ai-automated-contact' ); ?></span>
				</span>
			</div>
			<div class="fsweb-aic-header__actions">
				<button type="button" class="fsweb-aic-icon-button" id="fsweb-aic-reset" aria-label="<?php esc_attr_e( 'New conversation', 'fsweb-ai-automated-contact' ); ?>" title="<?php esc_attr_e( 'New conversation', 'fsweb-ai-automated-contact' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5V2L7 6l5 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z" fill="currentColor"/></svg>
				</button>
				<button type="button" class="fsweb-aic-icon-button" id="fsweb-aic-close" aria-label="<?php esc_attr_e( 'Close', 'fsweb-ai-automated-contact' ); ?>" title="<?php esc_attr_e( 'Close', 'fsweb-ai-automated-contact' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
				</button>
			</div>
		</header>

		<div class="fsweb-aic-messages" id="fsweb-aic-messages" role="log" aria-live="polite"></div>

		<form class="fsweb-aic-composer" id="fsweb-aic-composer">
			<label class="screen-reader-text" for="fsweb-aic-input"><?php esc_html_e( 'Type your message', 'fsweb-ai-automated-contact' ); ?></label>
			<div class="fsweb-aic-composer__field">
				<textarea id="fsweb-aic-input" class="fsweb-aic-input" rows="1" maxlength="2000"
					placeholder="<?php esc_attr_e( 'Type your message…', 'fsweb-ai-automated-contact' ); ?>"></textarea>
				<button type="submit" class="fsweb-aic-send" id="fsweb-aic-send" aria-label="<?php esc_attr_e( 'Send', 'fsweb-ai-automated-contact' ); ?>" title="<?php esc_attr_e( 'Send', 'fsweb-ai-automated-contact' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3.4 20.4l17.5-8.4L3.4 3.6l3.1 6.6 8.4 .8-8.4 .8-3.1 6.6z" fill="currentColor"/></svg>
				</button>
			</div>
		</form>

		<p class="fsweb-aic-disclaimer"><?php echo esc_html( $settings['privacy_note'] ); ?></p>
	</section>
</div>
