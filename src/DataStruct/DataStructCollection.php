<?php

namespace CF\DataStruct;

use CF\DataStruct\Field\DataStructField;
use CF\DataStruct\Filter\DataStructFilter;
use CF\DataStruct\Join;
use CF\DataStruct\Field;
use CF\DataStruct\Filter;

use CF\DataStruct\Join\DataStructJoin;
use ConscriboDataStructCollectionManager;
use CF\DataStruct\DataStructCollectionManager;
use CF\DataStruct\DataStructManager;
use CF\Exception\DeveloperException;
use CF\DataStruct\VirtualDataStructCollection;

trait DataStructCollection {

	//abstract static function initializeCollectionDefinition();

	// filters kun je hier op zetten
	/**
	 * @var DataStructFilter[]
	 */
	protected $filters;

	/**
	 * @var mixed
	 */
	protected $groupings;

	/**
	 * @var int limit
	 */
	protected $limit;

	/**
	 * @var int offset
	 */
	protected $offset;

	/**
	 * @var array (key => order, //)
	 */
	protected $sorters;

	/**
	 * Is de collection al geladen
	 * @var bool
	 */
	protected $collectionObjectsLoaded = false;

	/**
	 * performance variabele bij het itereren van het collectionObject (in welke variabele bevindt de data zich)
	 * @var string
	 */
	protected $iteratorVariableName;

	/**
	 * Waarop is de collection gebaseerd
	 * @var string
	 */
	protected $baseClassName;

	/**
	 * Wat is de classname van deze collection?
	 * @var string
	 */
	protected $collectionClassName;

	/**
	 * Moet er in de query worden meegegeven dat het aantal rijen moet worden berekend?
	 * @var bool
	 */
	protected $calculateNumberOfRows;


	/**
	 * Het totaal aantal rijen dat voldoet aan de filters (gebruik hiervoor eerst calculateNumberOfRows())
	 * @var int
	 */
	protected $totalNumRows;


	// Voor het opslaan en laden van oneToManyObjectJoins:

	/**
	 * @var Mixed NULL of het object waarin de collection is geladen
	 */
	protected $governedByObject;


	/**
	 * @var boolean. Werkt als een filter, geef geen resultaten terug als er geen filter is ingesteld.
	 */
	protected $filterNoResultsOnNoFilter;

	/**
	 * @var ConscriboDataStructCollectionManager::LOAD_TYPE_PARTIAL | ConscriboDataStructCollectionManager::LOAD_TYPE_FULL
	 */
	protected $loadType;

	/**
	 * @var bool Is this collection used as a collection inside another collection as specializedCollection?
	 *           If false, the collection uses specializedCollections per specialization to load and store objects.
	 */
	protected $isSpecializedCollection;


	/**
	 * @param $descriptor
	 * @return DataStructCollection
	 * @throws \Exception als een classname niet bestaat
	 */
	static function createWithDescriptor($descriptor) {

		if(VirtualDataStructCollection::isVirtual($descriptor['collectionClassName'])) {
			$collection = new VirtualDataStructCollection($descriptor['baseClassName']);
		} else {
			if(!class_exists($descriptor['collectionClassName'])) {
				throw new \Exception('Unkown collectionclass with name' . $descriptor['collectionClassName']);
			}
			$className = $descriptor['collectionClassName'];
			$collection = new $className();
		}
		/**
		 * @var DataStructCollection $collection
		 */
		$collection->setFiltersFromDescriptors($descriptor['filters']);
		$collection->setGroupingsFromDescriptor($descriptor['groupings']);
		$collection->setLimit($descriptor['limit']);
		$collection->setOffset($descriptor['offset']);
		if(isset($descriptor['sorters'])) {
			foreach($descriptor['sorters'] as $sorter) {
				foreach($sorter as $fieldName => $order) {
					$collection->addOrder($fieldName, $order);
				}
			}
		}
		$collection->calculateNumberOfRows($descriptor['calculateNumberOfRows']);
		$collection->setFilterNoResultsOnNoFilter($descriptor['noResultsOnNoFilter']);
		return $collection;
	}

	public function getDescriptor() {

		return array('baseClassName' => $this->baseClassName,
					 'collectionClassName' => $this->collectionClassName,
					 'filters' => $this->createFilterDescriptors(),
					 'groupings' => $this->createGroupingsDescriptor(),
					 'limit' => $this->limit,
					 'offset' => $this->offset,
					 'sorters' => $this->sorters,
					 'calculateNumberOfRows' => $this->calculateNumberOfRows,
					 'noResultsOnNoFilter' => $this->filterNoResultsOnNoFilter);
	}


	/**
	 * @return DataStructCollection
	 * @throws \Exception
	 */
	public function cloneCollection() {
		$data = $this->getDescriptor();
		return self::createWithDescriptor($data);
	}

	/**
	 * Geeft een descriptor terug waarmee de filters opnieuw kunnen worden aangemaakt.
	 * @return array
	 */
	protected function createFilterDescriptors() {
		$res = array();
		if($this->filters === NULL) {
			return $res;
		}
		foreach($this->filters as $index => $filter) {
			$res[$index] = $filter->getDescriptor();
		}
		return $res;
	}

	/**
	 * @internal
	 * @param array $filterDescriptors
	 */
	public function setFiltersFromDescriptors($filterDescriptors) {
		foreach($filterDescriptors as $index => $descriptor) {
			$this->_addFilter(DataStructFilter::createWithDescriptor($descriptor, DataStructManager::gI()->getDataFields($this->getBaseClassName())));
		}
	}


	public function setFilterNoResultsOnNoFilter($toggle) {
		$this->filterNoResultsOnNoFilter = $toggle;
	}

