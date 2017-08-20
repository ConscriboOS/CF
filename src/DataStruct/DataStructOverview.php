<?php
namespace CF\DataStruct;

use CF\DataStruct\DataStructCollection;
use CF\DataStruct\Field\DataStructField;
use CF\DataStruct\DataStructManager;
use CF\Template;
use CF\DataStruct\DataStructOverviewCollectionInterface;
use CF\Exception\DeveloperException;
use Exception;

/**
 * User: Dre
 * Bevat de mogelijkheid om tabellen en overzichten te tonen op basis van DataStructCollections
 * Date: 19-8-14
 * Time: 19:35
 */
class DataStructOverview {
	/**
	 * Dre2002 Universal viewer project
	 * Generate on the basis of an Collection and some definitions a pageable, sortable, groupable dynamic table
	 * Version Conscribo financial 2009-08-15
	 * Adapted from Conscribo ledenadministratie 2009-01-10
	 * Adapted from Conscribo overview for Conscribo Collections
	 */

	const OVERVIEW_TEMPLATE = 0;
	const USER_TEMPLATE = 1;
	const MAX_SORT_FIELDS = 3;

	const BASE_TEMPLATE_FILENAME = CONSCRIBO_LIB_ROOT . 'templates/datastructoverview.tpl';

	/**
	 * Definitie van een kolom in de overview
	 * @var array
	 */
	private $cellDefinitions;

	/**
	 * @var DataStructOverviewCollectionInterface
	 */
	private $collection;

	/**
	 * @var bool
	 */
	private $prepared;

	/**
	 * @param string : De section in het template die een lege datacell representeert.
	 */
	private $emptyColumnSection;

	/**
	 * @param string : De standaard section in het template datacell representeert.
	 */
	private $defaultColumnSection;

	private $headerCellSection;

	private $controlHeaderCellSection;

	private $rowSection;

	/**
	 * @param int : entries per page
	 */
	private $rowsPerPage;

	/**
	 * @var int welke pagina zijn we nu?
	 */
	private $page;

	private $overviewName;

	private $outputTemplate;
	private $overviewTemplate;

	private $alternatingRows;

	private $orderFields;
	private $dominantOrder;

	private $fieldsInEditMode;
	private $editModeEnabled;

	private $customColumnOrder;

	/**
	 * Javascript Callback functies die worden aangeroepen nadat een overview wijzigt.
	 * Een callback krijgt een array met currentVisibleRowIds mee, en een totalRowCount
	 * @var String[]
	 */
	private $jsCallback;


	/**
	 * @var string|null Als deze var != NULL dan wordt de selector getoond (@see enableSelector)
	 */
	private $selectorName;
	private $selectionCaptionSingle;
	private $selectionCaptionPlural;

	/**
	 * @var string[] Callback die wordt aangeroepen als er een selector aanwezig is. Deze is verantwoordelijk voor het renderen van de selectierow
	 *                    Params bij aanroep: DatastructOverview, template, <rowId> (Zodat de row onzichtbaar kan worden bij het tekenen
	 *                    Moet terug geven : array('<tr>...</tr>', <nrCols in tr>)
	 */
	private $selectorButtonsCallback;

	/**
	 * @var int[] de in het overview door de selector geselecteerde ids.
	 */
	private $selectedIds;

	/**
	 * @var string, wordt onder de header gerendered als aanwezig
	 */
	public $subHeaderData;

	public $preTableData;
	public $postTableData;
	public $tableClassName;

	/**
	 * @var string de prefix die in elk id field van een tr element wordt gezet.
	 */
	private $trIdPrefix;
	/**
	 * @var string het dataveld die in elk id field van een tr element achter de prefix wordt gezet.
	 */
	private $trIdField;

	/**
	 * @var int[] een cache met de ids die zijn geladen
	 */
	private $dataIds;

	/**
	 * @var array
	 */
	private $groupInfo;

	/**
	 * @see addPreRenderCallback();
	 * @var array
	 */
	private $preRenderCallback;

	/**
	 * Gebruik een formatterfunctie ipv de getValueFormatted functie uit de collection
	 * @var callBack[<fieldName>]
	 */
	private $formatModifier;


	/**
	 * @var mixed[] In deze variabele kan extra informatie voor intern gebruik worden opgeslagen. Hiermee kan een format callback b.v. extra info krijgen uit het originele object.
	 * @use setExtraInfo, getExtraInfo
	 */
	private $extraInfo;

	/**
	 * @var boolean is de huidige rij een even of een odd rij (styling)
	 */
	private $currentRowIsEven;

	/**
	 * @param $overviewName
	 * @return DataStructOverview|null
	 * @throws Exception
	 */
	static function loadOverviewFromSession($overviewName, $internal = true) {

		$descriptor = getSesVar('ds_overview_' . $overviewName, NULL);

		if($descriptor === NULL) {
			return NULL;
		}

		$overview = new DataStructOverview($overviewName, $internal);
		$overview->loadFromSession($descriptor);
		return $overview;
	}

	/**
	 * @param $overviewName
	 * geeft output in javascript die het betreffende overview refreshed als deze al bestaat op de pagina.
	 */
	static function refreshFromAjax($overviewName) {
		$overview = DataStructOverview::loadOverviewFromSession($overviewName);
		$overview->prepare();
		$output = 'overviewContent = '. jsString($overview->render()) .";\n";
		$output.= "renderOverview('". $overviewName ."', overviewContent) ;\n";
		$output.= $overview->renderJavascript();
		return $output;


	}


	/**
	 * Overview genereert lijsten voor o.a. het journaal, relationOverview en entityGroups
	 * @param $overviewName string Naam van de overview
	 * @param $internal     boolean is dit een interne aanroep van het overview
	 */
	function __construct($overviewName, $internal = false) {
		$this->overviewName = $overviewName;
		$this->internalCall = $internal;
		$this->extraInfo = array();
		$this->prepared = false;

		$this->cellDefinitions = array();
		$this->defaultColumnSection = array('tpl' => DataStructOverview::OVERVIEW_TEMPLATE, 'section' => 'DEFAULT_COLUMN');
		$this->headerCellSection = array('tpl' => DataStructOverview::OVERVIEW_TEMPLATE, 'section' => 'HEADER_CELL');
		$this->controlHeaderCellSection = array('tpl' => DataStructOverview::OVERVIEW_TEMPLATE, 'section' => 'CONTROL_HEADER_CELL');
		$this->emptyColumnSection = array('tpl' => DataStructOverview::OVERVIEW_TEMPLATE, 'section' => 'EMPTY_COLUMN');
		$this->rowSection = array('tpl' => DataStructOverview::OVERVIEW_TEMPLATE, 'section' => 'ROW');

		$this->rowsPerPage = 20;
		$this->page = 0;
		$this->orderFields = array();
		$this->dominantOrder = NULL;
		$this->customColumnOrder = NULL;
		$this->outputTemplate = NULL;
		$this->overviewTemplate = NULL;
		$this->alternatingRows = NULL;
		$this->showFoundRows = NULL;
		$this->jsCallback = NULL;
		$this->controlHeaderCellSectionException = array();
		$this->editModeEnabled = false;
		$this->fieldsInEditMode = array();
		$this->subHeaderData = NULL;
		$this->preRenderCallback = NULL;
		$this->formatModifier = array();
		$this->tableClassName = 'modernTable';
		$this->currentRowIsEven = false;


		$this->selectorName = NULL;
		$this->selectionCaptionSingle = 'rij';
		$this->selectionCaptionPlural = 'rijen';
		$this->selectedIds = array();
		$this->selectorButtonsCallback = NULL;

		$this->alternateRows('class="dataTdLight"', 'class="dataTdDark"');
	}

