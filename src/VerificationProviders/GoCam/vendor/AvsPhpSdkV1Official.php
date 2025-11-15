<?php


class AvsPhpSdkV1 {

	const AVS_URL = 'https://go.cam';

	const VERIFICATION_VERSION_STANDARD_V1 = 1;
	const VERIFICATION_VERSION_IFRAME_V1   = 2;

	const LANGUAGE_EN = 'en';
	const LANGUAGE_FR = 'fr';
	const LANGUAGE_DE = 'de';
	const LANGUAGE_NL = 'nl';
	const LANGUAGE_IT = 'it';
	const LANGUAGE_PT = 'pt';
	const LANGUAGE_ES = 'es';

	const KEY_ACCOUNT_ID           = 1;
	const KEY_ACCOUNT_DATA         = 2;
	const KEY_USER_IP_STR          = 3;
	const KEY_USER_IP_ID           = 4;
	const KEY_USER_IP_COUNTRY      = 5;
	const KEY_HTTP_USER_AGENT      = 6;
	const KEY_HTTP_LINK_BACK       = 7;
	const KEY_WEBSITE_HOSTNAME     = 8;
	const KEY_HTTP_PARAM_LIST      = 9;
	const KEY_VERIFICATION_VERSION = 10;
	const KEY_VERIFICATION_RESULT  = 11;
	const KEY_USER_IP_STATE        = 12;

	const HMAC_ALGO = 'sha256';

	// hmac sha256: 32 bytes
	const HMAC_SIZE = 32;

	const CIPHER_ALGO = 'aes-256-cbc';

	protected $partnerId;

	public $userId;
	public $userData;
	public $userIpStr;
	public $userIpId;
	public $userIpCountry;
	public $userIpState;
	public $httpUserAgent;
	public $linkBack;
	public $verificationVersion;
	public $websiteHostname;
	public $httpParamList;
	public $verificationResult;
	public $creationTimestamp;
	public $requestTime;

	protected $cipherKeyBin;
	protected $hmacKeyBin;

	private $language;

	public function __construct($partnerId, $cipherKeyHex, $hmacKeyHex) {
		$this->partnerId    = $partnerId;
		$this->cipherKeyBin = $this->hexToBin($cipherKeyHex);
		$this->hmacKeyBin   = $this->hexToBin($hmacKeyHex);

		$this->language = self::LANGUAGE_EN;

	}

	public function getUid() {
		return $this->partnerId . '-' . md5($this->userData);
	}

	public function partnerIdGet() {
		return $this->partnerId;
	}

	public function setLanguage($languageCode) {

		if (
			in_array(
				$languageCode,
				[
					self::LANGUAGE_EN,
					self::LANGUAGE_FR,
					self::LANGUAGE_DE,
					self::LANGUAGE_NL,
					self::LANGUAGE_IT,
					self::LANGUAGE_PT,
					self::LANGUAGE_ES,
				]
			)
		) {
			$this->language = $languageCode;
		}

	}

	public function getLanguage() {

		return $this->language;

	}

	public function fillRequest($requestList) {

		$paramList = [];
		if (isset($requestList['http']['paramList'])) {
			$paramList = $requestList['http']['paramList'];
		}

		$ipStr       = isset($requestList['ipStr']) ? $requestList['ipStr'] : '';
		$countryCode = isset($requestList['countryCode']) ? $requestList['countryCode'] : '';
		$stateCode   = isset($requestList['stateCode']) ? $requestList['stateCode'] : '';

		$verificationVersion = (isset($requestList['verificationVersion']) ? $requestList['verificationVersion'] : self::VERIFICATION_VERSION_STANDARD_V1);

		$this->userId              = $requestList['userData']['userId'];
		$this->userData            = json_encode($requestList['userData']);
		$this->httpUserAgent       = $requestList['http']['userAgent'];
		$this->websiteHostname     = $requestList['http']['websiteHostname'];
		$this->httpParamList       = $paramList;
		$this->linkBack            = $requestList['linkBack'];
		$this->verificationVersion = $verificationVersion;
		$this->userIpCountry       = $countryCode;
		$this->userIpState         = $stateCode;
		$this->userIpStr           = $ipStr;
		$this->userIpId            = 0;
		$this->creationTimestamp   = time();

	}

