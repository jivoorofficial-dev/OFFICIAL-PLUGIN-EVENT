<?php
/**
 * Frontend Router & Shortcodes for /launch, /launch/vip, /launch/ticket, and /launch/checkin
 *
 * Completely independent of the active WordPress theme while also providing
 * optional shortcodes ([tarbwy_landing_page], [tarbwy_registration_form]) for Gutenberg/Elementor integration.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FrontendRouter {

	/**
	 * Initialize rewrite rules, query vars, template redirect, and shortcodes
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_route_dispatch' ), 1 );

		// Shortcodes for Gutenberg / Elementor compatibility if site editors embed inside pages
		add_shortcode( 'tarbwy_landing_page', array( __CLASS__, 'render_landing_shortcode' ) );
		add_shortcode( 'tarbwy_landing_v2', array( __CLASS__, 'render_landing_v2_shortcode' ) );
		add_shortcode( 'tarbwy_registration_form', array( __CLASS__, 'render_registration_form_shortcode' ) );
	}

	/**
	 * Register custom rewrite rules for /launch, /launch/vip, /launch/ticket, /launch/checkin
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule( '^launch/?$', 'index.php?tarbwy_route=landing', 'top' );
		add_rewrite_rule( '^launch-v2/?$', 'index.php?tarbwy_route=landing_v2_redirect', 'top' );
		add_rewrite_rule( '^launch/v2/?$', 'index.php?tarbwy_route=landing_v2_redirect', 'top' );
		add_rewrite_rule( '^launch/vip/?$', 'index.php?tarbwy_route=vip', 'top' );
		add_rewrite_rule( '^launch/ticket/?$', 'index.php?tarbwy_route=ticket', 'top' );
		add_rewrite_rule( '^launch/checkin/?$', 'index.php?tarbwy_route=checkin', 'top' );
	}

	/**
	 * Register query variables
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'tarbwy_route';
		$vars[] = 'token';
		$vars[] = 't';
		return $vars;
	}

	/**
	 * Intercept and render standalone RTL institutional templates
	 */
	public static function handle_route_dispatch() {
		$route = get_query_var( 'tarbwy_route' );

		// Fallback path inspection if permalink flush is pending
		if ( empty( $route ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path = trim( wp_parse_url( $uri, PHP_URL_PATH ), '/' );
			if ( 'launch' === $path ) {
				$route = 'landing';
			} elseif ( 'launch-v2' === $path || 'launch/v2' === $path ) {
				$route = 'landing_v2_redirect';
			} elseif ( 'launch/vip' === $path ) {
				$route = 'vip';
			} elseif ( 'launch/ticket' === $path ) {
				$route = 'ticket';
			} elseif ( 'launch/checkin' === $path ) {
				$route = 'checkin';
			}
		}

		if ( empty( $route ) ) {
			return;
		}

		if ( 'landing_v2_redirect' === $route || 'landing_v2' === $route ) {
			wp_safe_redirect( home_url( '/launch' ), 301 );
			exit;
		}

		$event = RestAPI::get_active_event();

		if ( in_array( $route, array( 'vip', 'ticket', 'checkin' ), true ) ) {
			Security::send_no_leak_headers();
		}

		if ( 'checkin' === $route ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url( home_url( '/launch/checkin' ) ) );
				exit;
			}
			if ( ! Roles::can_checkin() ) {
				wp_die( 'عذراً، ليست لديك صلاحية الوصول إلى بوابة تسجيل الدخول (Check-in).', 'غير مصرح', array( 'response' => 403, 'back_link' => true ) );
			}
			include TARBWY_EM_PATH . 'templates/checkin.php';
			exit;
		}

		if ( 'vip' === $route ) {
			include TARBWY_EM_PATH . 'templates/vip-rsvp.php';
			exit;
		}

		if ( 'ticket' === $route ) {
			include TARBWY_EM_PATH . 'templates/ticket.php';
			exit;
		}

		if ( 'landing' === $route ) {
			include TARBWY_EM_PATH . 'templates/landing.php';
			exit;
		}
	}

	/**
	 * Shortcode [tarbwy_landing_page] for Gutenberg / Elementor compatibility
	 */
	public static function render_landing_shortcode() {
		ob_start();
		$event              = RestAPI::get_active_event();
		$is_shortcode_embed = true;
		include TARBWY_EM_PATH . 'templates/landing.php';
		return ob_get_clean();
	}

	/**
	 * Backward-compatible shortcode alias [tarbwy_landing_v2] -> renders /launch v2 template
	 */
	public static function render_landing_v2_shortcode() {
		return self::render_landing_shortcode();
	}

	/**
	 * Shortcode [tarbwy_registration_form] for embedding only the form inside Elementor/Gutenberg
	 */
	public static function render_registration_form_shortcode() {
		$event = RestAPI::get_active_event();
		if ( ! $event ) {
			return '<p dir="rtl">الحدث غير متاح حالياً.</p>';
		}
		ob_start();
		?>
		<div class="tarbwy-embedded-form" dir="rtl">
			<p>لطلب حضور «<?php echo esc_html( $event->title ); ?>»، يرجى الانتقال إلى <a href="<?php echo esc_url( home_url( '/launch#register' ) ); ?>">استمارة التسجيل الرسمية</a>.</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
