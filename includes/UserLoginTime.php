<?php

namespace PluginizeLab\WpLoginLogoutRedirect;

class UserLoginTime {
	/**
	* The constructor.
	*/
	public function __construct() {
		add_action( 'wp_login', array( $this, 'update_user_login_timestamp' ) );
        add_filter( 'manage_users_columns', array( $this, 'add_user_table_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'user_last_login_time' ), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this, 'user_login_time_sortable_columns' ) );
        add_action( 'pre_get_users', array( $this, 'sort_user_last_login_column' ) );
	}

    /**
    * Update login user time on db
    *
    * @param string     $user_login User login.
    * @param int|object $user       User.
    * @return void
    */
	public function update_user_login_timestamp( $user_login, $user ) {
		update_user_meta( $user->ID, 'wplalr_last_login', sanitize_text_field( time() ) );
	}

    /**
    * Add last login column to user table
    *
    * @param array $columns
    * @return array
    */
    public function add_user_table_column( $columns ) {
        $columns['wplalr_last_login'] = esc_html__('Last Login', 'wp-login-logout-redirect');
        return $columns;
    }

    /**
     * Retrive Value of each user last login time and show on our added user table column
     *
	 * @param string $output HTML for the column.
	 * @param string $column_id User list table column.
	 * @param int    $user_id User ID.
	 *
	 * @return string
     */
    public function user_last_login_time( $output, $column_id, $user_id ){
        $wplalr_output = '';
        
        if( $column_id == 'wplalr_last_login' ) {
            $wplalr_last_login = get_user_meta( $user_id, 'wplalr_last_login', true );
            $wplalr_date_format = get_option( 'date_format' ) .' '. get_option( 'time_format' );
            $wplalr_output = $wplalr_last_login ? esc_html( wp_date( $wplalr_date_format, $wplalr_last_login ) ) : '-';
        }
     
        return $wplalr_output;
    }

    /**
     * Make user last login column sortable
     *
     * @param array $columns
     * @return array
     */
    public function user_login_time_sortable_columns( $columns ) {
        return wp_parse_args( array(
            'wplalr_last_login' => 'wplalr_last_login'
        ), $columns );
    }

    
    /**
     * Sort user last login column
     *
     * @param object $query
     * @return object
     */
    public function sort_user_last_login_column( $query ) {
        if( !is_admin() ) {
            return $query;
        }
     
        $screen = get_current_screen();
        if( isset( $screen->id ) && $screen->id !== 'users' ) {
            return $query;
        }
     
        if( isset( $_GET[ 'orderby' ] ) && $_GET[ 'orderby' ] == 'wplalr_last_login' ) {
            $query->query_vars['meta_key'] = 'wplalr_last_login';
            $query->query_vars['orderby'] = 'meta_value';
        }
     
        return $query;
    }
}
