<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package	   MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\BackendIntegration;

use DcGeneral\Event\EventPropagator;
use MetaModels\BackendIntegration\Contao2\initializeSystemHOOKHack;
use MetaModels\BackendIntegration\Events\BackendIntegrationEvent;
use MetaModels\Dca\MetaModelDcaBuilder;

/**
 * This class is the abstract base class used in the backend to build the menu.
 * See the concrete implementation in the ContaoX folders (depending on Contao Core version)
 *
 * @package    MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class Boot
{
	/**
	 * Returns the proper user object for the current context.
	 *
	 * @return \BackendUser|\FrontendUser|null the BackendUser when TL_MODE == 'BE', the FrontendUser when TL_MODE == 'FE' or null otherwise
	 */
	protected static function getUser()
	{
		if(TL_MODE=='BE')
		{
			return \BackendUser::getInstance();
		} else if(TL_MODE=='FE')
		{
			return \FrontendUser::getInstance();
		}
		return null;
	}

	/**
	 * This initializes the Contao Singleton object stack as it must be,
	 * when using singletons within the config.php file of an Extension.
	 *
	 * @return bool
	 */
	protected static function initializeContaoObjectStack()
	{
		if (!file_exists(TL_ROOT . '/system/config/localconfig.php'))
		{
			return false;
		}

		// all of these getInstance calls are neccessary to keep the instance stack intact
		// and therefore prevent an Exception in unknown on line 0.
		// Hopefully this will get fixed with Contao Reloaded or Contao 3.

		require_once(TL_ROOT . '/system/config/localconfig.php');

		\Config::getInstance();

		\Environment::getInstance();
		\Input::getInstance();

		self::getUser();

		\Database::getInstance();

		return true;
	}

	protected static function isDBInitialized()
	{
		// When coming from install.php or somewhere else when localconfig.php
		// has not yet completely been initialized, we will run into an exception here.
		try
		{
			$objDB = \Database::getInstance();
			return $objDB && $objDB->tableExists('tl_metamodel', null, true);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	protected static function authenticateBackendUser()
	{
		$objUser = self::getUser();
		// work around as the TL_PATH constant is set after this routine has been run.
		// if this is not in place, BackendUser::authenticate() will redirect us to
		// http://domain.tldtl_path/contao/index.php
		// if no user is properly logged in (note the missing slash in the middle right after .tld).
		// We also have to fix up the "script" parameter, as this one will otherwise try to redirect from
		// "contao/index.php" to "/contao/index.php" therefore creating an infinite redirect loop.
		$Env = \Environment::getInstance();

		// issue #66 - contao/install.php is not working anymore. Thanks to Stefan Lindecke (@lindesbs)
		if (strpos($Env->request,"install.php") !== false)
		{
			return;
		}

		$Env->base = $Env->url . $GLOBALS['TL_CONFIG']['websitePath'] . '/';
		$Env->script = preg_replace('/^' . preg_quote($GLOBALS['TL_CONFIG']['websitePath'], '/') . '\/?/i', '', $Env->scriptName);

		// Bugfix: If the user is not authenticated, contao will redirect to contao/index.php
		// But in this moment the TL_PATH is not defined, so the $this->Environment->request
		// generate a url without replacing the basepath(TL_PATH) with an empty string.
		if(!defined(TL_PATH))
		{
			define('TL_PATH', $GLOBALS['TL_CONFIG']['websitePath']);
		}

		// TODO: double, triple and quadro check that this is really safe context here.
		$objUser->authenticate();
		// restore initial settings.
		$Env->base = null;
		$Env->script = null;
	}

	public static function perform()
	{
		if (!(self::initializeContaoObjectStack() && self::isDBInitialized()))
		{
			return;
		}

		// if no backend user authenticated, we will get redirected.
		self::authenticateBackendUser();

		global $container;
		$propagator = new EventPropagator($container['event-dispatcher']);

		$propagator->propagate(
			BackendIntegrationEvent::NAME,
			new BackendIntegrationEvent()
		);

		MetaModelDcaBuilder::getInstance()->injectBackendMenu();
		MetaModelDcaBuilder::getInstance()->injectIntoBackendModules();
	}

	/**
	 * Called from config.php in TL_MODE == 'BE' to register for startup in the backend.
	 *
	 * @return void
	 */
	public static function MetaModels()
	{
		if (version_compare(VERSION, '3.0', '<'))
		{
			initializeSystemHOOKHack::register();
		}
		else
		{
			$GLOBALS['TL_HOOKS']['initializeSystem'][] = array(__CLASS__, 'perform');
		}
	}
}

