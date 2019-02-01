<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path( __FILE__ ) .'ResultsDataAccess.php';
	
class BaseController 
{
    protected $namespace;

    protected $dataAccess;    
    
    protected $user;
	
	public function __construct($namespace) {        
        $this->namespace = $namespace;
        $this->dataAccess = new ResultsDataAccess();
	}
	
	public function isValidId( $value, $request, $key ) {
		if ( $value < 1 ) {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must be greater than 0', $key, $value ), array( 'status' => 400 ) );
		} else {
			return true;
		}
    }
    
    public function isAuthorized( \WP_REST_Request $request ) {
        $id = $this->basicAuthHandler();
        if ( $id  <= 0 ) {				
            return new \WP_Error( 'rest_forbidden',
                sprintf( 'You must be logged in to use this API.' ), array( 'status' => 403 ) );
        } else if (!user_can( $id, 'publish_pages' )){
            return new \WP_Error( 'rest_forbidden',
                sprintf( 'You do not have enough privlidges to use this API.' ), array( 'status' => 403 ) );
        } else {
            return true;
        }
    }

    private function basicAuthHandler() {
        // Don't authenticate if logged in
        if ( ! empty( $this->user ) ) {
            return $this->user->ID;
        }
        
        // Check that we're trying to authenticate ?????
        if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
            return $this->user->ID;
        }

        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        
        /**
         * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
         * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
         * recursion and a stack overflow unless the current function is removed from the determine_current_user
         * filter during authentication.
         */
        remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
        $user = wp_authenticate( $username, $password );
        add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
        if ( is_wp_error( $user ) ) {				
            return 0;
        }
        
        return $user->ID;
    }
}
?>