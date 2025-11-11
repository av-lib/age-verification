<?php 

namespace AVLib\AgeVerification;

enum VerificationProviderEnum
{
	case RedactID;
	case GoCam;

	public static function fromString(string $providerName)
	{
		foreach (VerificationProviderEnum::cases() as $provider) {
			if ($provider->name === $providerName) {
				return $provider;
			}
		}
		return null; // Not found
	}
}
