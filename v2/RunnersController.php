<?php
namespace IpswichJAFFARunningClubAPI\V2;

require_once plugin_dir_path(__FILE__) . 'BaseController.php';
require_once plugin_dir_path(__FILE__) . 'IRoute.php';

class RunnersController extends BaseController implements IRoute
{

    public function __construct($namespace, $db)
    {
        parent::__construct($namespace, $db);
    }

    public function registerRoutes()
    {
        register_rest_route($this->namespace, '/runners', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array($this, 'getRunners'),
        ));

        register_rest_route($this->namespace, '/runners/(?P<runnerId>[\d]+)', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array($this, 'getRunner'),
            'args' => array(
                'runnerId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/runners', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'saveRunner'),
            'args' => array(
                'runner' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validateRunner'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/runners/(?P<runnerId>[\d]+)', array(
            'methods' => \WP_REST_Server::DELETABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'deleteRunner'),
            'args' => array(
                'runnerId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/runners/(?P<runnerId>[\d]+)', array(
            'methods' => \WP_REST_Server::EDITABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'updateRunner'),
            'args' => array(
                'runnerId' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidId'),
                ),
                'field' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'isValidRunnerUpdateField'),
                ),
                'value' => array(
                    'required' => true,
                ),
            ),
        ));

        register_rest_route($this->namespace, '/genders', array(
            'methods' => \WP_REST_Server::READABLE,
            'permission_callback' => array($this, 'isAuthorized'),
            'callback' => array($this, 'getGenders'),
        ));
    }

    public function getGenders(\WP_REST_Request $request)
    {

        $response = $this->dataAccess->getGenders();

        return rest_ensure_response($response);
    }

    public function getRunners(\WP_REST_Request $request)
    {
        $loggedIn = $this->isAuthorized($request);
        $response = $this->dataAccess->getRunners($loggedIn);

        return rest_ensure_response($response);
    }

    public function getRunner(\WP_REST_Request $request)
    {
        $response = $this->dataAccess->getRunner($request['runnerId']);
        $certificates = $this->dataAccess->getStandardCertificates($request['runnerId']);
        $distances = array(1, 2, 3, 4, 5, 7, 8);
        $rankings = $this->dataAccess->getRunnerRankings($request['runnerId'], $response->sexId, $distances);

        $response->certificates = $certificates;
        $response->rankings = $rankings;

        return rest_ensure_response($response);
    }

    public function saveRunner(\WP_REST_Request $request)
    {

        $response = $this->dataAccess->insertRunner($request['runner']);

        return rest_ensure_response($response);
    }

    public function deleteRunner(\WP_REST_Request $request)
    {
        // TODO deleteResults parameter.
        $response = $this->dataAccess->deleteRunner($request['runnerId'], false);

        return rest_ensure_response($response);
    }

    public function updateRunner(\WP_REST_Request $request)
    {

        $response = $this->dataAccess->updateRunner($request['runnerId'], $request['field'], $request['value']);

        return rest_ensure_response($response);
    }

    public function isValidRunnerUpdateField($value, $request, $key)
    {
        if ($value == 'name') {
            return true;
        } else {
            return new \WP_Error('rest_invalid_param',
                sprintf('%s %d must be name only.', $key, $value), array('status' => 400));
        }
    }

    public function validateRunner($runner, $request, $key)
    {
        if (empty($runner['name'])) {
            return new \WP_Error('rest_invalid_param',
                sprintf('%s %s has invalid name value.', $key, json_encode($runner)), array('status' => 400));
        }

        if (intval($runner['sexId']) < 0) {
            return new \WP_Error('rest_invalid_param',
                sprintf('%s %s has invalid sexId value', $key, json_encode($runner)), array('status' => 400));
        }

        $date = date_parse($runner['dateOfBirth']);
        if (checkdate($date['month'], $date['day'], $date['year']) === false) {
            return new \WP_Error('rest_invalid_param',
                sprintf('%s %s has invalid dateOfBirth value', $key, json_encode($runner)), array('status' => 400));
        } else {
            return true;
        }
    }
}
