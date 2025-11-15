<?php

namespace AVLib\AgeVerification;

require_once(__DIR__ . "/CommonBlockedRegions.php");

require_once(__DIR__ . "/VerificationProviders/VerificationProviderEnum.php");
require_once(__DIR__ . "/VerificationProviders/VerificationProviderAbstract.php");

require_once(__DIR__ . "/VerificationProviders/RedactId/RedactId.php");
require_once(__DIR__ . "/VerificationProviders/GoCam/GoCam.php");

use GeoIp2\Database\Reader;

/**
 * Utilities for server side age verification.  This class holds site-specific AV logic, as well as some logic that
 * is not intended to be site-specific, but you can still override if needed.
 * It is expected that you extend this class with a site-specific implementation in your own code for at least the
 * essential functions at the top.
 */
abstract class AgeVerificationAbstract
{
	// ------------------------------------------------------------------------------------------------------
	// Functions at the top are the most likely you will need to override for your purposes.
	// ------------------------------------------------------------------------------------------------------

	/**
	 * Memcache lookups to reduce database access.  If disabled, memcache will still be written to, but will not be
	 * read from.  Turning this off is primarily intended for ease of debugging.
	 */
	function isMemcacheReadEnabled()
	{
		return true;
	}

	function getPathForAgeGate()
	{
		return "/ageBlock.php";
	}

	function getUserIP()
	{
		return $_SERVER["REMOTE_ADDR"];

		// Useful test IP's:
		// $ip = "131.247.253.85"; 	// University of Florida (blocked due to FL)
		// $ip = "8.8.8.8"; 		// USA, but no state (not blocked, no state)
	}

	/**
	 * Should check if user is logged in, and if so, return their numeric ID.
	 * If user is not logged in (guest user), return 0.	 
	 * This will only be called before output is displayed, so it is okay to trigger a session_start if needed here.
	 */
	function getAccountID()
	{
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		$accountID = 0;
		if (isset($_SESSION['accountID']))
			$accountID = $_SESSION['accountID'];
		return $accountID;
	}

	/**
	 * You can enable this to require all human/non-bot traffic undergo age verification, regardless of region.
	 */
	function isGloballyRestricted()
	{
		return false;
	}

	/**
	 * This will be prefixed before any memcache keys.  It is good to have a short prefix so avoid collisions with
	 * other services.
	 */
	function getMemcachePrefix()
	{
		return "AV_";
	}

	/**
	 * Customize which countries/regions are blocked.
	 * True will require AV for that region, false will not.
	 * If isGloballyRestricted is enabled, this function will not be called.
	 */
	function isRegionRestricted($countryISO, $subdivisionISO)
	{
		// A convenience function is provided for US states, but you should verify its block list is correct for your 
		// purposes and up to date.
		return CommonBlockedRegions::isRestrictiveUsState($countryISO, $subdivisionISO);
	}

	function getGeoLiteDatabasePath()
	{
		// Recommended to provide a full, absolute path if you can.
		return "GeoLite2-City.mmdb";
	}

	/**
	 * For the providers you support, return their configuration parameters
	 */
	function getProviderConfig(VerificationProviderEnum $provider):VerificationProviderConfig|null
	{
		return null;
	}

	/**
	 * Return your MySQL database using PDO driver with exceptions turned on.
	 * If you want different databases for the two tables, ignore this and see further below.
	 */
	function getPdo()
	{
		DIE("No database defined");
	}

	function getMem()
	{
		static $memcache = null;
		if ($memcache == null)
		{
			$memcache = new \Memcache;
			$memcache->connect('localhost', 11211) or die ("Could not connect");
		}

		return $memcache;
	}

	function getSqlStatementForAccountSelect($accountID)
	{
		// Customize account ID field/account table name as needed
		$statement = $this->getPdoForAccountTable()->prepare("SELECT ageVerified FROM accounts where accountID = :accountID");
		$statement->bindParam(":accountID", $accountID);
		return $statement;
	}

	function getSqlStatementForAccountUpdate($accountID)
	{
		// Customize account ID field/account table name as needed

		$statement = $this->getPdoForAccountTable()->prepare(
			"UPDATE accounts " .
			"SET ageVerified = :method, verificationReference = :verificationReference " .
			"WHERE accountID = :accountID");

		$statement->bindParam(":accountID", $accountID, \PDO::PARAM_INT);

		return $statement;
	}

	// ------------------------------------------------------------------------------------------------------
	// Below functions are less likely that you will need to override, but it is still worth reading through.
	// ------------------------------------------------------------------------------------------------------

	/**
	 * After the user's region is looked up, this function will be called to store the result.  It could then be 
	 * displayed to the user.  In this implementation, we should stash it in a session variable, but you could do
	 * something different.  If the user is not to be blocked, it will be set to ""
	 */
	function storeRegionNameForUserDisplay($regionName)
	{
		$_SESSION["blockedRegionNameDisplayable"] = $regionName;
	}

