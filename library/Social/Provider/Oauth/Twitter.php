<?php

/**
 * Helper for Twitter integration.
 */
class Social_Provider_Oauth_Twitter extends Social_Provider_Oauth_Abstract
{
	public $provider = 'twitter';
	public $siteUrl = 'https://api.twitter.com/oauth';
	public $authUrl = 'https://api.twitter.com/oauth/authenticate';

	/**
	 * @param Zend_Oauth_Token_Access $token
	 *
	 * @return array
	 */
	public function getProfile($authId = null)
	{
		//$this->search();
		if(!$authId) $authId = $this->token->getParam('user_id');
		$client = XenForo_Helper_Http::getClient('https://api.twitter.com/1/users/show.json');
		$client->setParameterGet('user_id', $authId);
		$response = json_decode($client->request('GET')->getBody(), true);

		$profile['auth_id'] = isset($response['id']) ? $response['id'] : 0;
		$profile['username'] = isset($response['screen_name']) ? $response['screen_name'] : '';
		$profile['profile_url'] = 'https://twitter.com/#!/'.$profile['username'];
		$profile['avatar_url'] = 'https://api.twitter.com/1/users/profile_image?size=original&user_id='.$profile['auth_id'];

		return $profile;
	}

	/*public function search()
	{
		$client = XenForo_Helper_Http::getClient('https://api.twitter.com/1/users/search.json?q=Twitter%20API');
		$client->setParameterGet('oauth_token', urlencode($this->token->getParam('oauth_token')));
		$client->setParameterGet('oauth_token_secret', urlencode($this->token->getParam('oauth_token_secret')));
		$client->setParameterGet('oauth_consumer_key', urlencode($this->key));
		$client->setParameterGet('oauth_consumer_secret', urlencode($this->secret));
		$response = json_decode($client->request('GET')->getBody(), true);
		die(Zend_Debug::dump($response));
	}*/
}