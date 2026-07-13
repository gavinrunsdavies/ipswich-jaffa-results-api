<?php

namespace IpswichJAFFARunningClubAPI\V2;

abstract class BaseCommand
{
    protected $dataAccess;

    public function __construct($dataAccess)
    {
        $this->dataAccess =  $dataAccess;
    }

    public function isAuthorized()
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

    protected function processDataResponse($response, $queryFunction)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        return rest_ensure_response($queryFunction($response));
    }

    protected function toInt($value): int
    {
        return (int) $value;
    }

    protected function toFloat($value): float
    {
        return (float) $value;
    }

    protected function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
