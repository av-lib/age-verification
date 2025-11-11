<?php

// This file is the central user-visible destination for age verification.  It will show the user a prompt to age
// verify, or let them know they don't need to.  It will also be used to launch AV checks and receive callbacks.

DIE("Sample file.");

//ini_set('display_errors', 1);
//error_reporting(0);
//error_reporting(-1);

require_once("include/ageVerificationMySite.php"); // Your site-specific implementation
require_once("include/account.php"); // Your site-specific include files...

// Autoload should pull in the library:
require_once($DOC_ROOT . "/vendor/autoload.php");
// but if not:
//require_once($DOC_ROOT . "/vendor/av-lib/age-verification/ageVerificationAbstract.php");

session_start();

// AV:  Intercept provider requests and pass those over to the AV library to handle further
if (isset($_REQUEST["provider"]))
{
	$provider = AgeVerificationMySite::instance()->getProviderByString($_REQUEST["provider"]);
	
	if ($provider != null)
	{
		if (isset($_REQUEST["launch"]))
			$provider->launch();
		else if (isset($_REQUEST["callback"]))
			$provider->callback();
		else if (isset($_REQUEST["linkback"]))
			$provider->linkback();
	} else {
		DIE("Unknown/unsupported provider.");
	}
}

/*
// This can be used to test the age verification without using a provider.  Don't leave it in for production!
if (isset($_REQUEST["test-force"]))
{
	//	AgeVerificationMySite::instance()->setAgeVerified(1, "GOCAM");
	AgeVerificationMySite::instance()->setAgeVerifiedTokenCookie();
}
*/

http_response_code(451); // Unavailable for legal reasons

pageTop(); // Site specific template code

// Check if user has arrived on this page, but doesn't actually need to verify so we don't ask them to verify a second 
// time.
if (!AgeVerificationMySite::instance()->shouldAgeVerifyCurrentSession())
{
	?><h1>✅ Success</h1>
	<p>You have either verified your age, or our systems detect you are not in one of the age verification required 
		US states.  You may browse the site as desired.
	<p>If you are redirected to this page again, it may be due to your browser caching the old redirects.  In that 
		situation, it is recommended to clear page cache (but not cookies or session data!)
	<?php 
	pageBottom(); // Site specific template code
	DIE();
}

// It is useful to display the user's region to ensure they understand why they are being blocked (in case there
// is a geo detection problem, they could inform you, etc.)  By default, the library sets a session variable during the
// shouldAgeVerifyCurrentSession() call, but your override can change this behavior.
$blockedRegion = "REGION";
if (isset($_SESSION["blockedRegionNameDisplayable"])) // Set by shouldAgeVerifyCurrentSession()
	$blockedRegion = $_SESSION["blockedRegionNameDisplayable"];
if ($blockedRegion == "" || $blockedRegion == null)
	$blockedRegion = "REGION";

?>

<H1>🛑 Age Verification Required for <b><?php echo $blockedRegion; ?></b> Visitor 🛑</h1>

<p>Sorry, due to your location in <b><?php echo $blockedRegion; ?></b>, we believe we may be required by your government 
to verify you are 18 years or older.

<?php 
// Site-specific registered logic.  Basically, we are intending to show the user a prompt encouraging them to make an
// account first, but not requiring it.  If they're already registered, skip that part.
$registered = isset($_SESSION["username"]) && $_SESSION["username"] !== null;

if (!$registered)
{ 
	?>
	<h2>Step 1: Create Account (optional)</h2>
	<p>It is recommended you first <a href="/login.php">login or create an account</a> so you do not need to verify 
	every visit (however, having an account is not mandatory).
	<p><a href="/login.php" class="btn btn-primary btn-lg px-4">Login or Create Account</a>
	<h2>Step 2: Verify Age</h2>
<?php } ?>

<p><?php if ($registered) echo "To"; else echo "Then, to"; ?> verify your age, choose one of the verification options below:

<p><a href="?provider=RedactID&launch&cache=<?php echo time(); ?>" class="btn <?php if ($registered) echo "btn-primary"; else echo "btn-outline-secondary"; ?> btn-lg px-4 gap-3">Submit a redacted copy of ID (via Redact-ID.com)</a>
<p><a href="?provider=GoCam&launch&cache=<?php echo time(); ?>" class="btn <?php if ($registered) echo "btn-primary"; else echo "btn-outline-secondary"; ?> btn-lg px-4 gap-3">Submit Selfie (via GoCam)</a>

<hr>
<p>If you wish to write to your legislators to protest mandatory age verification legislation in your region, there is an easy way to do so <a href="https://www.defendonlineprivacy.com/action/" target="_blank">here</a>.
<hr>

<h2>FYI:  Preview of this website</h2>
<p>Sample description of your website, explaining what content it offers and providing a preview.

<p><a href="/images/siteSamples/full/sample1.png"><img class="sitePreviewImage" src="/images/siteSamples/downsize/sample1.webp"></a>
<p><a href="/images/siteSamples/full/sample2.png"><img class="sitePreviewImage" src="/images/siteSamples/downsize/sample2.webp"></a>
<p><a href="/images/siteSamples/full/sample3.png"><img class="sitePreviewImage" src="/images/siteSamples/downsize/sample3.webp"></a>

<p>Interested?  <b>Use verify links above to view the full website!</b> :)
<?php
pageBottom(); // Site-specific template code
?>