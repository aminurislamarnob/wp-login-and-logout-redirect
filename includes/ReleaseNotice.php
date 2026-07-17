<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

/**
 * Dismissible admin notice announcing the current release.
 *
 * Shown to admins on every admin screen (Dokan-style card with the plugin
 * logo, blurb and a "See What's New" CTA) until dismissed. The dismissal is
 * stored per release version, so shipping a new version re-surfaces the
 * notice once.
 */
class ReleaseNotice {

	/**
	 * Option storing the version the notice was dismissed for.
	 */
	const DISMISSED_OPTION = 'wplalr_release_notice_dismissed';

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'wp_ajax_wplalr_dismiss_release_notice', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Whether the notice should render on this request.
	 *
	 * @return bool
	 */
	protected function should_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return get_option( self::DISMISSED_OPTION ) !== WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION;
	}

	/**
	 * Render the release notice card.
	 */
	public function render_notice() {
		if ( ! $this->should_render() ) {
			return;
		}

		$logo_url      = WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_ADMIN_ASSET . '/images/notice-icon.png';
		$whats_new_url = admin_url( 'admin.php?page=wplalr_whats_new' );
		$nonce         = wp_create_nonce( 'wplalr_dismiss_release_notice' );
		?>
		<div class="notice wplalr-release-notice" id="wplalr-release-notice">
			<div class="wplalr-release-notice__logo">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Entryway', 'wp-login-logout-redirect' ); ?>" />
			</div>
			<div class="wplalr-release-notice__content">
				<h3 class="wplalr-release-notice__title">
					<?php
					printf(
						/* translators: %s: plugin version number. */
						esc_html__( 'Meet Entryway — WP Login & Logout Redirect v%s is here.', 'wp-login-logout-redirect' ),
						esc_html( WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION )
					);
					?>
				</h3>
				<p class="wplalr-release-notice__text">
					<?php esc_html_e( 'Same plugin, new name — now with a redirect rule engine, audit logs, live session management and email alerts.', 'wp-login-logout-redirect' ); ?>
				</p>
				<div class="wplalr-release-notice__actions">
					<a href="<?php echo esc_url( $whats_new_url ); ?>" class="wplalr-release-notice__btn wplalr-release-notice__btn--primary">
						<?php esc_html_e( "See What's New", 'wp-login-logout-redirect' ); ?>
					</a>
					<button type="button" class="wplalr-release-notice__btn wplalr-release-notice__btn--ghost" id="wplalr-release-notice-dismiss">
						<?php esc_html_e( 'Dismiss', 'wp-login-logout-redirect' ); ?>
					</button>
				</div>
			</div>
		</div>
		<style>
			.wplalr-release-notice {
				display: flex;
				align-items: flex-start;
				gap: 20px;
				padding: 20px;
				margin: 15px 15px 15px 0;
				border: none;
				border-left: 4px solid #3858e9;
				border-radius: 0;
				background: #ffffff;
				box-shadow: none;
			}
			.wplalr-release-notice__logo img {
				display: block;
				width: 88px;
				height: 88px;
			}
			.wplalr-release-notice__title {
				margin: 0 0 6px;
				font-size: 15px;
				font-weight: 600;
				color: #1e1e1e;
			}
			.wplalr-release-notice__content p.wplalr-release-notice__text {
				margin: 0 0 10px;
				font-size: 13px;
				color: #888888;
			}
			.wplalr-release-notice__actions {
				display: flex;
				gap: 10px;
			}
			.wplalr-release-notice .wplalr-release-notice__btn,
			.wplalr-release-notice .wplalr-release-notice__btn:hover,
			.wplalr-release-notice .wplalr-release-notice__btn:focus,
			.wplalr-release-notice .wplalr-release-notice__btn:active,
			.wplalr-release-notice .wplalr-release-notice__btn:visited {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				height: 30px;
				padding: 0 14px;
				border-radius: 0;
				border: 1px solid #3858e9;
				font-size: 13px;
				font-weight: 500;
				line-height: 1;
				text-decoration: none;
				cursor: pointer;
				box-sizing: border-box;
				transition: background 0.15s ease, color 0.15s ease;
			}
			.wplalr-release-notice .wplalr-release-notice__btn--primary,
			.wplalr-release-notice .wplalr-release-notice__btn--primary:visited {
				background: #3858e9;
				color: #ffffff;
			}
			.wplalr-release-notice .wplalr-release-notice__btn--primary:hover,
			.wplalr-release-notice .wplalr-release-notice__btn--primary:focus,
			.wplalr-release-notice .wplalr-release-notice__btn--primary:active {
				background: #2145e6;
				border-color: #2145e6;
				color: #ffffff;
			}
			.wplalr-release-notice .wplalr-release-notice__btn--ghost,
			.wplalr-release-notice .wplalr-release-notice__btn--ghost:visited {
				background: #ffffff;
				color: #3858e9;
			}
			.wplalr-release-notice .wplalr-release-notice__btn--ghost:hover,
			.wplalr-release-notice .wplalr-release-notice__btn--ghost:focus,
			.wplalr-release-notice .wplalr-release-notice__btn--ghost:active {
				background: #edf1fe;
				color: #2145e6;
			}
			.wplalr-release-notice .wplalr-release-notice__btn:focus {
				outline: none;
				box-shadow: 0 0 0 3px rgba( 56, 88, 233, 0.18 );
			}
		</style>
		<script>
			( function () {
				var button = document.getElementById( 'wplalr-release-notice-dismiss' );

				if ( ! button ) {
					return;
				}

				button.addEventListener( 'click', function () {
					var notice = document.getElementById( 'wplalr-release-notice' );
					var data   = new FormData();

					data.append( 'action', 'wplalr_dismiss_release_notice' );
					data.append( 'nonce', <?php echo wp_json_encode( $nonce ); ?> );

					window.fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
						method: 'POST',
						credentials: 'same-origin',
						body: data,
					} );

					if ( notice ) {
						notice.remove();
					}
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * AJAX handler: persist the dismissal for the current version.
	 */
	public function dismiss_notice() {
		check_ajax_referer( 'wplalr_dismiss_release_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		update_option( self::DISMISSED_OPTION, WP_LOGIN_LOGOUT_REDIRECT_PLUGIN_VERSION );

		wp_send_json_success();
	}
}
