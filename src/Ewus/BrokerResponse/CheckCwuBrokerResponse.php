<?php

/*
 * This file is part of the Ewus package.
 *
 * (c) Bartosz Pietrzak <b3k@b3k.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ewus\BrokerResponse;

/**
 * Object representing CheckCWU response
 * 
 * @author Bartosz Pietrzak <b3k@b3k.pl>
 */
class CheckCwuBrokerResponse {

    const NS2 = 'ns2';
    const NS2_URL = 'https://ewus.nfz.gov.pl/ws/broker/ewus/status_cwu/v3';
    const NS3 = 'ns3';
    const NS3_URL = 'http://xml.kamsoft.pl/ws/broker';
    
    const NS_EXECUTE_SERVICE_RETURN = '/soapenv:Envelope/soapenv:Body/ns3:executeServiceReturn/';
    const NS_STATUS_CWU_ODP = 'ns3:payload/ns3:textload/ns2:status_cwu_odp';
    
    private $date;
    private $system_nfz_name;
    private $system_nfz_version;
    private $provider_id;
    private $provider_id_ow;
    private $provider_id_operator;
    private $patient_expiry_date;
    private $patient_status_cwu;
    private $patient_pesel;
    private $patient_name;
    private $patient_surname;
    private $signature;
    private $signature_canonicalization_method;
    private $signature_method;
    private $signature_reference_transforms;
    private $signature_reference_digest_method;
    private $signature_reference_digest_value;

    /**
     * Creates CheckCwuBrokerResponse object from array
     * 
     * While parsing each element from array, they can manipulate some data
     * that are defined in $transform array as anonymous functions.
     * 
     * @param array $data
     */
    public function __construct(Array $data) {
        // put var names that will not be overwriten
        $reject = array();

        // put lambda-style function for given key if you want more transforms on given value
        $transform = array(
            'date' => function($in) {
                return is_string($in) ? \DateTime::createFromFormat('Y-m-d\TH:i:s\.uP', $in) : $in;
            },
            'patient_expiry_date' => function($in) {
                return is_string($in) ? \DateTime::createFromFormat('Y-m-d', $in) : $in;
            },
        );
        $vars = array_keys(get_object_vars($this));
        foreach ($data as $key => $value) {
            if (in_array($key, $vars) && !is_null($value) && !in_array($key, $reject)) {
                $this->{$key} = (isset($transform[$key]) && is_callable($transform[$key])) ? $transform[$key]($value) : $value;
            }
        }
    }

    /**
     * Converts CheckCWU XML response into CheckCwuBrokerResponse object
     * 
     * Looks just little ugly becouse PHP XML tools (SimpleXML) can't handle it
     * in easy way.
     * 
     * @param string $xml
     * @return CheckCwuBrokerResponse
     */
    public static function createFromXml($xmls) {
        $return = array();
        try {
            $xml = simplexml_load_string($xmls);
            $xml->registerXPathNamespace(self::NS3, self::NS3_URL);
            $xml->registerXPathNamespace(self::NS2, self::NS2_URL);

            $return['date'] = $xml->xpath(self::NS_EXECUTE_SERVICE_RETURN.'ns3:date');
            $return['date'] = (string) $return['date'][0];

            $system_nfz_name = $xml->xpath(self::NS_EXECUTE_SERVICE_RETURN.self::NS_STATUS_CWU_ODP.'/ns2:system_nfz');
            $system_nfz_name = $system_nfz_name[0]->attributes();
            $return['system_nfz_name'] = (string) $system_nfz_name['nazwa'];
            $return['system_nfz_version'] = (string) $system_nfz_name['wersja'];

            $nss = array(
                'provider_id' => '/ns2:swiad/ns2:id_swiad',
                'provider_id_ow' => '/ns2:swiad/ns2:id_ow',
                'provider_id_operator' => '/ns2:swiad/ns2:id_operatora',
                'patient_expiry_date' => '/ns2:pacjent/ns2:data_waznosci_potwierdzenia',
                'patient_status_cwu' => '/ns2:pacjent/ns2:status_ubezp',
                'patient_pesel' => '/ns2:numer_pesel',
                'patient_name' => '/ns2:pacjent/ns2:imie',
                'patient_surname' => '/ns2:pacjent/ns2:nazwisko'
            );
            
            foreach($nss as $key=>$ns){
                $return[$key] = $xml->xpath(self::NS_EXECUTE_SERVICE_RETURN.self::NS_STATUS_CWU_ODP.$ns);
                $return[$key] = (string) $return[$key][0];
            }

            // ns2:status_cwu_odp
            $cwuOdp = $xml->xpath(self::NS_EXECUTE_SERVICE_RETURN.self::NS_STATUS_CWU_ODP);
            $signedInfo = $cwuOdp[0]->Signature->SignedInfo;

            $return['signature'] = $cwuOdp[0]->Signature;
            $return['signature'] = (string) $return['signature'][0]->SignatureValue;
            $return['signature_canonicalization_method'] = (string) $signedInfo->CanonicalizationMethod->attributes()->Algorithm;
            $return['signature_method'] = $signedInfo->SignatureMethod->attributes()->Algorithm;

            foreach ($signedInfo->Reference->Transforms->Transform as $elem) {
                $return['signature_reference_transforms'][] = (string) $elem->attributes()->Algorithm;
            }
            
            $return['signature_reference_digest_method'] = (string) $signedInfo->Reference->DigestMethod->attributes()->Algorithm;
            $return['signature_reference_digest_value'] = (string) $signedInfo->Reference->DigestValue;
        } 
        catch (Exception $e) {
            throw new \InvalidArgumentException('Error while parsing XML string.');
        }
        
        return new self($return);
    }

    /**
     * @return DateTime
     */
    public function getDate() {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getSystemNfzName() {
        return $this->system_nfz_name;
    }

    /**
     * @return string
     */
    public function getSystemNfzVersion() {
        return $this->system_nfz_version;
    }

    /**
     * @return string
     */
    public function getProviderId() {
        return $this->provider_id;
    }

    /**
     * @return integer
     */
    public function getProviderIdOw() {
        return $this->provider_id_ow;
    }

    /**
     * @return integer
     */
    public function getProviderIdOperator() {
        return $this->provider_id_operator;
    }

    /**
     * @return DateTime
     */
    public function getPatientExpiryDate() {
        return $this->patient_expiry_date;
    }

    /**
     * @return integer
     */
    public function getPatientStatusCwu() {
        return $this->patient_status_cwu;
    }

    /**
     * @return integer
     */
    public function getPatientPesel() {
        return $this->patient_pesel;
    }

    /**
     * @return string
     */
    public function getPatientName() {
        return $this->patient_name;
    }

    /**
     * @return string
     */
    public function getPatientSurname() {
        return $this->patient_surname;
    }

    /**
     * @return string
     */
    public function getSignature() {
        return $this->signature;
    }

    /**
     * @return string
     */
    public function getSignatureCanonicalizationMethod() {
        return $this->signature_canonicalization_method;
    }

    /**
     * @return string
     */
    public function getSignatureMethod() {
        return $this->signature_method;
    }

    /**
     * @return string
     */
    public function getSignatureReferenceTransforms() {
        return $this->signature_reference_transforms;
    }

    /**
     * @return string
     */
    public function getSignatureReferenceDigestMethod() {
        return $this->signature_reference_digest_method;
    }

    /**
     * @return string
     */
    public function getSignatureReferenceDigestValue() {
        return $this->signature_reference_digest_value;
    }
}