	protected function createGroupingsDescriptor() {
		$res = $this->groupings;
		if(!is_array($this->groupings)) {
			return array();
		}
		foreach($this->groupings as $index => $grouping) {
			foreach($grouping['filters'] as $id => $filter) {
				$res[$index]['filters'][$id] = $filter->getIdentifier();

			}
		}
		return $res;
	}

	public function setGroupingsFromDescriptor($descriptor) {
		$this->groupings = $descriptor;

		foreach($this->groupings as $index => $grouping) {
			foreach($grouping['filters'] as $id => $filterId) {
				if($this->getFilterByIdentifier($filterId) === NULL) {
					unset($this->groupings[$index]['filters'][$id]);
					continue;
				}
				$this->groupings[$index]['filters'][$id] = $this->getFilterByIdentifier($filterId);
			}
		}
	}

	/**
	 * @return bool is this collection loaded.
	 */
	public function getCollectionObjectsLoaded() {
		return $this->collectionObjectsLoaded;
	}

	/**
	 * @param bool $toggle be able to determine the total datasetsize regardless of limits and offsets using function ->getNumRows()
	 */
	public function calculateNumberOfRows($toggle) {
		$this->calculateNumberOfRows = $toggle;
		$this->collectionObjectsLoaded = false;
		return $this;
	}

	/**
	 * Clear All Orderings
	 */
	public function clearOrder() {
		$this->sorters = array();
		$this->collectionObjectsLoaded = false;
		return $this;
	}

	/**
	 * @param string $fieldName
	 * @param string $order DataStructField::ORDER_ASC| DataStructField::ORDER_DESC
	 */
	public function addOrder($fieldName, $order = DataStructField::ORDER_ASC) {
		$this->sorters[] = array($fieldName => $order);
		$this->collectionObjectsLoaded = false;
		return $this;
	}

	/**
	 * Return the total records found in database when ->calcNumRows() is toggled true,
	 * the number of loaded results otherwise (same as count())
	 * @return int
	 */
	public function getNumRows() {
		return $this->totalNumRows;
	}

	/**
	 * Perform a lazy refresh, in next call, objects will reload.
	 */
	public function refresh() {
		$this->collectionObjectsLoaded  = false;
		$this->totalNumRows = NULL;
	}