	public function loadFromSession($sessionStr) {

		$session = unserialize($sessionStr);

		$fileName = $session['outputTemplate'];
		if(strpos($fileName, FILE_ROOT) !== 0) {
			if(strpos($fileName, '..') !== false) {
				throw new Exception('intrusion detected');
			}
			$fileName = FILE_ROOT . $fileName;
		}


		if($fileName !== NULL) {
			$tpl = new Template($fileName);
			$this->setTemplate($tpl);
		}
		$this->cellDefinitions = $session['cellDefinitions'];
		$this->emptyColumnSection = $session['emptyColumnSection'];
		$this->defaultColumnSection = $session['defaultColumnSection'];
		$this->headerCellSection = $session['headerCellSection'];
		$this->controlHeaderCellSection = $session['controlHeaderCellSection'];
		if(isset($session['controlHeaderCellSectionException'])) {
			$this->controlHeaderCellSectionException = $session['controlHeaderCellSectionException'];
		}
		if(isset($session['extraInfo'])) {
			$this->extraInfo = $session['extraInfo'];
		}

		$this->rowSection = $session['rowSection'];
		$this->rowsPerPage = $session['rowsPerPage'];

		$collectionClassName = $session['collectionClassName'];

		$this->collection = $collectionClassName::createWithDescriptor($session['collectionDescriptor']);

		$this->page = $session['page'];
		$this->jsCallback = $session['jsCallback'];

		$this->alternatingRows = $session['alternatingRows'];
		$this->orderFields = $session['orderFields'];
		$this->dominantOrder = $session['dominantOrder'];
		$this->tableClassName = $session['tableClassName'];

		if(isset($session['showFoundRows'])) {
			$this->showFoundRows = $session['showFoundRows'];
		}
		if(isset($session['formatModifier'])) {
			$this->formatModifier = $session['formatModifier'];
		}
		$this->customColumnOrder = $session['customColumnOrder'];

		// Kan in opgeslagen weergave ontbreken
		if(isset($session['editModeEnabled'])) {
			$this->editModeEnabled = $session['editModeEnabled'];
			$this->fieldsInEditMode = $session['fieldsInEditMode'];
		} else {
			$this->editModeEnabled = false;
		}
		if(isset($session['subHeaderData'])) {
			$this->subHeaderData = $session['subHeaderData'];
		}
		if(isset($session['preRenderCallback'])) {
			$this->preRenderCallback = $session['preRenderCallback'];
		}

		if(isset($session['selectorName'])) {
			$this->selectorName = $session['selectorName'];
			$this->selectionCaptionSingle = $session['selectionCaptionSingle'];
			$this->selectionCaptionPlural = $session['selectionCaptionPlural'];
			$this->selectedIds = $session['selectedIds'];
			$this->selectorButtonsCallback = $session['selectorButtonsCallback'];
		}

	}

	/**
	 * @param string      $name          fieldname zoals in een datastruct gebruikt
	 * @param string|null $dataFieldName Het dataveld waarop deze is gebaseerd. Standaard hetzelfde als de veldnaam
	 * @return DataStructOverview
	 */
	public function addDataCell($name, $dataFieldName = NULL) {
		if($dataFieldName === NULL) {
			$dataFieldName = $name;
		}
		$this->cellDefinitions[$name] = array('templateSection' => NULL,
											  'basedUpon' => $dataFieldName,
											  'parameters' => NULL,
											  'type' => 'data',
											  'modifications' => array());
		return $this;
	}

	/**
	 * @return DataStructOverview
	 */
	public function clearDataCells() {
		foreach($this->cellDefinitions as $name => $cell) {
			if($cell['type'] == 'data') {
				$this->clearDataCell($name);
			}
		}
		return $this;
	}

	/**
	 * @param $name
	 * @return DataStructOverview
	 */
	public function clearDataCell($name) {
		unset($this->cellDefinitions[$name]);
		return $this;
	}

	/**
	 * Maak een kolom aan welke geen datavelden hoeft te bevatten
	 * @param string   $name
	 * @param string   $basedUpon typeName gebaseerd op de groepering
	 * @param string   $caption   titel
	 * @param int|NULL $position
	 * @return DataStructOverview
	 */
	public function addControlCell($name, $basedUpon, $caption, $position = NULL) {

		$this->cellDefinitions[$name] = array('templateSection' => NULL,
											  'type' => 'control',
											  'modifications' => array(),
											  'basedUpon' => $basedUpon,
											  'caption' => $caption);
		if($position !== NULL) {
			$this->cellDefinitions[$name]['position'] = $position;
		}
		return $this;
	}

	/**
	 * Maak checkboxes voor elke rij, en zorg dat de geselecteerde id's in javascript en op de server beschikbaar worden.
	 * @param        $name
	 * @param string $captionSingle
	 * @param string $captionPlural
	 *                               /**
	 * @param mixed  $buttonCallback Callback die wordt aangeroepen als er een selector aanwezig is. Deze is verantwoordelijk voor het renderen van de selectierow
	 *                               Params bij aanroep: DatastructOverview, template, <rowId> Zodat de row onzichtbaar kan worden bij het tekenen.
	 *                               Moet terug geven : array('<tr>...</tr>', <nrCols in tr>)
	 */

	public function enableSelector($name, $buttonCallBack, $captionSingle = 'rij', $captionPlural = 'rijen') {
		$this->selectorName = $name;
		$this->selectionCaptionSingle = $captionSingle;
		$this->selectionCaptionPlural = $captionPlural;
		$this->selectorButtonsCallback = $buttonCallBack;

		if($this->selectorName !== NULL) {
			$this->registerJsRenderCallBack($name . 'Selector.overviewPageChanged');
		}
	}

	public function getSelectorName() {
		return $this->selectorName;
	}

	public function getSelectedIds() {
		return $this->selectedIds;
	}

	/**
	 * Wijzig de huidige lijst met selectedIds (voor init en ajaxcalls)
	 * @param $includeList
	 * @param $excludeList
	 */
	public function recordNewSelection($includeList, $excludeList) {

		foreach($includeList as $id) {
			$this->selectedIds[$id] = $id;
		}
		foreach($excludeList as $id) {
			unset($this->selectedIds[$id]);
		}
	}