	public function toArray() {

		$dataList = [
			'requestTime' => time()
		];

		if (!empty($this->userId)) {
			$dataList[self::KEY_ACCOUNT_ID] = $this->userId;
		}

		if (empty($this->userData)) {
			throw new Exception('userData payload is mandatory');
		}

		$dataList[self::KEY_ACCOUNT_DATA] = $this->userData;

		$nbIpField = 0;
		if (!empty($this->userIpStr)) {
			$dataList[self::KEY_USER_IP_STR] = $this->userIpStr;
			$nbIpField++;
		}

		if (!empty($this->userIpId)) {
			$dataList[self::KEY_USER_IP_ID] = $this->userIpId;
			$nbIpField++;
		}

		if (empty($nbIpField)) {
			throw new Exception('Ip information missing');
		}

		if (!empty($this->userIpCountry)) {
			$dataList[self::KEY_USER_IP_COUNTRY] = $this->userIpCountry;
		}

		if (!empty($this->userIpState)) {
			$dataList[self::KEY_USER_IP_STATE] = $this->userIpState;
		}

		if (!empty($this->httpUserAgent)) {
			$dataList[self::KEY_HTTP_USER_AGENT] = $this->httpUserAgent;
		}

		if (!empty($this->linkBack)) {
			$dataList[self::KEY_HTTP_LINK_BACK] = $this->linkBack;
		}

		if (!empty($this->verificationVersion)) {
			$dataList[self::KEY_VERIFICATION_VERSION] = $this->verificationVersion;
		}

		if (!empty($this->websiteHostname)) {
			$dataList[self::KEY_WEBSITE_HOSTNAME] = $this->websiteHostname;
		}

		if (!empty($this->httpParamList)) {
			$dataList[self::KEY_HTTP_PARAM_LIST] = $this->httpParamList;
		}

		if (!empty($this->verificationResult)) {
			$dataList[self::KEY_VERIFICATION_RESULT] = $this->verificationResult;
		}

		return $dataList;
	}

	public function fromArray($data) {

		if (!empty($data[self::KEY_ACCOUNT_ID])) {
			$this->userId = intval($data[self::KEY_ACCOUNT_ID]);
		}

		if (!empty($data[self::KEY_ACCOUNT_DATA])) {
			$this->userData = $data[self::KEY_ACCOUNT_DATA];
		}

		if (!empty($data[self::KEY_HTTP_LINK_BACK])) {
			$this->linkBack = $data[self::KEY_HTTP_LINK_BACK];
		}

		if (!empty($data[self::KEY_VERIFICATION_VERSION])) {
			$this->verificationVersion = $data[self::KEY_VERIFICATION_VERSION];
		}

		if (!empty($data[self::KEY_WEBSITE_HOSTNAME])) {
			$this->websiteHostname = $data[self::KEY_WEBSITE_HOSTNAME];
		}

		if (!empty($data[self::KEY_HTTP_PARAM_LIST])) {
			$this->httpParamList = $data[self::KEY_HTTP_PARAM_LIST];
		}

		if (!empty($data[self::KEY_HTTP_USER_AGENT])) {
			$this->httpUserAgent = $data[self::KEY_HTTP_USER_AGENT];
		}

		if (!empty($data[self::KEY_USER_IP_STR])) {
			$this->userIpStr = $data[self::KEY_USER_IP_STR];
		}

		if (!empty($data[self::KEY_USER_IP_ID])) {
			$this->userIpId = intval($data[self::KEY_USER_IP_ID]);
		}

		if (!empty($data[self::KEY_VERIFICATION_RESULT])) {
			$this->verificationResult = $data[self::KEY_VERIFICATION_RESULT];
		}

		if (empty($this->userIpStr) && empty($this->userIpId)) {
			return false;
		}

		if (!empty($data[self::KEY_USER_IP_COUNTRY])) {
			$this->userIpCountry = $data[self::KEY_USER_IP_COUNTRY];
		}

		if (!empty($data[self::KEY_USER_IP_STATE])) {
			$this->userIpState = $data[self::KEY_USER_IP_STATE];
		}

		if (empty($data['requestTime'])) {
			return false;
		}

		if (empty($data['requestTime'])) {
			return false;
		}

		$this->requestTime = intval($data['requestTime']);

		if ($this->requestTime < 30000) {
			return false;
		}

		return true;

	}