	/**
	 * It's theoretically possible you might want the account table and age token table to be in different databases.
	 * If so, these are the functions to override.
	 */
	function getPdoForAccountTable()
	{
		return $this->getPDO();
	}

	function getPdoForAgeTokenTable()
	{
		return $this->getPDO();
	}

	// ------------------------------------------------------------------------------------------------------
	// Below functions are unlikely that you will need to override.
	// ------------------------------------------------------------------------------------------------------

	/**
	 * Convenience utility function for pages to call to do age verification redirection, if needed.
	 * This library isn't generally aware of per-page logic.  That's left to individual pages to call or not call this
	 * function, as appropriate.
	 */
	function redirectToAgeVerificationIfShould()
	{
		if ($this->shouldAgeVerifyCurrentSession())
		{
			// Force verification		
			header("Location: " . $this->getPathForAgeGate());
			DIE();
		}
	}

	/**
	 * Convenience utility for the current page to get all needed information and check if AV block is needed.
	 */
	function shouldAgeVerifyCurrentSession()
	{
		$ip = $this->getUserIP();

		// Put the current user's account ID in this $accountID variable, if they are logged in
		$accountID = $this->getAccountID();

		// Get the age verification token for guest users, if one is set
		$ageVerificationToken = "";
		if (isset($_COOKIE["ageVerificationToken"]))
			$ageVerificationToken = $_COOKIE["ageVerificationToken"];

		return $this->shouldAgeVerify($ip, $ageVerificationToken, $accountID);
	}

	/**
	 * If the age verification cookie is set, and validates correctly
	 */
	function isValidAgeVerificationCookiePresent()
	{
		$ageVerificationToken = "";
		if (isset($_COOKIE["ageVerificationToken"]))
		{
			$ageVerificationToken = $_COOKIE["ageVerificationToken"];
			if ($this->checkAgeVerificationToken($ageVerificationToken) === true)
				return true;
		}

		return false;
	}

	/**
	 * Checks if a given page & visitor should be age verified
	 * (This function assumes nothing other than what is passed in)
	 */
	function shouldAgeVerify($ip, $ageVerificationToken="", $accountID=0)
	{
		if (!$this->ipInRestrictedTerritory($ip))
			return false;

		if ($accountID > 0 && $this->getAgeVerifiedAccount($accountID))
			return false; // already verified

		if ($ageVerificationToken != "" && $this->checkAgeVerificationToken($ageVerificationToken) === true)
			return false; // already verified

		// Exempt search engine crawlers/bots
		if ($this->isBotAgent())
			return false;

		return true;
	}

	function isBotAgent()
	{
		$lowercaseAgent = strtolower($_SERVER['HTTP_USER_AGENT']); // performance boost by not changing case every check
		if (strpos($lowercaseAgent, "bot") !== false ||
			strpos($lowercaseAgent, "spider") !== false ||
			strpos($lowercaseAgent, "facebook") !== false ||
			strpos($lowercaseAgent, "gpt") !== false ||
			strpos($lowercaseAgent, "anthropic") !== false ||
			strpos($lowercaseAgent, "crawler") !== false ||
			strpos($lowercaseAgent, "curl") !== false)
		{
			return true;
		}

		return false;
	}

	/**
	 * This function is provided with the intent that you can override it with your own IP when you need to check AV
	 * in production.
	 * true = will always be considered in restricted territory, age checking will be performed.
	 * false = IP will never be considered in restricted territory
	 * null = no special treatment (function should return null for most IPs)
	 */
	function alwaysConsiderIPInRestrictedTerritory($ip)
	{
		return null;
	}