	/**
	 * Verwijder de selectie
	 */
	public function clearSelection() {
		$this->selectedIds = array();
	}

	/**
	 * Selecteer alle rijen.
	 */
	public function recordNewSelectionAll() {

		$selCollection = clone $this->collection;
		$selCollection->setLimit(NULL);
		$selCollection->setOffset(NULL);

		$ids = $selCollection->getIds();

		$this->selectedIds = array_combine($ids, $ids);
	}


	/**
	 * @param DataStructOverviewCollectionInterface $collection
	 * @return DataStructOverview
	 */
	public function setDataCollection(DataStructOverviewCollectionInterface $collection) {
		$this->collection = $collection;
		return $this;
	}

	/**
	 * @return DataStructCollection
	 */
	public function getDataCollection() {
		return $this->collection;
	}

	/**
	 * @param null $rowsPerPage
	 * @return DataStructOverview
	 */
	public function setRowsPerPage($rowsPerPage = NULL) {
		$this->rowsPerPage = $rowsPerPage;
		return $this;
	}

	/**
	 * @param $pageNr
	 * @return DataStructOverview
	 */
	public function setPage($pageNr) {
		$this->page = $pageNr;
		return $this;
	}

	/**
	 * Zorg ervoor dat elke TR een id krijgt.
	 * @param $prefix         wat wordt voor de tr in de prefix gezet?
	 * @param $dataFieldName  welk dataveld wordt gebruikt
	 * @return DataStructOverview
	 */
	public function setTrIdField($prefix, $dataFieldName) {
		$this->trIdPrefix = $prefix;
		$this->trIdField = $dataFieldName;
		return $this;
	}


	public function prepare() {
		if(!isset($this->collection)) {
			throw new Exception('Collection object not set');
		}

		if($this->rowsPerPage !== NULL) {
			// paginering staat aan
			$this->collection->calculateNumberOfRows(true);
			$offset = NULL;
			if($this->page > 0 && $this->page < PHP_INT_MAX) {
				$offset = intval($this->page * intval($this->rowsPerPage));
			}
			$this->collection->setLimit($this->rowsPerPage);
			$this->collection->setOffset($offset);
		} else {
			$this->collection->setLimit(NULL);
			$this->collection->setOffset(NULL);
		}


		// we laden alle kolommen maar om troep tegen te gaan kijken we eerst of we ze wel uit deze collectie kunnen gebruiken
		$loadFields = array();
		foreach($this->cellDefinitions as $name => $val) {
			if($val['type'] == 'data') {
				if(!DataStructManager::gI()->fieldExists($this->collection->getBaseClassName(), $val['basedUpon'])) {
					// Het veld bestaat niet langer. Waarschijnlijk is deze verwijderd door de gebruiker. Negeren.
					unset($this->cellDefinitions[$name]);
					continue;
				}
				$loadFields[] = $name;
			}
		}

		$this->collection->clearOrder();

		if(isset($this->orderFields) && is_array($this->orderFields)) {
			foreach($this->orderFields as $field => $order) {
				$this->collection->addOrder($field, $order);
			}
		}

		$this->dataIds = $this->collection->getIds();
		$this->prepared = true;
	}

	/**
	 * Geef aan of er een totaal aantal rijen getoond moet worden
	 * @param bool $show
	 * @return DataStructOverview
	 */
	public function showFoundRows($show) {
		$this->showFoundRows = $show;
		return $this;
	}

	/**
	 * Geeft aan of er een sortering is aangegeven in deze overview
	 * @return bool
	 */
	public function getOrderIsSet() {
		if(count($this->orderFields) > 0) {
			return true;
		}
		return false;
	}

	/**
	 * @param      $fieldName
	 * @param      $order
	 * @param bool $dominant zegt of het de eerste order moet zijn (dus iemand heeft op een order ding geklikt en verwacht dat deze sortering leidend is)
	 * @return DataStructOverview
	 */
	public function addOrder($fieldName, $order, $dominant = false) {
		if(!isset($this->collection)) {
			throw new DeveloperException('Eerst moet de collection geset zijn, voor een order mag worden toegevoegd', EXCEPTION_PRECONDITIONS_NOT_MET);
		}
		if(!DataStructManager::gI()->fieldExists($this->collection->getBaseClassName(), $fieldName)) {
			return;
		}

		if(!in_array($order, array(DataStructField::ORDER_ASC, DataStructField::ORDER_DESC))) {
			return;
		}

		if(!isset($this->orderFields)) {
			$this->orderFields = array();
		}

		if(!$dominant) {
			if(count($this->orderFields) <= DataStructOverview::MAX_SORT_FIELDS) {
				$this->orderFields[$fieldName] = $order;
			}
		} else {
			$this->dominantOrder = $fieldName;
			unset($this->orderFields[$fieldName]);
			$this->orderFields = array($fieldName => $order) + $this->orderFields;
			if(count($this->orderFields) > DataStructOverview::MAX_SORT_FIELDS) {
				array_pop($this->orderFields);
			}
		}
		return $this;
	}

	/**
	 * Op welke volgorde zien we kolommen? array('asd','adsfs','dsgdf');
	 * @param array $columnOrder
	 * @return DataStructOverview
	 */
	public function setColumnOrder($columnOrder) {
		if(!is_array($columnOrder)) {
			throw new Exception('Columnorder should be an array in dataStruct '. $this->overviewName);
		}
		$this->customColumnOrder = array_flip($columnOrder);
		return $this;
	}

	/**
	 * @param Template $template
	 * @return DataStructOverview
	 */
	public function setTemplate(Template $template) {
		$this->outputTemplate = $template;
		return $this;
	}

	/**
	 * @param $fieldName
	 * @param $groupByField
	 * @throws Exception
	 * @return DataStructOverview
	 */
	public function groupRowsByField($fieldName, $groupByField) {
		if(!isset($this->cellDefinitions[$fieldName])) {
			throw new Exception('Unknown field to group by: ' . $fieldName);
		}
		// het groupByField hoeft niet in de overview voor te komen, als deze maar in de collection voorkomt.
		$this->cellDefinitions[$fieldName]['groupByField'] = $groupByField;
		return $this;
	}

	/**
	 * Dit is dezelfde functie als GroupRowsByField met as verschil dat er alleen wordt gegroepeerd als de waarden gelijk zijn binnen het gegroepeerde veld
	 * @param $fieldName
	 * @param $groupByField
	 * @throws Exception
	 * @return DataStructOverview
	 */
	public function groupRowsByFieldIfEqual($fieldName, $groupByField) {
		if (!isset($this->cellDefinitions[$fieldName])){
			throw new Exception('Unknown field to group by: '. $fieldName);
		}
		// het groupByField hoeft niet in de overview voor te komen, als deze maar in de collection voorkomt.
		$this->cellDefinitions[$fieldName]['groupByFieldIfEqual'] = $groupByField;
		return $this;
	}

