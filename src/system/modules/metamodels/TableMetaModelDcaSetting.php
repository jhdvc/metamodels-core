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
 * @copyright  CyberSpectrum
 * @license    private
 * @filesource
 */
if (!defined('TL_ROOT'))
{
	die('You cannot access this file directly!');
}

/**
 * This class is used from DCA tl_metamodel_rendersetting for various callbacks.
 *
 * @package	   MetaModels
 * @subpackage Backend
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */

class TableMetaModelDcaSetting extends Backend
{
	/**
	 * @var TableMetaModelRenderSetting
	 */
	protected static $objInstance = null;

	protected $objMetaModel = null;

	protected $objSetting = null;

	/**
	 * Get the static instance.
	 *
	 * @static
	 * @return MetaPalettes
	 */
	public static function getInstance()
	{
		if (self::$objInstance == null) {
			self::$objInstance = new TableMetaModelDcaSetting();
		}
		return self::$objInstance;
	}

	/**
	 * Protected constructor for singleton instance.
	 */
	protected function __construct()
	{
		parent::__construct();
	}

	public function createDataContainer($strTableName)
	{
		if ($strTableName != 'tl_metamodel_dcasetting')
		{
			return;
		}
		$this->objectsFromUrl(null);

		if (!$this->objMetaModel)
		{
			return;
		}

		$objMetaModel=$this->objMetaModel;

		return;
		foreach ($objMetaModel->getAttributes() as $objAttribute)
		{
			$strColName = sprintf('%s_%s', $objAttribute->getColName(), $objAttribute->get('id'));
			$strTypeName = $objAttribute->get('type');
			// GOTCHA: watch out to never ever use anyting numeric in the palettes anywhere else.
			// We have the problem, that DC_Table does not call the load callback when determining the palette.
			$GLOBALS['TL_DCA']['tl_metamodel_rendersetting']['metapalettes'][$objAttribute->get('id') . ' extends ' . $strTypeName] = array();
		}
	}

	protected function objectsFromUrl($objDC)
	{
		// TODO: detect all other ways we might end up here and fetch $objMetaModel accordingly.
		if ($this->objMetaModel)
		{
			return;
		}

		if($objDC && $objDC->getCurrentModel())
		{
			$this->objSetting = $this->Database->prepare('SELECT * FROM tl_metamodel_dca WHERE id=?')->execute($objDC->getCurrentModel()->getProperty('pid'));
			$this->objMetaModel = MetaModelFactory::byId($this->objSetting->pid);
		}
	}

	/**
	 * Prepares a option list with alias => name connection for all attributes.
	 * This is used in the attr_id select box.
	 *
	 * @param DataContainer $objDC the data container calling.
	 *
	 * @return
	 */
	public function getAttributeNames($objDC)
	{
		$this->objectsFromUrl($objDC);
		$arrResult = array();
		if (!$this->objMetaModel)
		{
			return;
		}
		$objMetaModel = $this->objMetaModel;

		foreach ($objMetaModel->getAttributes() as $objAttribute)
		{
			$strTypeName = $objAttribute->get('type');
			$arrResult[$objAttribute->get('id')] = $objAttribute->getName() . ' [' . $strTypeName . ']';
		}

		return $arrResult;
	}

	public function drawSetting($arrRow, $strLabel = '', DataContainer $objDC = null, $imageAttribute='', $blnReturnImage=false, $blnProtected=false)
	{
		switch ($arrRow['dcatype'])
		{
			case 'attribute':
				$objSetting = $this->Database->prepare('SELECT * FROM tl_metamodel_dca WHERE id=?')->execute($arrRow['pid']);
				$objMetaModel = MetaModelFactory::byId($objSetting->pid);

				if (!$objMetaModel)
				{
					return '';
				}

				$objAttribute = $objMetaModel->getAttributeById($arrRow['attr_id']);

				if (!$objAttribute)
				{
					return '';
				}

				$strImage = $GLOBALS['METAMODELS']['attributes'][$objAttribute->get('type')]['image'];

				if (!$strImage || !file_exists(TL_ROOT . '/' . $strImage))
				{
					$strImage = 'system/modules/metamodels/html/filter_default_icon.png';
				}

				$strImage = $this->generateImage($strImage, '', $imageAttribute);

				// Return the image only
				if ($blnReturnImage)
				{
					return $strImage;
				}

				$strLabel = $objAttribute->getName();

				$strReturn = sprintf(
					$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['row'],
					$strImage,
					$strLabel ? $strLabel : $objAttribute->get('type'),
					$objAttribute->get('type')
				);
				return $strReturn;
			break;

			case 'legend':
				$strReturn = sprintf(
					$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['legend_row'],
					$arrRow['legendtitle'] ? $arrRow['legendtitle'] : 'legend',
					$arrRow['legenddefault']
				);
				return $strReturn;
			break;

			default:
			break;
		}
	}

	/**
	 * Fetch the template group for the detail view of the current MetaModel module.
	 *
	 * @param DataContainer $objDC the datacontainer calling this method.
	 *
	 * @return array
	 *
	 */
	public function getTemplates(DataContainer $objDC)
	{
		if (!($this->objMetaModel))
		{
			return array();
		}

		$objAttribute = $this->objMetaModel->getAttributeById($objDC->activeRecord->attr_id);

		if (!$objAttribute)
		{
			return array();
		}

		return $this->getTemplateGroup('mm_attr_' . $objAttribute->get('type') /*, theme id how the heck shall I fetch that one? */);
	}
}

?>