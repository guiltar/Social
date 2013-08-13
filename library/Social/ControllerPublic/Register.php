<?php

class Social_ControllerPublic_Register extends XFCP_Social_ControllerPublic_Register
{

	/**
	 * Displays a form to join using Google or logs in an existing account.
	 * @param $helper Social_Provider_Abstract
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getProviderResponse(Social_Provider_Abstract $helper)
	{
		$associate = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
		$assocUserId = $associate ? XenForo_Visitor::getUserId() : 0;
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$redirectUri = XenForo_Link::buildPublicLink('canonical:register/' . $helper->provider, false, array(
			'assoc' => ($associate ? $associate : false)
		));

		if ($associate && !$assocUserId)
		{
			return $this->responseError(new XenForo_Phrase('action_not_completed_because_no_longer_logged_in'));
		}

		if ($this->_input->filterSingle('reg', XenForo_Input::UINT))
		{
			$redirect = XenForo_Link::convertUriToAbsoluteUri($this->getDynamicRedirect());

			XenForo_Application::getSession()->set($helper->provider . 'Redirect', $redirect);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				$helper->getAuthorizeUrl($redirectUri)
			);
		}

		$token = $helper->authenticate($redirectUri);
		$profile = $helper->getProfile();
		$permanentInfo = $helper->getPermanentUserInfo($profile);
		$provider = $helper->provider;

		if (!$profile['auth_id'])
		{
			return $this->responseError(new XenForo_Phrase('social_authentication_failed'));
		}

		XenForo_Application::getSession()->set($helper->provider . '_token', $token);

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$providerAssoc = $userExternalModel->getExternalAuthAssociation($provider, $profile['auth_id']);
		//die(Zend_Debug::dump($providerAssoc));
		if ($providerAssoc && $userModel->getUserById($providerAssoc['user_id']))
		{
			$redirect = XenForo_Application::getSession()->get($provider . 'Redirect');
			
			$userExternalModel->updateExternalAuthAssociationExtra(
				$providerAssoc['user_id'], $provider, array_merge(array('token' => $token), $permanentInfo)
			);

			XenForo_Helper_Cookie::setCookie($provider . 'AuthId', $profile['auth_id'], 14 * 86400);
			$userModel->setUserRememberCookie($providerAssoc['user_id']);
			XenForo_Application::getSession()->changeUserId($providerAssoc['user_id']);
			XenForo_Visitor::setup($providerAssoc['user_id']);

			XenForo_Application::getSession()->remove($provider . 'Redirect');
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		XenForo_Helper_Cookie::setCookie($provider . 'AuthId', 0, 14 * 86400);

		parent::_assertBoardActive($provider);

		$existingUser = false;
		$emailMatch = false;
		if (XenForo_Visitor::getUserId())
		{
			$existingUser = XenForo_Visitor::getInstance();
		}
		else if ($assocUserId)
		{
			$existingUser = $userModel->getUserById($assocUserId);
		}

		if ($existingUser)
		{
			// must associate: matching user
			return $this->responseView('Social_Google_ViewPublic_Register_Google', 'social_register_provider', array(
				'associateOnly' => true,

				'profile' => $profile,
				'provider' => $provider,
				'providerName' => new XenForo_Phrase('social_' . $provider),

				'existingUser' => $existingUser,
				'emailMatch' => $emailMatch,
				'redirect' => $redirect,
			));
		}

		if (!XenForo_Application::getSession()->get('registrationSetup', 'enabled'))
		{
			$this->_assertRegistrationActive();
		}

		$this->_assertSuitableAge($profile);

		// give a unique username suggestion
		$i = 2;
		$origName = $profile['username'];
		$suggestedName = &$profile['username'];
		while ($userModel->getUserByName($suggestedName) || $userModel->getUserByName(utf8_deaccent(utf8_romanize($suggestedName))))
		{
			$suggestedName = $origName . ' ' . $i++;
		}

		return $this->responseView('Social_Google_ViewPublic_Register_Google', 'social_register_provider', array(

			'profile' => $profile,
			'provider' => $provider,
			'providerName' => new XenForo_Phrase('social_' . $provider),

			'redirect' => $redirect,

			'customFields' => $this->_getFieldModel()->prepareUserFields(
				$this->_getFieldModel()->getUserFields(array('registration' => true)),
				true
			),

			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
			'dobRequired' => XenForo_Application::getOptions()->get('registrationSetup', 'requireDob'),
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		), $this->_getRegistrationContainerParams());
	}

	/**
	 *  Registers a new account (or associates with an existing one) using Google.
	 * @param $helper Social_Provider_Abstract
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getProviderRegisterResponse(Social_Provider_Abstract $helper)
	{
		$this->_assertPostOnly();

		$provider = $helper->provider;

		$token = XenForo_Application::getSession()->get($helper->provider . '_token');
		if (!$helper->isValidToken($token))
		{
			return $this->responseError(new XenForo_Phrase('social_invalid_access_token'));
		}

		$helper->token = $token;
		$profile = $helper->getProfile();
		$permanentInfo = $helper->getPermanentUserInfo($profile);

		if (!$profile['auth_id'])
		{
			return $this->responseError(new XenForo_Phrase('social_authentication_failed'));
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING)
			|| $this->_input->filterSingle('force_assoc', XenForo_Input::UINT)
		);

		if ($doAssoc)
		{
			$associate = $this->_input->filter(array(
				'associate_login' => XenForo_Input::STRING,
				'associate_password' => XenForo_Input::STRING
			));

			$loginModel = $this->_getLoginModel();

			if ($loginModel->requireLoginCaptcha($associate['associate_login']))
			{
				return $this->responseError(new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
			}

			$userId = $userModel->validateAuthentication($associate['associate_login'], $associate['associate_password'], $error);
			if (!$userId)
			{
				$loginModel->logLoginAttempt($associate['associate_login']);
				return $this->responseError($error);
			}

			$userExternalModel->updateExternalAuthAssociation($provider, $profile['auth_id'], $userId, null, array_merge(array('token' => $token), $permanentInfo));
			XenForo_Helper_Cookie::setCookie($provider . 'AuthId', $profile['auth_id'], 14 * 86400);

			$redirect = XenForo_Application::getSession()->get($provider . 'Redirect');
			XenForo_Application::getSession()->changeUserId($userId);
			XenForo_Visitor::setup($userId);

			XenForo_Application::getSession()->remove($provider . 'Redirect');
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		$this->_assertRegistrationActive();

		$data = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'email' => XenForo_Input::STRING,
			'timezone' => XenForo_Input::STRING,
			'dob_day' => XenForo_Input::UINT,
			'dob_month' => XenForo_Input::UINT,
			'dob_year' => XenForo_Input::UINT,
		));

		$this->_assertSuitableAge($data, XenForo_Application::getOptions()->get('registrationSetup', 'requireDob'));
		$this->_assertSuitableAge($profile);


		$data['email'] = !empty($data['email']) ? $data['email'] : (!empty($profile['email']) ? $profile['email'] : '');
		$data['dob_day'] = !empty($data['dob_day']) ? $data['dob_day'] : (!empty($profile['dob_day']) ? $profile['dob_day'] : '');
		$data['dob_month'] = !empty($data['dob_month']) ? $data['dob_month'] : (!empty($profile['dob_month']) ? $profile['dob_month'] : '');
		$data['dob_year'] = !empty($data['dob_year']) ? $data['dob_year'] : (!empty($profile['dob_year']) ? $profile['dob_year'] : '');

		if (XenForo_Dependencies_Public::getTosUrl() && !$this->_input->filterSingle('agree', XenForo_Input::UINT))
		{
			return $this->responseError(new XenForo_Phrase('you_must_agree_to_terms_of_service'));
		}

		$options = XenForo_Application::getOptions();

		/* @var $writer XenForo_DataWriter_User */
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($registrationDefaults = $options->get('registrationDefaults'))
		{
			$writer->bulkSet($registrationDefaults, array('ignoreInvalidFields' => true));
		}
		$writer->bulkSet($data);

