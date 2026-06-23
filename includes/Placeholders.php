<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expands dynamic placeholders inside redirect URLs.
 *
 * Free tokens: {{username}}, {{user_slug}}, {{website_url}}. Pro extensions add
 * tokens (e.g. {{current_page}}, {{previous_page}}) via the
 * `wplalr/placeholders` filter.
 */
class Placeholders {

	/**
	 * Build the placeholder => replacement map for a user.
	 *
	 * @param \WP_User|null $user The user being redirected.
	 * @return array
	 */
	public function get_map( $user = null ) {
		$map = array(
			'{{website_url}}' => home_url(),
		);

		if ( $user instanceof \WP_User && $user->exists() ) {
			$map['{{username}}']  = $user->user_login;
			$map['{{user_slug}}'] = $user->user_nicename;
		}

		/**
		 * Filter the placeholder replacement map.
		 *
		 * Pro extensions hook here to register dynamic tokens such as
		 * {{current_page}} and {{previous_page}}.
		 *
		 * @param array         $map  Map of `{{token}}` => replacement value.
		 * @param \WP_User|null $user The user being redirected.
		 */
		return apply_filters( 'wplalr/placeholders', $map, $user );
	}

	/**
	 * Replace placeholders in a URL.
	 *
	 * @param string        $url  The URL, possibly containing placeholders.
	 * @param \WP_User|null $user The user being redirected.
	 * @return string
	 */
	public function replace( $url, $user = null ) {
		if ( empty( $url ) || strpos( $url, '{{' ) === false ) {
			return $url;
		}

		return strtr( $url, $this->get_map( $user ) );
	}
}
