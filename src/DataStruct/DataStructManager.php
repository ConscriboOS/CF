<?php
namespace CF\DataStruct;

use CF\associative;
use CF\Classname;
use CF\DataStruct\Join\DataStructJoin;
use CF\DataStruct\DataStruct;
use CF\DataStruct\Field\DataStructField;
use CF\DataStruct\Join\ObjectOneToManyJoin;
use CF\the;
use CF\Exception\DeveloperException;
use Exception;


/**
 * Class ter optimalisatie van de dataStructures (Zodat we dingen maar 1 keer hoeven laden e.d!)
 */
class DataStructManager {

	/**
	 * @var DataStructField[]
	 */
	protected $fields;

	/**
	 * @var DataStructJoin[]
	 */
	protected $joins;

	/**
	 * @var array('baseClassName' => array('fieldName' => <fieldName>, 'callBack' => <callBack>)
	 */
	protected $classExtendabilityInfo;

	private $loadingDefinitions;
	private $currentLoading;

	private $saverId;

	/**
	 * @var Mixed array met alle objecten die geladen zijn, wordt hier bijgehouden.
	 * //TODO: een garbagecollection functie maken die dingen er weer uit gooit als we ze niet meer gebruiken.
	 */
	private $universe;

	/**
	 * @var int Een id dat dit request uniek is.
	 */
	private $uniqueId;


	/**
	 * @var
	 */
	private $afterStoreCallbacks;


	/**
	 * Callbacks that are executed after registering a field.
	 * @see DatastructManager::addAutoCallbackAfterFieldRegistration
	 * @var String[]
	 */
	private $autoCallbacksAfterFieldRegistration;

	/**
	 * @return DataStructManager
	 */
	static function gI() {
		static $manager = NULL;
		if($manager === NULL) {
			$manager = new DataStructManager();
		}
		return $manager;
	}

	function __construct() {
		$this->fields = array();
		$this->keys = array();
		$this->joins = array();
		$this->loadingDefinitions = array();
		$this->currentLoading = NULL;
		$this->saverId = 0;
		$this->uniqueId = 0;
		$this->afterStoreCallbacks = array();
		$this->autoCallbacksAfterFieldRegistration = array();
		$this->classExtendabilityInfo = array();
	}

	/**
	 * Geef voor een classname de universal classname terug. Deze zorgt ervoor dat \Conscribo\Bla\ kan worden vergeleken met Conscribo\Bla\
	 * @param $className
	 * @return string
	 */
	public static function getUniversalClassName($className) {
		if(substr($className, 0,1) == '\\') {
			return $className;
		}
		return '\\'.$className;
	}

	/**
	 * @param DataStruct $object
	 * @return array
	 */
	public function getDataDefinitionsByObject($object) {
		return $this->getDataDefinitionsByClassName($object->_getClassName());
	}

	public function getDataDefinitionsByClassName($className) {
		$struct = array();
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}

