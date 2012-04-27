<?php

Class Social_Listener
{
    public static function loadClassController($class, &$extend)
   	{
   		if ($class == 'XenForo_ControllerPublic_Register'){
   			$extend[] = 'Social_ControllerPublic_Register';
   		}

        if ($class == 'XenForo_ControllerPublic_Account'){
           $extend[] = 'Social_ControllerPublic_Account';
        }
   	}
}

