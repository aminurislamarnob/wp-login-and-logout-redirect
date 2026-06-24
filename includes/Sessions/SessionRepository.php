<?php

namespace PluginizeLab\WpLoginLogoutRedirect\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PluginizeLab\WpLoginLogoutRedirect\Logs\UserAgent;
use WP_Session_Tokens;
use WP_User_Query;

/**
 * Reads and acts on WordPress's own login-session store.
 *
 * The only class that touches session tokens. It does not invent session
 * tracking: it lists users with a non-empty `session_tokens` meta and expands
 * each via WP_Session_Tokens, and destroys sessions through the supported core
 * APIs. Sessions are keyed to the client by a non-reversible token_id (a hash of
 * the verifier), never the raw token.
 */
class SessionRepository {

	/**
	 * Paginated list of users with active sessions.
	 *
	 * @param array $args page|per_page|search|role.
	 * @return array{items:array, total:int, pages:int}
	 */
	public function query( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 20,
				'search'   => '',
				'role'     => '',
			)
		);

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = min( 100, max( 1, absint( $args['per_page'] ) ) );

		// Only users that have a session_tokens meta row. meta_query keeps the
		// scanned set to logged-in users rather than the whole user table.
		$query_args = array(
			'meta_key'     => 'session_tokens', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_compare' => 'EXISTS',
			'number'       => $per_page,
			'paged'        => $page,
			'count_total'  => true,
			'fields'       => 'all',
			'orderby'      => 'ID',
			'order'        => 'ASC',
		);

		if ( '' !== $args['search'] ) {
			$query_args['search']         = '*' . sanitize_text_field( $args['search'] ) . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( '' !== $args['role'] ) {
			$query_args['role'] = sanitize_key( $args['role'] );
		}

		$user_query = new WP_User_Query( $query_args );
		$users      = (array) $user_query->get_results();
		$total      = (int) $user_query->get_total();

		$current_token = $this->current_token_id();

		$items = array();
		foreach ( $users as $user ) {
			$sessions = $this->sessions_for_user( $user->ID, $current_token );

			// A user can carry a stale (all-expired) session_tokens meta; skip it.
			if ( empty( $sessions ) ) {
				continue;
			}

			$items[] = array(
				'user_id'       => (int) $user->ID,
				'user_login'    => $user->user_login,
				'display_name'  => $user->display_name,
				'user_email'    => $user->user_email,
				'avatar'        => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
				'roles'         => array_values( (array) $user->roles ),
				'session_count' => count( $sessions ),
				'sessions'      => $sessions,
			);
		}

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Expand a single user's non-expired sessions.
	 *
	 * @param int    $user_id       User id.
	 * @param string $current_token The requesting admin's own token id, if any.
	 * @return array
	 */
	protected function sessions_for_user( $user_id, $current_token = '' ) {
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$now     = time();
		$out     = array();

		foreach ( $manager->get_all() as $token_data ) {
			if ( empty( $token_data['expiration'] ) || $token_data['expiration'] < $now ) {
				continue;
			}

			$ua     = isset( $token_data['ua'] ) ? (string) $token_data['ua'] : '';
			$parsed = UserAgent::parse( $ua );

			$token_id = $this->token_id( $user_id, $token_data );

			$out[] = array(
				'token_id'   => $token_id,
				'login'      => isset( $token_data['login'] ) ? (int) $token_data['login'] : 0,
				'expiration' => (int) $token_data['expiration'],
				'ip'         => isset( $token_data['ip'] ) ? (string) $token_data['ip'] : '',
				'browser'    => $parsed['browser'],
				'device_os'  => $parsed['device_os'],
				'is_current' => '' !== $current_token && hash_equals( $current_token, $token_id ),
			);
		}

		return $out;
	}

	/**
	 * Stable, non-reversible id for a session.
	 *
	 * Core stores sessions keyed by the token verifier (a hash) since WP 4.0; we
	 * hash it again with the user id so the client never receives anything that
	 * maps back to a usable token. The id is only used to address a session for
	 * destruction within this repository.
	 *
	 * @param int   $user_id    User id.
	 * @param array $token_data The session array from WP_Session_Tokens::get_all.
	 * @return string
	 */
	protected function token_id( $user_id, $token_data ) {
		// get_all() drops the verifier key, so derive a stable id from the
		// session's immutable fields. This is matched the same way on destroy.
		$seed = $user_id . '|' . ( isset( $token_data['login'] ) ? $token_data['login'] : '' ) . '|' . ( isset( $token_data['expiration'] ) ? $token_data['expiration'] : '' ) . '|' . ( isset( $token_data['ip'] ) ? $token_data['ip'] : '' ) . '|' . ( isset( $token_data['ua'] ) ? $token_data['ua'] : '' );

		return wp_hash( $seed );
	}

	/**
	 * The token id of the request's own session, for self-identification.
	 *
	 * @return string
	 */
	protected function current_token_id() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$token = wp_get_session_token();

		if ( ! $token ) {
			return '';
		}

		$manager = WP_Session_Tokens::get_instance( $user_id );
		$session = $manager->get( $token );

		if ( ! is_array( $session ) ) {
			return '';
		}

		return $this->token_id( $user_id, $session );
	}

	/**
	 * Destroy one session of a user, addressed by its token_id.
	 *
	 * @param int    $user_id  User id.
	 * @param string $token_id Stable session id from query().
	 * @return bool Whether a matching session was destroyed.
	 */
	public function destroy_session( $user_id, $token_id ) {
		$user_id  = absint( $user_id );
		$token_id = (string) $token_id;

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}

		// WP_Session_Tokens only exposes destroy($raw_token) / destroy_others /
		// destroy_all, and get_all() does not return the raw token. To drop one
		// specific device we filter the raw session_tokens meta — the same store
		// core's WP_User_Meta_Session_Tokens reads and writes.
		$sessions = get_user_meta( $user_id, 'session_tokens', true );

		if ( ! is_array( $sessions ) ) {
			return false;
		}

		$found = false;
		foreach ( $sessions as $verifier => $data ) {
			if ( hash_equals( $this->token_id( $user_id, $data ), $token_id ) ) {
				unset( $sessions[ $verifier ] );
				$found = true;
			}
		}

		if ( ! $found ) {
			return false;
		}

		if ( empty( $sessions ) ) {
			delete_user_meta( $user_id, 'session_tokens' );
		} else {
			update_user_meta( $user_id, 'session_tokens', $sessions );
		}

		$this->fire_destroyed( $user_id, 'session' );

		return true;
	}

	/**
	 * Destroy all sessions of a single user.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public function destroy_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}

		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		$this->fire_destroyed( $user_id, 'user' );

		return true;
	}

	/**
	 * Destroy all sessions for a set of users (bulk).
	 *
	 * @param int[] $user_ids User ids.
	 * @return int Number of users affected.
	 */
	public function destroy_users( array $user_ids ) {
		$affected = 0;

		foreach ( $user_ids as $user_id ) {
			if ( $this->destroy_user( $user_id ) ) {
				++$affected;
			}
		}

		return $affected;
	}

	/**
	 * Destroy every user's sessions, optionally excluding some users.
	 *
	 * @param int[] $exclude User ids to leave logged in (e.g. the current admin).
	 * @return int Number of users affected.
	 */
	public function destroy_all( array $exclude = array() ) {
		$exclude = array_map( 'absint', $exclude );

		// Only iterate users that actually have sessions.
		$user_ids = get_users(
			array(
				'meta_key'     => 'session_tokens', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
			)
		);

		$affected = 0;
		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;

			if ( in_array( $user_id, $exclude, true ) ) {
				continue;
			}

			if ( $this->destroy_user( $user_id ) ) {
				++$affected;
			}
		}

		return $affected;
	}

	/**
	 * Fire the extension hook after a destroy.
	 *
	 * @param int    $user_id User id.
	 * @param string $context self|session|user|bulk|all.
	 * @return void
	 */
	protected function fire_destroyed( $user_id, $context ) {
		/**
		 * Fires after a session (or sessions) is forcibly destroyed.
		 *
		 * The audit logger hooks this to record a forced_logout row.
		 *
		 * @param int    $user_id The affected user.
		 * @param string $context Scope: session|user|bulk|all.
		 */
		do_action( 'wplalr_session_destroyed', $user_id, $context );
	}
}