	/**
	 * @param     $section
	 * @param int $templateType
	 * @return DataStructOverview
	 */
	public function setDefaultTemplateColumnSection($section, $templateType = DataStructOverview::USER_TEMPLATE) {
		$this->defaultColumnSection = array('tpl' => $templateType, 'section' => $section);
		return $this;
	}

	/**
	 * Bestaat de cell?
	 * @param $name
	 * @return bool
	 */
	public function templateColumnExists($name) {
		return (isset($this->cellDefinitions[$name]));
	}

	/**
	 * @param     $name
	 * @param     $section
	 * @param int $templateType
	 * @throws Exception
	 * @return DataStructOverview
	 */
	public function setTemplateColumnSection($name, $section, $templateType = DataStructOverview::USER_TEMPLATE) {
		if(!isset($this->cellDefinitions[$name])) {
			throw new Exception('Unknown field in templateColumnSection: ' . $name);

		}
		$this->cellDefinitions[$name]['templateSection'] = array('tpl' => $templateType, 'section' => $section);
		return $this;
	}


	/**
	 * @param string[] $names   array(leadFieldname => fieldValueSection in template, fieldname2 =>);
	 * @param string   $section tells in which section in the USER_TEMPLATE the cell should be written
	 *                          example: $o->setCellCombination(array('settled' => 'VALUE_SETTLED', 'settled_enabled' => 'SETTLED_ENABLED'), 'SETTLED_COLUMN');
	 * @throws Exception
	 * @return DataStructOverview
	 */
	function setCellCombination($names, $section) {

		$leadName = '';
		foreach($names as $fieldName => $valueSection) {
			// leadInfo vullen:
			if(empty($leadName)) {
				$leadName = $fieldName;
				$this->setTemplateColumnSection($leadName, $section, DataStructOverview::USER_TEMPLATE);
				$this->cellDefinitions[$leadName]['parameters']['isCombinedSection'] = true;
				$this->cellDefinitions[$leadName]['combinedSections'][$fieldName] = $valueSection;
			} else {
				// niet leadCell:
				$this->cellDefinitions[$leadName]['combinedSections'][$fieldName] = $valueSection;
			}
		}
		return $this;
	}

	/**
	 * @param     $headerCellSection
	 * @param int $templateType
	 * @return DataStructOverview
	 */
	public function setTemplateHeaderCellSection($headerCellSection, $templateType = DataStructOverview::USER_TEMPLATE) {
		$this->headerCellSection = array('tpl' => $templateType, 'section' => $headerCellSection);
		return $this;
	}

	/**
	 * @param     $emptyCellSection
	 * @param int $templateType
	 * @return DataStructOverview
	 */
	public function setTemplateEmptyCellSection($emptyCellSection, $templateType = DataStructOverview::USER_TEMPLATE) {
		$this->emptyColumnSection = array('tpl' => $templateType, 'section' => $emptyCellSection);
		return $this;
	}

	/**
	 * @param     $section
	 * @param int $templateType
	 * @return DataStructOverview
	 */
	public function setTemplateControlHeaderCellSection($section, $templateType = DataStructOverview::USER_TEMPLATE) {
		$this->controlHeaderCellSection = array('tpl' => $templateType, 'section' => $section);
		return $this;
	}

	/**
	 * @param     $fieldName
	 * @param     $section
	 * @param int $templateType
	 * @return DataStructOverview
	 */
	public function setTemplateControlHeaderCellSectionForField($fieldName, $section, $templateType = DataStructOverview::USER_TEMPLATE) {
		$this->controlHeaderCellSectionException[$fieldName] = array('tpl' => $templateType, 'section' => $section);
		return $this;
	}


	/**
	 * Gebruik voor deze fieldname een valueFormatter callback
	 * De callback dient er uit te zien als:
	 * function <callback>(DataStruct $obj, Template $tpl){
	 *        $tpl->addData('VALUE', $obj->getBla());
	 * }
	 * @param          $fieldName
	 * @param callback $formatter
	 * @return DataStructOverview
	 */
	public function modifyValueFormatter($fieldName, $formatter) {
		$this->formatModifier[$fieldName] = $formatter;
		return $this;
	}

	/**
	 * Geef een callback op om de inhoud van de rij verder vorm te geven. De defaultformatter wordt eerst aangeroepen (alle fields e.d.) daarna wordt deze callback aangeroepen.
	 * @param $formatter callback , void functie met argumenten ($valueObject, $template, $overviewObject)
	 *
	 * @return DataStructOverview
	 */
	public function addRowFormatter($formatter) {
		$this->formatModifier['__row'] = $formatter;
		return $this;
	}


	/**
	 * @param     $rowSection
	 * @param int $templateType
	 * @return DataStructOverview
	 */
	public function setRowSection($rowSection, $templateType = DataStructOverview::USER_TEMPLATE) {
		$this->rowSection = array('tpl' => $templateType, 'section' => $rowSection);
		return $this;
	}

	/**
	 * @param $evenRowAttachment
	 * @param $oddRowAttachment
	 * @return DataStructOverview
	 */
	public function alternateRows($evenRowAttachment, $oddRowAttachment) {
		$this->alternatingRows = array('even' => $evenRowAttachment, 'odd' => $oddRowAttachment);
		return $this;
	}


	/**
	 * Geeft een andere class aan de overviewtable
	 * @param String $className de css class van de table indien afwijkend.
	 */
	public function setTableClassName($className) {
		$this->tableClassName = $className;
	}

	/**
	 * @param $editMode
	 * @return DataStructOverview
	 */
	public function enableEditMode($editMode) {
		$this->editModeEnabled = $editMode;
		return $this;
	}

	/**
	 * @param $fieldName
	 * @param $editMode
	 * @return DataStructOverview
	 */
	public function toggleFieldEditMode($fieldName, $editMode) {
		if($this->editModeEnabled) {
			if(!$editMode) {
				unset($this->fieldsInEditMode[$fieldName]);
			} else {
				if($this->cellIsEditable($fieldName)) {
					$this->fieldsInEditMode[$fieldName] = $fieldName;
				}
			}
		}
		return $this;
	}

	/**
	 * Voer een callbackfunctie uit voor het renderen van de overview. Dit zorgt ervoor dat dingen nog kunnen worden geupdate!
	 * De callback wordt met twee argumenten aangeroepen: <callback>(DatastructOverview $overview, $arguments)
	 * @param string|array $callBack
	 * @param mixed        $arguments
	 * @return DataStructOverview
	 */
	public function addPreRenderCallback($callBack, $arguments = NULL) {
		$this->preRenderCallback = array('callBack' => $callBack,
										 'arguments' => $arguments);
		return $this;
	}

