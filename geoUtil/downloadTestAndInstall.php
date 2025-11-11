<?php

// This CLI script is intended to run daily (or every couple days).  It will download the latest geolite db from the 
// community github mirror, then test it.  If all looks good, it will replace the current DB with it.

// You may wish to put it in a path outside your document root, though it is not a problem to have it inside.

if (php_sapi_name() === 'cli')
{ } else {
	DIE("Run via CLI only.");
}

require_once '../../www/vendor/autoload.php';
use GeoIp2\Database\Reader;

// For simplicity, we are using one of the github mirrors to download the GeoLite database.  You might instead choose
// to sign up with MaxMind and get your own API key.  They also sell a premium product with enhanced accuracy as well.
// https://www.maxmind.com/en/geoip-databases  You would want the "City" database product in order to determine
// what state a user is in.  However, I don't have a spare $1,500/year lying around, so I will use the free version ;)

$url = "https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-City.mmdb";
$stagingFile = "GeoLite2-City-staging.mmdb";
$finalFile = "GeoLite2-City.mmdb";
$metadataFile = "GeoLite2-City.scm"; // "SCM" => Skye's CRC Metadata ;)

function reportResult($success)
{
	$pingUrl = "https://example.com/..."; // <== Replace this with your HealthChecks.io URL for alerting (you can sign up or self host it)
	
	if (!$success)
		$pingUrl .= "/fail";
	
	file_get_contents($pingUrl);
}

// Step 0: Clear any previous staging file
if (file_exists($stagingFile))
	unlink($stagingFile);

echo "Downloading...\n";

// Step 1: File download:
// set_time_limit(0); // CLI, should not need to set this
$fp = fopen (dirname(__FILE__) . '/' . $stagingFile, 'w+');
//Here is the file we are downloading, replace spaces with %20
$ch = curl_init(str_replace(" ","%20", $url));
// make sure to set timeout to a high enough value
// if this is too low the download will be interrupted
curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 1hr
// write curl response to file
curl_setopt($ch, CURLOPT_FILE, $fp); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// get curl response
curl_exec($ch); 
curl_close($ch);
fclose($fp);

// Step 2: Basic checks
$bytes = filesize($stagingFile);
if ($bytes < 30 * 1024 * 1024) // Usually file is ~60MB, if it has shrunk so much then something odd has happened
{
	echo "Unexpected filesize $bytes -- quitting without installing new file.\n";
	reportResult(false);
	DIE();
}

echo "Download OK.\n";

// Step 3: Advanced checks -- make sure readable using the library
$sampleIP = "128.101.101.101"; // This one is used by maxmind for testing, owned by university of Minnesota
$cityDbReader = new Reader($stagingFile);
$record = $cityDbReader->city($sampleIP);
$state = $record->mostSpecificSubdivision->isoCode;
if ($state == "MN")
{
	echo "DB Appears OK\n";
} else {
	echo "Unexpected DB result, state was: $state.  Not installing.";
	reportResult(false);
	DIE();
}

// Step 4: Staleness check / tracking
$crc = hash_file('crc32c', $stagingFile);
if (file_exists($metadataFile))
{
	$metadata = json_decode(file_get_contents($metadataFile), true);
	if ($metadata["crc"] == $crc)
	{
		// File unchanged, possibly been a while
		$duration = time() - $metadata["time"];
		unlink($stagingFile);
		if ($duration > 60 * 60 * 24 * 10) // been ten days with no changes, that's odd
		{
			echo "File has not been changed in a while, possible problem with upstream github?\n";
			reportResult(false);
			DIE();
		} else {
			echo "File unchanged, no further action needed.\n";
			reportResult(true);
			DIE();
		}
	}
}

// (At this point, either no prior metadata, or CRC has changed.  DB should be installed.)

// Step 5: Install
// rename() on linux should be atomic
// (existing processes using the old database should not be interrupted)
if (rename($stagingFile, $finalFile))
{
	echo "Moved OK\n";
}
else 
{
	echo "Rename failed.";
	reportResult(false);
	DIE();
}

// Step 6: Metadata
// (Occurs after move so as to avoid a move failure not having wrong metadata,
// which would wrongly delay future attempts until next update).

$metadata = [
	"crc" => $crc,
	"time" => time()
];
file_put_contents($metadataFile, json_encode($metadata));

echo "ALL OK\n";

reportResult(true);

