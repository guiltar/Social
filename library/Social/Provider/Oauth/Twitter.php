<?php

require_once(XenForo_Application::getInstance()->getRootDir().'/library/Social/Extra/twitteroauth/twitteroauth.php');

/**
 * Helper for Twitter integration.
 */
class Social_Provider_Oauth_Twitter extends Social_Provider_Oauth_Abstract
{
	public $provider = 'twitter';
	public $siteUrl = 'https://api.twitter.com/oauth';
	public $authUrl = 'https://api.twitter.com/oauth/authenticate';

	public function getAuthorizeUrl($redirectUri)
	{
		/* Build TwitterOAuth object with client credentials. */
		$connection = new TwitterOAuth($this->key, $this->secret);

		/* Get temporary credentials. */
		$request_token = $connection->getRequestToken($redirectUri);

		$session = XenForo_Application::getSession();

		/* Save temporary credentials to session. */
		$session->set('twitter_oauth_token', $request_token['oauth_token']);
		$session->set('twitter_oauth_token_secret', $request_token['oauth_token_secret']);

		return $connection->getAuthorizeURL($request_token['oauth_token']);
	}

	public function getAccessToken($redirectUri)
	{
		$session = XenForo_Application::getSession();

		/* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
		$connection = new TwitterOAuth($this->key, $this->secret, $session->get('twitter_oauth_token'), $session->get('twitter_oauth_token_secret'));

		/* Request access tokens from twitter */
		$access_token = $connection->getAccessToken($this->_controller->getInput()->filterSingle('oauth_verifier', XenForo_Input::STRING));

		/* Remove no longer needed request tokens */
		$session->set('twitter_oauth_token', null);
		$session->set('twitter_oauth_token_secret', null);

		return $access_token;
	}

	/**
	 * @param Zend_Oauth_Token_Access $token
	 *
	 * @return bool
	 */
	public function isValidToken($token)
	{
		return !empty($token['user_id']) && !empty($token['oauth_token']) && !empty($token['oauth_token_secret']);
	}

	/**
	 * @param Zend_Oauth_Token_Access $token
	 *
	 * @return array
	 */
	public function getProfile($authId = null)
	{
		/* Create a TwitterOauth object with consumer/user tokens. */
		$connection = new TwitterOAuth($this->key, $this->secret, $this->token['oauth_token'], $this->token['oauth_token_secret']);

		/* Get logged in user to help with tests. */
		$user = $connection->get('account/verify_credentials');

		$profile['auth_id'] = @$user->id;
		$profile['username'] = @$user->screen_name;
		$profile['profile_url'] = 'https://twitter.com/#!/' . @$user->screen_name;
		$profile['avatar_url'] = @$user->profile_image_url;

		return $profile;
	}
}