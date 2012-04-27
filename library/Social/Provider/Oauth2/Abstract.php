<?php

/**
 * Helper for Google integration.
 */
abstract class Social_Provider_Oauth2_Abstract extends Social_Provider_Abstract
{
	public $id;
	public $secret;

	public $tokenUrl;
	public $scope;

	protected function _constructSetup()
	{
		$options = XenForo_Application::getOptions();
		$this->id = $options->get($this->provider.'AppId');
		$this->secret = $options->get($this->provider.'AppSecret');

		if (!$this->id || !$this->secret)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseMessage(new XenForo_Phrase('social_provider_not_properly_configured'))
			);
		}
	}

	/**
	 * Takes a code (with a redirect URL) and gets an access token.
	 *
	 * @param string $url
	 * @param string $code
	 *
	 * @return array|false Array of info (may be error); false if Provider integration not active
	 */
	public function getAccessToken($redirectUri)
	{
		if (!$code = $this->_controller->getInput()->filterSingle('code', XenForo_Input::STRING))
		{
			throw $this->_controller->responseException(
				$this->_controller->responseMessage(new XenForo_Phrase('social_invalid_authorization_code'))
			);
		}

		try
		{
			$client = XenForo_Helper_Http::getClient($this->tokenUrl);

			$client->setParameterPost(array(
				'client_id' => $this->id,
				'client_secret' => $this->secret,
				'redirect_uri' => $redirectUri,
				'code' => $code,
				'grant_type' => 'authorization_code',
			));

			$body = $client->request('POST')->getBody();

			if (preg_match('#^[{\[]#', $body))
			{
				$parts = json_decode($body, true);
			}
			else
			{
				$parts = XenForo_Application::parseQueryString($body);
			}

			return $parts;
		}
		catch (Zend_Http_Client_Exception $e)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError($e->getMessage())
			);
		}
	}


	public function isValidToken($token)
	{
		return !empty($token['access_token']);
	}

	public function getAuthorizeUrl($redirectUri)
	{
		return $this->authUrl
			. '?client_id=' . $this->id
			. '&scope=' . urlencode($this->scope)
			. '&state=profile'
			. '&redirect_uri=' . urlencode($redirectUri)
			. '&response_type=code';
	}
}