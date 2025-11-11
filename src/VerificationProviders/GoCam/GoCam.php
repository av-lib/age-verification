<?php

namespace AVLib\AgeVerification;

require_once(__DIR__ . "/GoCamConfig.php");
require_once(__DIR__ . "/upstreamLib/AvsPhpSdkV1.php");

class GoCam extends VerificationProviderAbstract
{
	protected function getConfig():GoCamConfig|null
	{
		if ($this->config != null && $this->config instanceof GoCamConfig)
			return $this->config;

		return null;
	}

	public function launch()
	{
		// Setup initial variables
		$accountID = $this->ageVerification->getAccountID();

		$ageVerificationToken = "";
		if (isset($_COOKIE["ageVerificationToken"]))
			$ageVerificationToken = $_COOKIE["ageVerificationToken"];

		// Check current status
		$alreadyAgeVerifiedAccount = $accountID > 0 && $this->ageVerification->getAgeVerifiedAccount($accountID);
		$alreadyAgeVerifiedToken = $ageVerificationToken != "" && $this->ageVerification->checkAgeVerificationToken($ageVerificationToken) === true;

		// If already age verified, skip a second age verification
		if ($alreadyAgeVerifiedAccount || $alreadyAgeVerifiedToken)
		{
			// If have token, push that value to account (if needed)
			if ($accountID > 0 && !$alreadyAgeVerifiedAccount && $alreadyAgeVerifiedToken)
			{
				$this->ageVerification->setAgeVerifiedAccount($accountID, "COOKIE"); 
			}

			header("Location: " . $this->ageVerification->getPathForAgeGate() . "?cache=" . time());
			DIE();
		}

		// Not already age verified, so age verify.

		// Gocam will need a token to refer to, so generate one if one is not already set
		if ($ageVerificationToken == "")
		{
			$ageVerificationToken = $this->ageVerification->setAgeVerifiedTokenCookie(false);
		}

		$cipherKey = $this->getConfig()->cipherKey;

		// create a new verification library instance
		$avsInstance = new \AvsPhpSdkV1($cipherKey);

		// optional: provide the color config for your implementation
		$colorConfigBodyBackground      = '#ffffff';
		$colorConfigBodyForeground      = '#000000';
		$colorConfigButtonBackground    = '#9acd1f';
		$colorConfigButtonForeground    = '#ffffff';
		$colorConfigButtonForegroundCTA = '#ffffff';

		// provide required user agent
		$userAgent = $_SERVER['HTTP_USER_AGENT'];

		// provide required website hostname
		$websiteHostname = $_SERVER['SERVER_NAME'];

		// optional: should the detection process show the detected face or document age in the process or not, boolean
		$showDetectedAgeNumber = false;

		// provide the required link back, the user will be taken back to this page after the detection process is finisher with success
		$linkBack    = $this->getConfig()->linkbackURL;
		$callbackUrl = $this->getConfig()->callbackURLBase;

		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
		$defaultCommon = $protocol . $_SERVER["HTTP_HOST"] . 
				$this->ageVerification->getPathForAgeGate() . "?cache="  . time() . "&provider=GoCam";

		// Generated defaults if none
		if ($linkBack == "")
			$linkBack = $defaultCommon . "&linkback";
		if ($callbackUrl == "")
			$callbackUrl = $defaultCommon . "&callback&k=" . $this->getConfig()->callbackKey;
		else
		{
			// Had a non-default value, so use the value provided.  But append key if needed.
			if ($this->getConfig()->callbackKey != "")
				$callbackUrl .= "&k=" . $this->getConfig()->callbackKey;
		}

		//DIE("callback will be $callbackUrl");

		// provide required user ip
		$userIp = $_SERVER['REMOTE_ADDR'];

		// optional: provide the user country code
		$countryCode = '';
		// optional: provide the user state code
		$stateCode = '';

		// use all the provided data above to create a request object
		$avsInstance->fillRequest(
			array(
				'userData'            => array(
					'userId'      => $accountID,
					'userData'	  => $ageVerificationToken,
					// optional
					'colorConfig' => array(
						'body' => array(
							'background' => $colorConfigBodyBackground,
							'foreground' => $colorConfigBodyForeground,
							'button'     => array(
								'background'             => $colorConfigButtonBackground,
								'foreground'             => $colorConfigButtonForeground,
								'foregroundCallToAction' => $colorConfigButtonForegroundCTA,
							)
						),
					)
				),
				'httpParamList'       => array(
					'userAgent'       => $userAgent,
					'websiteHostname' => $websiteHostname,
					'paramList'       => array(
						// optional
						'showDetectedAgeNumber' => $showDetectedAgeNumber,
						'verificationTypeList'  => array('selfie', 'scanId'),
						'userAgent'             => $userAgent,
					)
				),
				'verificationVersion' => \AvsPhpSdkV1::VERIFICATION_VERSION_STANDARD_V1,
				'linkBack'            => $linkBack,
				'callbackUrl'         => $callbackUrl,
				'ipStr'               => $userIp,
				// optional
				'countryCode'         => $countryCode,
				'stateCode'           => $stateCode,
			)
		);

		// encrypt the request object and get the age verification page url
		$verificationUrl = $avsInstance->toUrl($this->getConfig()->goCamBaseURL);

		//echo "Verif url:" . $verificationUrl;
		Header("Location: " . $verificationUrl);
		DIE();
	}

	public function callback() 
	{ 
		// Extra security in case the cipher key is known to prevent random people from submitting data to callback
		// (This is only really a concern if multiple people sharing the default open source cipher key.)
		if ($this->getConfig()->callbackKey != "")
		{
			if ($_GET["k"] != $this->getConfig()->callbackKey)
				DIE("Invalid k parameter.");
		}

		if ($_POST["state"] != "success")
		{
			DIE("State was not success.");
		}

		if ($_POST["websiteHostname"] != $_SERVER['SERVER_NAME'])
		{
			DIE("Wrong hostname");
		}

		$userData = json_decode($_POST["userData"], true);

		$accountID = $userData["userId"];
		$ageToken = $userData["userData"];

		if ($accountID != "" && $accountID > 0)
		{
			$this->ageVerification->setAgeVerifiedAccount($accountID, "GOCAM");
		}

		if ($ageToken != "")
		{
			$this->ageVerification->upgradeAgeVerificationToken($ageToken);
		}

		DIE();
	}
}