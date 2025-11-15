<?php 

// This sample demonstrates how you can create your own AV implementation for your own site and customize it as needed
// by overriding the base class functions.

DIE("Sample file.");

// (Ensure local config is included first before requiring this file, so DOC_ROOT can find util.)

use AVLib\AgeVerification\GoCamConfig;
use AVLib\AgeVerification\RedactIdConfig;
use AVLib\AgeVerification\VerificationProviderConfig;
use AVLib\AgeVerification\VerificationProviderEnum;

require_once($DOC_ROOT . "/include/account.php"); // Site-specific include file
require_once($DOC_ROOT . "/include/cookieAuth.php"); // Site-specific include file

// Autoload should pull in the library:
require_once($DOC_ROOT . "/vendor/autoload.php");
// But if not:
//require_once($DOC_ROOT . "/vendor/av-lib/age-verification/ageVerificationAbstract.php");

/**
 * Example implementation of Age Verification library
 */
class AgeVerificationMySite extends AVLib\AgeVerification\AgeVerificationAbstract
{
	function getAccountID()
	{
		\cookieAuth\loginViaCookie();  // Attempt login from existing cookie, if present

		return parent::getAccountID();
	}

	function getMemcachePrefix()
	{
		return "MYSITE_AV_";
	}

	function getGeoLiteDatabasePath()
	{
		return $GLOBALS["DOC_ROOT"] . "/../internal/geoip/GeoLite2-City.mmdb";
	}

	function getPdo()
	{
		static $database;

		if ($database == null)
		{
			try {
				$host = "example";
				$user = "example";
				$pass = "example"; // Or grab from credential store, etc...
				$dbname = "example";

				$database = new \PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
				
				$database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				
				return $database;
			} catch (\PDOException $e) {
				die("Database error."); //, $e->getMessage());
			}
		} else 
			return $database;

		// Or if you're already using PDO, you could just return the existing database object from your site's code...
	}

	static function instance()
	{
		static $_instance = new AgeVerificationMySite();
		return $_instance;
	}

	function getProviderConfig($provider):VerificationProviderConfig|null
	{
		if ($provider == VerificationProviderEnum::RedactID)
		{
			$redactConfig = redactIdParameters(); // Site provided function that pulls parameters from config

			$config = new RedactIdConfig();
			$config->redactIdURL = $redactConfig["redactIdURL"];
			$config->linkbackURL = $redactConfig["linkbackURL"];
			$config->siteId = $redactConfig["siteId"];

			return $config;
		}

		if ($provider == VerificationProviderEnum::GoCam)
		{
			$goCamConfig = goCamParameters(); // Site provided function that pulls parameters from config

			$config = new GoCamConfig(); // <-- More documentation on each of these parameters are in the class file

			$config->cipherKey = $goCamConfig["cipherKey"] ?? ""; // default open source cipher key, change this
			$config->goCamBaseURL = $goCamConfig["goCamBaseURL"] ?? ""; // URL to instance of gocam server
			$config->callbackKey = $goCamConfig["callbackKey"] ?? ""; // Extra security to prevent another site calling the target URL.

			$config->callbackURLBase = $goCamConfig["callbackURLBase"] ?? ""; // Only need to provide for self-hosted/open source version
			$config->linkbackURL = $goCamConfig["linkbackURL"] ?? "";

			$config->partnerID = $goCamConfig["partnerID"] ?? 0; // Only specify if using the official server
			$config->hmacKey = $goCamConfig["hmacKey"] ?? ""; // Only specify if using the official server

			$config->verificationOptions = array('creditCard', 'selfie', 'scanId', 'email');

			return $config;
		}

		return null;
	}
}


?>