	/**
	 * Render de overview
	 * @return string
	 * @throws Exception
	 */
	public function render() {

		if(!$this->prepared) {
			throw new Exception('Cannot render when not prepared!');
		}

		if(isset($this->preRenderCallback)) {
			// voer eventueel de callback uit:
			call_user_func($this->preRenderCallback['callBack'], $this, $this->preRenderCallback['arguments']);
		}

		if($this->outputTemplate === NULL) {
			throw new Exception('Template not set');
		}

		if(defined('CF_DATASTRUCTOVERVIEW_CUSTOM_TEMPLATE_PATH')) {
			$templates = array(DataStructOverview::OVERVIEW_TEMPLATE => new Template(CF_DATASTRUCTOVERVIEW_CUSTOM_TEMPLATE_PATH),
							   DataStructOverview::USER_TEMPLATE => $this->outputTemplate);
		} else {
			// te gebruiken templates:
			$templates = array(DataStructOverview::OVERVIEW_TEMPLATE => new Template(self::BASE_TEMPLATE_FILENAME),
								   DataStructOverview::USER_TEMPLATE => $this->outputTemplate);
			}

		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('OVERVIEW_NAME', $this->overviewName, true);
		$templates[DataStructOverview::USER_TEMPLATE]->addData('OVERVIEW_NAME', $this->overviewName, true);

		// order cell definitions:
		$this->applyColumnOrder();

		// controleren of er een dominant order bestaat, anders pak de eerste order:
		if($this->dominantOrder === NULL) {
			$__order = array_keys($this->orderFields);
			$this->dominantOrder = reset($__order);
		}

		if($this->selectorName !== NULL) {
			$this->renderSelectorHeader($templates);
		}
		// headercells vullen
		$headerCells = '';

		if($this->selectorName !== NULL) {
			$headerCells .= $this->renderSelectorHeaderCell($templates);
		}
		foreach($this->cellDefinitions as $name => $cellDefinition) {
			$headerCells .= $this->renderHeaderCell($name, $templates);
		}
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('HEADER_CELLS', $headerCells, true);

		if(isset($this->subHeaderData)) {
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('SUB_HEADER_CELLS', $this->subHeaderData);
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->parseOnSpot('SUB_HEADER_ROW');
		}
		if(isset($this->preTableData)) {
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('PRE_TABLE', $this->preTableData);
		}
		if(isset($this->postTableData)) {
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('POST_TABLE', $this->postTableData);
		}

		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('TABLE_CLASS', $this->tableClassName);

		// kolommen vullen:

		$rows = '';

		$groupingRowsLeft = 0;
		$this->prepareGrouping();
		$this->currentRowIsEven = false;
		foreach($this->dataIds as $id) {

			if($groupingRowsLeft == 0) {
				$groupingRowsLeft = $this->getGroupingRowsLeft($id);
				// rowspan als er gegroepeerd moet worden op die kolom
				$totalRowSpan = $groupingRowsLeft;
				$this->currentRowIsEven = !$this->currentRowIsEven;
			} else {
				$totalRowSpan = 1;
			}

			$groupingRowsLeft--;
			$rows .= $this->renderDataRow($id, $templates, $totalRowSpan, $this->currentRowIsEven);
		}

		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('ROWS', $rows, true);

		// datails in javascript array plaatsen

		// paginering:
		if(!is_null($this->rowsPerPage)) {
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('PAGER', $this->renderPager($templates[DataStructOverview::OVERVIEW_TEMPLATE]));
		}

		$this->storeInSession();

		if(!$this->internalCall) {
			\CF\Runtime\Runtime::gI()->addJavascriptFile('dataStructOverview.js');
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('TABLE', $templates[DataStructOverview::OVERVIEW_TEMPLATE]->write_block('OVERVIEW_TABLE'), true);

			return $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('OVERVIEW');

		} else {

			// dit is een ajax/interne aanroep:
			return $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('OVERVIEW_TABLE');
		}

	}

	private function applyColumnOrder() {
		if(!isset($this->customColumnOrder)) {
			$order = $this->createDefaultColumnOrder();
		} else {
			$order = $this->customColumnOrder;
		}

		// controlCells opnemen in de sortering:
		foreach($this->cellDefinitions as $fieldName => $def) {
			if($def['type'] == 'control') {
				if(isset($def['position'])) {
					$order[$fieldName] = $def['position'];
				}
			}
		}
		asort($order);
		$this->cellDefinitions = align($this->cellDefinitions, $order);
	}

	private function renderSelectorHeader($templates) {
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('SELECTOR_NAME', $this->selectorName, true);
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('PLURAL_CAPTION', $this->selectionCaptionPlural, true);

		list($rowHTML, $nrCols) = call_user_func($this->selectorButtonsCallback, $this, $templates[DataStructOverview::USER_TEMPLATE], 'select_tr2_' . $this->selectorName);
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('EXTRA_SELECTOR_ROWS', $rowHTML);
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('COLSPAN', $nrCols);
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeAndConcat('SELECTOR_HEADER', 'SELECTOR_HEADER');


	}

	private function renderSelectorHeaderCell($templates) {
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('SELECTOR_NAME', $this->selectorName, true);
		return $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('SELECTOR_HEADER_CELL');
	}


	/**
	 * render de kolomkop van de een kolom
	 * @param $fieldName
	 * @param $templates array met gebruikte templates
	 * @return string
	 */
	private function renderHeaderCell($fieldName, $templates) {
		$cellHeaderStr = '';

		$cellDefinition = $this->cellDefinitions[$fieldName];
		$dataFieldName = $cellDefinition['basedUpon'];

		if($cellDefinition['type'] == 'data') {
			$section = $this->headerCellSection;
			if(isset($this->controlHeaderCellSectionException[$fieldName])) {
				$section = $this->controlHeaderCellSectionException[$fieldName];
			}

			$dataStructField = DataStructManager::gI()->getFieldFromClassName($this->collection->getBaseClassName(), $dataFieldName);
			$templates[$section['tpl']]->addData('CELL_NAME', $fieldName, true);
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('CELL_NAME', $fieldName, true);

			// Render het pijltje in deze column
			if(!isset($this->orderFields[$fieldName]) || $this->dominantOrder != $fieldName) {
				$order = DataStructField::ORDER_ASC;
			} else {
				$order = ($this->orderFields[$fieldName] == DataStructField::ORDER_ASC) ? DataStructField::ORDER_DESC : DataStructField::ORDER_ASC;
			}

			$templates[$section['tpl']]->addData('NEW_ORDER', $order, true);

			if($this->dominantOrder == $fieldName) {
				$currentOrder = ($order == DataStructField::ORDER_ASC) ? DataStructField::ORDER_DESC : DataStructField::ORDER_ASC;

				$templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeAndConcat('ORDER', 'ORDER_ATTACHMENT_'. strtoupper($currentOrder));

				//$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('ORDER','order_'.$currentOrder);
				//$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('ORDER', ($currentOrder == DataStructField::ORDER_ASC) ? '' : '-alt');
				$templates[$section['tpl']]->addData('ORDER_ATTACHMENT', $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('ORDER_ATTACHMENT'));
			} else {
				$templates[$section['tpl']]->clear_var('ORDER_ATTACHMENT');
			}

			$templates[$section['tpl']]->addHTMLData('LABEL', ucfirst($dataStructField->getLabel()));
			/**if($this->editModeEnabled) {
				if($this->cellIsEditable($fieldName)) {
					if(in_array($fieldName, $this->fieldsInEditMode)) {
						if($dataStructField->getFieldType() == 'DateField') {
							$templates[$section['tpl']]->addData('EXTRA_LABEL', '<small> <i>(dd-mm-jjjj)</i></small>');
						}
						$templates[$section['tpl']]->addData('SWITCH_EDIT_MODE', $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('CANCEL_EDIT_MODE'));
					} else {
						$templates[$section['tpl']]->addData('SWITCH_EDIT_MODE', $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('ENABLE_EDIT_MODE'));
					}
				}
			}*/
			$cellHeaderStr .= $templates[$section['tpl']]->writeSection($section['section']);

			/**if($this->editModeEnabled) {
				if($this->cellIsEditable($fieldName)) {
					$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('FIELDTYPE', $this->collection->getFieldType($fieldName));
					$templates[DataStructOverview::OVERVIEW_TEMPLATE]->parseOnSpot('FIELDTYPE');
				}
			}*/

		} elseif($cellDefinition['type'] == 'control') {
			$section = $this->controlHeaderCellSection;

			if(isset($this->controlHeaderCellSectionException[$fieldName])) {
				$section = $this->controlHeaderCellSectionException[$fieldName];
			}
			$templates[$section['tpl']]->addData('CELL_NAME', $fieldName, true);
			$templates[$section['tpl']]->addHTMLData('LABEL', $cellDefinition['caption'], true);
			$cellHeaderStr .= $templates[$section['tpl']]->writeSection($section['section']);
		}

		return $cellHeaderStr;
	}

