<?php 

namespace AVLib\AgeVerification;

class GoCamConfig extends VerificationProviderConfig
{
	public string $cipherKey; // default open source cipher key, change this
	public string $goCamBaseURL; // URL to instance of gocam server
	public string $callbackKey; // Extra security to prevent another site calling the target URL.  (Unnecessary unless cipher shared with others)

	public string $linkbackURL = ""; // Will default to HTTP Host + age gate page parameters if left as ""
	public string $callbackURLBase = ""; // Will default to HTTP Host + age gate page parameters if left as ""
}