		/**$struct['fields'] = array();
		 * foreach($this->fields[$className] as $key => $field) {
		 * $struct['fields'][$key] = clone $field;
		 * }
		 */
		$struct['fields'] = $this->fields[$className];
		$struct['joins'] = $this->joins[$className];
		return $struct;
	}


	/**
	 * @param $className
	 * @return DataStructJoin[]
	 */
	public function getDataJoins($className) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		return $this->joins[$className];
	}

	/**
	 * @param $className
	 * @return DataStructField[]
	 */
	public function getDataFields($className) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}

		return $this->fields[$className];
	}

	/**
	 * @param $className
	 * @return DataStructField
	 */
	public function getDataField($className, $fieldName) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		if(!isset($this->fields[$className][$fieldName])) {
			throw new Exception('Unknown field requested with dataStructManager: ' . $className . ':' . $fieldName);
		}
		return $this->fields[$className][$fieldName];
	}


	/**
	 * Maak een array met een key value array
	 * @param $row
	 */
	public function _getUniversalKeyArrayFromDatabaseRow($className, $row) {
		$className = self::getUniversalClassName($className);
		$res = array();
		foreach($this->getKeyFields($className) as $field) {
			if(!array_key_exists($field->getName(), $row)) {
				throw new DeveloperException('Insufficient information to retrieve object from universe. Key: ' . $fieldName . ' Needed: ' . implode(',', array_keys($keyFields)) . ' Given: ' . implode(',', array_keys($collectionKeys)));
			}
			$res[$field->getName()] = $row[$field->getName()];
		}
		return $res;
	}

	public function _getUniversalKeyFromDatabaseRow($className, $localRecord) {
		return implode('_', $this->_getUniversalKeyArrayFromDatabaseRow($className, $localRecord));
	}



	private function loadDefinitionsForClassName($className) {
		$className = self::getUniversalClassName($className);
		if(isset($this->loadingDefinitions[$className])) {
			throw new Exception('Cyclic definition loading detected! Help!', EXCEPTION_SITUATION_UNKNOWN);
		}

		$oldLoading = $this->currentLoading;
		$this->currentLoading = $className;

		$this->loadingDefinitions[$className] = true;

		$this->fields[$className] = array();
		$this->joins[$className] = array();

		if(!class_exists($className)) {
			throw new DeveloperException('Class ' . $className . ' not included or in autoload while loading definitions');
		}

		$struct = $className::InitDataDefinitions($this);

		if($struct !== NULL) {
			// Oude manier van registreren:

			foreach($struct['fields'] as $index => $field) {
				if($field === NULL || !($field instanceof DataStructField)) {
					$this->currentLoading = $oldLoading;
					throw new DeveloperException('invalid field definition for field :' . $index . ',' . var_export($field, true), EXCEPTION_RECORD_NOT_FOUND);
				}
				$this->fields[$className][$field->getName()] = $field;
			}

			if(isset($struct['joins'])) {
				foreach($struct['joins'] as $join) {
					// Joins toevoegen die op de oude manier zijn aangeleverd:
					$this->joins[$className][] = $join;
				}
			}
		}

		// Parseer en controleer de dataJoins:
		// foreign keys zijn ook dbkeys voor ons:

		foreach($this->joins[$className] as $dataJoin) {
			foreach($dataJoin->getForeignKey() as $localFieldName => $foreignFieldName) {
				//$this->fields[$className][$localFieldName]->isDBKey();
				// als het een onetoonejoin is, is de foreignkey ook een veld bij ons, en dus ook een key
				if($dataJoin->getJoinType() == DataStructJoin::TYPE_EXTENSION && $dataJoin->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {

					if(!isset($this->fields[$className][$foreignFieldName])) {
						$this->currentLoading = $oldLoading;
						throw new Exception('Invalid oneToOne ExtensionJoin. Foreignkey field ' . $className . ':' . $foreignFieldName . ' does not exist', EXCEPTION_RECORD_NOT_FOUND);
					}
					$this->fields[$className][$foreignFieldName]->isDBKey();
					$this->fields[$className][$foreignFieldName]->isForeignKey();
				}
			}
		}

		foreach($this->fields[$className] as $field) {
			/**
			 * @var DataStructField $field
			 */
			if($field->getIsDBKey() && !$field->getIsForeignKey()) {
				$this->keys[$className][$field->getName()] = $field;
			}
		}

		$this->clearAllAutoCallbacksAfterFieldRegistration();
		unset($this->loadingDefinitions[$className]);

		$this->currentLoading = $oldLoading;
	}

	public function getUniqueId() {
		$this->uniqueId++;
		return $this->uniqueId;
	}


	/**
	 * In plaats van de definitie van een object uit te gaan zoeken bij de foreign class, vragen we dit
	 * aan de manager, deze heeft dit resultaat wellicht in cache.
	 * @param string $ourClassName
	 * @param string $foreignClassName
	 * @return DataStructJoin Het join object zoals gedefinieerd aan de andere kant van de join.
	 * @throws Exception EXCEPTION_PRECONDITIONS_NOT_MET indien de join aan de foreign side niet bestaat.
	 */
	public function getObjectJoinFromForeignSide($ourClassName, $foreignClassName) {
		$ourClassName = self::getUniversalClassName($ourClassName);
		$foreignClassName = self::getUniversalClassName($foreignClassName);
		if(!isset($this->fields[$foreignClassName])) {
			$this->loadDefinitionsForClassName($foreignClassName);
		}

		foreach($this->joins[$foreignClassName] as $dataJoin) {
			/**
			 * @var ObjectOneToManyJoin $dataJoin
			 */
			if($dataJoin->getJoinType() !== DataStructJoin::TYPE_OBJECT) {
				// we hebben alleen twee definities bij objecten
				continue;
			}

			if($dataJoin->getForeignClassName() != $ourClassName) {
				continue;
			}

			return $dataJoin;
		}

		throw new Exception('Object join has no foreign side: ' . $ourClassName . ' => ' . $foreignClassName, EXCEPTION_PRECONDITIONS_NOT_MET);
	}

	/**
	 * geeft een fieldObject terug wat bij het datatype hoort.
	 * @param string $className
	 * @param string $fieldName
	 * @return DataStructField
	 */
	public function getFieldFromClassName($className, $fieldName) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		if(!isset($this->fields[$className][$fieldName])) {
			throw new Exception('Field requested from Class' . $className . ' that does not exists: ' . $fieldName);
		}
		return $this->fields[$className][$fieldName];
	}

	/**
	 * Bestaat een veld in de dataStruct
	 * @param string $className
	 * @param string $fieldName
	 * @return bool
	 */
	public function fieldExists($className, $fieldName) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		return isset($this->fields[$className][$fieldName]);
	}

	/**
	 * Geeft alle fieldnames terug die in een dataStruct zijn geregistreerd
	 * @param $className
	 * @return string[]
	 */
	public function getExistingFieldNames($className) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		return array_keys($this->fields[$className]);
	}

	/**
	 * @param $className
	 * @return string|null
	 */
	public function getVirtualKeyForClass($className) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		foreach($this->fields[$className] as $field) {
			/**
			 * @var DataStructField $field
			 */
			if($field->getIsVirtualCollectionKey()) {
				return $field->getName();
			}
		}
		return NULL;
	}

	private function getLoadingClassName() {
		if($this->currentLoading === NULL) {
			throw new DeveloperException('Nothing is loading, use only in context loadFieldDefinitions');
		}
		return $this->currentLoading;
	}

	/**
	 * Geeft terug of de class in definitiefase zit.
	 * @return bool
	 */
	public function getIsDefinitionsLoading() {
		foreach($this->loadingDefinitions as $className => $loading) {
			if($loading) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Geeft de keys terug die zijn geregistreerd
	 * @return DataStructField[]
	 */
	public function getKeyFields($className) {
		$className = self::getUniversalClassName($className);
		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}
		return $this->keys[$className];
	}

	/**
	 * Register a datastructfield, Can only be used in loadFieldDefinitions context
	 * @param DataStructField $field
	 * @throws DeveloperException not in load context
	 */
	public function registerField(DataStructField $field) {
		$className = $this->getLoadingClassName();
		if(isset($this->fields[$className][$field->getName()])) {
			throw new DeveloperException('Double definition for field ' . $field->getName() . ' in class ' . $className);
		}
		$this->fields[$className][$field->getName()] = $field;

		// autoCallbackAfterFieldRegistration:
		if(isset($this->autoCallbacksAfterFieldRegistration[$className])) {
			foreach($this->autoCallbacksAfterFieldRegistration[$className] as $callback) {
				call_user_func($callback, $field);
			}
		}
	}

	/**
	 * @param string $fieldName	 Fieldname in baseclass that determines which extended class to use when loading.
	 * @param String $determinerFunctionName Callback functionname in baseclass that uses fieldname to determine which class to use.
	 *                                       defaults to 'getSpecializationClassName'.
	 *                                       Example: there is one specialization of a class 'BaseAsdf' named 'ExtendedAsdf'. The value that determines which class to use is 'isAsdf' which contains the value 'asdf' when a specialization exists.
	 *                                       			in initDataDefinitions of the baseClass: DatastructManager::setClassExtendableVia('isAsdf');
	 *                                       			in baseclass: static function determineSpecialization($fieldValue) { if($fieldValue == 'asdf') { return '\\ExtendedAsdf' ;} else { return '\\BaseAsdf';}
	 */
	static function setClassExtendableVia($fieldName, $determinerFunctionName = 'getSpecializationClassName') {
		DataStructManager::gI()->registerExtendableHandler($fieldName, $determinerFunctionName);
	}

	public function registerExtendableHandler($fieldName, $determinerFunctionName = NULL) {
		$className = $this->getLoadingClassName();

		if($determinerFunctionName === NULL) {
			$determinerFunctionName = 'getSpecializationClassName';
		}

		$this->classExtendabilityInfo[$className] = array('fieldName' => $fieldName,
														  'determinerFunctionName' => $determinerFunctionName);
	}

	/**
	 * Do extensions exist for given baseClassName?
	 * @param string $className
	 * @return bool
	 */
	public function isExtendableClass($className) {
		$className = self::getUniversalClassName($className);

		if(!isset($this->fields[$className])) {
			$this->loadDefinitionsForClassName($className);
		}

		return isset($this->classExtendabilityInfo[$className]);
	}

	/**
	 * @param $className
	 * @return array('fieldName' => <fieldName>, 'determinerFunctionName' => <functionName in class to call to determine>)
	 * @throws DeveloperException
	 */
	public function getExtendabilityInfo($className) {
		$className = self::getUniversalClassName($className);
		if(!$this->isExtendableClass($className)) {
			throw  new DeveloperException('Class is not extendable, check first with isExtendableClass');
		}
		return $this->classExtendabilityInfo[$className];
	}

	/**
	 * StartTableContext: All fields that are registered after calling this function will automatically get a databaseField assigned with the name converted from camelCase to lowercase_underscore
	 * e.g. acceptantReturnUrl > acceptant_return_url
	 * @param string $tableName  the table on which to assign
	 * @return DatastructManager
	 */
	public static function startReadOnlyDBTable($tableName) {
		self::startDBTable($tableName);
		DataStructManager::gI()->addAutoCallbackAfterFieldRegistration(
			function (DataStructField $field) {
				$field->isReadOnly(true);
			},
			'autoIsReadOnly'
		);
	}

	/**
	 * StartTableContext: All fields that are registered after calling this function will automatically get a databaseField assigned with the name converted from camelCase to lowercase_underscore
	 * e.g. acceptantReturnUrl > acceptant_return_url
	 * @param string $tableName  the table on which to assign
	 * @return DatastructManager
	 */
	public static function startDBTable($tableName) {
		self::endDBTable();

		DataStructManager::gI()->addAutoCallbackAfterFieldRegistration(
			function (DataStructField $field) use ($tableName) {

				$dbFieldName = mb_strtolower(preg_replace('([A-Z]{1})', '_$0', $field->getName()));
				$field->setDatabaseFieldName($tableName, $dbFieldName);

			},
			'autoStartDBTable');
	}

	/**
	 * Add a callback function that is executed after each fieldRegistration in the current defining class. It is called with one DatastructField as param
	 * @param Callable $callback
	 * @param String   $identifier an identifier to make it possible to clear the callback.
	 * @throws DeveloperException if an identifier already exists
	 */
	public function addAutoCallbackAfterFieldRegistration($callback, $identifier = NULL) {
		$className = $this->getLoadingClassName();

		if(!isset($this->autoCallbacksAfterFieldRegistration[$className])) {
			$this->autoCallbacksAfterFieldRegistration[$className] = array();
		}
		if($identifier === NULL) {
			$identifier = 0;
			while(isset($this->autoCallbacksAfterFieldRegistration[$className][$identifier])) {
				$identifier++;
			}
		}
		if(isset($this->autoCallbacksAfterFieldRegistration[$className][$identifier])) {
			throw new DeveloperException('Attempt to add an autoCallbackAfterFieldRegistration with the same identifier as used earlier in class: ' . $className . ', identifier: ' . $identifier);
		}

		$this->autoCallbacksAfterFieldRegistration[$className][$identifier] = $callback;
	}

	/**
	 * Remove an autocallback as defined with addAutoCallbackAfterFieldRegistration
	 * @param $identifier
	 */
	public function clearAutoCallbackAfterFieldRegistration($identifier) {
		$className = $this->getLoadingClassName();
		unset($this->autoCallbacksAfterFieldRegistration[$className][$identifier]);
	}

	/**
	 * Remove all autocallbacks as defined with addAutoCallbackAfterFieldRegistration
	 */
	public function clearAllAutoCallbacksAfterFieldRegistration() {
		$className = $this->getLoadingClassName();
		$this->autoCallbacksAfterFieldRegistration[$className] = array();
	}

	/**
	 * Stop de databasedefinitie bepaald door StartDBTable
	 */
	public static function endDBTable() {
		DataStructManager::gI()->clearAutoCallbackAfterFieldRegistration('autoStartDBTable');
		DataStructManager::gI()->clearAutoCallbackAfterFieldRegistration('autoIsReadOnly');
	}


	/**
	 * Register a DataStructJoin, Can only be used in loadFieldDefinitions context
	 * @param DataStructJoin $join
	 * @throws DeveloperException not in loadcontext
	 */
	public function registerJoin(DataStructJoin $join) {
		$className = $this->getLoadingClassName();
		$this->joins[$className][] = $join;
	}

	/**
	 * Retreive a join by an identifier set by setIdentifier()
	 * @param string $className
	 * @param string $joinIdentifier
	 * @throws DeveloperException if join cannot be found
	 */
	public function getJoinDefinitionByIdentifier($className, $joinIdentifier) {
		if(!isset($this->joins[$className])) {
			throw new DeveloperException('Class '. $className .' not found, or it has no joins');
		}
		foreach($this->joins[$className] as $join) {
			/**
			 * @var DataStructJoin $join
			 */
			if($join->getIdentifier() == $joinIdentifier) {
				return $join;
			}
		}
		throw new DeveloperException('Join with identifier '. $joinIdentifier .' not found in class '. $className);
	}

	/**
	 * Voeg een object toe aan het universe
	 * @param DataStruct|object $object
	 */
	public function addObjectToUniverse($object) {
		$objectType = $object->_getClassName();
		$key = $object->getUniversalKey();
		$this->universe[$objectType][$key] = $object;
	}

	public function removeObjectFromUniverse($object) {
		$objectType = $object->_getClassName();
		$key = $object->getUniversalKey();
		unset($this->universe[$objectType][$key]);
	}


	/**
	 * Haal een object uit het universe
	 * @param string $objectType     Classname die we zoeken
	 * @param mixed $collectionKeys associative array met de keys waarop we zoeken
	 * @return null | DataStruct
	 * @throws \CF\Exception\DeveloperException als niet alle keys zijn meegegeven.
	 */
	public function getObjectFromUniverse($objectType, $collectionKeys) {
		$objectType = self::getUniversalClassName($objectType);
		if(!isset($this->universe[$objectType])) {
			return NULL;
		}

		$universalKey = $this->_getUniversalKeyFromDatabaseRow($objectType, $collectionKeys);

		if(isset($this->universe[$objectType][$universalKey])) {
			return $this->universe[$objectType][$universalKey];
		}
		return NULL;
	}

	public function getObjectFromUniverseWithUniversalKey($objectType, $universalKey) {
		if(isset($this->universe[$objectType][$universalKey])) {
			return $this->universe[$objectType][$universalKey];
		}
		return NULL;
	}

	/**
	 * Voor testdoeleinden, maak het universe leeg
	 */
	public function clearUniverse() {
		$this->universe = array();
	}


	/**
	 * Een object geeft aan dat deze als executing laag een opslagtaak gaat uitvoeren.
	 */
	public function registerExecutingSaver() {
		$this->saverId++;
	}

	/**
	 * een object vraagt dat niet executing is, vraagt op welke saveactie verantwoordelijk is voor het uitvoeren van de opslagtaak ter behoefte van ontdubbeling bij het opslaan.
	 * @return int
	 */
	public function getExecutingSaverId() {
		return $this->saverId;
	}


	/**
	 * De aanroeper claimt de verantwoordelijkheid voor het uitvoeren van het opslaan. Dit kan een datastruct object zelf zijn, of een collection die hierbovenhangt.
	 * @return array
	 */
	public function _claimStoreExecution() {

		// De sqlStatements zijn opgebouwd aan de hand van een aantal inserts updates en deletes die later in 1 keer worden uitgevoerd.
		// omdat verschillende classes theoretisch dezelfde tabellen kunnen updaten, met andere definities (andere geladen velden b.v.) wordt
		// er een scheiding gemaakt eerst op className, en dan op tableName. Meerdere updates op 1 tabel van dezelfde classes, kunnen dus in 1 keer worden verwerkt,
		// terwijl meerdere updates van verschillende classes op 1 tabel apart zal worden uitgevoerd.

		$sqlStatements = array('keys' => array(), 'cols' => array(), 'insert' => array(), 'update' => array(), 'delete' => array());
		DataStructManager::gI()->registerExecutingSaver();
		return $sqlStatements;
	}


	public function _executeStore($sqlStatements) {
		if(count($sqlStatements['delete']) > 0) {
			$this->_executeDeletes($sqlStatements['delete'], $sqlStatements['keys']);
		}

		if(count($sqlStatements['update']) > 0) {
			$this->_executeUpdates($sqlStatements['update'], $sqlStatements['keys'], $sqlStatements['cols']);
		}

		if(count($sqlStatements['insert']) > 0) {
			$this->_executeInserts($sqlStatements['insert'], $sqlStatements['keys'], $sqlStatements['cols']);
		}
	}


	private function _executeDeletes($records, $keys) {
		foreach($records as $className => $tables) {
			foreach($tables as $tableName => $updateInfo) {
				if(count($updateInfo['records']) == 0) {
					continue;
				}
				if(count($keys[$className][$tableName]) == 0) {
					throw new Exception('Table ' . $tableName . ' has no keydefinitions!, add keyDefinitions with field->isDBKey();', EXCEPTION_INVALID_DATA_KEY);
				}

				db()->multipleDelete($tableName, array_values($keys[$className][$tableName]), $updateInfo['records']);
			}
		}
	}

	/**
	 * Updaten van records in de db
	 * @param $records = array(<className>=> <tableName> => array('keys' => , 'cols' => , 'records' => )
	 */
	private function _executeUpdates($records, $keys, $cols) {
		// per table gaan we een multipleupdate uitvoeren:
		foreach($records as $className => $tables) {
			foreach($tables as $tableName => $updateInfo) {
				if(count($updateInfo['records']) == 0) {
					continue;
				}

				// op zoek naar velden met meerdere dbkolommen (die moeten worden uitgesplitst)
				// Note: een arraykolom kan geen key zijn!!!
				foreach($cols[$className][$tableName] as $fieldName => $columns) {
					if(!is_array($columns)) {
						continue;
					}
					// de kolommen opslitsen naar tablecols:
					foreach($columns as $dbCol) {
						$cols[$className][$tableName][$dbCol] = $dbCol;
					}
					unset($cols[$className][$tableName][$fieldName]);
				}

				if(count($keys[$className][$tableName]) == 0) {
					throw new Exception('Table ' . $tableName . ' has no keydefinitions!, add keyDefinitions with field->isDBKey();', EXCEPTION_INVALID_DATA_KEY);
				}

				db()->multipleUpdate($tableName, array_values($cols[$className][$tableName]), array_values($keys[$className][$tableName]), $updateInfo['records']);
			}
		}
	}

	/**
	 * Toevoegen van records in de db
	 * @param $records = array(<className>=> <tableName> => array('keys' => , 'cols' => , 'records' => )
	 */
	private function _executeInserts($records, $keys, $cols) {
		// per table gaan we een insertstatement uitvoeren:

		foreach($records as $className => $tables) {
			foreach($tables as $tableName => $insertInfo) {
				if(count($insertInfo['records']) == 0) {
					continue;
				}
				$inserts = array();
				foreach($insertInfo['records'] as $record) {
					$sqlInsertValues = array();

					foreach($cols[$className][$tableName] as $fieldName => $columns) {
						if(is_array($columns)) {
							foreach($columns as $dbCol) {
								$sqlInsertValues[] = $record[$dbCol];
							}
						} else {
							$sqlInsertValues[] = $record[$columns];
						}
					}
					$inserts[] = '(' . implode(',', $sqlInsertValues) . ')';
				}
				$columns = array();
				foreach($cols[$className][$tableName] as $fieldName => $_cols) {
					if(is_array($_cols)) {
						foreach($_cols as $dbCol) {
							$columns[] = '`'. $dbCol .'``';
						}
					} else {
						$columns[] = '`'. $_cols .'`';
					}
				}
				if(count($columns) > 0) {
					// Later: opsplitsen in chunks van max een stuk of 500;
					$sqlString = 'INSERT INTO `' . $tableName . '` (' . implode(',', $columns) . ') VALUES ' . implode(',', $inserts);
					db()->query($sqlString);
				}
			}
		}
	}


	public function addAfterStoreCallback($callBack) {
		$this->afterStoreCallbacks[] = $callBack;
	}

	public function executeAfterStoreCallbacks() {
		$callBacks = $this->afterStoreCallbacks;
		$this->afterStoreCallbacks = array();

		foreach($callBacks as $callBack) {
			call_user_func($callBack);
		}
	}

}
