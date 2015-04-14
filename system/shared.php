<?php

/** check if environment is development and display errors **/

function setReporting()
{
	if(DEVELOPMENT_ENVIRONMENT == true)
	{
		error_reporting(E_ALL);
		ini_set('display_errors', 'On');

	}

	else
	{
		error_reporting(E_ALL);
		ini_set('display_errors', 'Off');
		ini_set('log_errors', 'On');
		ini_set('error_log', ROOT.DS.'tmp'.DS.'logs'.DS.'error.log');

	}

}

/** check for magic quotes and remove them **/

function stripSlashesDeep($value)
{
	$value = is_array($value) ? array_map('stripSlashesDeep', $value) : stripslashes($value);

	return $value;

}

function removeMagicQuotes()
{
	if(get_magic_quotes_gpc())
	{
		$_GET 		= stripSlashesDeep($_GET	);
		$_POST 		= stripSlashesDeep($_POST	);
		$_COOKIE 	= stripSlashesDeep($_COOKIE	);

	}

}

/** check register globals and remove them **/

function unregisterGlobals()
{
	if(ini_get('register_globals'))
	{
		$array = array('_SESSION', '_POST', '_GET', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');

		foreach ($array as $value) 
		{
			foreach ($GLOBALS[$value] as $key => $var) 
			{
				if($var === $GLOBALS[$key])
				{
					unset($GLOBALS[$key]);

				}

			}

		}

	}

}

/** main call function **/

function callHook()
{
	global $url;

	$urlArray = array();
	$urlArray = explode("/", $url);

	$controller = $urlArray[0];
	array_shift($urlArray);
	$action = $urlArray[0];
	array_shift($urlArray);
	$queryString = $urlArray;

	$controllerName = $controller;
	$controller 	= ucwords($controller);
	$model 			= rtrim($controller, 's');
	$controller 	= 'Controllers\\' . $controller . 'Controller';
	$dispatch 		= new $controller($model, $controllerName, $action);

	if((int)method_exists($controller, $action))
	{
		call_user_func_array(array($dispatch, $action), $queryString);

	} 

	else
	{
		/** Error generation code here **/


	}

}

setReporting();
removeMagicQuotes();
unregisterGlobals();
callHook();