<?php 

namespace AVLib\AgeVerification;

class GoCamConfig extends VerificationProviderConfig
{
	// URL to instance of gocam server.  If "", will use official server.
	public string $goCamBaseURL = ""; 

	// Change to your cipher key, defaults to open source cipher key
	public string $cipherKey; 

	// Can specify a random string for extra security to prevent a nefarious user from calling the target URL with
	// false data.  (Optional value, but recommended)
	public string $callbackKey = ""; 

	// Will default to HTTP Host + age gate page parameters if left as ""
	public string $linkbackURL = ""; 

	// Callback is where the gocam server will POST the user data to.  
	//
	// Open-source/selfhosted version:  Likely safe to leave as "", will default to HTTP Host + age gate page parameters
	//
	// Official version:  Currently ignored by the official server -- it just uses whatever is in your account settings.  
	// You will want to go into your account settings and use something like 
	// http://mysite.example.com/ageBlock.php?provider=GoCam&callback
	// Or if you enable callbackKey feature, add:
	// http://mysite.example.com/ageBlock.php?provider=GoCam&callback&k=YOUR-CALLBACK-KEY-HERE
	public string $callbackURLBase = ""; // Will default to HTTP Host + age gate page parameters if left as ""
	
	// Only specify if using the official server
	public int $partnerID = 0; 

	// Only specify if using the official server
	public string $hmacKey = ""; 

	// Supported verification options
	public array $verificationOptions = array('creditCard', 'selfie', 'scanId', 'email');
}