	function ipInRestrictedTerritory($ip)
	{
		if ($this->isGloballyRestricted())
			return true;

		$overrideBehavior = $this->alwaysConsiderIPInRestrictedTerritory($ip);
		if ($overrideBehavior === true)
			return true;

		if ($overrideBehavior === false)
			return false;

		// Check cache first (performance boost versus hitting the DB file)
		$mem = $this->getMem();
		$memKey = $this->getMemcachePrefix() . "IP_" . $ip;
		$secondsToCache = 300; // 5 Min

		if ($this->isMemcacheReadEnabled())
		{
			$cache = $mem->get($memKey);
			if ($cache != false) // 'false' meaning not cached, not actually false...
			{
				if ($cache === -1)
					return false;
				else if ($cache === true)
					return true;
			}
		}

		// Top private IP ranges (possibly wireguard, internal network, etc)
		if (str_starts_with($ip, "127.") ||
			str_starts_with($ip, "192.168.") ||
			str_starts_with($ip, "10."))
		{
			$mem->set($memKey, -1, NULL, $secondsToCache); // false
			return false;
		}

		$dbFile = $this->getGeoLiteDatabasePath();
		
		if (!file_exists($dbFile))
		{
			//echo "\nmissing file!";
			return false; // temporary (hopefully) error in getting region
		}

		$blocked = false;

		try
		{
			$cityDbReader = new Reader($dbFile);
			$record = $cityDbReader->city($ip);

			$country = $record->country->isoCode;
			$state = $record->mostSpecificSubdivision->isoCode;

			$this->storeRegionNameForUserDisplay($record->mostSpecificSubdivision->name);

			//echo "country is $country and state is $state";

			if ($this->isRegionRestricted($country, $state))
				$blocked = true;

		} catch (\Exception $e)
		{
			// Temporary (hopefully) error in getting region
			// (This would probably benefit from some operator alerting.)
			return false; 
		}

		if (!$blocked)
			$this->storeRegionNameForUserDisplay("");

		// Cache result

		if ($blocked)
			$mem->set($memKey, true, NULL, $secondsToCache);
		else
			$mem->set($memKey, -1, NULL, $secondsToCache); // getting "false" out of memcache php is apparently a PITA, so...

		return $blocked;
	}

	function setAgeVerifiedAccount($accountID, $method, $verificationReference="")
	{
		try
		{
			$statement = $this->getSqlStatementForAccountUpdate($accountID);
			
			$statement->bindParam(":method", $method, \PDO::PARAM_STR);

			if ($method == "REDACT-ID" && isset($verificationReference) && $verificationReference != "")
			{
				$statement->bindParam(":verificationReference", $verificationReference, \PDO::PARAM_STR);
			} else {
				$statement->bindValue(":verificationReference", NULL);
			}

			$sqlOkay = $statement->execute();

			// also update memcache
			$mem = $this->getMem();
			$memKey = $this->getMemcachePrefix() . "ACCT_" . $accountID;
			$secondsToCache = 60 * 60 * 3; // 3 hours
			$mem->set($memKey, true, NULL, $secondsToCache);

			return $sqlOkay;
		} catch (\PDOException $e)
		{
			safeEx($e);
			return false;
		}
	}

	function getAgeVerifiedAccount($accountID)
	{
		$accountID = (int) $accountID;

		// Check memcache to reduce DB hits
		$mem = $this->getMem();
		$memKey = $this->getMemcachePrefix() . "ACCT_" . $accountID;
		if ($this->isMemcacheReadEnabled())
		{
			$cache = $mem->get($memKey);
			if ($cache != false) // 'false' meaning not cached, not actually false...
			{
				if ($cache === -1)
				{
					return false;
				}
				else if ($cache === true)
				{
					return true;
				}
			}
		}

		try
		{
			$statement = $this->getSqlStatementForAccountSelect($accountID);
			$statement->execute();
			
			if ($statement->rowCount() == 0)
				return null;
				
			$row = $statement->fetch(\PDO::FETCH_ASSOC);

			$secondsToCache = 60 * 60 * 3; // 3 hours
			if ($row["ageVerified"] != "")
			{
				// Cache result also
				$mem->set($memKey, true, NULL, $secondsToCache);

				return true;
			} else {
				// Should still cache result to make it easier
				$mem->set($memKey, -1, NULL, $secondsToCache); // No good way of getting 'false' out of php memcache without it confusing it for null...
			}
				
			return false;			
		} catch (\PDOException $e)
		{
			safeEx($e);
			return false;
		}
	}

	/**
	 * Age verification tokens can be used for long-lasting verification stored in cookie, without needing an account.
	 * Can return true for verified, false for unverified, or null for unknown
	 */
	function checkAgeVerificationToken($token)
	{
		// Token cannot be more than 32 chars, anything longer than that is incorrect
		if (strlen($token) > 32)
			return false;

		// Check memcache to reduce DB hits
		$mem = $this->getMem();
		$memKey = $this->getMemcachePrefix() . "TOKEN_" . urlencode($token); // out of an abundance of caution, urlencoding.  Not really needed though
		if ($this->isMemcacheReadEnabled())
		{
			$cache = $mem->get($memKey);
			if ($cache != false) // 'false' meaning not cached, not actually false...
			{
				if ($cache === -1)
					return false;
				else if ($cache === -2)
					return null;
				else if ($cache === 1)
					return true;
			}
		}

		try
		{
			$statement = $this->getPdoForAgeTokenTable()->prepare("SELECT tokenID, verified FROM ageTokens where token = :token");
			$statement->bindParam(":token", $token, \PDO::PARAM_STR);
			$statement->execute();
			
			$validToken = $statement->rowCount() > 0;

			// Cache result so future checks do not require db hits
			$secondsToCache = 60 * 60 * 3; // 3 hours for valid token
			if (!$validToken)
				$secondsToCache = 60 * 3; // 3 minutes for invalid token, could be someone just spamming us with tokens

			$row = $statement->fetch();

			if ($validToken && $row["verified"] == 1)
			{
				$mem->set($memKey, 1, NULL, $secondsToCache);
				return true; // verified
			}
			else if ($validToken && $row["verified"] == 0)
			{
				$mem->set($memKey, -1, NULL, $secondsToCache);
				return false; // not yet verified
			}
			else
			{
				$mem->set($memKey, -2, NULL, $secondsToCache); // no such key
				return null;
			}

			return $validToken;
		} catch (\PDOException $e)
		{
			safeEx($e);
			return false;
		}
	}