	private function renderDataRow($id, $templates, $totalRowSpan, $even) {
		$skipFields = $this->getGroupingSkipFields($id);

		$columns = '';
		// In elke rij wordt altijd een id geset:
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('ID', $id, true);
		$templates[DataStructOverview::USER_TEMPLATE]->addData('ID', $id, true);

		/**
		 * @var DataStructField[] $localFieldDefs
		 */
		$localFieldDefs = array();

		/**
		 * De eerste kolom is een selectiekolom
		 */
		if($this->selectorName !== NULL) {
			$columns .= $this->renderSelectionColumn($id, $templates, $totalRowSpan, $even);
		}

		foreach($this->cellDefinitions as $name => $cellDefinition) {
			if(isset($skipFields[$name])) {
				continue;
			}
			if(!isset($localFieldDefs[$name])) {
				if($cellDefinition['type'] == 'control') {
					$currentDataFieldName = $cellDefinition['basedUpon'];
					$localFieldDefs[$currentDataFieldName] = DataStructManager::gI()->getFieldFromClassName($this->collection->getBaseClassName(), $currentDataFieldName);
				} else {
					$currentDataFieldName = $cellDefinition['basedUpon'];
					$localFieldDefs[$name] = DataStructManager::gI()->getFieldFromClassName($this->collection->getBaseClassName(), $currentDataFieldName);
				}
			}

			//$templates[$this->headerCellSection['tpl']]->addData('CELL_NAME', $name, true);
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('CELL_NAME', $name, true);
			$rowSpan = $totalRowSpan;

			if(!isset($cellDefinition['groupByField']) && !isset($cellDefinition['groupByFieldIfEqual'])) {
				$rowSpan = 1;
			}
			if(isset($this->brokenGrouping[$name][$id]) && $this->brokenGrouping[$name][$id]) {
				// Deze grouping is verbroken
				$rowSpan = 1;
			}

			// gecombineerde cell:
			/*if(isset($cellDefinition['parameters']['isCombinedSection']) && $cellDefinition['parameters']['isCombinedSection']) {
				$sectionTpl = $templates[DataStructOverview::USER_TEMPLATE];
				$sectionName = $cellDefinition['templateSection']['section'];

				foreach($cellDefinition['combinedSections'] as $currentDataFieldName => $templateSection){

					if ($cellDefinition['type'] == 'control'){
						$currentDataFieldName = $cellDefinition['basedUpon'];
					}
					if(isset($this->formatModifier[$name])) {
						// Er is een decorator ingesteld, gebruik deze:
						$content = call_user_func($this->formatModifier[$name], $this->collection->getValue($currentDataFieldName, $id));
					} else {
						// gebruik de default uit het datastructField
						$content = $this->collection->getValueFormatted($currentDataFieldName, $id, Collection::FORMAT_HTML);
					}

					$sectionTpl->addData($templateSection, $content, true);


				}

				if(!is_null($this->alternatingRows)){
					$sectionTpl->addData('EVEN_ODD_ATTACHMENT', $this->alternatingRows[$even ? 'even' : 'odd'], true);
				}

				$sectionTpl->addData('ROW_SPAN', $rowSpan, true);
				$columns .= $sectionTpl->write_block($sectionName);

			} else {*/
			//singe cell

			// control cells zijn gebaseerd op echte data, dus deze echte data gebruiken:

			$currentDataFieldName = $cellDefinition['basedUpon'];

			// templatesection bepalen:

			if(!$localFieldDefs[$currentDataFieldName]->isEmptyValue($this->collection->getValue($currentDataFieldName, $id)) || isset($this->formatModifier[$name])) {

				if(!is_null($cellDefinition['templateSection'])) {
					$sectionName = $cellDefinition['templateSection']['section'];
					$sectionTpl = $templates[$cellDefinition['templateSection']['tpl']];
				} else {
					$sectionName = $this->defaultColumnSection['section'];
					$sectionTpl = $templates[$this->defaultColumnSection['tpl']];
				}

				$sectionTpl->addData('ROW_SPAN', $rowSpan, true);

				if(!is_null($this->alternatingRows)) {
					$sectionTpl->add_var('EVEN_ODD_ATTACHMENT', $this->alternatingRows[$even ? 'even' : 'odd']);
				}

				if(isset($this->formatModifier[$name])) {
					// Er is een decorator ingesteld, gebruik deze:
					call_user_func($this->formatModifier[$name], $this->collection->offsetGet($id), $sectionTpl, $name, $this);
				} else {
					// gebruik de default uit het datastructField
					$content = $this->collection->getValueFormatted($currentDataFieldName, $id, DataStructField::VALUE_FORMAT_HTML);
					$sectionTpl->addData('VALUE', $content);
				}


				$columns .= $sectionTpl->writeSection($sectionName);
			} else {
				// Empty column
				if(!is_null($cellDefinition['templateSection'])) {
					// Indien er een custom cell is, dan moet deze het oplossen
					$sectionName = $cellDefinition['templateSection']['section'];
					$sectionTpl = $templates[$cellDefinition['templateSection']['tpl']];
					$sectionTpl->addData('VALUE', '&nbsp;');
					$columns .= $sectionTpl->writeSection($sectionName);
				} else {
					// Anders gebruiken we de standaard cell
					$templates[$this->emptyColumnSection['tpl']]->addData('ROW_SPAN', $rowSpan, true);
					if(!is_null($this->alternatingRows)) {
						$templates[$this->emptyColumnSection['tpl']]->addData('EVEN_ODD_ATTACHMENT', $this->alternatingRows[$even ? 'even' : 'odd'], true);
					}
					$columns .= $templates[$this->emptyColumnSection['tpl']]->writeSection($this->emptyColumnSection['section']);
				}

			}
			//}
		}
		$templates[$this->rowSection['tpl']]->addData('COLUMNS', $columns, true);

		// trIdRowFields:

		if(isset($this->trIdPrefix)) {
			$content = $this->collection->getValueFormatted($this->trIdField, $id, DataStructField::VALUE_FORMAT_HTML);
			$templates[$this->rowSection['tpl']]->addData('TR_ID_FIELD', 'id="' . $this->trIdPrefix . $content . '"');
		}

		if(isset($this->formatModifier['__row'])) {
			call_user_func($this->formatModifier['__row'], $this->collection->offsetGet($id), $templates[$this->rowSection['tpl']], $this);
		}

		return $templates[$this->rowSection['tpl']]->writeSection($this->rowSection['section'], true);
	}


