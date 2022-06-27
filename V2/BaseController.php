<?php

namespace IpswichJAFFARunningClubAPI\V2;

abstract class BaseController
{
    protected $route;

    protected $command;

    public function __construct($route, $command)
    {
        $this->route = $route;
        $this->command =  $command;
    }

    public function isValidId(string $value, \WP_REST_Request $request, string $key)
    {
        if ($value < 1) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf('%s %d must be greater than 0.', $key, $value),
                array('status' => 400)
            );
        } else {
            return true;
        }
    }

    public function isAuthorized(\WP_REST_Request $request)
    {
        if (!(current_user_can('editor') || current_user_can('administrator'))) {
            $current_user = wp_get_current_user();
            return new \WP_Error(
                'rest_forbidden',
                sprintf('You do not have enough privileges to use this API.'),
                array('status' => 403, 'Username' => $current_user->user_login)
            );
        }

        return true;
    }

    protected function isNotNull($value, $request, $key){
		if ( $value != null ) {
			return true;
		} else {
			return new \WP_Error( 'rest_invalid_param',
				sprintf( '%s %d must not be null.', $key, $value ), array( 'status' => 400 ) );
		} 			
	}
}