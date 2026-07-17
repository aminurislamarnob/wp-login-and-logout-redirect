<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves login/logout redirect URLs from the ordered rule set.
 *
 * A rule matches when *every* one of its conditions passes (AND logic). Within
 * a single condition, any one of its values is enough (OR logic). Rules are
 * evaluated in order and the first matching, enabled rule with a URL for the
 * requested event wins. When nothing matches, the caller falls back to the
 * legacy global options.
 */
class RuleEngine {

	/**
	 * Option key holding the ordered array of rule objects.
	 *
	 * @var string
	 */
	const OPTION_RULES = 'wplalr_redirect_rules';

	/**
	 * Get the stored rules.
	 *
	 * @return array
	 */
	public function get_rules() {
		$rules = get_option( self::OPTION_RULES, array() );

		return is_array( $rules ) ? array_values( $rules ) : array();
	}

	/**
	 * Resolve the redirect URL for a given event and user.
	 *
	 * @param string        $event 'login' or 'logout'.
	 * @param \WP_User|null $user  The user being redirected.
	 * @return string The resolved URL, or an empty string when no rule applies.
	 */
	public function resolve( $event, $user = null ) {
		do_action( 'wplalr_before_resolve', $event, $user );

		$url     = '';
		$matched = null;
		$url_key = 'logout' === $event ? 'logout_url' : 'login_url';

		foreach ( $this->get_rules() as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['enabled'] ) ) {
				continue;
			}

			if ( ! $this->matches( $rule, $user ) ) {
				continue;
			}

			if ( ! empty( $rule[ $url_key ] ) ) {
				$url     = (string) $rule[ $url_key ];
				$matched = $rule;
				break;
			}
		}

		/**
		 * Filter the resolved redirect URL before validation.
		 *
		 * Pro extensions hook here to override the destination (referrer
		 * placeholders, WooCommerce/EDD-aware flows, etc.).
		 *
		 * @param string        $url     The resolved URL (may be empty).
		 * @param \WP_User|null $user    The user being redirected.
		 * @param array|null    $matched The matched rule, or null.
		 * @param string        $event   'login' or 'logout'.
		 */
		$url = apply_filters( 'wplalr_resolve_redirect', $url, $user, $matched, $event );

		do_action( 'wplalr_after_resolve', $url, $event, $user, $matched );

		return $url;
	}

	/**
	 * Whether every condition of a rule passes for the given user.
	 *
	 * A rule with no conditions matches everyone.
	 *
	 * @param array         $rule The rule object.
	 * @param \WP_User|null $user The user being redirected.
	 * @return bool
	 */
	protected function matches( $rule, $user ) {
		$conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] )
			? $rule['conditions']
			: array();

		if ( empty( $conditions ) ) {
			return true;
		}

		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return false;
		}

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || ! $this->condition_passes( $condition, $user ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a single condition passes for the user (OR within its values).
	 *
	 * @param array    $condition The condition object.
	 * @param \WP_User $user      The user being redirected.
	 * @return bool
	 */
	protected function condition_passes( $condition, \WP_User $user ) {
		$type   = isset( $condition['type'] ) ? $condition['type'] : '';
		$values = isset( $condition['values'] ) && is_array( $condition['values'] )
			? $condition['values']
			: array();

		if ( empty( $values ) ) {
			return false;
		}

		switch ( $type ) {
			case 'role':
				return (bool) array_intersect( array_map( 'strval', $values ), (array) $user->roles );

			case 'user':
				return in_array( (string) $user->ID, array_map( 'strval', $values ), true );

			case 'capability':
				foreach ( $values as $cap ) {
					if ( $user->has_cap( (string) $cap ) ) {
						return true;
					}
				}

				return false;

			default:
				/**
				 * Resolve a match for a condition type the free plugin does not handle.
				 *
				 * Pro extensions hook here to evaluate custom match types registered
				 * via the `wplalr_rule_match_types` filter.
				 *
				 * @param bool     $matched Whether the condition matches. Default false.
				 * @param string   $type    The condition type.
				 * @param array    $values  The condition values.
				 * @param \WP_User $user    The user being redirected.
				 */
				return (bool) apply_filters( 'wplalr_match_condition', false, $type, $values, $user );
		}
	}
}