	/**
	 * Generate new age token
	 */
	function createNewAgeToken($verified)
	{
		// Generate a new, random token
		$token = "";
		while ($token == "")
		{
			$token = bin2hex(openssl_random_pseudo_bytes(16)); // 32 characters
			if (strlen($token) != 32)
				return ""; // wrong token length generated

			if ($this->checkAgeVerificationToken($token) !== null)
			{
				// Already exists, try a different one
				$token = "";
			}
		}
		
		try
		{
			$sth = $this->getPdoForAgeTokenTable()->prepare("INSERT INTO ageTokens SET " .
				"token = :token, " .
				"issued = NOW(),
				verified = :verified");
			
			$sth->bindParam(":token", $token, \PDO::PARAM_STR);
			$sth->bindParam(":verified", $verified, \PDO::PARAM_INT);
			
			if ($sth->execute())
			{
				// Update memcache in case token was previously checked and was stored as false somehow
				$memKey = $this->getMemcachePrefix() . "TOKEN_" . $token;
				$secondsToCache = 60 * 60 * 3; // 3 hours for valid token
				$this->getMem()->set($memKey, $token, NULL, $secondsToCache);

				return $token;
			} else {
				if (!isProduction())
				{
					echo "Error: ";
					print_r($sth->errorInfo());
				}
					
				return "";
			}
		} catch (\PDOException $e)
		{
			safeEx($e);
			return false;
		}
	}

	/**
	 * Converts an unverified age token to a verified age token
	 */
	function upgradeAgeVerificationToken($token)
	{
		// Token cannot be more than 32 chars, anything longer than that is incorrect
		if (strlen($token) > 32)
			return false;

		$currentStatus = $this->checkAgeVerificationToken($token);
		if ($currentStatus === null || $currentStatus === true)
			return; // nothing to do, either because token doesn't exist, or is already verified

		// Check memcache to reduce DB hits
		$mem = $this->getMem();
		$memKey = $this->getMemcachePrefix() . "TOKEN_" . urlencode($token); // out of an abundance of caution, urlencoding.  Not really needed though

		try
		{
			$statement = $this->getPdoForAgeTokenTable()->prepare("UPDATE ageTokens set verified = true where token = :token");
			$statement->bindParam(":token", $token, \PDO::PARAM_STR);
			$statement->execute();
			
			// Cache result so future checks do not require db hits
			$secondsToCache = 60 * 60 * 3; // 3 hours for valid token

			$row = $statement->fetch();
			$mem->set($memKey, 1, NULL, $secondsToCache);
			return true; // OK
		} catch (\PDOException $e)
		{
			safeEx($e);
			return false;
		}
	}

	/**
	 * Creates an age verified token and sets cookie with it.  Must be called prior to any output!
	 */
	function setAgeVerifiedTokenCookie($verified)
	{
		// Check any existing cookie, if set
		if (isset($_COOKIE["ageVerificationToken"]))
		{
			$ageVerificationToken = $_COOKIE["ageVerificationToken"];
			if ($this->checkAgeVerificationToken($ageVerificationToken) === true)
				return; // Already set, nothing to do
		}

		$token = $this->createNewAgeToken($verified);
		$cookieExpires = time() + 60 * 60 * 24 * 31 * 12 * 25; // 25 years in the future (man, I hope 2050 has better laws about AV...)
		
		setcookie("ageVerificationToken", $token, $cookieExpires); 

		return $token;
	}

	function clearAgeVerifiedTokenCookie()
	{
		setcookie("ageVerificationToken", "", time() - 3600);
	}

	/**
	 * Creates a particular provider.
	 */
	public function providerFactory(VerificationProviderEnum $providerEnum)
	{
		$config = $this->getProviderConfig($providerEnum);

		if ($providerEnum == VerificationProviderEnum::RedactID && $config != null)
			return new RedactId($this, $config);

		if ($providerEnum == VerificationProviderEnum::GoCam && $config != null)
			return new GoCam($this, $config);

		return null;
	}

	/**
	 * Gets a particular provider.
	 */
	public function getProviderByString(string $providerName)
	{
		$provider = VerificationProviderEnum::fromString($providerName);
		if ($provider == null)
			return null;
		return $this->providerFactory($provider);
	}
}