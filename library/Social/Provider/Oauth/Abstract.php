<?php

/**
 * Helper for Twitter integration.
 */
abstract class Social_Provider_Oauth_Abstract extends Social_Provider_Abstract
{
	public $key;
	public $secret;

	public $siteUrl;

	protected function _constructSetup()
	{
		$options = XenForo_Application::getOptions();
		$this->key = $options->get($this->provider.'ConsumerKey');
		$this->secret = $options->get($this->provider.'ConsumerSecret');

		if (!$this->key || !$this->secret)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase(
					'social_provider_not_properly_configured',
					array('provider'=> new XenForo_Phrase('social_'.$this->provider))))
			);
		}
	}

	/**
	 * @param string $url
	 * @param string $code
	 *
	 * @return array|false Array of info (may be error); false if Provider integration not active
	 */
	public function getAccessToken($redirectUri)
	{
		$input = $this->_controller->getInput()->getInput();
		$request = new Zend_Oauth_Token_Request();
		$request->setToken($input['oauth_token']);
		//$request->setParam('oauth_verifier', $input['oauth_verifier']);
		$consumer = new Zend_Oauth_Consumer($this->_getConfig($redirectUri));
		$token = $consumer->getAccessToken($input, $request);
		return $token;
	}

	/**
	 * @param Zend_Oauth_Token_Access $token
	 *
	 * @return bool
	 */
	public function isValidToken($token)
	{
		return $token->isValid();
	}

	public function getAuthorizeUrl($redirectUri)
	{
		$consumer = new Zend_Oauth_Consumer($this->_getConfig($redirectUri));
		$consumer->getRequestToken();
		return $consumer->getRedirectUrl();
	}

	protected function _getConfig($redirectUri)
	{
		return array(
			'callbackUrl'	=> $redirectUri,
			'siteUrl'		=> $this->siteUrl,
			'consumerKey'	=> $this->key,
			'consumerSecret'=> $this->secret,
			'authorizeUrl'  => $this->authUrl
		);
	}
}