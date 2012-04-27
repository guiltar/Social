<?php

class Social_ControllerPublic_Account extends XFCP_Social_ControllerPublic_Account
{
	protected function _getProviderResponse(Social_Provider_Abstract $helper)
	{
		$provider = $helper->provider;
		$visitor = XenForo_Visitor::getInstance();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			$disassociate = $this->_input->filter(array(
				'disassociate' => XenForo_Input::STRING,
				'disassociate_confirm' => XenForo_Input::STRING
			));
			if ($disassociate['disassociate'] && $disassociate['disassociate_confirm'])
			{
				XenForo_Helper_Cookie::setCookie($provider.'AuthId', 0, 14 * 86400);
				$this->getModelFromCache('XenForo_Model_UserExternal')->deleteExternalAuthAssociation(
					$provider, $visitor[$provider.'_auth_id'], $visitor['user_id']
				);

				if (!$auth->hasPassword())
				{
					$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($visitor['user_id']);
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/'.$provider)
			);
		}
		else
		{
			if ($visitor[$helper->provider.'_auth_id'])
			{
				$viewParams['profile'] = $profile = $helper->getProfile($visitor[$provider.'_auth_id']);
				if($providerAssoc = $this->_getUserExternalModel()->getExternalAuthAssociation($provider, $visitor[$provider.'_auth_id']))
				{
					$viewParams['profile'] = array_merge($viewParams['profile'], unserialize($providerAssoc['extra_data']));
				}
			}

			$viewParams['hasPassword'] = $auth->hasPassword();
			$viewParams['provider'] = $provider;
			$viewParams['providerName'] = new XenForo_Phrase('social_'.$provider);

			return $this->_getWrapper(
				'account', $provider,
				$this->responseView('XenForo_ViewPublic_Account_Google', 'social_account_provider', $viewParams)
			);
		}
	}

	/**
	 * Displays a form to join using Google or logs in an existing account.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionGoogle()
	{
		$helper = $this->getHelper('Social_Provider_Oauth2_Google');

		return $this->_getProviderResponse($helper);
	}

	/**
	 * Displays a form to join using Twitter or logs in an existing account.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	/*public function actionLinkedin()
	{
		$helper = $this->getHelper('Social_Provider_Oauth_Linkedin');

		return $this->_getProviderResponse($helper);
	}*/

	/**
	 * Displays a form to join using Twitter or logs in an existing account.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionTwitter()
	{
		$helper = $this->getHelper('Social_Provider_Oauth_Twitter');

		return $this->_getProviderResponse($helper);
	}

	/**
	 * Displays a form to join using VK or logs in an existing account.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionVK()
	{
		$helper = $this->getHelper('Social_Provider_Oauth2_VK');

		return $this->_getProviderResponse($helper);
	}

	/**
	 * @return XenForo_Model_UserExternal
	 */
	protected function _getUserExternalModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserExternal');
	}
}