	public function renderSelectionColumn($id, $templates, $rowSpan, $even) {

		/**
		 * @var Template[] $templates
		 */
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('SELECTOR_NAME', $this->selectorName);
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('ROW_SPAN', $rowSpan);
		if(!is_null($this->alternatingRows)) {
			$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('EVEN_ODD_ATTACHMENT', $this->alternatingRows[$even ? 'even' : 'odd'], true);
		}
		$templates[DataStructOverview::OVERVIEW_TEMPLATE]->addData('ID', $id);

		return $templates[DataStructOverview::OVERVIEW_TEMPLATE]->writeSection('SELECTOR_COLUMN');

	}

	public function createDefaultColumnOrder() {
		return array_flip(DataStructManager::gI()->getExistingFieldNames($this->collection->getBaseClassName()));
	}

	/**
	 * Registreer een JS callback welke wordt uitgevoerd bij het wijzigen van elke overviewpagina
	 * de callback krijgt als argument een array met visibleRowIds mee, en een totalRowCount.
	 * @param $callBackName
	 * @return DataStructOverview
	 */
	public function registerJsRenderCallBack($callBackName) {
		if(!isset($this->jsCallback)) {
			$this->jsCallback = array();
		}
		$this->jsCallback[] = $callBackName;
		return $this;
	}

	/**
	 * method die zowel in normale als in de ajaxcall wordt gebruikt om de overview van juiste data te voorzien
	 * @return string
	 */
	public function renderJavascript() {
		$output = '';

		if($this->selectorName !== NULL) {
			$output .= 'var ' . $this->selectorName . 'Selector = new OverviewSelection;' . "\n";
			$output .= $this->selectorName . 'Selector.create(' . jsString($this->selectorName) . ', ' . jsString($this->selectionCaptionSingle) . ', ' . jsString($this->selectionCaptionPlural) . ', ' . jsString($this->overviewName) . ');' . "\n";
		}

		if($this->jsCallback !== NULL && count($this->jsCallback) > 0) {
			// vul array met alle ids
			if(count($this->dataIds) == 0) {
				$output .= 'var overviewRowIds = [];' . "\n";
			} else {
				$output .= 'var overviewRowIds = [' . implode(',', $this->dataIds) . '];' . "\n";
			}

			$selIds = array();
			if(count($this->selectedIds) == 0) {
				$output .= 'var selectedIds = {};' . "\n";
			} else {
				foreach($this->selectedIds as $id) {
					$selIds['i_' . $id] = $id;
				}
				$output .= 'var selectedIds = ' . json_encode($selIds) . ';' . "\n";
			}


			// roep callback met array aan
			foreach($this->jsCallback as $callBack) {
				$output .= $callBack . '(overviewRowIds, selectedIds, ' . jsInt(count($selIds)) . ',' . intval($this->getNrTotalRows()) . ');' . "\n";
			}

		}
		return $output;
	}


	private function prepareGrouping() {

		$rows = array_reverse($this->dataIds);

		$lastRow = NULL;
		$rowCounter = 0;
		$this->groupInfo = array();
		$rowGrouped = false;
		$currentGroups = array();

		// Geeft per fieldName aan welke rows toch niet zijn gegroepeerd.
		$this->brokenGrouping = array();

		foreach($rows as $key => $row) {

			if (!is_null($lastRow)){

				foreach($this->cellDefinitions as $fieldName => $def){

					if(isset($def['groupByField'])) {
						// er is een groupering, komen waardes overeen?
						if ($this->collection->getValue($def['groupByField'], $lastRow) == $this->collection->getValue($def['groupByField'], $row)) {
							// waardes van groepering komen overeen:
							$rowGrouped = true;
							$this->groupInfo[$lastRow]['skipFields'][$fieldName] = $fieldName;
						}
					}


					// Standaard maken we dezelfde groep aan als in een groupByField, maar op het moment dat de groepering klopt, maar de waarden nog verschillen, dan wordt
					// deze groep weer verbroken. We registreren dat door de al geskipte velden weer te unsetten, en in $this->brokenGrouping te zeggen welke uitzonderingsrijen er een rowspan van 1 krijgen.
					if(isset($def['groupByFieldIfEqual'])) {
						if($this->collection->getValue($def['groupByFieldIfEqual'], $lastRow) == $this->collection->getValue($def['groupByFieldIfEqual'], $row)) {

							if(!isset($currentGroups[$fieldName]) || $currentGroups[$fieldName]['groupByValue'] != $this->collection->getValue($def['groupByFieldIfEqual'], $lastRow)) {
								$currentGroups[$fieldName] = array('groupByValue' => $this->collection->getValue($def['groupByFieldIfEqual'], $lastRow),
																   'rowIds' => array($lastRow),
																   'groupBroken' => false,
																	'valueEquals' => $this->collection->getValue($fieldName, $lastRow));
							}

							if($currentGroups[$fieldName]['groupBroken']) {
								$this->brokenGrouping[$fieldName][$row] = true;
								continue;
							}

							if($this->collection->getValue($fieldName, $row) == $currentGroups[$fieldName]['valueEquals']) {
								// waardes van groepering komen overeen:
								$this->groupInfo[$lastRow]['skipFields'][$fieldName] = $fieldName;
								$currentGroups['rowIds'][] = $row;
								$rowGrouped = true;
							} else {
								$currentGroups[$fieldName]['groupBroken'] = true;

								$currentGroups[$fieldName]['rowIds'][]= $row;
								foreach($currentGroups[$fieldName]['rowIds'] as $_rowId) {
									// waardes van groepering komen (toch) niet overeen:
									unset($this->groupInfo[$_rowId]['skipFields'][$fieldName]);
									$this->brokenGrouping[$fieldName][$_rowId] = true;
								}
							}
						}
					}
				}
			}

			if($rowGrouped) {
				$rowCounter++;
			} else {
				$rowCounter = 1;
			}

			$this->groupInfo[$row] = array('rows' => $rowCounter, 'skipFields' => array());
			$lastRow = $row;
			$rowGrouped = false;
		}
	}

