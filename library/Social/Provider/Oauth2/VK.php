<?php

/**
 * Helper for VK integration.
 */
class Social_Provider_Oauth2_VK extends Social_Provider_Oauth2_Abstract
{
	public $provider = 'vk';
	public $tokenUrl = 'https://api.vk.com/oauth/token';
	public $authUrl = 'https://api.vk.com/oauth/authorize';
	public $scope = '';

	public function getProfile($authId=null)
	{
		if(!$authId) $authId = $this->token['user_id'];
		$client = XenForo_Helper_Http::getClient('https://api.vk.com/method/getProfiles');
		$client->setParameterGet('uid', $authId);
		$client->setParameterGet('fields', 'first_name,last_name,nickname,screen_name,sex,bdate,timezone,photo_rec,photo_big');
		if(isset($this->token['access_token']))
			$client->setParameterGet('access_token', $this->token['access_token']);

		$response = json_decode($client->request('GET')->getBody(), true);
		$response = isset($response['response'][0]) ? $response['response'][0] : $response;

		$profile['auth_id'] = isset($response['uid']) ? $response['uid'] : 0;
		$profile['screen_name'] = isset($response['screen_name']) ? $response['screen_name'] : '';
		$profile['first_name'] = isset($response['first_name']) ? $response['first_name'] : '';
		$profile['last_name'] = isset($response['last_name']) ? $response['last_name'] : '';
		$profile['username'] = !empty($response['nickname']) ? $response['nickname'] : $profile['first_name'].' '.$profile['last_name'];
		$profile['profile_url'] = 'http://vk.com/'. $profile['screen_name'];

		if(isset($response['photo_big']) && strpos($response['photo_big'],'http')!==false)
		{
			$profile['avatar_url'] = $response['photo_big'];
		}

		if(isset($response['bdate']))
		{
			$birthdayParts = explode('.', $response['bdate']);

			if (count($birthdayParts) == 3)
			{
				list($profile['dob_day'], $profile['dob_month'], $profile['dob_year']) = $birthdayParts;
			}
		}

		if (isset($response['sex']))
		{
			switch ($response['sex'])
			{
				case '2': $profile['gender'] = 'male'; break;
				case '1': $profile['gender'] = 'female'; break;
			}
		}

		return $profile;
	}
}