<?php

namespace AVLib\AgeVerification;

require_once(__DIR__ . "/redactIdConfig.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php");

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Validation\Constraint\HasClaim;
use Lcobucci\JWT\Validation\Constraint\HasClaimWithValue;

class RedactId extends VerificationProviderAbstract
{
	protected function getConfig():RedactIdConfig|null
	{
		if ($this->config != null && $this->config instanceof RedactIdConfig)
			return $this->config;

		return null;
	}

	/**
	 * Send user to redact id for verification
	 */
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
		Header("Location: " . $this->getConfig()->redactIdURL);
		DIE();
	}

	/**
	 * User has returned from redact ID, verify success.
	 */
	public function linkback()
	{
		if (!isset($_POST["redactJwt"]) || $_POST["redactJwt"] == "")
		{
			echo ("Did not receive post request from redact ID.");
			echo ("<a href='/redactIdLauncher.php'>Retry</a>");
			DIE();
		}

		$parser = new Parser(new JoseEncoder());

		$token = $parser->parse($_POST["redactJwt"]);

		$publicKey = InMemory::base64Encoded($this->getConfig()->redactIdPublicKey);

		$validator = new Validator();

		//echo "ours is" . $redactIdConfig["linkbackURL"] . "\n";

		try {
			$validator->assert($token, new SignedWith(new Eddsa(), $publicKey));
			$validator->assert($token, new IssuedBy("https://redact-id.com"));

			// Subject is the site id, which is easier to validate than audience, as in horni's implementation differs due to
			// cache bust parameter.
			$validator->assert($token, new RelatedTo($this->getConfig()->siteId));
			//$validator->assert($token, new PermittedFor($redactIdConfig["linkbackURL"]));
			// Permitted for (aud) claim will be the 
			$validator->assert($token, new StrictValidAt(SystemClock::fromSystemTimezone()));

			$validator->assert($token, new HasClaimWithValue("18plus", true));

			$validator->assert($token, new HasClaim("reference"));

			//$validator->assert($token, new HasClaimWithValue("ip", $_SERVER['REMOTE_ADDR']));
			$validator->assert($token, new HasClaim("ip"));

		} catch (RequiredConstraintsViolated $e) {
			// list of constraints violation exceptions:

			$message = "Unknown error, session may have expired";
			//$message = $e->getMessage();
			//var_dump($e->violations());
			$violations = $e->violations();
			$message = $violations[0]->getMessage();
			//	$message = $e[0]->message;
			echo "Problem validating data from Redact ID -- $message<p><a href=\"" . $this->getConfig()->redactIdURL . "\">Return to RedactID</a>.";
			DIE();
		}

		// Basic checks are done

		// To deter token sharing (already unlikely), can check IP is correct or that token was not previously consumed.
		// Since it's not impossible that a user might migrate between IPs (perhaps some kind of CGNAT setup?), we'll check
		// for token consumption.

		$mem = $this->ageVerification->getMem();
		$memKeyCheck = $this->ageVerification->getMemcachePrefix() . "_REDACT_ID_TOKEN_USED_" . $token->claims()->get("jti"); // jti should be unique for each token, especially within one hour
		if ($mem->get($memKeyCheck) === true)
		{
			// Token already used
			echo "You may have already used this token, please <a href=\"" . $this->getConfig()->redactIdURL . "\">Return to RedactID</a>.";

			if ($this->ageVerification->isValidAgeVerificationCookiePresent())
			{
				echo "<p>Note - you are already age verified, you probably want to continue to the main website at this point.  <a href=\"/\">Continue</a>.";
			}

			DIE();
		}

		// Consume token
		$mem->set($memKeyCheck, true, NULL, 60 * 60 * 3); // Cache for three hours, well beyond the max token timeline.

		// Set age verification success
		$accountID = (int) $this->ageVerification->getAccountID();
		if ($accountID > 0)
		{
			$this->ageVerification->setAgeVerifiedAccount($accountID, "REDACT-ID", $token->claims()->get("reference"));
		}

		if (isset($_COOKIE["ageVerificationToken"]))
		{
			// Already has a cookie, upgrade it
			$this->ageVerification->upgradeAgeVerificationToken($_COOKIE["ageVerificationToken"]);
		} else {
			// No cookie yet
			$this->ageVerification->setAgeVerifiedTokenCookie(true);
		}

		header("Location: " . $this->ageVerification->getPathForAgeGate() . "?cache=" . time()); // Will hopefully show success message
		DIE();
	}
}