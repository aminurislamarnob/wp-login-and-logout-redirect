<?php

namespace PluginizeLab\WpLoginLogoutRedirect\Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access layer for the audit-log table.
 *
 * The only class that issues SQL against the log table. All input is sanitized
 * here; dynamic identifiers (orderby/order) are allowlisted and values are bound
 * via $wpdb->prepare. Callers pass/receive plain arrays.
 */
class LogRepository {

	/**
	 * Columns that may be sorted on (allowlist for `orderby`).
	 *
	 * @var string[]
	 */
	const SORTABLE = array( 'id', 'created_at', 'username', 'event', 'status' );

	/**
	 * Event/status columns we expose for filtering.
	 *
	 * @var string[]
	 */
	const EVENTS = array( 'login', 'logout', 'failed', 'forced_logout' );

	/**
	 * Insert a log row.
	 *
	 * @param array $data Row data keyed by column.
	 * @return int Inserted row id, or 0 on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row = array(
			'user_id'      => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
			'username'     => isset( $data['username'] ) ? sanitize_text_field( $data['username'] ) : '',
			'event'        => isset( $data['event'] ) ? sanitize_key( $data['event'] ) : '',
			'status'       => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'success',
			'redirect_url' => isset( $data['redirect_url'] ) && '' !== $data['redirect_url'] ? esc_url_raw( $data['redirect_url'] ) : null,
			'rule_id'      => isset( $data['rule_id'] ) && '' !== $data['rule_id'] ? sanitize_text_field( $data['rule_id'] ) : null,
			'ip'           => isset( $data['ip'] ) ? sanitize_text_field( $data['ip'] ) : null,
			'agent'        => isset( $data['agent'] ) ? sanitize_text_field( $data['agent'] ) : null,
			'browser'      => isset( $data['browser'] ) ? sanitize_text_field( $data['browser'] ) : null,
			'device_os'    => isset( $data['device_os'] ) ? sanitize_text_field( $data['device_os'] ) : null,
			'error_code'   => isset( $data['error_code'] ) ? sanitize_text_field( $data['error_code'] ) : '',
			'description'  => isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : null,
			// Stored in GMT; prepare_item() converts to a unix timestamp and to site-local for display.
			'created_at'   => isset( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql', true ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( Installer::table_name(), $row );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Paginated, filtered query of log rows.
	 *
	 * @param array $args page|per_page|event|status|search|orderby|order.
	 * @return array{items:array, total:int, pages:int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 20,
				'event'    => '',
				'status'   => '',
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = min( 100, max( 1, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$orderby = in_array( $args['orderby'], self::SORTABLE, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		list( $where_sql, $where_values ) = $this->build_where( $args );

		$table = Installer::table_name();

		// Total count for pagination.
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) ( $where_values ? $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) ) : $wpdb->get_var( $count_sql ) );

		// Page of rows. orderby/order are allowlisted above; values bound below.
		$list_sql      = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_values   = array_merge( $where_values, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );

		return array(
			'items' => array_map( array( $this, 'prepare_item' ), (array) $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Build the WHERE clause + bound values from query args.
	 *
	 * @param array $args Query args.
	 * @return array{0:string,1:array} [ where_sql, values ]
	 */
	protected function build_where( array $args ) {
		$clauses = array();
		$values  = array();

		if ( '' !== $args['event'] && in_array( $args['event'], self::EVENTS, true ) ) {
			$clauses[] = 'event = %s';
			$values[]  = $args['event'];
		}

		if ( '' !== $args['status'] ) {
			$clauses[] = 'status = %s';
			$values[]  = sanitize_key( $args['status'] );
		}

		if ( '' !== $args['search'] ) {
			global $wpdb;
			$like      = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$clauses[] = '( username LIKE %s OR ip LIKE %s )';
			$values[]  = $like;
			$values[]  = $like;
		}

		$where_sql = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';

		return array( $where_sql, $values );
	}

	/**
	 * Normalize a raw DB row for API output.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	protected function prepare_item( $row ) {
		// created_at is stored in GMT; PHP's timezone is UTC under WP, so this
		// yields the correct epoch. Display is converted to the site timezone.
		$timestamp = ! empty( $row['created_at'] ) ? (int) mysql2date( 'U', $row['created_at'] ) : 0;

		$item = array(
			'id'           => (int) $row['id'],
			'user_id'      => $row['user_id'] ? (int) $row['user_id'] : 0,
			'username'     => (string) $row['username'],
			'event'        => (string) $row['event'],
			'status'       => (string) $row['status'],
			'redirect_url' => (string) $row['redirect_url'],
			'rule_id'      => (string) $row['rule_id'],
			'ip'           => (string) $row['ip'],
			'browser'      => (string) $row['browser'],
			'device_os'    => (string) $row['device_os'],
			'error_code'   => (string) $row['error_code'],
			'created_at'   => $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : '',
			'time_diff'    => $timestamp ? sprintf(
				/* translators: %s: human-readable time difference, e.g. "5 mins". */
				__( '%s ago', 'wp-login-logout-redirect' ),
				human_time_diff( $timestamp )
			) : '',
		);

		/**
		 * Filter a single log row before it is returned by the REST API.
		 *
		 * @param array $item The prepared row.
		 * @param array $row  The raw DB row.
		 */
		return apply_filters( 'wplalr_rest_log_item', $item, $row );
	}

	/**
	 * Delete a single row.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( Installer::table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Delete every row (TRUNCATE).
	 *
	 * @return void
	 */
	public function delete_all() {
		global $wpdb;

		$table = Installer::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Retention window in days.
	 * @return int Rows deleted.
	 */
	public function purge_older_than( $days ) {
		global $wpdb;

		$days = absint( $days );

		if ( $days < 1 ) {
			return 0;
		}

		$table  = Installer::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Grouped counts by event for the dashboard cards.
	 *
	 * @param int $days Window in days (0 = all time).
	 * @return array{login:int, logout:int, failed:int, total:int}
	 */
	public function stats( $days = 0 ) {
		global $wpdb;

		$days  = absint( $days );
		$table = Installer::table_name();

		if ( $days > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT event, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY event", $cutoff ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( "SELECT event, COUNT(*) AS total FROM {$table} GROUP BY event", ARRAY_A );
		}

		$stats = array(
			'login'  => 0,
			'logout' => 0,
			'failed' => 0,
			'total'  => 0,
		);

		foreach ( (array) $rows as $row ) {
			$event = isset( $row['event'] ) ? $row['event'] : '';
			$count = isset( $row['total'] ) ? (int) $row['total'] : 0;

			if ( isset( $stats[ $event ] ) ) {
				$stats[ $event ] = $count;
			}

			$stats['total'] += $count;
		}

		return $stats;
	}
}
