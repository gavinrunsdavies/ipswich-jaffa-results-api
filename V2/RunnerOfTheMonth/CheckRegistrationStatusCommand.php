<?php

namespace IpswichJAFFARunningClubAPI\V2\RunnerOfTheMonth;

require_once 'CheckRegistrationStatusResult.php';

class CheckRegistrationStatusCommand
{
	private $UkAthleticsWebAccessKey;
	private $UkAthleticsLicenceCheckUrl;

	public function __construct($UkAthleticsLicenceCheckUrl, $UkAthleticsWebAccessKey)
	{
		$this->UkAthleticsLicenceCheckUrl = $UkAthleticsLicenceCheckUrl;
		$this->UkAthleticsWebAccessKey = $UkAthleticsWebAccessKey;
	}

	public function checkRegistrationStatus($urn)
	{
		$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
		<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://tempuri.org/">
		  <SOAP-ENV:Body>
			<ns1:CheckRegistrationStatus_Urn>
			  <ns1:webUserKey>' . $this->UkAthleticsWebAccessKey . '</ns1:webUserKey>
			  <ns1:urn>' . $urn . '</ns1:urn>
			</ns1:CheckRegistrationStatus_Urn>
		  </SOAP-ENV:Body>
		</SOAP-ENV:Envelope>';

		$headers = array(
			"Content-type: text/xml;charset=\"utf-8\"",
			"Accept: text/xml",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"SOAPAction: http://tempuri.org/ILicenceCheck/CheckRegistrationStatus_Urn",
			"Content-length: " . strlen($soapRequest),
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_URL, $this->UkAthleticsLicenceCheckUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($ch);
		curl_close($ch);

		$xml = new \SimpleXMLElement($response);
		$xml->registerXPathNamespace('tmp', 'http://tempuri.org/');
		$xml->registerXPathNamespace('lc', 'http://schemas.datacontract.org/2004/07/LicenceCheckService');

		$status = new CheckRegistrationStatusResult();
		$urnResult = $xml->xpath("//tmp:CheckRegistrationStatus_UrnResult")[0];
		//$status->xml = $response;
		if ($urnResult == 'MatchFound') {
			$registered = $xml->xpath("//lc:Registered")[0];
			if ($registered == "true") {
				$firstClaimClub = $xml->xpath("//lc:FirstClaimClub")[0];
				$clubName = 'Ipswich JAFFA RC';
				if (stripos($firstClaimClub, $clubName) !== FALSE) {
					$status->success = true;
					$status->lastName = implode('', $xml->xpath("//lc:LastName")); // TODO understand why!
				} else {
					$status->errors[] = "Not registered first claim with $clubName";
				}
			} else {
				$status->errors[] = 'Athlete is no-longer registered with UK Athletics';
			}
		} else {
			$status->errors[] = 'No match found';
		}

		return $status;
	}
}
