<?php

namespace AVLib\AgeVerification;

/**
 * This class is intended to be updated with various locations that may need age verification.  However, it is ultimately
 * your responsibility to verify these are correct and up to date.
 */
class CommonBlockedRegions
{
	/**
	 * US State Blocks
	 */
	static function isRestrictiveUsState($countryISO, $subdivisionISO)
	{
		if ($countryISO == "US")
		{
			$blockedStates = [
				"AL",
				"AZ",
				"AR",
				"FL",
				"GA",
				"ID",
				"IN",
				"KS",
				"KY",
				"LA",
				"MS",
				"MO",
				"MT",
				"NE",
				"NC",
				"ND",
				"OH",
				"OK",
				"SC",
				"SD",
				"TN",
				"TX",
				"UT",
				"VA",
				"WY",
			];

			if (in_array($subdivisionISO, $blockedStates))
				return true;
		}
		
		return false;
	}
}