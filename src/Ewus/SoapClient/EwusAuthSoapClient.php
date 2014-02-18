<?php

/*
 * This file is part of the Ewus package.
 *
 * (c) Bartosz Pietrzak <b3k@b3k.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ewus\SoapClient;

use Ewus\Exception\EwusBadCredentialsException;
use Ewus\Exception\EwusUnexpectedResponseException;

/**
 * EwusAuthSoapClient
 * @author Bartosz Pietrzak <b3k@b3k.pl>
 */
class EwusAuthSoapClient extends EwusSoapClient {

    const LOGIN_TYPE_LEK = 'LEK';
    const LOGIN_TYPE_SWD = 'SWD';
    const SOAP_HEADER_NAMESPACE = 'http://xml.kamsoft.pl/ws/common';

    /**
     * Regexp for getting auth response code
     */
    const AUTH_RESPONSE_REGEXP = '|^\[([0-9]{3})\] (.*)$|si';

    /**
     * 
     * @param type $params
     * @return type
     * @throws EwusBadCredentialsException
     * @throws EwusUnexpectedResponseException
     */
    public function authLogin(Array $params) {
        $header = $return = $response_msg = array();

        $response = parent::__soapCall('login', array(
                array(
                    'credentials' => $this->getCredentials($params),
                    'password' => $params['password'])
                ), null, null, $header);

        if (preg_match(self::AUTH_RESPONSE_REGEXP, $response, $response_msg)) {
            $return['response_code'] = $response_msg[1];
            $return['response_msg'] = $response_msg[2];
        } else {
            throw new EwusBadCredentialsException('Unexpected response from Auth Service');
        }

        if (isset($header['session']) && isset($header['session']->id)) {
            $return['session_id'] = $header['session']->id;
        }

        if (isset($header['authToken']) && isset($header['authToken']->id)) {
            $return['auth_token'] = $header['authToken']->id;
        }

        if (!isset($return['auth_token']) || !isset($return['session_id'])) {
            throw new EwusUnexpectedResponseException('No required authentication values');
        }

        return $return;
    }

    /**
     * Logout from SOAP
     * 
     * @param array $params
     * @return boolean
     */
    public function authLogout(Array $params) {
        $headers = array(
            new \SoapHeader(self::SOAP_HEADER_NAMESPACE, "authToken", array('id' => $params['auth_token']), false),
            new \SoapHeader(self::SOAP_HEADER_NAMESPACE, "session", array('id' => $params['session_id']), false),
        );
        try {
            $response = parent::__soapCall('logout', array(), null, $headers);
        } catch (\SoapFault $e) {
            return FALSE;
        }
        return strtolower($response) == 'wylogowany' ? TRUE : FALSE;
    }

    protected function getCredentials(array $params) {
        $r = array(
            array('name' => 'domain', 'value' => array('stringValue' => $params['domain'])),
            array('name' => 'login', 'value' => array('stringValue' => $params['username']))
        );

        if( ! is_null($params['provider_type']) && ! is_null($params['provider_code'])) 
        {
            $r[] = array('name' => 'type', 'value' => array('stringValue' => $params['provider_type']));

            $type = ($params['provider_type'] == self::LOGIN_TYPE_SWD) ? 'idntSwd' : 'idntLek';

            $r[] = array('name' => $type, 'value' => array('stringValue' => $params['provider_code']));
        }

        return $r;
    }

}
