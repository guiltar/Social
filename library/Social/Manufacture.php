<?php

class Social_Manufacture
{
	private static $_instance;

	protected $_db;

	public static final function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function _getDb()
	{
		if ($this->_db === null)
		{
			$this->_db = XenForo_Application::getDb();
		}

		return $this->_db;
	}

	public static function build($existingAddOn, $addOnData)
	{
		if (XenForo_Application::$versionId < 1010270)
		{
			throw new XenForo_Exception(new XenForo_Phrase('social_requires_minimum_xenforo_version', array('version' => '1.1.2')));
		}

		/* @var $addOnModel XenForo_Model_AddOn*/
		$addOnModel = XenForo_Model::create('XenForo_Model_AddOn');

		if (!$addOnModel->getAddOnById('TMS'))
		{
			throw new XenForo_Exception(new XenForo_Phrase('social_requires_tms'));
		}


		$addOnDw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		if ($addOnDw->setExistingData('Twitter'))
		{
			$addOnDw->set('uninstall_callback_class', '');
			$addOnDw->set('uninstall_callback_method', '');
			$addOnDw->delete();
		}

		$addOnDw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		if ($addOnDw->setExistingData('Vkontakte'))
		{
			$addOnDw->set('uninstall_callback_class', '');
			$addOnDw->set('uninstall_callback_method', '');
			$addOnDw->delete();
		}

		$startVersion = 1;
		$endVersion = $addOnData['version_id'];

		if ($existingAddOn)
		{
			$startVersion = $existingAddOn['version_id'] + 1;
		}

		$install = self::getInstance();

		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		for ($i = $startVersion; $i <= $endVersion; $i++)
		{
			$method = '_installVersion' . $i;

			if (method_exists($install, $method) === false)
			{
				continue;
			}

			$install->$method();
		}

		XenForo_Db::commit($db);
	}

	protected function _installVersion1()
	{
		$db = $this->_getDb();

		// Google
		$db->query("
			ALTER TABLE xf_user_profile
			ADD google_auth_id VARBINARY(150) NOT NULL DEFAULT 0 AFTER facebook_auth_id
		");

		// VK
		$db->query("
            UPDATE xf_user_external_auth SET provider = 'vk'
            WHERE provider = 'vkontakte'
        ");

		if (!$db->fetchRow('SHOW columns FROM xf_user_profile WHERE field = \'vkontakte_auth_id\''))
		{
			$db->query("
				ALTER TABLE xf_user_profile
				ADD vk_auth_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER facebook_auth_id
			");
		}
		else
		{
			$db->query("
	            ALTER TABLE xf_user_profile
	            CHANGE vkontakte_auth_id vk_auth_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0
        	");
		}

		// Twitter
		if (!$db->fetchRow('SHOW columns FROM xf_user_profile WHERE field = \'twitter_auth_id\''))
		{
			$db->query("
				ALTER TABLE xf_user_profile
				ADD twitter_auth_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER facebook_auth_id
			");
		}
	}

	public static function destroy()
	{
		$lastUninstallStep = 3;

		$uninstall = self::getInstance();

		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		for ($i = 1; $i <= $lastUninstallStep; $i++)
		{
			$method = '_uninstallStep' . $i;

			if (method_exists($uninstall, $method) === false)
			{
				continue;
			}

			$uninstall->$method();
		}

		XenForo_Db::commit($db);
	}

	protected function _uninstallStep1()
	{
		$db = $this->_getDb();

		$db->query("
      			ALTER TABLE xf_user_profile
      			DROP twitter_auth_id,
      			DROP google_auth_id,
      			DROP vk_auth_id
      		");

		$db->query("
                DELETE FROM xf_user_external_auth
                WHERE provider = 'vk' OR provider = 'twitter' OR provider = 'google'
      		");
	}
}