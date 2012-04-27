<?php

/**
 * Helper for Google integration.
 */
abstract class Social_Provider_Abstract extends XenForo_ControllerHelper_Abstract
{
	public $provider;
	public $authUrl;
	public $token;

	public function authenticate($redirectUri = null)
	{
		$token = $this->getAccessToken($redirectUri);

		if(!$this->isValidToken($token))
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('social_invalid_access_token'))
			);
		}

		$this->token = $token;
		return $token;
	}

	abstract public function getProfile($authId = null);

	abstract public function isValidToken($token);

	abstract public function getAccessToken($redirectUri);

	abstract public function getAuthorizeUrl($redirectUri);

	public function getPermanentUserInfo(array $profile)
	{
		return array();
	}
}