	public function toPayload() {
		$buffer = json_encode($this->toArray());

		$ivContent  = \openssl_random_pseudo_bytes(\openssl_cipher_iv_length(self::CIPHER_ALGO));
		$ciphertext = \openssl_encrypt(
			$buffer,
			self::CIPHER_ALGO,
			$this->cipherKeyBin,
			1,
			$ivContent
		);

		$buffer = $ivContent . $ciphertext;
		$hmac   = hash_hmac(self::HMAC_ALGO, $buffer, $this->hmacKeyBin, true);

		return $this->base64UrlEncode($hmac . $buffer);

	}

	protected function debug($msg) {
		// error_log(date('c') . ' ' . $msg);
	}

	public function fromPayload($payloadBase64) {
		$payloadBase64 = strtr($payloadBase64, '-_', '+/');

		$buffer = base64_decode($payloadBase64, true);;
		if ($buffer === false) {
			$this->debug("Base64 decode error");

			return false;
		}
		$bufferLen = strlen($buffer);

		if ($bufferLen < self::HMAC_SIZE) {
			$this->debug("Bad data len: $buffer");

			return false;
		}
		$signatureInput = substr($buffer, 0, self::HMAC_SIZE);

		if ($bufferLen === self::HMAC_SIZE) {
			$bufferWithoutSignature = '';
		}
		else {
			$bufferWithoutSignature = substr($buffer, self::HMAC_SIZE);
		}

		$signatureGenerated = hash_hmac(self::HMAC_ALGO, $bufferWithoutSignature, $this->hmacKeyBin, true);

		if ($signatureGenerated !== $signatureInput) {
			$this->debug("Signature different (algo: " . self::HMAC_ALGO . ", bufferLen: " . strlen($bufferWithoutSignature) . ")");

			return false;
		}

		$ivSize = \openssl_cipher_iv_length(self::CIPHER_ALGO);
		if ($ivSize < 4) {
			$this->debug("Bad ivSize for " . self::CIPHER_ALGO);

			return false;
		}

		$iv         = substr($bufferWithoutSignature, 0, $ivSize);
		$ciphertext = substr($bufferWithoutSignature, $ivSize);

		$output = \openssl_decrypt(
			$ciphertext,
			self::CIPHER_ALGO,
			$this->cipherKeyBin,
			1,
			$iv
		);
		if ($output === false) {
			$this->debug('Decrypt error');

			return false;
		}

		$dataList = json_decode($output, true);

		if (!isset($dataList) || ($dataList === false)) {
			$this->debug('Unable to json_decode the buffer');

			return false;
		}

		return $this->fromArray($dataList);
	}

	public function toUrl($avsUrl = null) {

		if (empty($avsUrl)) {
			$avsUrl = self::AVS_URL;
		}

		return $avsUrl . '/' . $this->language . '/token/?p=' . $this->partnerId . '&d=' . urlencode($this->toPayload());
	}

	public function toIframeUrl($avsUrl = null) {

		if (empty($avsUrl)) {
			$avsUrl = self::AVS_URL;
		}

		return $avsUrl . '/' . $this->language . '/token/iframeCheck?p=' . $this->partnerId . '&d=' . urlencode($this->toPayload());
	}

	private function hexToBin($hexStr) {
		$n    = strlen($hexStr);
		$sbin = "";
		$i    = 0;
		while ($i < $n) {
			$a = substr($hexStr, $i, 2);
			$c = pack("H*", $a);
			if ($i == 0) {
				$sbin = $c;
			}
			else {
				$sbin .= $c;
			}
			$i += 2;
		}

		return $sbin;
	}

	private function base64UrlEncode($dataRaw) {
		return strtr(str_replace('=', '', base64_encode($dataRaw)), '+/', '-_');
	}

}