	/**
	 * @return string className on which this collection is based. Object loaded in this collection can be instances of this class or specializations from it.
	 */
	public function getBaseClassName() {
		if(!isset($this->baseClassName)) {
			$this->baseClassName = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName())->getBaseClassName();
		}
		return $this->baseClassName;
	}

	/**
	 * Geeft de className van de collection
	 * @return string
	 */
	protected function getCollectionClassName() {
		if(empty($this->collectionClassName)) {
			$this->collectionClassName = DataStructManager::getUniversalClassName(get_class($this));
		}
		return $this->collectionClassName;
	}


	/**
	 * Notify this collection is an elementary collection and has no specialized subcollections
	 * @param boolean $isSpecializedCollection
	 * @return DataStructCollection
	 */
	private function setIsSpecializedCollection($isSpecializedCollection) {
		$this->isSpecializedCollection = $isSpecializedCollection;
		return $this;
	}



	/**
	 * @param $fieldName
	 * @return DataStructFilter|StringFilter|\CF\DataStruct\Filter\DateFilter|TextAreaFilter|IntegerFilter|\CF\DataStruct\Filter\AmountFilter|FloatFilter|EmailFilter|CheckBoxFilter|\CF\DataStruct\Filter\EnumFilter|\CF\DataStruct\Filter\BankAccountFilter
	 */
	public function createFilter($fieldName, $identifier = NULL) {
		$definition = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());

		$field = DataStructManager::gI()->getFieldFromClassName($definition->getBaseClassName(), $fieldName);
		if($field === NULL) {
			throw new DeveloperException('Field ' . $fieldName . ' Niet gevonden in class ' . $definition->getBaseClassName());
		}
		$filter = $field->createFilterObject();
		if($identifier !== NULL) {
			$filter->setIdentifier($identifier);
		}
		$this->_addFilter($filter);
		return $filter;
	}

	/**
	 * Create a filter on a 1-n extensionjoin on an object. the join should have an identifier assigned for this to work
	 * @param string $joinIdentifier
	 * @param string $fieldName
	 * @return DataStructFilter|StringFilter|\CF\DataStruct\Filter\DateFilter|TextAreaFilter|IntegerFilter|\CF\DataStruct\Filter\AmountFilter|FloatFilter|EmailFilter|CheckBoxFilter|\CF\DataStruct\Filter\EnumFilter|\CF\DataStruct\Filter\BankAccountFilter
	 */

	public function createExtensionJoinFilter($joinIdentifier, $fieldName, $identifier = NULL) {
		throw new \DeveloperException('not implemented yet');
		//TODO
		// We hebben hier wel een implementatie voor, alleen moet er een aparte query interface worden gebouwd . Nu worden de filters teogepast in de basisquery en onetoone join. niet in de onetomany extensions. Deze worden later pas geladen.
		//
		$definition = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());

		$join = DataStructManager::gI()->getJoinDefinitionByIdentifier($definition->getBaseClassName(), $joinIdentifier);
		/**
		 * @var Join\ExtendedTableOneToManyJoin $join
		 */
		$field = $join->getFieldDefinitionByName($fieldName);
		/**
		 * @var DataStructField $field
		 */
		$filter = $field->createFilterObject();

		if($identifier !== NULL) {
			$filter->setIdentifier($identifier);
		}
		$this->_addFilter($filter);
		return $filter;
	}


	/**
	 * @param                    $identifier
	 * @param DataStructFilter[] $filters
	 * @param null               $parentGroupIdentifier
	 * @throws \Exception
	 */
	public function addOrGroupWithIdentifier($identifier, $filters, $parentGroupIdentifier = NULL) {
		if($parentGroupIdentifier === NULL) {
			$parentGroupIdentifier = '_root';
		}
		$this->addGroup(DataStructFilter::GROUP_OR, $identifier, $parentGroupIdentifier);

		foreach($filters as $filter) {
			$this->addFilterToGroup($filter, $identifier);
		}
	}

	public function addOrGroup($filters, $parentGroupIdentifier = NULL) {
		$orId = 0;
		while(isset($this->groupings['or_'. $orId])) {
			$orId ++;
		}
		$groupName = 'or_'. $orId;

		$this->addOrGroupWithIdentifier($groupName, $filters, $parentGroupIdentifier);
	}

	public function addAndGroupWithIdentifier($identifier, $filters, $parentGroupIdentifier = NULL) {
		if($parentGroupIdentifier === NULL) {
			$parentGroupIdentifier = '_root';
		}
		$this->addGroup(DataStructFilter::GROUP_OR, NULL, $parentGroupIdentifier);

		foreach($filters as $filter) {
			$this->addFilterToGroup($filter, $identifier);
		}

	}

	public function addAndGroup($filters, $parentGroupIdentifier = NULL) {
		$orId = 0;
		while(isset($this->groupings['or_'. $orId])) {
			$orId ++;
		}
		$groupName = 'and_'. $orId;

		$this->addOrGroupWithIdentifier($groupName, $filters, $parentGroupIdentifier);

	}


	private function addGroup($groupType, $groupName, $parentId = NULL) {
		$this->groupings[$groupName] = array('type' => $groupType,
											 'parentId' => $parentId,
											 'filters' => array());
	}

	function addFilterToGroup($filter, $identifier = NULL) {
		if($identifier === NULL) {
			// root group
			if(!isset($this->groupings['_root'])) {
				$this->addGroup(DataStructFilter::GROUP_AND, '_root', NULL);
			}
			$identifier = '_root';
		}
		if(!isset($this->groupings[$identifier])) {
			throw new \Exception('Cannot add filter to unknown group: ' . $identifier);
		}

		// haal hem uit de group waar hij is ingezet
		foreach($this->groupings as $id => $filters) {
			foreach($filters['filters'] as $fId => $_filter) {
				if($_filter->getIdentifier() == $filter->getIdentifier()) {
					unset($this->groupings[$id]['filters'][$fId]);
				}
			}
		}

		$this->groupings[$identifier]['filters'][] = $filter;
		$this->collectionObjectsLoaded = false;
	}


	/**
	 * Vervang indien filter met identifier bestaat het filter. Voeg deze anders toe
	 * @param $identifier
	 * @param $fieldName
	 * @return \CF\DataStruct\Filter\AmountFilter|\CF\DataStruct\Filter\BankAccountFilter|CheckBoxFilter|\CF\DataStruct\Filter\DateFilter|EmailFilter|\CF\DataStruct\Filter\EnumFilter|DataStructFilter|FloatFilter|\CF\DataStruct\Filter\IntegerFilter|StringFilter|TextAreaFilter
	 * @throws \CF\Exception\DeveloperException
	 */
	public function replaceFilter($identifier, $fieldName) {
		$oldFilter = $this->getFilterByIdentifier($identifier);

		if($oldFilter !== NULL) {
			$groupingId = $this->getGroupingIdentifierByFilter($oldFilter);
			$this->clearFilterWithIdentifier($identifier);
		}
		$filter = $this->createFilter($fieldName, $identifier);
		if($oldFilter !== NULL) {
			$this->addFilterToGroup($filter, $groupingId);
		}
		return $filter;
	}

	/**
	 * Verwijder alle filters met deze fieldName
	 * @param $fieldName
	 * @return $this
	 */
	public function resetFilter($fieldName) {

		$this->filterNoResultsOnNoFilter = NULL;

		if($this->filters === NULL) {
			return $this;
		}
		foreach($this->filters as $index => $filter) {
			if($filter->getField()->getName() == $fieldName) {
				$this->clearFilterWithIdentifier($filter->getIdentifier());
			}
		}

		return $this;
	}

	/**
	 * Geef het filterobject terug met identifier ...
	 * @param $identifier
	 * @return DataStructFilter
	 */
	public function getFilterByIdentifier($identifier) {
		if(!is_array($this->filters)) {
			return NULL;
		}
		foreach($this->filters as $index => $filter) {
			if($filter->getIdentifier() == $identifier) {
				return $filter;
			}
		}
		return NULL;
	}

	/**
	 * Geeft de groupingIdent terug van een filter.
	 * @param DataStructFilter $filter
	 * @return string
	 */
	public function getGroupingIdentifierByFilter(DataStructFilter $filter) {
		foreach($this->groupings as $groupingId => $grouping) {
			foreach($grouping['filters'] as $_filter) {
				if($_filter->getIdentifier() == $filter->getIdentifier()) {
					return $groupingId;
				}
			}
		}
		return NULL;
	}

	/**
	 * Verwijder het filter met identifier
	 * @param $identifier
	 * @return $this
	 */
	public function clearFilterWithIdentifier($identifier) {
		if($this->filters === NULL) {
			return $this;
		}
		foreach($this->filters as $index => $filter) {
			if($filter->getIdentifier() == $identifier) {
				$groupingId = $this->getGroupingIdentifierByFilter($filter);
				foreach($this->groupings[$groupingId]['filters'] as $id => $_filter) {
					if($_filter->getIdentifier() == $identifier) {
						unset($this->groupings[$groupingId]['filters'][$id]);
					}
				}

				unset($this->filters[$index]);
				$this->collectionObjectsLoaded = false;
			}
		}
		return $this;
	}


	protected function _addFilter(DataStructFilter $filter) {
		$this->filters[] = $filter;
		$this->addFilterToGroup($filter, NULL);
		$this->collectionObjectsLoaded = false;
		return $this;
	}

	public function setLimit($limit) {
		$this->limit = $limit;
		$this->collectionObjectsLoaded = false;
		return $this;
	}

	/**
	 * @param int $offset
	 * @return DataStructCollection
	 */
	public function setOffset($offset) {
		$this->offset = $offset;
		$this->collectionObjectsLoaded = false;
		return $this;
	}


	/**
	 * @return array
	 */
	public function getIds() {
		$this->_loadObjects();

		$definition = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());
		$fieldName = $definition->variableName;
		return array_keys($this->$fieldName);
	}


	/**
	 * Geeft het eerste lokaal geladen record terug dat voldoet aan de waarde
	 * @param $fieldName
	 * @param $value
	 * @return NULL | DataStruct
	 */
	public function selectLocalUniqueRecordWithValue($fieldName, $value) {
		$this->_prepareIteratorVariableName();

		$variableName = $this->iteratorVariableName;
		$entries = $this->$variableName;

		foreach($this->getIds() as $id) {
			if(DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName)->valueEquals($this->getValue($fieldName, $id), $value)) {
				return $entries[$id];
			}
		}
		return NULL;
	}


	/**
	 * Geeft alle lokaal geladen records terug welke voldoen aan de waardes
	 * @param       $fieldName
	 * @param array $values
	 * @return NULL | DataStructCollection
	 */
	public function selectLocalRecordsWithValues($fieldName, $values) {
		$this->_prepareIteratorVariableName();

		$res = clone($this);

		$dataField = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		foreach($this->getIds() as $id) {
			$val = $this->getValue($fieldName, $id);
			if(!$dataField->valueInArray($val, $values)) {
				unset($res[$id]);
			}
		}
		return $res;
	}

	/**
	 * Geef aan hoe de collection is/wordt geladen
	 * standaard LOAD_TYPE_FULL: de collection heeft de volledige dataset binnenghaald. Op het moment dat er keys niet bestaan, dan doen we ook niets
	 *            LOAD_TYPE_PARTIAL: de collection is voorzien van objecten die beschikbaar waren. als een key niet bestaa, wordt alsnog een volledige load uitgevoerd
	 * @param $newLoadType
	 */
	public function setLoadType($newLoadType) {
		$this->loadType = $newLoadType;
	}

	/**
	 * @param DataStruct $object
	 * We laden een ONE side van een join (bv folder in de folder file relatie) via een file, daarbij wordt in de folder een collection aangemaakt met files.
	 * deze filescollection wordt alvast gevuld met 1 entry: de file waaruit de folder geladen is.
	 *
	 */
	public function placeLoaderObject($object) {
		//$this->governedByObject = $object;

		$collectionDef = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());

		$variableName = $collectionDef->variableName;
		$destination = &$this->$variableName;

		$id = $object->_getValue($collectionDef->indexField);
		$destination[$id] = $object;

	}


	/**
	 * @param $object
	 * De collection wordt geladen in een lazy loading scenario vanuit een ONE object (b.v. een Folder in de folder /file relatie)
	 * Als er daadwerkelijk gaat worden geladen, wordt in de file objecten dit ONE object geplaatst.
	 */
	public function setGoverningJoinInfo($dataJoin, $object) {

	}

	/**
	 * Determine we do not receive any records when no filter is set. (default is false)
	 */
	public function createFilterNoResultsOnNoFilter($toggle = true) {
		$this->filterNoResultsOnNoFilter = $toggle;
	}

	/**
	 * Clear all entries
	 * same as unsetting all values (for usage in properties of object)
	 */
	public function clearValues() {
		$var = $this->_prepareIteratorVariableName();
		$this->$var = array();

		return;
	}


	protected function _loadObjects($forceReload = false, $relevantKey = NULL) {
		if(!$forceReload && $this->collectionObjectsLoaded) {
			return;
		}

		/**
		 * Voor gedeeltelijk geladen collections:
		 */
		if($relevantKey !== NULL) {
			$var = $this->iteratorVariableName;
			$a = &$this->$var;
			if(isset($a[$relevantKey])) {
				// Deze key is geladen!
				return;
			}
		}

		// Determine if we have to create specializations or if we have only one class
		$collectionDef = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());
		$baseClassName = $collectionDef->getBaseClassName();

		if(!DataStructManager::gI()->isExtendableClass($baseClassName)) {
			// No extenstions possible, we are a specializedCollection
			$this->setIsSpecializedCollection(true);
		}

		$localRecords = $this->_loadLocalRecords();
		if($localRecords === NULL) {
			$this->collectionObjectsLoaded = true;
			return;
		}

		if($this->isSpecializedCollection) {
			// continue loading in our collection
			$this->_loadObjectsFromLocalRecords($localRecords);
			return;
		}

		// check if we can associate the index for all specializedcollections:
		if($collectionDef->indexField === NULL) {
			throw new DeveloperException('Cannot use an extendable collection without specifying a field to use as key. In collection '. $this->getCollectionClassName());
		}

		// determine what specialization should be used:
		$extendabilityInfo = DataStructManager::gI()->getExtendabilityInfo($baseClassName);

		$callBackClass = $baseClassName;
		$callBackFunction = $extendabilityInfo['determinerFunctionName'];
		$field = DataStructManager::gI()->getFieldFromClassName($baseClassName, $extendabilityInfo['fieldName']);

		$compositeObjectIndex = array();

		$keyFields = DataStructManager::gI()->getKeyFields($baseClassName);
		$keyValuesPerClass = array();

		// load objects in specializedCollections
		foreach($localRecords as $id => $record) {
			$value = $field->parseDBFormat($record[$extendabilityInfo['fieldName']]);
			$className = $callBackClass::$callBackFunction($value);
			$compositeObjectIndex[$id] = $className;

			foreach($keyFields as $fieldName => $keyField) {
				$value = $keyField->parseDBFormat($record[$fieldName]);
				$keyValuesPerClass[$className][$fieldName][$value] = $value;
			}
		}
		unset($localRecords);


		// place objects in our collection:
		$variableName = $collectionDef->variableName;
		$destination = &$this->$variableName;
		$destination = array();

		$specializedCollections = array();
		// create specialized collections:
		foreach($keyValuesPerClass as $className => $keyFieldsAndValues) {
			$specializedCollections[$className] = new VirtualDataStructCollection($className);
			$specializedCollections[$className]->setIsSpecializedCollection(true);
			$specializedCollections[$className]->useOtherIndexField($collectionDef->indexField);

			// apply filters to collection:
			foreach($keyFieldsAndValues as $fieldName => $values) {
				$specializedCollections[$className]->createFilter($fieldName)->in($values);
			}
			$specializedCollections[$className]->_loadObjects();
		}


		foreach($compositeObjectIndex as $id => $className) {
			$destination[$id] = $specializedCollections[$className]->offsetGet($id);
		}
	}

	/**
	 * @return array
	 */
	private function _loadLocalRecords() {
		// create dataStructBlock:
		$collectionDef = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());

		// het object aanmaken en vullen
		$variableName = $collectionDef->variableName;
		$destination = &$this->$variableName;
		$destination = array();

		if(($this->filterNoResultsOnNoFilter !== NULL && $this->filterNoResultsOnNoFilter) && (!is_array($this->filters) || count($this->filters) == 0)) {
			// er zijn geen filters ingesteld in combinatie met het niet willen hebben van resultaten als er geen filters zijn.
			$this->collectionObjectsLoaded = true;
			return NULL;
		}

		$className = $collectionDef->className;
		$blockName = DataStructCollectionManager::gI()->getUniqueDbBlockName($className);

		/**
		 * @var DataStruct $className
		 */

		// Maak een database blockQuery die de datastruct kan gebruiken om zichzelf te laden:

		$struct = DataStructManager::gI()->getDataDefinitionsByClassName($className);

		if(!$this->isSpecializedCollection) {
			// only load keys and determiners
			$minimalFields = array();
			// keys:
			$keyFields = DataStructManager::gI()->getKeyFields($className);
			foreach($keyFields as $fieldName => $field) {
				$minimalFields[$fieldName] = $struct['fields'][$fieldName];
			}
			// determiner:
			$info = DataStructManager::gI()->getExtendabilityInfo($className);
			$minimalFields[$info['fieldName']] = $struct['fields'][$info['fieldName']];
			$struct['fields'] = $minimalFields;
		}

		DataStruct::_createDBSelectBlockFromDataStructDefinition($struct, $blockName);

		$this->_applyFilters($blockName);
		$this->_applySorters($blockName);
		$this->_applyLimiters($blockName);
		return $this->_executeQuery($blockName);
	}

	public function _loadObjectsFromLocalRecords($resultArray) {

		$collectionDef = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());
		$className = $collectionDef->className;
		$variableName = $collectionDef->variableName;

		$destination = &$this->$variableName;
		$destination = array();

		$universalKeys = array();
		// first, retreive objects that are already loaded, from the universe:

		foreach($resultArray as $indexValue => $localRecord) {
			$universalKey = DataStructManager::gI()->_getUniversalKeyFromDatabaseRow($className, $localRecord);

			if($_obj = DataStructManager::gI()->getObjectFromUniverseWithUniversalKey($className, $universalKey)) {
				$destination[$indexValue] = $_obj;
				unset($resultArray[$indexValue]);
			} else {
				// they are not loadable, retreive them from database
				$universalKeys[$indexValue] = $universalKey;
				$destination[$indexValue] = NULL;
			}
		}

		if(count($resultArray) > 0) {
			$extensionRecords = $this->_queryOneToManyJoins($resultArray);
			// De objecten instantieren:
			foreach($resultArray as $indexValue => $localRecord) {
				//kijken of het object niet al bestaat. In dat geval, het object gebruiken.
				$universalKey = $universalKeys[$indexValue];
				$destination[$indexValue] = $className::loadWithDBRecord($className, $localRecord, NULL, $extensionRecords[$indexValue], $universalKey);
			}
		}
		// Objectjoins waarvan het collected object ONE is, zijn nu niet geladen.
		$this->collectionObjectsLoaded = true;
	}

	/**
	 * Maak sql filters gebaseerd op de filters op collection;
	 * @param $blockName
	 */
	public function applyCollectionFiltersToQuery($blockName) {
		$this->_applyFilters($blockName);
	}

	/**
	 * Maak sql filters gebaseerd op de filters op collection;
	 * @param $blockName
	 */
	public function applySortersToQuery($blockName) {
		$this->_applySorters($blockName);
	}

	protected function _applyFilters($dbBlockName) {
		if(!is_array($this->filters)) {
			return;
		}
		$where = $this->_getWherePhraseForGroup('_root');
		if($where !== NULL) {
			\CF\Runtime\Runtime::gI()->db()->addComplexWhere($dbBlockName, $where);
		}
	}

	private function _getWherePhraseForGroup($identifier) {

		$groupingBlockName = 'sub_' . $identifier;
		\CF\Runtime\Runtime::gI()->db()->startBlock($groupingBlockName);

		/**
		 * Een grouping bestaat uit:
		 * 'type' => $groupType,
		 * 'parentId' => $parentId,
		 * 'filters' =>
		 */

		// eerst filters:
		foreach($this->groupings[$identifier]['filters'] as $filter) {
			$filter->applyFilterToSqlResult($groupingBlockName);
		}

		// eventuele andere groupings:

		foreach($this->groupings as $childId => $grouping) {
			if($grouping['parentId'] == $identifier) {
				$where = $this->_getWherePhraseForGroup($childId);
				if($where !== NULL) {
					\CF\Runtime\Runtime::gI()->db()->addComplexWhere($groupingBlockName, $where);
				}
			}
		}

		$where = \CF\Runtime\Runtime::gI()->db()->createWhereString($groupingBlockName, $this->groupings[$identifier]['type']);
		if(empty($where)) {
			return NULL;
		}
		return '(' . $where . ')';
	}

	protected function _applySorters($dbBlockName) {
		if($this->sorters === NULL) {
			return;
		}
		/**
		 * $order 'asc' | 'desc' | '= value'
		 */
		foreach($this->sorters as $sorting) {
			// Ik weet niet waarom dit een array is!
			foreach($sorting as $fieldName => $order) {
				$field = DataStructManager::gI()->getFieldFromClassName($this->getBaseClassName(), $fieldName);
				if($field !== NULL) {
					$field->addOrderSql($dbBlockName, $order);
				}
			}
		}
	}


	protected function _applyLimiters($dbBlockName) {

		if($this->offset !== NULL && $this->limit !== NULL) {
			db()->setLimit($dbBlockName, $this->offset . ',' . $this->limit);
		} elseif($this->offset !== NULL) {
			db()->setLimit($dbBlockName, $this->offset . ', 999999999');
		} elseif($this->limit !== NULL) {
			db()->setLimit($dbBlockName, $this->limit);
		}

		if($this->calculateNumberOfRows) {
			db()->setCalcFoundRows($dbBlockName, true);
		}
	}

	/**
	 * @param $blockName
	 * @return array met records uit de database;
	 */
	private function _executeQuery($blockName) {

		db()->setCalcFoundRows($blockName, $this->calculateNumberOfRows == true);

		db()->queryBlock($blockName, 'CollectionLoad');

		if($this->calculateNumberOfRows) {
			list($this->totalNumRows) = db()->select('SELECT FOUND_ROWS()');
		} else {
			list($this->totalNumRows) = db()->getNumRows('CollectionLoad');
		}
		$collectionDef = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName());

		// De resultset ophalen adhv de opgegeven index:
		if($collectionDef->indexField !== NULL) {
			$dbFieldName = $collectionDef->indexField;
		} else {
			// dan maar achter elkaar plakken!
			$dbFieldName = NULL;
		}

		$resultSet = db()->fetchAllAssoc($dbFieldName, 'CollectionLoad');
		return $resultSet;
	}


	private function _queryOneToManyJoins($localRecords) {
		if(count($localRecords) == 0) {
			return array();
		}

		$extensionRecords = array();

		$className = $this->getCollectionClassName();
		$collectionDef = DataStructCollectionManager::gI()->getDefinition($className);

		$joins = DataStructManager::gI()->getDataJoins($collectionDef->className);
		$localFields = DataStructManager::gI()->getDataFields($collectionDef->className);

		foreach($joins as $join) {

			// We ondersteunen nu alleen one to many optimizations:
			if($join->getJoinType() == DataStructJoin::TYPE_EXTENSION && $join->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_MANY) {
				/**
				 * @var ExtendedTableOneToManyJoin $join
				 */

				$parentPropertyName = $join->getParentPropertyName();
				$joinFields = $join->getFieldInfo();

				// basisQuery opstellen:
				$blockName = DataStructCollectionManager::gI()->getUniqueDbBlockName($className . 'ext12many');

				$struct = array('fields' => $joinFields,
								'joins' => array());
				DataStruct::_createDBSelectBlockFromDataStructDefinition($struct, $blockName);

				// where:

				// WHERE (`foreignTable`.`foreignKeyFieldName1`, `foreignTable`.`foreignKeyFieldName2`) IN (('foreignValue1','foreignValue2'), ..)

				// Keys opstellen:


				// We stellen ook alvast een resultIndex op. Dit is een array die alle indexfields van deze join bevat zoals ze uit het sqlresult komen.
				$key = array();
				$elCount = 0;
				foreach($join->getForeignKey() as $localKeyFieldName => $foreignKeyFieldName) {
					/**
					 * @var DataStructField[] $joinFields
					 */
					$key[$elCount] = $joinFields[$foreignKeyFieldName]->_getFullDatabaseFieldName();
					$elCount++;
				}
				$keyStr = '(' . implode(',', $key) . ')';

				// JoinValues opstellen:
				$joinValueElements = array();
				$elCount = 0;
				foreach($join->getForeignKey() as $localKeyFieldName => $foreignKeyFieldName) {
					foreach($localRecords as $indexValue => $localRecord) {
						$joinValueElements[$indexValue][$elCount] = $localFields[$localKeyFieldName]->dbFormat($localRecord[$localKeyFieldName]);
					}
					$elCount++;
				}

				// Lookup van de key value => indexValue
				$resultIndex = array();

				$joinValues = array();
				foreach($joinValueElements as $indexValue => $joinValue) {
					$valStr = implode(',', $joinValue);
					$joinValues[] = '(' . implode(',', $joinValue) . ')';
					// Met de resultIndex kunnen we nu terugvinden bij welke indexValue een resultRow straks horen.
					$resultIndex[$valStr] = $indexValue;

					// Direct de ResultSet voorbereiden nu we hier toch zijn:
					$extensionRecords[$indexValue][DataStructJoin::TYPE_EXTENSION][$parentPropertyName] = array();
				}
				db()->addComplexWhere($blockName, $keyStr . ' IN (' . implode(',', $joinValues) . ')');

				// uitvoeren:
				db()->queryBlock($blockName, $blockName);

				// nu op de juiste positie de waarden weer invullen:
				while($row = db()->fetchAssoc($blockName)) {

					$keyEl = array();
					//opzoeken wie het is:
					foreach($join->getForeignKey() as $localKeyFieldName => $foreignKeyFieldName) {
						$keyEl[] = $localFields[$localKeyFieldName]->dbFormat($row[$foreignKeyFieldName]);
					}
					$keyStr = implode(',', $keyEl);
					$extensionRecords[$resultIndex[$keyStr]][DataStructJoin::TYPE_EXTENSION][$parentPropertyName][] = $row;
				}
			}
		}

		// Er zijn geen records die kunnen worden geladen
		if(count($extensionRecords) == 0 && count($localRecords) > 0) {
			$extensionRecords = array_fill_keys(array_keys($localRecords), array());
		}
		return $extensionRecords;
	}

	/**
	 * Let op: Dit werkt alleen op public properties
	 * @param $fieldName
	 * @param $recordId
	 * @return mixed
	 */
	public function getValue($fieldName, $recordId) {
		$var = $this->_prepareIteratorVariableName($recordId);
		$ar = $this->$var;
		if(!isset($ar[$recordId])) {
			return NULL;
		}
		/**
		 * @var DataStruct[] $ar
		 */
		return $ar[$recordId]->_getValue($fieldName);
	}


	/**
	 * doorzoek de resultset naar values waar fieldName in values
	 * @param $fieldName
	 * @param $values
	 * @return array
	 */
	public function findInResultset($fieldName, $values) {
		$this->_loadObjects();

		$res = array();

		$var = $this->_prepareIteratorVariableName();
		$ar = $this->$var;

		foreach($ar as $index => $value) {
			/**
			 * DataStruct $value
			 */
			if($value->_isValueIn($fieldName, $values)) {
				$res[$index] = $value;
			}
		}

		return $res;
	}

	/**
	 * Geeft een geformatteerde versie van de waarde terug
	 * @param      $fieldName
	 * @param      $recordId
	 * @param null $format
	 * @return mixed
	 */
	public function getValueFormatted($fieldName, $recordId, $format = NULL) {
		$var = $this->_prepareIteratorVariableName($recordId);
		$ar = $this->$var;
		if(!isset($ar[$recordId])) {
			return NULL;
		}
		/**
		 * @var DataStruct[] $ar
		 */
		return $ar[$recordId]->_getValueFormatted($fieldName, $format);
	}

	/**
	 * Geeft alle waarden uit een collection terug van een bepaalde veldnaam.
	 * @param      $fieldName
	 * @param bool $ignoreNull mogen NULL waarden als element worden opgenomen?
	 * @return array[value] => value
	 */
	public function getAllDistinctValues($fieldName, $ignoreNull = false) {
		$ret = array();
		foreach($this->getIds() as $id) {
			if($ignoreNull && $this->getValue($fieldName, $id) === NULL) {
				continue;
			}
			$ret[$this->getValue($fieldName, $id)] = $this->getValue($fieldName, $id);
		}
		return $ret;
	}


	/**
	 * Return ONE object from loaded set, with $value in $fieldName or NULL if no object loaded in
	 * @param $fieldName
	 * @param $value
	 * @return DataStruct |null
	 */
	public function getObjectWithValue($fieldName, $value) {
		$res = $this->getObjectsWithValue($fieldName, $value);
		if(count($res) == 0) {
			return NULL;
		}
		return reset($res);
	}

	/**
	 * @param string $fieldName
	 * @param mixed $value
	 * @return DataStruct[]
	 */
	public function getObjectsWithValue($fieldName, $value) {
		$res = array();
		$definition = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		foreach($this->getIds() as $id) {
			if($definition->valueEquals($this->getValue($fieldName, $id), $value)) {
				$res[$id] = $this->offsetGet($id);
			}
		}
		return $res;
	}

	/**
	 * Returns the object in a loaded collection with the maximum value in <fieldName>
	 * @param $fieldName
	 * @return DataStruct | NULL if empty collection
	 */
	public function getObjectWithMaxValue($fieldName) {
		$maxObj = NULL;
		$maxValue = NULL;
		$definition = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		foreach($this->getIds() as $id) {
			if($maxObj === NULL || $definition->isValueAGreaterThanB($this->getValue($fieldName, $id), $maxValue)) {
				$maxObj = $this->offsetGet($id);
				$maxValue = $this->getValue($fieldName, $id);
			}
		}
		return $maxObj;
	}

	/**
	 * Return the sum of values for field $fieldName.
	 * @pre There is no check if the value is summizable, fieldname should contain summizable values
	 * @param $fieldName
	 * @return int|mixed
	 * @throws \Exception
	 */
	public function getSum($fieldName) {
		$sum = 0;
		$definition = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		//TODO, check on  ability to add?
		foreach($this->getIds() as $id) {
			$val = $this->getValue($fieldName, $id);
			if($val !== NULL) {
				$sum += $val;
			}
		}
		return $sum;
	}

	/**
	 * Return the product (multiplication) of all values for field $fieldName.
	 * @pre There is no check if the value is summizable, fieldname should contain summizable values
	 * @param $fieldName
	 * @return int|mixed
	 * @throws \Exception
	 */
	public function getProduct($fieldName) {
		$sum = 0;
		$definition = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		//TODO, check on  ability to multiply?
		foreach($this->getIds() as $id) {
			$val = $this->getValue($fieldName, $id);
			if($val !== NULL) {
				$sum *= $val;
			}
		}
		return $sum;
	}

	public function getAverage($fieldName) {
		$sum = 0;
		$quantifier = 0;

		$definition = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		//TODO, check on  ability to add?
		foreach($this->getIds() as $id) {
			$val = $this->getValue($fieldName, $id);
			if($val !== NULL) {
				$sum += $val;
			}
			$quantifier++;
		}
		if($quantifier == 0) {
			return NULL;
		}
		return $sum / $quantifier;
	}

	/**
	 * Returns the object in a loaded collection with the maximum value in <fieldName>
	 * @param $fieldName
	 * @return DataStruct | NULL if empty collection
	 */
	public function getObjectWithMinValue($fieldName) {
		$maxObj = NULL;
		$maxValue = NULL;
		$definition = DataStructManager::gI()->getDataField($this->getBaseClassName(), $fieldName);
		foreach($this->getIds() as $id) {
			if($maxObj === NULL || $definition->isValueASmallerThanB($this->getValue($fieldName, $id), $maxValue)) {
				$maxObj = $this->offsetGet($id);
				$maxValue = $this->getValue($fieldName, $id);
			}
		}
		return $maxObj;
	}


	/**
	 * Geeft alle waarden uit een collection terug van een bepaalde veldnaam.
	 * @param $fieldName
	 * @return array[id] => value
	 */
	public function getAllValues($fieldName) {
		$ret = array();
		foreach($this->getIds() as $id) {
			$ret[$id] = $this->getValue($fieldName, $id);
		}
		return $ret;
	}

	/**
	 * Geeft alle waarden uit een collection geformat terug van een bepaalde veldnaam.
	 * @param $fieldName
	 * @param $format
	 * @return array[id] => value
	 */
	public function getAllValuesFormatted($fieldName, $format = NULL) {
		$ret = array();
		foreach($this->getIds() as $id) {
			$ret[$id] = $this->getValueFormatted($fieldName, $id, $format);
		}
		return $ret;
	}

	/**
	 * @pre  Alle objecten moeten valid zijn. De exceptions die terugkomen als ze niet valid zijn, worden genegeerd
	 * @post Alle objecten zijn opgeslagen
	 */
	public function storeAllObjects() {
		if($this->count() == 0) {
			return;
		}
		$var = $this->_prepareIteratorVariableName();
		/**
		 * @var DataStruct[] $objects
		 */
		$objects = $this->$var;

		$sqlStatements = DataStructManager::gI()->_claimStoreExecution();

		foreach($this->getIds() as $id) {
			try {
				$objects[$id]->_storeDataStruct($sqlStatements);
			} catch(Exception $e) {
				\CF\Runtime\Runtime::gI()->addWarning('Could not store object from collection ' . $this->getCollectionClassName());
			}
		}
		DataStructManager::gI()->_executeStore($sqlStatements);
	}

	public function getIterator() {
		$var = $this->_prepareIteratorVariableName();
		if($this->$var === NULL) {
			return new \ArrayIterator(array());
		}
		return new \ArrayIterator($this->$var);
	}

	/**
	 * @return object De eerste entry uit dde struct
	 */
	public function first() {
		$var = $this->_prepareIteratorVariableName();
		return reset($this->$var);
	}


	/**
	 * Maakt de collection beschikbaar.
	 * Op het moment dat een relevantKey wordt meegegeven, dan betekent dat, dat alleen die key hoeft te zijn geladen.
	 * @param null $relevantKey
	 * @return string
	 */
	private function _prepareIteratorVariableName($relevantKey = NULL, $load = true) {
		if($this->iteratorVariableName === NULL) {
			$this->iteratorVariableName = DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName())->variableName;
		}
		if($load) {
			$this->_loadObjects(false, $relevantKey);
		}

		return $this->iteratorVariableName;
	}


	/**
	 * @param mixed $sorter
	 * @return DataStructCollection
	 */
	protected function _addSorter($sorter) {
		$this->sorters[] = $sorter;
		return $this;
	}


	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 *                      An offset to check for.
	 *                      </p>
	 * @return boolean true on success or false on failure.
	 *                      </p>
	 *                      <p>
	 *                      The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset) {
		$var = $this->_prepareIteratorVariableName(NULL, false);

		if($this->$var !== NULL && array_key_exists($offset, $this->$var)) {
			return true;
		}
		$this->_loadObjects();
		return array_key_exists($offset, $this->$var);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 *                      The offset to retrieve.
	 *                      </p>
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset) {
		$var = $this->_prepareIteratorVariableName($offset);
		$a = &$this->$var;
		return $a[$offset];
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 *                      The offset to assign the value to.
	 *                      </p>
	 * @param mixed $value  <p>
	 *                      The value to set.
	 *                      </p>
	 * @return void
	 */
	public function offsetSet($offset, $value) {
		$var = $this->_prepareIteratorVariableName();
		$a = &$this->$var;
		$a[$offset] = $value;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 *                      The offset to unset.
	 *                      </p>
	 * @return void
	 */
	public function offsetUnset($offset) {
		$var = $this->_prepareIteratorVariableName();
		$a = &$this->$var;
		unset($a[$offset]);
	}

	/**
	 * @param int $mode
	 * @return int
	 */
	public function count() {
		$var = $this->_prepareIteratorVariableName();
		return count($this->$var);
	}


	/**
	 * Geeft terug wat er geladen is. Let op: Deze functie is voor intern gebruik. Je krijgt hiermee alleen de zaken terug die al daadwerkelijk in de collectie geladen zijn.
	 * @internal
	 * @return DataStruct[] | NULL
	 */
	public function _getLoadedObjects() {
		$var = $this->_prepareIteratorVariableName(NULL, false);
		return $this->$var;
	}

}