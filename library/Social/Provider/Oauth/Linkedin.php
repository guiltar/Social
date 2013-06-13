<?php

/**
 * Helper for Twitter integration.
 */
class Social_Provider_Oauth_Linkedin extends Social_Provider_Oauth_Abstract
{
	public $provider = 'linkedin';
	public $siteUrl = 'https://api.linkedin.com/uas/oauth';
	public $authUrl = 'https://api.linkedin.com/uas/oauth/authorize';
	
	/**
	 * @param Zend_Oauth_Token_Access $token
	 *
	 * @return array
	 */
	public function getProfile($authId = null)
	{
		$client = XenForo_Helper_Http::getClient('https://api.linkedin.com/v1/people/~:(id,formatted-name,public-profile-url,picture-url)');
		
		// Set Method (GET, POST or PUT)
		$client->setMethod(Zend_Http_Client::GET);
		// Get the XML containing User's Profile
		$response = new SimpleXmlElement($client->request('GET')->getBody());
				
		$profile['auth_id'] = (string) $response->{'id'};
		$profile['token'] = serialize($this->token);
		$profile['username'] = (string) $response->{'formatted-name'};
		$profile['profile_url'] = (string) $response->{'public-profile-url'};
		$profile['avatar_url'] = (string) $response->{'picture-url'};
		
		return $profile;
	}
	
	protected function _getConfig($redirectUri)
	{
		$config = array(
			'requestTokenUrl' => $this->siteUrl . '/requestToken',
			'userAuthorisationUrl' => $this->authUrl,
			'accessTokenUrl' => $this->siteUrl . '/accessToken',
			'consumerKey'	=> $this->key,
			'consumerSecret'=> $this->secret,
		);

		if ($redirectUri)
		{
			$config['callbackUrl'] = $redirectUri;
			$config['localUrl'] = $redirectUri;
		}

		return $config;
	}
}