		$auth = XenForo_Authentication_Abstract::create('XenForo_Authentication_NoPassword');
		$writer->set('scheme_class', $auth->getClassName());
		$writer->set('data', $auth->generate(''), 'xf_user_authenticate');

		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));

		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
		$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));
		$writer->setCustomFields($customFields, $customFieldsShown);

		$writer->advanceRegistrationUserState(false);
		$writer->preSave();

		// TODO: option for extra user group

		$writer->save();
		$user = $writer->getMergedData();

		$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
		if ($avatarFile && !empty($profile['avatar_url']))
		{
			$data = XenForo_Helper_Http::getClient($profile['avatar_url'])->request('GET')->getBody();
			if ($data && $data[0] != '{') // ensure it's not a json response
			{
				file_put_contents($avatarFile, $data);

				try
				{
					$user = array_merge($user,
						$this->getModelFromCache('XenForo_Model_Avatar')->applyAvatar($user['user_id'], $avatarFile)
					);
				}
				catch (XenForo_Exception $e)
				{
				}
			}

			@unlink($avatarFile);
		}

		$userExternalModel->updateExternalAuthAssociation($provider, $profile['auth_id'], $user['user_id'], null, array_merge(array('token' => $token), $permanentInfo));

		XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');

		XenForo_Helper_Cookie::setCookie($provider . 'AuthId', $profile['auth_id'], 14 * 86400);

		XenForo_Application::getSession()->changeUserId($user['user_id']);
		XenForo_Visitor::setup($user['user_id']);

		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$viewParams = array(
			'user' => $user,
			'redirect' => ($redirect ? XenForo_Link::convertUriToAbsoluteUri($redirect) : ''),
			$provider => true
		);

		return $this->responseView(
			'XenForo_ViewPublic_Register_Process',
			'register_process',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}

	protected function _assertSuitableAge($profile, $requireDob = false)
	{
		$correctDob = !empty($profile['dob_day']) && !empty($profile['dob_month']) && !empty($profile['dob_year']) &&
			$profile['dob_day'] && $profile['dob_month'] && $profile['dob_year'];

		if ($correctDob)
		{
			$userAge = $this->_getUserProfileModel()->calculateAge($profile['dob_year'], $profile['dob_month'], $profile['dob_day']);
			if ($userAge < intval(XenForo_Application::getOptions()->get('registrationSetup', 'minimumAge')))
			{
				throw $this->responseException($this->responseError(new XenForo_Phrase('sorry_you_too_young_to_create_an_account')));
			}
		}
		elseif ($requireDob)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('please_enter_valid_date_of_birth')));
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
	 * Registers a new account (or associates with an existing one) using Google.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionGoogleRegister()
	{
		$helper = $this->getHelper('Social_Provider_Oauth2_Google');

		return $this->_getProviderRegisterResponse($helper);
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
	 * Registers a new account (or associates with an existing one) using Twitter.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	/*public function actionLinkedinRegister()
	{
		$helper = $this->getHelper('Social_Provider_Oauth_Linkedin');

		return $this->_getProviderRegisterResponse($helper);
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
	 * Registers a new account (or associates with an existing one) using Twitter.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionTwitterRegister()
	{
		$helper = $this->getHelper('Social_Provider_Oauth_Twitter');

		return $this->_getProviderRegisterResponse($helper);
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
	 * Registers a new account (or associates with an existing one) using VK.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionVKRegister()
	{
		$helper = $this->getHelper('Social_Provider_Oauth2_VK');

		return $this->_getProviderRegisterResponse($helper);
	}

	/*protected function _assertBoardActive($action)
	{
		if (strtolower($action) != $helper->provider)
		{
			parent::_assertBoardActive($action);
		}
	}

	protected function _assertCorrectVersion($action)
	{
		if (strtolower($action) != $helper->provider)
		{
			parent::_assertBoardActive($action);
		}
	}*/
}