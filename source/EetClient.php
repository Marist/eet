<?php

namespace Fritak\eet;

use DOMDocument;
use Fritak\eet\Certificate;
use RobRichards\WsePhp\WSSESoap;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SoapClient;

/**
 * Soap client for data message.
 * 
 * @author Marek Sušický <marek.susicky@fritak.eu>
 * @version 1.0
 * @package eet
 */
class EetClient extends SoapClient
{
    const TIMEOUT_INI_KEY = 'default_socket_timeout';

    /**
     *
     * @var Certificate 
     */
    protected $certificate;
    
    /**
     * Timeout in seconds
     * @var int
     */
    protected $timeout;
    
    /**
     * Connection timeout in seconds
     * @var int
     */
    protected $connectionTimeout;
    
    /**
     * 
     * @param string $wsdl
     * @param Certificate $certificate
     * @param int $timeout Timeout time in seconds
     * @param int $connectionTimeout Connection timeout in seconds
     */
    public function __construct($wsdl, Certificate $certificate, $timeout = FALSE, $connectionTimeout = FALSE)
    {
        $this->certificate = $certificate;
        $this->timeout = $timeout;
        $this->connectionTimeout = $connectionTimeout;
        
        $opts = ['exceptions' => TRUE];
        if ($this->connectionTimeout !== FALSE){
            $opts['connection_timeout'] = $connectionTimeout;
        }
        parent::__construct($wsdl, $opts);
    }

    public function __doRequest($request, $location, $saction, $version, $one_way = 0)
    {
        $this->exception = FALSE;
        $XMLSecurityKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $DOMDocument = new DOMDocument('1.0');

        $DOMDocument->loadXML($request);
        $WSSESoap = new WSSESoap($DOMDocument);

        $XMLSecurityKey->loadKey($this->certificate->pkey);

        $WSSESoap->addTimestamp();
        $WSSESoap->signSoapDoc($XMLSecurityKey, ["algorithm" => XMLSecurityDSig::SHA256]);

        $binaryToken = $WSSESoap->addBinaryToken($this->certificate->cert);
        $WSSESoap->attachTokentoSig($binaryToken);

        $original = ini_get(self::TIMEOUT_INI_KEY);
        
        if ($this->timeout !== FALSE) { ini_set(self::TIMEOUT_INI_KEY, $this->timeout*1000); }
        $response = parent::__doRequest($WSSESoap->saveXML(), $location, $saction, $version, $one_way);
        ini_set(self::TIMEOUT_INI_KEY, $original);
        return $response;
    }
    
    public function setCertificate(Certificate $certificate){
        $this->certificate = $certificate;
    }
    
    public function getException(){
        return $this->exception;
    }

}

