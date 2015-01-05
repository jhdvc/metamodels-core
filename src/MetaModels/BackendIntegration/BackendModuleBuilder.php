<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\BackendIntegration;

use MetaModels\IMetaModelsServiceContainer;
use MetaModels\BackendIntegration\InputScreen\IInputScreen;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use MetaModels\Helper\ToolboxFile;
use ContaoCommunityAlliance\Contao\Bindings\Events\Image\ResizeImageEvent;
use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;

/**
 * This class builds the backend module list and is responsible for creating the backend menu array for Contao.
 */
class BackendModuleBuilder
{
    /**
     * The service container.
     *
     * @var IMetaModelsServiceContainer
     */
    protected $container;

    /**
     * The view combinations.
     *
     * @var ViewCombinations
     */
    protected $viewCombinations;

    /**
     * The contents of the backend menu to be set.
     *
     * @var array
     */
    protected $backendMenu = array();

    /**
     * The language strings for the modules.
     *
     * @var array
     */
    protected $languageStrings = array();

    /**
     * Create a new instance.
     *
     * @param IMetaModelsServiceContainer $container        The service container.
     *
     * @param ViewCombinations            $viewCombinations The view combinations.
     */
    public function __construct(IMetaModelsServiceContainer $container, ViewCombinations $viewCombinations)
    {
        $this->container        = $container;
        $this->viewCombinations = $viewCombinations;

        if (!$this->loadFromCache()) {
            $this->resolve();
            $this->saveToCache();
        }
    }

    /**
     * Try to load the combinations from cache.
     *
     * @return bool
     */
    protected function loadFromCache()
    {
        $key = $this->calculateCacheKey();
        if (!$this->container->getCache()->contains($key)) {
            return false;
        }

        // Perform loading now.
        $data = json_decode($this->container->getCache()->fetch($key), true);

        if (empty($data)) {
            return false;
        }

        $this->backendMenu     = $data['backendMenu'];
        $this->languageStrings = $data['languageStrings'];

        return true;
    }

    /**
     * Try to load the combinations from cache.
     *
     * @return bool
     */
    protected function saveToCache()
    {
        return $this->container->getCache()->save(
            $this->calculateCacheKey(),
            json_encode(
                array(
                    'backendMenu'     => $this->backendMenu,
                    'languageStrings' => $this->languageStrings,
                ),
                JSON_PRETTY_PRINT
            )
        );
    }

    /**
     * Try to load the combinations from cache.
     *
     * @return string|null
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    protected function calculateCacheKey()
    {
        // Determine cache key.
        $key = sprintf(
            'backend_menu_%s_%s_%s',
            strtolower(TL_MODE),
            $this->viewCombinations->getUser()->id ?: 'anonymous',
            $GLOBALS['TL_LANGUAGE']
        );

        return $key;
    }

    /**
     * Retrieve the event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    protected function getEventDispatcher()
    {
        return $this->container->getEventDispatcher();
    }

    /**
     * Build a 16x16 sized representation of the passed icon if it exists or fallback to the default icon otherwise.
     *
     * @param string $icon The path to the icon (relative to TL_ROOT).
     *
     * @return string The path to the generated icon.
     */
    protected function buildIcon($icon)
    {
        // Determine image to use.
        if ($icon && file_exists(TL_ROOT . '/' . $icon)) {
            $event = new ResizeImageEvent($icon, 16, 16);

            $this->getEventDispatcher()->dispatch(ContaoEvents::IMAGE_RESIZE, $event);

            return $event->getResultImage();
        }

        return 'system/modules/metamodels/assets/images/icons/metamodels.png';
    }

