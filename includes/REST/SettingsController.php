<?php

namespace PluginizeLab\WpLoginLogoutRedirect\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\WpLoginLogoutRedirect\RuleEngine;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * Admin settings REST API controller.
 */
class SettingsController extends WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base;

	/**
	 * Constructor.
	 *
	 * Sets the namespace and rest base for the controller.
	 */
	public function __construct() {
		$this->namespace = 'wplalr/v1';
		$this->rest_base = 'settings';
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_settings_permissions_check' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_settings_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);
	}

	/**
	 * Get the settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 */
	public function get_settings( $request ) {
		$settings = array(
			'wplalr_login_redirect'      => get_option( 'wplalr_login_redirect', '' ),
			'wplalr_logout_redirect'     => get_option( 'wplalr_logout_redirect', '' ),
			'rules'                      => array_values( (array) get_option( RuleEngine::OPTION_RULES, array() ) ),
			'wplalr_enable_logs'         => 'yes' === get_option( 'wplalr_enable_logs', 'no' ),
			'wplalr_logs_retention_days' => (int) get_option( 'wplalr_logs_retention_days', 30 ),
		);

		/**
		 * Filter the settings response payload.
		 *
		 * Pro extensions hook here to surface additional settings.
		 *
		 * @param array $settings The settings payload.
		 */
		$settings = apply_filters( 'wplalr_rest_settings_response', $settings );

		return rest_ensure_response( $settings );
	}

	/**
	 * Update the settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 */
	public function update_settings( $request ) {
		if ( $request->has_param( 'wplalr_login_redirect' ) ) {
			update_option( 'wplalr_login_redirect', $this->sanitize_redirect_url( $request->get_param( 'wplalr_login_redirect' ) ) );
		}

		if ( $request->has_param( 'wplalr_logout_redirect' ) ) {
			update_option( 'wplalr_logout_redirect', $this->sanitize_redirect_url( $request->get_param( 'wplalr_logout_redirect' ) ) );
		}

		if ( $request->has_param( 'rules' ) ) {
			update_option( RuleEngine::OPTION_RULES, $this->sanitize_rules( $request->get_param( 'rules' ) ) );
		}

		if ( $request->has_param( 'wplalr_enable_logs' ) ) {
			update_option( 'wplalr_enable_logs', $request->get_param( 'wplalr_enable_logs' ) ? 'yes' : 'no' );
		}

		if ( $request->has_param( 'wplalr_logs_retention_days' ) ) {
			update_option( 'wplalr_logs_retention_days', absint( $request->get_param( 'wplalr_logs_retention_days' ) ) );
		}

		return $this->get_settings( $request );
	}

	/**
	 * Sanitize a redirect URL while preserving {{placeholder}} tokens.
	 *
	 * Redirect URLs may contain placeholders such as `{{website_url}}` or
	 * `{{username}}`. `esc_url_raw()` strips the curly braces (and can prepend a
	 * scheme), which corrupts the template, so it is skipped when a placeholder is
	 * present. The resolved URL is escaped and validated *after* placeholder
	 * expansion at redirect time (see Redirection::resolve_login_redirect and
	 * Redirection::redirect_after_logout), so the stored template stays intact.
	 *
	 * @param mixed $value Raw URL from the request.
	 * @return string
	 */
	protected function sanitize_redirect_url( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/\{\{\s*[a-z0-9_]+\s*\}\}/i', $value ) ) {
			return sanitize_text_field( $value );
		}

		return esc_url_raw( $value );
	}

	/**
	 * Sanitize and validate an array of redirect rules.
	 *
	 * @param mixed $raw Raw rules from the request.
	 * @return array
	 */
	protected function sanitize_rules( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$match_types = $this->get_match_types();
		$clean       = array();

		foreach ( $raw as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$raw_conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] )
				? $rule['conditions']
				: array();

			$conditions = array();

			foreach ( $raw_conditions as $condition ) {
				if ( ! is_array( $condition ) ) {
					continue;
				}

				$type = isset( $condition['type'] ) ? sanitize_key( $condition['type'] ) : '';

				if ( ! in_array( $type, $match_types, true ) ) {
					continue;
				}

				$values = isset( $condition['values'] ) && is_array( $condition['values'] )
					? $this->sanitize_condition_values( $type, $condition['values'] )
					: array();

				if ( empty( $values ) ) {
					continue;
				}

				$conditions[] = array(
					'type'   => $type,
					'values' => $values,
				);
			}

			$clean_rule = array(
				'id'         => isset( $rule['id'] ) && '' !== $rule['id'] ? sanitize_text_field( $rule['id'] ) : wp_generate_uuid4(),
				'enabled'    => ! empty( $rule['enabled'] ),
				'label'      => isset( $rule['label'] ) ? sanitize_text_field( $rule['label'] ) : '',
				'conditions' => $conditions,
				'login_url'  => isset( $rule['login_url'] ) ? $this->sanitize_redirect_url( $rule['login_url'] ) : '',
				'logout_url' => isset( $rule['logout_url'] ) ? $this->sanitize_redirect_url( $rule['logout_url'] ) : '',
			);

			/**
			 * Filter a single sanitized rule before it is stored.
			 *
			 * Pro extensions hook here to add their own sanitized fields
			 * (e.g. first_login_only, wc_context) that the free plugin drops.
			 *
			 * @param array $clean_rule The sanitized rule.
			 * @param array $rule       The raw rule from the request.
			 */
			$clean[] = apply_filters( 'wplalr_rest_sanitize_rule', $clean_rule, $rule );
		}

		return $clean;
	}

	/**
	 * The condition match types the rule engine understands.
	 *
	 * @return array
	 */
	protected function get_match_types() {
		/**
		 * Filter the available rule condition match types.
		 *
		 * Pro extensions hook here to register custom match types; they should
		 * also handle their sanitization via `wplalr_sanitize_condition_values`
		 * and matching via `wplalr_match_condition`.
		 *
		 * @param array $types Match type slugs.
		 */
		return apply_filters( 'wplalr_rule_match_types', array( 'role', 'user', 'capability' ) );
	}

	/**
	 * Sanitize a condition's values against its type.
	 *
	 * @param string $type   Condition type: role|user|capability or a custom type.
	 * @param array  $values Raw values.
	 * @return array
	 */
	protected function sanitize_condition_values( $type, $values ) {
		if ( ! in_array( $type, array( 'role', 'user', 'capability' ), true ) ) {
			/**
			 * Sanitize the values for a custom condition type.
			 *
			 * @param array  $clean  Sanitized values. Default empty array.
			 * @param string $type   The condition type.
			 * @param array  $values The raw values.
			 */
			return array_values( (array) apply_filters( 'wplalr_sanitize_condition_values', array(), $type, $values ) );
		}

		$valid_roles = array_keys( wp_roles()->roles );
		$clean       = array();

		foreach ( $values as $value ) {
			switch ( $type ) {
				case 'role':
					$role = sanitize_key( $value );
					if ( in_array( $role, $valid_roles, true ) ) {
						$clean[] = $role;
					}
					break;

				case 'user':
					$user_id = absint( $value );
					if ( $user_id && get_user_by( 'id', $user_id ) ) {
						$clean[] = (string) $user_id;
					}
					break;

				case 'capability':
					$cap = sanitize_key( $value );
					if ( '' !== $cap ) {
						$clean[] = $cap;
					}
					break;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Schema for a single redirect rule.
	 *
	 * @return array
	 */
	protected function get_rule_schema() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'id'         => array( 'type' => 'string' ),
				'enabled'    => array( 'type' => 'boolean' ),
				'label'      => array( 'type' => 'string' ),
				'conditions' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'type'   => array(
								'type' => 'string',
								'enum' => array_values( $this->get_match_types() ),
							),
							'values' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'login_url'  => array(
					'type' => 'string',
				),
				'logout_url' => array(
					'type' => 'string',
				),
			),
		);

		/**
		 * Filter the schema for a single redirect rule.
		 *
		 * Pro extensions hook here to register additional rule fields.
		 *
		 * @param array $schema The rule schema.
		 */
		return apply_filters( 'wplalr_rest_rule_schema', $schema );
	}

	/**
	 * Check if a given request has access to get the settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has access, false otherwise.
	 */
	public function get_settings_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a given request has access to update the settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has access, false otherwise.
	 */
	public function update_settings_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the schema for a single item.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => array(
				'wplalr_login_redirect'  => array(
					'description' => __( 'URL to redirect the user to after a successful login. May contain {{placeholder}} tokens.', 'wp-login-logout-redirect' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'wplalr_logout_redirect' => array(
					'description' => __( 'URL to redirect the user to after a successful logout. May contain {{placeholder}} tokens.', 'wp-login-logout-redirect' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'rules'                  => array(
					'description' => __( 'Ordered redirect rules.', 'wp-login-logout-redirect' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => $this->get_rule_schema(),
				),
				'wplalr_enable_logs'         => array(
					'description' => __( 'Whether login/logout audit logging is enabled.', 'wp-login-logout-redirect' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'wplalr_logs_retention_days' => array(
					'description' => __( 'Auto-delete log rows older than this many days (0 = keep forever).', 'wp-login-logout-redirect' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
	}
}