	private function getGroupingRowsLeft($key) {
		return $this->groupInfo[$key]['rows'];
	}

	private function getGroupingSkipFields($key) {
		return $this->groupInfo[$key]['skipFields'];
	}

	public function getNrTotalRows() {
		return $this->collection->getNumRows();
	}

	/**
	 * Wat is de totale colspan van het overview?
	 * @return int
	 */
	public function getNrCols() {
		return count($this->cellDefinitions) + (($this->selectorName !== NULL) ? 1 : 0);
	}

	/**
	 * Geef terug of de huidige rij een even of een odd rij is.
	 * @return bool
	 */
	public function getCurrentRowIsEven() {
		return $this->currentRowIsEven;
	}

	private function renderPager($template) {

		$out = '';
		$nrRows = $this->collection->getNumRows();
		// geen paginering nodig:
		if($this->rowsPerPage >= $nrRows) {

			if(isset($this->showFoundRows) && $this->showFoundRows) {
				if($nrRows == 0) {
					$template->addData('NR_ROWS', '(Geen resultaten)');
				} elseif($nrRows == 1) {
					$template->addData('NR_ROWS', '(1 resultaat)');
				} else {
					$template->addData('NR_ROWS', '(' . $nrRows . ' resultaten)');
				}
			}

			return $template->writeSection('PAGER_NO_PAGES');
		}

		$maxPage = intval(ceil($nrRows / $this->rowsPerPage));

		$page = $this->page + 1;

		if((intval($page) < 1)) {
			$page = 1;
		}

		if((intval($page)) > ($maxPage)) {
			$page = $maxPage;
		}
		//1,...,3,4,5,6,7,...,29
		//      ___   ___
		$betweenPages = 4;

		//1,...,
		if($page > (2 + $betweenPages)) {
			$template->add_var('PAGE', '<<');
			$template->add_var('RAW_PAGE', 0);
			$out .= $template->write_block('PAGER_LINK') . '...';
		} elseif($page == (2 + $betweenPages)) {
			$template->add_var('RAW_PAGE', 0);
			$template->add_var('PAGE', 1);
			$out .= $template->write_block('PAGER_LINK') . '';
		}

		//3,4,
		for($teller = $page - $betweenPages; $teller < $page; $teller++) {
			if($teller < 1) {
				continue;
			}
			$template->add_var('RAW_PAGE', $teller - 1);
			$template->add_var('PAGE', $teller);
			$out .= $template->write_block('PAGER_LINK') . '';
		}

		// 5

		$template->add_var('PAGE', intval($page));
		$out .= $template->write_block('PAGER_CURRENT') . '';


		//,6,7
		for($teller = $page + 1; $teller < ($page + 1 + $betweenPages); $teller++) {
			if($teller > $maxPage) {
				continue;
			}
			$template->add_var('RAW_PAGE', $teller - 1);
			$template->add_var('PAGE', $teller);
			$out .= '' . $template->write_block('PAGER_LINK');
		}

		//,...,29
		if($page < ($maxPage - $betweenPages - 1)) {
			$template->add_var('RAW_PAGE', $maxPage - 1);
			$template->add_var('PAGE', '>>'); //$maxPage);
			$out .= '...' . $template->write_block('PAGER_LINK');
		} elseif($page < $maxPage - $betweenPages) {
			$template->add_var('RAW_PAGE', $maxPage - 1);
			$template->add_var('PAGE', '>>');//$maxPage);
			$out .= '' . $template->write_block('PAGER_LINK');
		}
		if(isset($this->showFoundRows) && $this->showFoundRows) {
			if($nrRows == 0) {
				$template->addData('NR_ROWS', '(Geen resultaten)');
			} elseif($nrRows == 1) {
				$template->addData('NR_ROWS', '(1 resultaat)');
			} else {
				$template->addData('NR_ROWS', '(' . $nrRows . ' resultaten)');
			}
		}

		$template->add_var('PAGER', $out);
		return $template->write_block('PAGER');
	}

	/**
	 * Hiermee kan extra informatie voor intern gebruik worden opgeslagen.
	 * Hiermee kan een format callback b.v. extra info krijgen uit het originele object.
	 * Wordt in de sessie opgeslagen
	 * @param $key
	 * @param $value
	 * @return DataStructOverview
	 */
	public function setExtraInfo($key, $value) {
		$this->extraInfo[$key] = $value;
		return $this;
	}

	/**
	 * @param $key
	 * @return mixed|null
	 */
	public function getExtraInfo($key) {
		if(isset($this->extraInfo[$key])) {
			return $this->extraInfo[$key];
		}
		return NULL;
	}


	public function storeInSession() {
		$templateName = substr($this->outputTemplate->getFileName(), strlen(FILE_ROOT));
		$sessionInfo = array('outputTemplate' => $templateName,
							 'cellDefinitions' => $this->cellDefinitions,
							 'emptyColumnSection' => $this->emptyColumnSection,
							 'defaultColumnSection' => $this->defaultColumnSection,
							 'headerCellSection' => $this->headerCellSection,
							 'controlHeaderCellSection' => $this->controlHeaderCellSection,
							 'controlHeaderCellSectionException' => $this->controlHeaderCellSectionException,
							 'rowSection' => $this->rowSection,
							 'rowsPerPage' => $this->rowsPerPage,
							 'orderFields' => $this->orderFields,
							 'dominantOrder' => $this->dominantOrder,
							 'showFoundRows' => $this->showFoundRows,
							 'page' => $this->page,
							 'jsCallback' => $this->jsCallback,
							 'alternatingRows' => $this->alternatingRows,
							 'collectionClassName' => get_class($this->collection),
							 'collectionDescriptor' => $this->collection->getDescriptor(),
							 'customColumnOrder' => $this->customColumnOrder,
							 'fieldsInEditMode' => $this->fieldsInEditMode,
							 'editModeEnabled' => $this->editModeEnabled,
							 'subHeaderData' => $this->subHeaderData,
							 'preRenderCallback' => $this->preRenderCallback,
							 'formatModifier' => $this->formatModifier,
							 'tableClassName' => $this->tableClassName,
							 'extraInfo' => $this->extraInfo,
							 'selectorName' => $this->selectorName,
							 'selectionCaptionSingle' => $this->selectionCaptionSingle,
							 'selectionCaptionPlural' => $this->selectionCaptionPlural,
							 'selectorButtonsCallback' => $this->selectorButtonsCallback,
							 'selectedIds' => $this->selectedIds);

		$str = serialize($sessionInfo);
		setSesVar('ds_overview_' . $this->overviewName, $str);
	}

}