    /**
     * Handle stand alone integration in the backend.
     *
     * @param IInputScreen $inputScreen The input screen containing the information.
     *
     * @return void
     */
    private function addModuleToBackendMenu($inputScreen)
    {
        $metaModel = $inputScreen->getMetaModel();

        $moduleName = 'metamodel_' . $metaModel->getTableName();

        $tableCaption = $metaModel->getName();

        $icon = $this->buildIcon(ToolboxFile::convertValueToPath($inputScreen->getIcon()));

        $section = $inputScreen->getBackendSection();

        if (!$section) {
            $section = 'metamodels';
        }

        $this->backendMenu[$section][$moduleName] = array
        (
            'tables'   => array($metaModel->getTableName()),
            'icon'     => $icon,
            'callback' => 'MetaModels\BackendIntegration\Module'
        );

        $caption = array($tableCaption);
        foreach ($inputScreen->getBackendCaption() as $languageEntry) {
            if ($languageEntry['langcode'] == 'en') {
                $caption = array($languageEntry['label'], $languageEntry['description']);
            }

            if (!empty($languageEntry['label'])
                && ($languageEntry['langcode'] == $this->viewCombinations->getUser()->language)
            ) {
                $caption = array($languageEntry['label'], $languageEntry['description']);
                break;
            }
        }

        $this->languageStrings['MOD'][$moduleName] = $caption;
    }

    /**
     * Inject all meta models into their corresponding parent tables.
     *
     * @param string[] $parentTables The names of the MetaModels for which input screens are to be added.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    private function addChildTablesToBackendModules($parentTables)
    {
        $localMenu = array_replace_recursive($GLOBALS['BE_MOD'], $this->backendMenu);
        $lastCount = count($parentTables);
        // Loop until all tables are injected or until there was no injection during one run.
        // This is important, as we might have models that are child of another model.
        while ($parentTables) {
            foreach ($parentTables as $parentTable => $childTables) {
                foreach ($localMenu as $groupName => $modules) {
                    foreach ($modules as $moduleName => $moduleConfiguration) {
                        if (
                            isset($moduleConfiguration['tables'])
                            && in_array($parentTable, $moduleConfiguration['tables'])
                        ) {
                            // First put them into our private list.
                            $this->backendMenu[$groupName][$moduleName]['tables'] = array_merge(
                                $localMenu[$groupName][$moduleName]['tables'],
                                $childTables
                            );
                            // And now buffer them in the backend menu copy to be able to resolve.
                            $localMenu[$groupName][$moduleName]['tables'] = array_merge(
                                $localMenu[$groupName][$moduleName]['tables'],
                                $this->backendMenu[$groupName][$moduleName]['tables']
                            );
                            unset($parentTables[$parentTable]);

                            break;
                        }
                    }
                }
            }
            // If the dependencies can not be resolved any further, we give up here.
            if (count($parentTables) == $lastCount) {
                break;
            }
            $lastCount = count($parentTables);
        }
    }

    /**
     * Retrieve the table names from a list of input screens.
     *
     * @param string[] $metaModelNames The names of the MetaModels for which input screens are to be added.
     *
     * @return string[]
     */
    private function getTableNamesFromInputScreens($metaModelNames)
    {
        $parentTables = array();
        foreach ($metaModelNames as $metaModelName) {
            $parentTable = $this->viewCombinations->getParentOf($metaModelName);

            $parentTables[$parentTable][] = $metaModelName;
        }

        return $parentTables;
    }

    /**
     * Inject MetaModels in the backend menu.
     *
     * @return void
     */
    private function resolve()
    {
        foreach ($this->viewCombinations->getStandaloneInputScreens() as $inputScreen) {
            $this->addModuleToBackendMenu($inputScreen);
        }

        $parentTables = $this->getTableNamesFromInputScreens(
            $this->viewCombinations->getParentedInputScreenNames(),
            $this->viewCombinations
        );
        $this->addChildTablesToBackendModules($parentTables);
    }

    /**
     * Set the local data into the GLOBALS config.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function export()
    {
        $GLOBALS['BE_MOD']  = array_merge_recursive($this->backendMenu, $GLOBALS['BE_MOD']);
        $GLOBALS['TL_LANG'] = array_merge_recursive($this->languageStrings, $GLOBALS['TL_LANG']);
    }
}