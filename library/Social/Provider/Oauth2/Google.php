<?php

/**
 * Helper for Google integration.
 */
class Social_Provider_Oauth2_Google extends Social_Provider_Oauth2_Abstract
{
	public $provider = 'google';
	public $tokenUrl = 'https://accounts.google.com/o/oauth2/token';
	public $authUrl = 'https://accounts.google.com/o/oauth2/auth';
	public $scope = 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email';

	public  function getProfile($authId = null)
	{
		$client = XenForo_Helper_Http::getClient('https://www.googleapis.com/oauth2/v1/userinfo');
		$client->setParameterGet('scope', $this->scope);
		if(isset($this->token['access_token']))
			$client->setParameterGet('access_token', $this->token['access_token']);

		$response = json_decode($client->request('GET')->getBody(), true);

		return array(
			'auth_id' => isset($response['id']) ? $response['id'] : 0,
			'username' => isset($response['name']) ? $response['name'] : '',
			'email' => isset($response['email']) ? $response['email'] : '',
			'profile_url' => 'https://www.google.com'
		);
	}

	public function getPermanentUserInfo(array $profile)
	{
		return array(
			'email' => isset($profile['email']) ? $profile['email'] : '',
		);
	}
}