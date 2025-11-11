<?php

namespace AVLib\AgeVerification;

require_once(__DIR__ . "/../ageVerificationAbstract.php");
require_once(__DIR__ . "/verificationProviderConfig.php");
require_once(__DIR__ . "/verificationProviderEnum.php");

abstract class VerificationProviderAbstract
{
	public function __construct(AgeVerificationAbstract $ageVerification, 
		VerificationProviderConfig $config)
	{
		$this->ageVerification = $ageVerification;
		$this->config = $config;
	}
	protected AgeVerificationAbstract $ageVerification;
	protected VerificationProviderConfig $config;

	abstract public function launch();

	public function callback() { }

	public function linkback() { }
}