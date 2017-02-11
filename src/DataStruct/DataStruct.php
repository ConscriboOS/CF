<?php
namespace CF\DataStruct;
use CF;
use CF\DataStruct\Field\DataStructField;
use CF\DataStruct\Join;
use CF\DataStruct\Join\DataStructJoin;
use CF\Exception\DeveloperException;
use Conscribo\CF\DataStructXMLBackup;
use ConscriboForm;
use Exception;


/**
 * Deze nieuwe trait is ontworpen voor gebruik met 'dataobjecten' Deze objecten worden een vervanging van de vele structs die nu Conscribo verlossen van los argumetnen heen en weer fietsen.
 * een Dataobject kan beter controle houden op de interne datastruct.
 *
 * Het nadeel van een object is dat de Json representatie niet meer dan een array bevat. en dat deze niet weer terug decode naar dat dataobject. Met deze trait kan een object worden voorzien van een json representatie die dat wel kan.
 */
trait DataStruct {

	/**
	 * @flag JSON_ENCODE_OBJECT_IGNORE
	 * @var DataStructField[] voor automatische afhandeling van dataStructs
	 */
	//protected $fieldInfo;

	/**
	 * Bevat informatie over de tabelnamen die gebruikt worden
	 * @var array
	 */
	//protected $tableNames;

	/**
	 * Bevat informatie over de joins die in de datastruct kunnen bestaan.
	 * @var array $joinInfo array([0..n] => [joinObject])
	 */
	protected $joinInfo;


	/**
	 * Houdt de wijzigingsstatus bij van de betrokken tabellen
	 * @var array, per table=> enum: new, unchanged, changed, deletePending
	 */
	protected $recordStatus;

	/**
	 * Bevat het record zoals dit het laatst bekend in de database stond. dit wordt gebruikt voor het controleren of de data gewijzigd is.
	 * @var array, per table => fieldName => value
	 */
	private $dbRecord;

	/**
	 * Houdt de wijzigingsstatus bij van de OnetoMany extension joins
	 * @var array, per property => per record => enum: new, unchanged, changed, deletePending
	 */
	protected $childRecordStatus;

	/**
	 * Bevat de childRecords zoals dit het laatst bekend in de database stond. dit wordt gebruikt voor het controleren of de data gewijzigd is.
	 * @var array, per property => per record => per field => value
	 */
	private $dbChildRecord;

	/**
	 * Houdt voor elke 1 to many join bij hoe het record in het lokale object moet worden omschreven.
	 * @var array('type' => joinType
	 *              'keyField' => fieldName)
	 */
	protected $childProperties;

	/**
	 * @var bool[]  associative array, is the property loaded. This is only use with objectOneToManyJoins to see if the join is ready for use.
	 */
	protected $propertyLoaded;


	/**
	 * @var int, geeft een volgnummer aan de storeactie die in dit process aangeroepen is, waarbij dit object voor het laatst is opgeslagen.
	 * Wordt gebruikt ter ontdubbeling van objecten die worden opgeslagen via joins
	 */
	protected $lastSaverId;

	protected $_className;

	/**
	 * notificeer de trait als deze in een jsonobject kan worden getransformeerd
	 */
	protected function dataStructNotifyUseJsonObject() {
//		$this->jsonIgnoreProperty('fieldInfo');
	}

	/**
	 * Laad het object met de juiste values in de key.
	 * Deze methode maakt gebruik van de universele cache
	 * @param mixed[] $keyValues
	 * @throws Exception Record Not Found
	 */
	static function _load($keyValues, $forceReload = false) {
		$className = get_called_class();

		if(!$forceReload && ($obj = DataStructManager::gI()->getObjectFromUniverse($className, $keyValues))) {
			return $obj;
		}

		$localRecord = NULL;

		// use this class as baseclass and load extension first:
		if(DataStructManager::gI()->isExtendableClass($className)) {
			$ghost = new DataStructGhost($className);
			$ghost->loadBaseData($keyValues);

			$className = $ghost->determineClassName();
		}


		$obj = new $className();
		/**
		 * @var DataStruct $obj ;
		 */
		foreach($keyValues as $keyName => $value) {
			$obj->set($keyName, $value);
		}
		$obj->_loadDataStruct();
		return $obj;
	}


	/**
	 * Uit te voeren na een object is aangemaakt en juiste data heeft.
	 */
	protected function _afterCreate() {
		DataStructManager::gI()->addObjectToUniverse($this);
	}

	/**
	 * Functie om het object te kunnen laden met een datastruct record.
	 * Omdat we in een trait zitten weten we niets van de constructor. Daarom moet als de constructor er
	 * anders uit ziet, deze functie worden overschreven
	 * (Ik had liever een abstracte functie ervan gemaakt, maar dat kan niet)
	 * @param string $className de class van het object wat we instantieren
	 * @param array $dbRecord , het base dbRecord van het object
	 * @param NULL | DataStruct $governingObject , vanuit welk object wordt dit object geladen
	 * @param NULL | array $childRecords
	 */
	static function loadWithDBRecord($className, $dbRecord, $governingObject, $childRecords = NULL, $universalKey = NULL) {

		$obj = new $className();
		$obj->_className = $className;
		$obj->_universalKey = $universalKey;

		/**
		 * @var DataStruct $obj
		 */
		$obj->setGoverningObject($governingObject);
		// We laden de collections wel als executing laag.
		$obj->_loadDataStruct($dbRecord, $childRecords, $governingObject === NULL);
		return $obj;
	}

	/**
	 * Informeer de class dat als er collections van dit object worden gemaakt (zoals in een dataObjectJoin), deze class moet worden gebruikt.
	 * @param String $collectionClassName (uses dataStructCollection)
	 */
	static function setDefiningCollectionClassName($collectionClassName) {
		DataStructCollectionManager::gI()->setDefiningCollectionName(get_class(), $collectionClassName);
	}


	/**
	 * DatastructCloned zorgt ervoor dat een object weer stabiel is nadat deze is gecloned.
	 * De functie dient NA eventuele nieuwe id toewijzingen te worden uitgevoerd, het object wordt toegevoegd aan het universe.
	 */
	public function dataStructCloned() {
		$this->recordStatus = array();
		$this->_universalKey = NULL;

		$struct = DataStructManager::gI()->getDataDefinitionsByObject($this);

		foreach($struct['fields'] as $field) {
			$tableName = $field->getTableName();
			$this->recordStatus[$tableName] = 'new';

		}

		DataStructManager::gI()->addObjectToUniverse($this);

		// TODO Clone ondersteund nu nog geen geclonede join. Deze wordt niet per definitie goed gereset.

	}


	protected function _fieldExists($fieldName) {
		return DataStructManager::gI()->fieldExists($this->_getClassName(), $fieldName);
	}

	/**
	 * @param $fieldName
	 * @return DataStructField
	 */
	protected function _getFieldInfoForField($fieldName) {
		return DataStructManager::gI()->getDataField($this->_getClassName(), $fieldName);
	}
	/**
	 * @return DataStructField[]
	 */
	protected function _getFieldInfo() {
		return DataStructManager::gI()->getDataFields($this->_getClassName());
	}

	protected function initDataStruct() {

		$struct = DataStructManager::gI()->getDataDefinitionsByObject($this);

	//	$this->fieldInfo = array();
		$this->joinInfo = array();

		$this->childProperties = array();
		$this->recordStatus = array();

		$this->governingObject = NULL;

		foreach($struct['fields'] as $field) {
			$tableName = $field->getTableName();
			$this->recordStatus[$tableName] = 'new';
			$this->addField($field);
		}

		if(isset($struct['joins'])) {
			foreach($struct['joins'] as $join) {
				$this->addJoin($join);
			}
		}
	}

	/**
	 * Geeft de datastruct aan vanuit welk object deze is geinstansieerd, zodat hier rekening mee gehouden kan worden bij het laden (en opslaan).
	 */
	protected function setGoverningObject($object) {
		$this->governingObject = $object;
	}


	/**
	 * Geeft aan of dit object in de database bestaat
	 * @return bool
	 */
	public function getObjectExistsInDb() {
		foreach($this->recordStatus as $tableName => $status) {
			if($status == 'new') {
				return false;
			}
		}
		return true;
	}

	/**
	 * Vertel de datastruct dat deze is gewijzigd.
	 * Dit is niet vereist, maar zorgt ervoor dat het systeem sneller kan werken
	 */
	protected function touchField($fieldName) {
		$tableName = $this->_getFieldInfoForField($fieldName)->getTableName();
		if($this->recordStatus[$tableName] == 'unchanged') {
			$this->recordStatus[$tableName] = 'changed';
		}
	}

	/**
	 * Vertel de datastruct dat een chilrecord is gewijzigd
	 */
	protected function touchChildRecord($propertyName, $recordKey, $remove = false) {
		if($remove) {
			$this->childRecordStatus[$propertyName][$recordKey] = 'remove';
		}
		if(!isset($this->childRecordStatus[$propertyName][$recordKey])) {
			$this->childRecordStatus[$propertyName][$recordKey] = 'new';
		}

		if($this->childRecordStatus[$propertyName][$recordKey] == 'unchanged') {
			$this->childRecordStatus[$propertyName][$recordKey] = 'changed';
		}
	}

	protected function addField(DataStructField $field) {
		//$this->fieldInfo[$field->name] = $field;
		$fieldName = $field->name;
		$this->$fieldName = $field->getDefault();

	}

	protected function addJoin(DataStructJoin $join) {
		$this->joinInfo[] = $join;

		// voor alle onetomany records wordt bijgehouden wat de status daarvan is.
		if($join->getJoinOrder() != DataStructJoin::ORDER_ONE_TO_ONE) {
			$propertyName = $join->getParentPropertyName();

			if($join->getJoinType() == DataStructJoin::TYPE_OBJECT) {
				if($join->getOurSide() == DataStructJoin::ONE) {
					$this->$propertyName = DataStructCollectionManager::gI()->getEmptyCollectionForObjectType($join->getForeignClassName());
				} else {
					$this->$propertyName = NULL;
				}
			} else {
				$this->$propertyName = array();
			}
			$this->childRecordStatus[$propertyName] = array();
			$this->childProperties[$propertyName] = array('type' => $join->getJoinType(), 'keyField' => $join->getKeyFieldName());
		}
	}


	public function addFieldsToForm(ConscriboForm $form) {
		foreach($this->_getFieldInfo() as $key => $field) {
			if($field->flags[DataStructField::USE_IN_FORMS]) {
				if(property_exists($this, $key)) {
					$field->addToForm($form, $this->$key);
				} else {
					$field->addToForm($form);
				}
			}
		}
	}


	/**
	 * Haalt de waarden uit een formulier en zet deze in de datastruct.
	 * @param ConscriboForm $form
	 * @return \CF\ErrorCollection
	 */
	public function populateFromForm(ConscriboForm $form) {

		$formValues = $form->getFormValues();
		$errors = CF\Runtime::gI()->createErrorCollection();

		foreach($this->_getFieldInfo() as $name => $field) {
			$code = $field->code;
			if(array_key_exists($code, $formValues)) {
				if(!$field->validateValue($formValues[$code], $errors)) {
					return $errors;
				}
				if($this->$name !== $formValues[$code]) {
					$this->set($name, $formValues[$code]);
				}
			}
		}

		if(method_exists($this, 'validateForm')) {
			$this->validateForm($form, $errors);
		}
		return $errors;
	}

	/**
	 * Controleert de datastruct op fouten.
	 * @return \CF\ErrorCollection errors
	 */
	public function validate($errors = NULL) {
		if($errors === NULL) {
			$errors = \CF\Runtime::gI()->createErrorCollection();
		}
		foreach($this->_getFieldInfo() as $name => $field) {
			if(property_exists($this, $name)) {
				$res = $field->validateValue($this->$name, $errors);
			} else {
				$res = $field->validateValue(NULL, $errors);
			}
			if(!$res) {
				return $errors;
			}
		}

		// als er childobjecten zijn, dan controleren we deze ook:
		foreach($this->joinInfo as $joinIndex => $joinInfo) {
			// is de join een uitbreiding op de velden (is altijd oneToOne),dan wordt deze al door de normale set gecontroleerd.
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION && $joinInfo->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {
				continue;
			}

			// records valideren:
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION) {
				$propertyName = $joinInfo->getParentPropertyName();
				$recordFieldInfo = $joinInfo->getFieldInfo();

				foreach($this->$propertyName as $key => $record) {
					foreach($recordFieldInfo as $name => $field) {
						if(!is_array($record)) {
							throw new DeveloperException('Non array value found in property of extended table:' . $propertyName . ' [' . $name . ']' . var_export($record, true));
						}
						if(key_exists($name, $record)) {
							$res = $field->validateValue($record[$name], $errors);
						} else {
							$res = $field->validateValue(NULL, $errors);
						}
					}
				}
			}

			// objecten valideren:
			// TODO: ook validatie bijhouden
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_OBJECT) {
				if($joinInfo->getOurSide() != DataStructJoin::ONE) {
					// we zijn ergens een child van. we 'gaan niet over' de parent.
					continue;
				}
				$propertyName = $joinInfo->getParentPropertyName();

				// de Property wordt opgeslagen, maar als we deze uberhaupt niet hebben geladen, dan is het goed om alleen WAT we geladen hebben te valideren
				if(is_array($this->$propertyName)) {
					throw new Exception('Property ' . $propertyName . ' of ' . get_class($this) . ' initialized as array. This is used in an objectJoin and should be a DataStructCollection');
				}
				if(is_array($this->$propertyName->_getLoadedObjects())) {
					foreach($this->$propertyName->_getLoadedObjects() as $key => $object) {
						$object->validate($errors);
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Set een property. Indien de property een oneToManyJoin gebruikt, wordt adhv de gegevens automatisch de key bepaald.
	 */
	protected function set($property, $value) {
		if($this->isChildProperty($property)) {
			$key = $this->_getKeyFromValueForChildProperty($property, $value);
			$thisProp = &$this->$property;
			$thisProp[$key] = $value;
			$this->touchChildRecord($property, $key);
		} else {
			$this->$property = $value;
			$this->touchField($property);
		}
	}

	/**
	 * Geeft een waarde terug uit het object (gegeven dat deze readable is)
	 * @param $property
	 * @param $_internal = false
	 */
	public function _getValue($property, $_internal = false) {

		if(!$_internal && !$this->_getFieldInfoForField($property)->getIsReadableProperty()) {
			throw new Exception('Cannot read property ' . $property);
		}
		return $this->$property;
	}

	/**
	 * @param string $property
	 * @param null $format Nog te implementeren
	 * @return mixed
	 */
	public function _getValueFormatted($property, $format = NULL) {
		$value = $this->_getValue($property);
		return $this->_getFieldInfoForField($property)->formatValue($value, $format);
	}


	/**
	 * Unset een property. Indien de property een oneToManyJoin gebruikt, wordt adhv de gegevens automatisch de key bepaald.
	 */
	protected function removeProperty($property, $key = NULL) {
		if($this->isChildProperty($property)) {
			$thisProp = &$this->$property;
			unset($thisProp[$key]);
			$this->touchChildRecord($property, $key, true);
		} else {
			unset($this->$property);
			$this->touchField($property);
		}
	}

	/**
	 * Laad de data zoals gedefinieerd in de datastruct in.
	 * Op later moment moet hier een lazy loading algoritme in komen (of als optie worden meegegeven, maar op dit moment houden we daar nog even afstand van)
	 * @throws EXCEPTION_RECORD_NOT_FOUND
	 * @param         childRecords = array(<joinType> => array(<parentPropertyName> => array(<joinRecord>))
	 * @param boolean $forceExecuting zijn we de executing laag (ook al geven we wel een record mee). In de executing laag wordt meer geladen (alle references) dan in andere lagen
	 */
	protected function _loadDataStruct($localRecord = NULL, $childRecords = NULL, $forceExecuting = false) {
		// Eerst basistabel met onetoonejoins lezen:

		$executing = ($localRecord === NULL || $forceExecuting);

		if($localRecord === NULL) {
			$localRecord = $this->_loadLocalRecord();
			if($localRecord === NULL) {
				return;
			}
		}

		$this->_hidrateWithDBRecord($localRecord);

		DataStructManager::gI()->addObjectToUniverse($this);

		// Nu de oneToManyJoins:
		foreach($this->joinInfo as $joinId => $joinInfo) {
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION && $joinInfo->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {

				// Deze zijn al geladen:
				continue;
			}

			$propertyName = $joinInfo->getParentPropertyName();

			// extensions laden:
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION) {

				if(isset($childRecords[DataStructJoin::TYPE_EXTENSION][$propertyName])) {
					// is door een andere class al geladen:
					$joinChildRecords = $childRecords[DataStructJoin::TYPE_EXTENSION][$propertyName];
				} else {
					$joinChildRecords = $this->_loadRecordsForOneToManyExtensionJoin($joinId);
				}

				// het record in de property zetten:
				$this->$propertyName = array();

				// duplicaat in localRecord voor het updaten later:
				$this->dbChildRecord[$propertyName] = array();

				// reference maken naar de property (php kan anders niet uniek resolven):
				$prop = &$this->$propertyName;
				$fields = $joinInfo->getFieldInfo();

				foreach($joinChildRecords as $row) {

					$record = array();
					foreach($fields as $fieldName => $field) {
						$record[$fieldName] = NULL;
						$this->_setDBProperty($row, $field, $record[$fieldName]);
					}
					// record in de properties zetten:
					$key = $this->_getKeyFromValueForChildProperty($propertyName, $record);

					$prop[$key] = $record;
					$this->dbChildRecord[$propertyName][$key] = $record;
					$this->childRecordStatus[$propertyName][$key] = 'unchanged';
				}
				continue;
			}

			// als laatste: objecten laden
			/**
			 * @var DataStructJoin $joinInfo
			 */
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_OBJECT) {

				$parentClassName = $joinInfo->getForeignClassName();
				$governingClassName = NULL;
				if(isset($this->governingObject)) {
					$governingClassName = $this->governingObject->_getClassName();
				}
				if($joinInfo->getOurSide() == DataStructJoin::ONE) {
					// we bevatten meer objecten

					// creer collection met deze objecten
					$collection = DataStructCollectionManager::gI()->getNewCollectionForObjectType($joinInfo->getForeignClassName());

					// vul de keys in
					$keys = $joinInfo->getForeignKey();
					foreach($keys as $localField => $foreignField) {
						$collection->createFilter($foreignField)->equals($this->_getValue($localField));
					}

					if(!$executing && $governingClassName == $parentClassName) {
						// we zijn wellicht executing, maar we worden geladen door onze parent, we willen nu de parent partial in de collection plaatsen:
						$collection->placeLoaderObject($this->governingObject);
					} else {
						// We doen aanlazy loading,
						// en ook daar hoeven we niets voor te doen.
					}

					$this->$propertyName = $collection;

					continue;
				} else {

					// we zijn de N zeide van een join, daarom laden we het object als deze het governing object is, anders laden we het object niet.
					if($governingClassName == $parentClassName) {

						$this->$propertyName = $this->governingObject;
						$this->propertyLoaded[$propertyName] = true;
						$this->childRecordStatus[$propertyName][$key] = 'unchanged';
						continue;
					} else {
						if($executing) {
							$this->_loadDataForObjectJoin($joinInfo);
						}
					}
				}

				// Objecten kunnen worden geladen vanuit andere objecten. Hier moeten we rekening mee houden bij het laden. Op het moment dat we niet de executing laag zijn, moeten we ervoor zorgen dat we niet zelf al onze childlagen gaan laden
				// Regel: We laden 1 object upstream (bv alleen transaction van transactionTask (zonder dat transaction andere objecten meelaadt)
				//		 We laden alle objecten downstream. Deze objecten hebben hopelijk geen objecten upstream.
				// TODO: Het kan nu dus voorkomen dat objecten niet zijn geladen, dit moet eigenlijk automatisch worden bijgeladen wanneer het gebruikt gaat worden!

			}
		}
		$this->afterDataStructLoad();
	}



	public function _getClassName() {
		if($this->_className === NULL) {
			$this->_className = DataStructManager::getUniversalClassName(get_class($this));
		}
		return $this->_className;
	}

	protected function _loadLocalRecord() {

		$ourClassName = $this->_getClassName();

		// zorg dat joins goed worden gedistribueerd:
		$this->_updateForeignKeys();

		$keys = array();
		$dbPresent = false;

		// local
		static::_createDBSelectBlockForLocalData($ourClassName, $ourClassName);

		foreach($this->_getFieldInfo() as $name => $field) {
			if($field->getDatabaseFieldName() === NULL) {
				// geen DB ondersteuning
				continue;
			}
			$fieldNames = $field->getDatabaseFieldName();

			// De veldnamen kunnen er 1 of meer zijn, we zorgen daarom dat het type goed wordt:
			if(!is_array($fieldNames)) {
				$fieldNames = array($fieldNames);
			}

			foreach($fieldNames as $fieldName) {

				if($field->getIsDBKey() && !$field->getIsForeignKey()) {

					$fullDBFieldName = $field->getTableName() . '.`' . $fieldName . '`';

					$keys[$fullDBFieldName] = $field->dbFormat($this->$name);
				}
			}
			$dbPresent = true;
		}

		// keys:
		$explination = array();
		foreach($keys as $fieldName => $value) {
			$explination[] = $fieldName . ' = ' . $value;
			gR()->db()->addWhere($ourClassName, $fieldName, '=', $value);
			$dbPresent = true;
		}

		if(!$dbPresent) {
			return NULL;
		}

		gR()->db()->queryBlock($ourClassName);

		$localRecord = gR()->db()->fetchAssoc();
		if($localRecord === false) {
			throw new Exception('Record Not Found:' . implode(', ', $explination), EXCEPTION_RECORD_NOT_FOUND);
		}
		return $localRecord;
	}

	/**
	 * @param Join\ObjectOneToManyJoin $join
	 * @return array the foreign key of this object.
	 * 			@optimize It would be nicer to be able to return a string for faster processing, but we cannot guarantee foreignkey order alignment with fielddefs.
	 * @throws DeveloperException
	 */
	private function getUniversalForeignKey(Join\ObjectOneToManyJoin $join) {
		$universalFK = array();
		// stel de foreign key op voor het ene record
		foreach($join->getForeignKey() as $localKeyFieldName => $foreignKeyFieldName) {

			if(!$this->_fieldExists($localKeyFieldName)) {
				throw new DeveloperException('Cannot find local field ' . $localKeyFieldName . ' in class , used in join with ' . $join->getForeignClassName());
			}
			$value = $this->_getFieldInfoForField($localKeyFieldName)->dbFormat($this->$localKeyFieldName);
			$universalFK[$foreignKeyFieldName] = $value;
		}

		return $universalFK;
	}

	private function _loadDataForObjectJoin(Join\ObjectOneToManyJoin $join, $force = true) {
		// Try to load all objects in one go:

		$propertyName = $join->getParentPropertyName();

		if(!$force && isset($this->propertyLoaded[$propertyName]) && $this->propertyLoaded[$propertyName]) {
			return;
		}
		// destruct alle bestaande objecten:
		$this->$propertyName = NULL;
		$prop = &$this->$propertyName;

		$this->propertyLoaded[$propertyName] = true;

		$childClassName = $join->getForeignClassName();
		if($join->getOurSide() == DataStructJoin::MANY) {
			$universalFK = $this->getUniversalForeignKey($join);
			$prop = $childClassName::_load($universalFK);
			return;
		}

		// We have many childs

		$collection = DataStructCollectionManager::gI()->getNewCollectionForObjectType($join->getForeignClassName());

		foreach($join->getForeignKey() as $localKeyFieldName => $foreignKeyFieldName) {
			if(!$this->_fieldExists($localKeyFieldName)) {
				throw new DeveloperException('Cannot find local field ' . $localKeyFieldName . ' in class , used in join with ' . $childClassName);
			}
			$collection->createFilter($foreignKeyFieldName)->equals($this->$localKeyFieldName);
		}
		$prop = $collection;
	}

	/**
	 * Wordt aangeroepen nadat _dataStructLoad is uitgevoerd. dit ten behoeven van de class zijn eigen load rituelen
	 */
	protected function afterDataStructLoad() {
		// void, kan worden 'geextend' in de echter class.
	}

	protected function beforeDataStructStore() {
	}


	/**
	 * Laad de records voor een oneToMany extension Join
	 * @param $joinId
	 * @throws Exception
	 */
	private function _loadRecordsForOneToManyExtensionJoin($joinId) {

		$joinInfo = $this->joinInfo[$joinId];

		// we maken een nieuwe struct zodat we deze in de selectblock kunnen gebruiken:
		$struct = array('fields' => $joinInfo->getFieldInfo(), 'joins' => array()); // een extending struct heeft geen joins.

		$extensionQB = 'extension_' . $joinId;
		static::_createDBSelectBlockFromDataStructDefinition($struct, $extensionQB);

		// nu gaan we de keys invullen. Dit zijn de keys zoals gevuld in het lokale object:
		// de foreign keys als criteria laden:
		foreach($joinInfo->getForeignKey() as $localKeyFieldName => $foreignKeyFieldName) {

			if(!isset($struct['fields'][$foreignKeyFieldName]) === NULL) {
				throw new Exception('Foreign key field not found: ' . $foreignKeyFieldName . ' with Childproperty: ' . $localKeyFieldName);
			}

			$value = $this->_getFieldInfoForField($localKeyFieldName)->dbFormat($this->$localKeyFieldName);
			gR()->db()->addWhere($extensionQB, '`' . $struct['fields'][$foreignKeyFieldName]->getTableName() . '`.`' . $struct['fields'][$foreignKeyFieldName]->getDatabaseFieldName() . '`', '=', $value);
		}

		gR()->db()->queryBlock($extensionQB, 'loadExtensionRecord');
		return gR()->db()->fetchAllAssoc(NULL, 'loadExtensionRecord');
	}


	/**
	 * Geeft terug of wij een child zijn van het object dat ons laadt (down), of dat we een parent zijn van het object dat ons laadt (up)
	 */
	private function determineGoverningDirection() {
		if($this->governingObject === NULL) {
			return NULL;
		}
		$className = $this->governingObject->getClassName();

		foreach($this->joinInfo as $join) {
			if($join->getJoinType() == DataStructJoin::TYPE_OBJECT) {
				if($join->getForeignClassName() == $className) {
					// dit is de join die on governed:
					return ($join->getOurSide() == DataStructJoin::ONE) ? 'up' : 'down';
				}
			}
		}
		return NULL;
	}

	static public function _createDBSelectBlockForLocalData($className, $blockName) {
		$struct = DataStructManager::gI()->getDataDefinitionsByClassName($className);
		return static::_createDBSelectBlockFromDataStructDefinition($struct, $blockName);
	}


	/**
	 * Creert een database blockQuery, met daarin ingevuld, welke velden moeten worden geladen, evenals de one-to-one extensionjoins hierin te verwerken.
	 * Hierbij worden dus nog geen keys igevuld.
	 * @param $struct
	 * @param $blockName
	 */
	static public function _createDBSelectBlockFromDataStructDefinition($struct, $blockName) {

		$fields = array();
		$tablesToLoad = array();


		foreach($struct['fields'] as $name => $field) {
			if($field->getDatabaseFieldName() === NULL) {
				// geen DB ondersteuning
				continue;
			}

			$tablesToLoad[$field->getTableName()] = $field->getTableName();

			$fieldName = $field->getDatabaseFieldName();

			// De veldnamen kunnen er 1 of meer zijn, we zorgen daarom dat het type goed wordt:
			if(!is_array($fieldName)) {
				$fullDBFieldName = $field->getTableName() . '.`' . $fieldName . '` AS `' . $field->getName() . '`';
				$fields[] = $fullDBFieldName;

			} else {
				foreach($fieldName as $atom => $fieldName) {
					$fullDBFieldName = $field->getTableName() . '.`' . $fieldName . '` AS `' . $field->getName() . '_' . $atom . '`';
					$fields[] = $fullDBFieldName;
				}
			}
		}

		gR()->db()->startBlock($blockName, $fields, $tablesToLoad);

		// onetooneTableJoins:

		foreach($struct['joins'] as $joinInfo) {
			/**
			 * @var DataStructJoin $joinInfo
			 */
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION && $joinInfo->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {

				$foreignKeys = $joinInfo->getForeignKey();
				// Eventueel een left join introduceren ipv een inner join:

				if($joinInfo->getJoinStructure() == DataStructJoin::STRUCTURE_LEFT) {
					// left:
					// Joinsyntax:
					// $db->addJoin('q','left', 'relations', 'customfieldvalues', array('id','klant'), ('recordid','klant'));
					$_localFields = array();
					$_foreignFields = array();

					foreach($foreignKeys as $localKeyField => $foreignKeyField) {
						$_localFields[] = $struct['fields'][$localKeyField]->getDatabaseFieldName();
						$_foreignFields[] = $struct['fields'][$foreignKeyField]->getDatabaseFieldName();
					}
					gR()->db()->addJoin($blockName, 'LEFT', $struct['fields'][$localKeyField]->getTableName(), $struct['fields'][$foreignKeyField]->getTableName(), $_localFields, $_foreignFields);

				} else {
					// inner:
					foreach($foreignKeys as $localKeyField => $foreignKeyField) {
						// Een join wordt over het algemeen gedaan tussen twee velden. We kunnen ook joinen op een constant.
						if($struct['fields'][$localKeyField]->getDatabaseFieldName() !== NULL) {
							// we joinen een db veld
							$left = '`' . $struct['fields'][$localKeyField]->getTableName() . '`.`' . $struct['fields'][$localKeyField]->getDatabaseFieldName() . '`';
						} else {
							// geen db ondersteuning. We kijken of de key een constant is:
							if($struct['fields'][$localKeyField]->getIsConstant()) {
								// we joinen met een constant (b.v. key = 1, `servername` = 'sofie')
								$left = $struct['fields'][$localKeyField]->dbFormat($struct['fields'][$localKeyField]->getConstantValue());
							} else {
								throw new Exception('KeyField has no database field, and is not constant ' . $localKeyField);
							}
						}

						if($struct['fields'][$foreignKeyField]->getDatabaseFieldName() !== NULL) {
							$right = '`' . $struct['fields'][$foreignKeyField]->getTableName() . '`.`' . $struct['fields'][$foreignKeyField]->getDatabaseFieldName() . '`';
						} else {
							// geen db ondersteuning. We kijken of de key een constant is:
							if($struct['fields'][$foreignKeyField]->getIsConstant()) {
								$left = $struct['fields'][$foreignKeyField]->dbFormat($struct['fields'][$foreignKeyField]->getConstantValue());
							} else {
								throw new Exception('KeyField has no database field, and is not constant ' . $foreignKeyField);
							}
						}

						gR()->db()->addWhere($blockName, $left, '=', $right);
					}
				}
			}
		}

		return;
	}


	/**
	 * Vul dit object met uit de DB geladen data.
	 */
	public function _hidrateWithDBRecord(array &$record) {
		//per veld:

		foreach($this->_getFieldInfo() as $fieldName => $field) {
			if($this->_setDBProperty($record, $field, $this->$fieldName)) {
				$this->dbRecord[$fieldName] = $this->$fieldName;
				$this->recordStatus[$field->getTableName()] = 'unchanged';
			}
		}
	}

	/**
	 * Converteert een value uit de database volgens een conventie en zet deze in $destination
	 */
	private function _setDBProperty(&$dbRecord, $field, &$destination) {

		$dbFieldNames = $field->getDatabaseFieldName();

		if($dbFieldNames === NULL) {
			// Dit veld ondersteund geen DB
			return false;
		}
		$name = $field->getName();

		if(!is_array($dbFieldNames)) {
			// elementair veld:
			if(array_key_exists($name, $dbRecord)) {
				$destination = $field->parseDBFormat($dbRecord[$name]);
				return true;
			}
		} else {
			// veld in array
			$res = array();
			$ok = false;
			foreach($dbFieldNames as $atom => $dbFieldName) {
				if(array_key_exists($name . '_' . $atom, $dbRecord)) {
					$res[$atom] = $dbRecord[$name . '_' . $atom];
					$ok = true;
				}
			}
			// alleen als er elementen zijn geladen van het veld plaatsen we ze in het object
			if($ok) {
				$destination = $field->parseDBFormat($res);
				return true;
			}
		}

		return false;
	}


	/**
	 * Slaat de data uit het object op in de database (Inclusief alle gekoppelde tebellen en objecten!)
	 *
	 * @recursive
	 * @param array $sqlStatements om aan te vullen (deze functie kan vanuit een parent worden aangeroepen:
	 *                             array('insert' => array(<className> => <tableName> => fields, 'update' =>,'delete' => )
	 *
	 */
	public function _storeDataStruct(&$sqlStatements = NULL) {

		//als statements nog niet bestaan, moet deze functie ze uitvoeren.
		$execute = false;
		if($sqlStatements === NULL) {
			$sqlStatements = DataStructManager::gI()->_claimStoreExecution();
			$execute = true;
		}

		if(!$execute && (DataStructManager::gI()->getExecutingSaverId() == $this->lastSaverId)) {
			// we zijn al opgeslagen.
			return;
		} else {
			$this->lastSaverId = DataStructManager::gI()->getExecutingSaverId();
		}


		$errors = $this->validate();
		if($errors->hasErrors()) {
			throw new Exception('Attempting to store an invalid dataStruct');
		}

		if(method_exists($this, 'beforeDataStructStore')) {
			if($this->beforeDataStructStore() === false) {
				throw new Exception('Attempting to store an invalid dataStruct (BeforeDataStructStore returned false)', EXCEPTION_PRECONDITIONS_NOT_MET);
			}
		}

		$this->beforeDataStructStore();

		// eerst zorgen we dat de joins de juiste keyinformatie hebben zodat deze 'dependencyloos' worden, en als elementaire tabellen kunnen worden bijgewerkt.
		$this->_updateForeignKeys();

		// nu zorgen we ervoor dat de recordStatus van ons en onze child reflecteren wat er gewijzigd is. Dit kan namelijk expliciet zijn opgegeven, maar kan ook impliciet zijn gewijzigd.
		$this->_updateRecordDBStatus();
		$ourClassName = $this->_getClassName();

		// eerst ons eigen record updaten: (kan in meerdere tabellen zitten)
		foreach($this->recordStatus as $tableName => $status) {

			// informatie over onze definitie toevoegen
			list($cols, $keys) = $this->_getTableColsAndKeys($tableName, $this->_getFieldInfo());
			$sqlStatements['keys'][$ourClassName][$tableName] = $keys;
			$sqlStatements['cols'][$ourClassName][$tableName] = $cols;

			$this->_addChangesToSqlStatements($sqlStatements, $tableName, $status, $ourClassName, $this->_getFieldInfo());
			if($status == 'deletePending') {
				$this->recordStatus[$tableName] = 'deleted';
			} else {
				$this->recordStatus[$tableName] = 'unchanged';
			}
		}
		// nu al onze childs updaten:

		// als er childobjecten zijn, dan controleren we deze ook:
		foreach($this->joinInfo as $joinInfo) {
			// is de join een uitbreiding op de velden (is altijd oneToOne),dan wordt deze al door de normale set hierboven uitgevoerd.
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION && $joinInfo->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {
				continue;
			}

			// records updaten:
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION) {

				$propertyName = $joinInfo->getParentPropertyName();
				$recordFieldInfo = $joinInfo->getFieldInfo();

				$tableNames = $joinInfo->getForeignTableNames();

				foreach($tableNames as $tableName) {
					list($cols, $keys) = $this->_getTableColsAndKeys($tableName, $recordFieldInfo);
					$sqlStatements['keys'][$ourClassName][$tableName] = $keys;
					$sqlStatements['cols'][$ourClassName][$tableName] = $cols;

					foreach($this->childRecordStatus[$propertyName] as $key => $status) {
						$prop = &$this->$propertyName;

						if($status == 'deletePending') {
							// in het geval van een delete hebben we de data zoals deze in de db stond nodig om het record te verwijderen.

							$this->_addDeletesToSqlStatement($sqlStatements, $tableName, $ourClassName, $recordFieldInfo, $this->dbChildRecord[$propertyName][$key]);
							// het record verwijderen uit de dataset:
							unset($prop[$key]);
							unset($this->childRecordStatus[$propertyName][$key]);
						} else {
							// we voegen de insert en updatestatements toe:
							$this->_addChangesToSqlStatements($sqlStatements, $tableName, $status, $ourClassName, $recordFieldInfo, $prop[$key]);
							$this->childRecordStatus[$propertyName][$key] = 'unchanged';
						}
					}
				}

			}

			// objecten updaten:
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_OBJECT) {
				$propertyName = $joinInfo->getParentPropertyName();
				if($joinInfo->getOurSide() == DataStructJoin::ONE) {
					if($this->$propertyName !== NULL && count($this->$propertyName->_getLoadedObjects()) > 0) {

						foreach($this->$propertyName->_getLoadedObjects() as $childObject) {
							$childObject->_storeDataStruct($sqlStatements);
						}
					}
				} else {
					if($this->$propertyName !== NULL) {
						$this->$propertyName->_storeDataStruct($sqlStatements);
					}
				}
			}
		}


		if($execute) {
			DataStructManager::gI()->_executeStore($sqlStatements);

			if(method_exists($this, 'afterDataStructStore')) {
				DataStructManager::gI()->addAfterStoreCallback(array($this, 'afterDataStructStore'));
			}
			DataStructManager::gI()->executeAfterStoreCallbacks();
		} else {
			if(method_exists($this, 'afterDataStructStore')) {
				DataStructManager::gI()->addAfterStoreCallback(array($this, 'afterDataStructStore'));
			}
		}


	}

	/**
	 * Zorgt ervoor dat records van het type Extension, de juiste waarden hebben in hun foreign keys velden
	 * B.v. als er meerdere records onder een transactie zitten, (extension one to many) dan wordt in deze functie bij elk record
	 * wat nog niet de juiste transaction_id, acceptant_id heeft, deze goed ingevuld (overgenomen van het parentObject).
	 */
	private function _updateForeignKeys() {
		foreach($this->joinInfo as $joinInfo) {
			// is de join een uitbreiding op de velden (is altijd oneToOne),dan is er een veld in de extended tabel wat overeen moet komen met het basisveld:
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION && $joinInfo->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {

				foreach($joinInfo->getForeignKey() as $localField => $foreignField) {
					// waarde van foreign key overnemen:
					if(!isset($this->$foreignField) || $this->$foreignField !== $this->$localField) {
						$this->$foreignField = $this->$localField;
					}
				}
				continue;
			}

			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION) {
				$propertyName = $joinInfo->getParentPropertyName();

				// voor elk foreignKeyField
				foreach($joinInfo->getForeignKey() as $localField => $foreignField) {

					if(!isset($this->$propertyName)) {
						// deze join is niet geladen:
						continue;
					}

					// voor elk record van de onetomany
					foreach($this->$propertyName as $id => &$record) {
						if(!isset($record[$foreignField]) || $record[$foreignField] !== $this->$localField) {
							$record[$foreignField] = $this->$localField;
						}
					}
					// pointer schoonmaken:
					unset($record);

				}
			}
			//Wellicht later:
			//We gaan er nu van uit dat op het moment dat een object gejoind is, dat deze zelf haar velden goed heeft ingevuld.
			//We zouden een validation kunnen maken die kijkt of de gejoinde objecten inderdaad nog steeds aan de constraints voldoen.
		}
	}

	/**
	 * Update $this->recordStatus zodat de storeroutine weet wat er gewijzigd is.
	 * De functie mengt twee bronnen bij elkaar:
	 *  -de recordStatus zoals deze is gebruikt en aangepast (als deze b.v. op changed is gezet hoeven we nergens meer naar te kijken)
	 *  -de oude en nieuwe waarden van de properties voor de gevallen dat de recordstatus unchanged zegt.
	 * @return enum new /changed / unchanges / pendingDelete Als er 1 sub is gewijzigd, is deze ook gewijzigd.
	 */
	public function _updateRecordDBStatus() {


		// kijk eerst naar onze eigen tabellen (Dit is dus inclusief one-one joins):

		$this->_updateRecordDBStatusForRecord($this->recordStatus, $this->_getFieldInfo(), $this->dbRecord, $this, 'asObject');

		// we willen de meest drastische wijziging teruggeven (van ons eigen record)
		$mostChange = 'unchanged';

		$changeWeights = array('unchanged' => 0, 'changed' => 1, 'new' => 2, 'pendingDelete' => 4);
		// unchanged = 0
		// changed = 1
		// new = 2
		// pendingdelete = 4

		foreach($this->recordStatus as $tableName => $changes) {
			if($changeWeights[$changes] > $changeWeights[$mostChange]) {
				$mostChange = $changes;
			}
		}

		$isChanged = ($mostChange != 'unchanged');

		// de childTabellen
		foreach($this->joinInfo as $joinInfo) {
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION && $joinInfo->getJoinOrder() == DataStructJoin::ORDER_ONE_TO_ONE) {
				// hebben we al verwerkt
				continue;
			}

			// TableExtensions:
			if($joinInfo->getJoinType() == DataStructJoin::TYPE_EXTENSION) {
				// one to many join
				$propertyName = $joinInfo->getParentPropertyName();

				if(!isset($this->childRecordStatus[$propertyName])) {
					// er is geen data geladen:
					continue;
				}


				// donelist bijhouden om later te controleren of er nieuwe records zijn toegevoegd
				$doneList = array();

				// bekijk eerst wat er volgens de childRecordstatus aan de hand is:
				// Als een record in deze array voorkomt (en niet als new is gemarkeerd) komt het record in de db voor.

				// we lopen eerst de statussen door om de changes en deletes te vinden
				$prop = &$this->$propertyName;
				$fieldInfo = $joinInfo->getFieldInfo();

				$extendingTables = $joinInfo->getForeignTableNames();

				foreach($extendingTables as $extendingTable) {
					foreach($this->childRecordStatus[$propertyName] as $key => &$childRecordStatus) {
						// als property niet meer exist
						if(!isset($prop[$key])) {
							$doneList[$key] = true;
							if($childRecordStatus == 'new') {
								// bestond al niet in de db:
								unset($this->childRecordStatus[$propertyName][$key]);
								continue;
							} else {
								// record moet worden verwijderd.
								$childRecordStatus = 'deletePending';
								$isChanged = true;

								continue;
							}
						}
						// als propertystatus == unchanged:
						if($childRecordStatus == 'unchanged') {
							// controleer of het record echt unchanged is:
							$newStatus = array($extendingTable => $childRecordStatus);
							// Deze functie kan naar meerdere tabellen kijken, maar aangezien we per join maar 1 tabel hebben, doen we dit niet.
							$this->_updateRecordDBStatusForRecord($newStatus, $fieldInfo, $this->childDbRecord[$key], $prop[$key], 'asArray');
							$childRecordStatus = $newStatus[$extendingTable];
							if($childRecordStatus != 'unchanged') {
								$isChanged = true;
							}
						}
						$doneList[$key] = true;
					}
					unset($childRecordStatus);
				}


				// hierna lopen we de echte property door om de overige new records te vinden
				foreach($this->$propertyName as $key => $record) {
					if(!isset($doneList[$key])) {
						// nog niet gehad, dan is het record new.
						$this->childRecordStatus[$propertyName][$key] = 'new';
						$isChanged = true;
					}
				}

				continue;
			}
		}

		if($isChanged && $mostChange == 'unchanged') {
			// het object zelf is niet gewijzigd, maar er is wel wat gewijzigd in haar kinderen.
			$mostChange = 'changed';
		}

		return $mostChange;
	}

	/**
	 * Controleer of er verschil zit tussen dbRecord  en currentRecord, en werk dit verschil bij in $recordStatus
	 * @param array $recordStatus array (<tableName> => status',..)
	 * @param DataStructField[] $fieldInfo array(<fieldName> => DataField, ..)
	 * @param array $dbRecord array(<fieldName> => lastvalue)
	 * @param array|Object $currentRecord fieldName => newValue
	 * @param enum $currentRecordType 'asArray' | 'asObject' geeft aan wat het currentRecord is.
	 */
	private function _updateRecordDBStatusForRecord(&$recordStatus, $fieldInfo, &$dbRecord, &$currentRecord, $currentRecordType) {


		// kijk welke tabellen we niet in de DBStatus kunnen terugvinden
		$tablesToCheck = array();
		foreach($recordStatus as $tableName => $status) {
			// als unchanged:
			if($status == 'unchanged') {
				$tablesToCheck[$tableName] = true;
			}
		}

		foreach($fieldInfo as $fieldName => $field) {

			// ReadOnly velden worden altijd unchanged

			if($field->getIsReadOnly()) {
				// TODO!!!! hele tabel wordt readOnly, misschien checken of rest van velden ook readOnly zijn.
				$recordStatus[$field->getTableName()] = 'unchanged';

				continue;
			}

			/**
			 * @var DataStructField $field
			 */
			if($field->getIsConstant() || !($field->getTableName())) {
				if(isset($recordStatus[$field->getTableName()])) {
					$recordStatus[$field->getTableName()] = 'unchanged';
				}
				continue;
			}

			// als tablename in tablestocheck zit
			$tableName = $field->getTableName();
			if(!isset($tablesToCheck[$tableName])) {
				continue;
			}

			// Nieuwe waarde ophalen:
			$newValue = NULL;
			if($currentRecordType == 'asArray') {
				if(isset($currentRecord[$fieldName])) {
					$newValue = $currentRecord[$fieldName];
				}
			} else {
				// asObject
				if(property_exists($currentRecord, $fieldName)) {
					$newValue = $currentRecord->$fieldName;
				}
			}

			// kijk of de waarden hetzelfde zijn:
			if(!$field->valueEquals($dbRecord[$fieldName], $newValue)) {
				$recordStatus[$tableName] = 'changed';
				// We hebben nu deze tabel voor dit record op changed gezet. We hoeven alleen nog andere tabellen te controleren
				// als er maar 1 tabel was om te checken zijn we nu dus klaar.
				if(count($tablesToCheck) == 1) {
					return;
				} else {
					unset($tablesToCheck[$tableName]);
				}
			}
		}

		return;
	}

	/**
	 * Geeft terug of een elementaire property in het object is gewijzigd.
	 * @param $fieldName
	 * @return bool
	 * @throws DeveloperException
	 */
	public function _isValueChanged($fieldName) {
		if(!$this->_fieldExists($fieldName)) {
			throw new DeveloperException('Unknown field ' . $fieldName);
		}

		if(!isset($this->dbRecord[$fieldName])) {
			return true;
		}

		return ($this->_getFieldInfoForField($fieldName)->valueEquals($this->$fieldName, $this->dbRecord[$fieldName]));
	}

	/**
	 * Returns the original value as retreived from the database, or current value if no changes where detected.
	 * @param $fieldName
	 * @return mixed
	 * @throws DeveloperException
	 */
	public function _getOriginalValue($fieldName) {
		if(!$this->_isValueChanged($fieldName)) {
			return $this->$fieldName;
		}
		return $this->dbRecord[$fieldName];
	}

	/**
	 * Breid het sqlStatement uit met updates en inserts (geen deletes!):
	 * @param array $sqlStatements : Het uit te breiden statement
	 * @param string $tableName : De tabel waarin de wijzigingen gaan gebeuren
	 * @param enum $status : Wat is de status van het record wat we gaan toevoegen aan de sqlStatements
	 * @param string $ourClassName onze className (zodat de namespacing goed gaat)
	 * @param array $fieldInfo de te gebruiken veldinformatie (bv. $this->fieldInfo, of die uit een join)
	 * @param array $dataRecord : -Indien NULL worden values direct uit objectproperties gelezen, indien het een array bevat worden de values uit het dataRecord gebruikt.
	 */
	private function _addChangesToSqlStatements(array &$sqlStatements, $tableName, $status, $ourClassName, $fieldInfo, $dataRecord = NULL) {
		switch($status) {
			case 'new':
				$el = 'insert';
				break;
			case 'changed':
				$el = 'update';
				break;
			case 'unchanged':
				// niets doen:
				return;
			default:
				throw new Exception('Unknown or invalid status for record');
		}

		if(empty($tableName)) {
			throw new Exception('Unknown table for field ' . $fieldInfo->name . ' in class ' . $ourClassName);
		}
		if(!isset($sqlStatements[$el][$ourClassName][$tableName]['records'])) {
			$sqlStatements[$el][$ourClassName][$tableName]['records'] = array();
		}
		$this->_addRecord($sqlStatements[$el][$ourClassName][$tableName], $tableName, $fieldInfo, $dataRecord, $sqlStatements['cols'][$ourClassName][$tableName]);
	}

	/**
	 * Zelfde functie als addChangesToSqlStatements alleen dan voor deletes
	 * @param array $dbRecord Krijgt het record mee zoals deze voor het laatst bekend in de database.
	 */
	private function _addDeletesToSqlStatement(array &$sqlStatements, $tableName, $ourClassName, $fieldInfo, $dbRecord) {
		if(!isset($sqlStatements['delete'][$ourClassName][$tableName]['records'])) {
			$sqlStatements['delete'][$ourClassName][$tableName]['records'] = array();
		}

		// we hoeven in het geval van een delete slechts de keys in het statement op te nemen:
		$this->_addRecord($sqlStatements['delete'][$ourClassName][$tableName], $tableName, $fieldInfo, $dbRecord, $sqlStatements['keys'][$ourClassName][$tableName]);

	}


	/**
	 * Geeft de databasekolommen terug die bij een record horen:
	 * Let op, per veld kunnen meerdere kolommen betrokken zijn.
	 * @param $tableName , de betrokken table
	 * @return array(array(<fieldName> => (<dbFieldName> | array(<dbFieldName>,...)),...), array(<dbKeyFieldName> | array(<dbKeyFieldName>,...)),...)
	 */
	private function _getTableColsAndKeys($tableName, $fieldInfo) {
		$cols = array();
		$keys = array();
		foreach($fieldInfo as $name => $field) {
			if($field->getTableName() != $tableName) {
				continue;
			}

			if($field->getIsDBKey()) {
				$keys[$name] = $field->getDatabaseFieldName();
				$cols[$name] = $field->getDatabaseFieldName();
				continue;
			}

			if($field->getIsReadOnly()) {
				continue;
			}

			$cols[$name] = $field->getDatabaseFieldName();
		}
		return array($cols, $keys);
	}

	/**
	 * Voeg een record toe aan de sqlStatamentsrecord zoals gedefinieerd in _storeDatastruct
	 * Zet in $sqlStatements in de records het record behorende bij het field
	 * @param array $dataRecord . Indien NULL, halen we de data direct uit dit object, indien array, gebruiken we het meegegeven record.
	 * @param enum $fieldNamesToAdd , welke fieldNames voegen we toe in het record
	 *
	 */
	private function _addRecord(array &$sqlStatements, $tableName, $fieldInfo, $dataRecord = NULL, $fieldNamesToAdd) {
		$record = array();

		foreach($fieldNamesToAdd as $fieldName => $dbFieldNames) {
			if($dataRecord === NULL) {
				$values = $fieldInfo[$fieldName]->_getDbFieldNameAndValueAsArray($this->$fieldName);
			} else {
				$values = $fieldInfo[$fieldName]->_getDbFieldNameAndValueAsArray($dataRecord[$fieldName]);
			}
			foreach($values as $dbFieldName => $value) {
				$record[$dbFieldName] = $value;
			}
		}
		$sqlStatements['records'][] = $record;
	}


	/**
	 * geeft terug of de betreffende property een door een join gedefinieerde property is.
	 * @param string $property De property.
	 * @return bool isChildProperty
	 */
	private function isChildProperty($property) {
		return isset($this->childProperties[$property]);
	}

	/**
	 * Geeft de key waarmee deze property in de childarray van dit object hoort te worden opgeslagen terug:
	 * @param string $property De property van de array
	 * @param mixed $value de Array of Object (met datastruct) waarin de key te vinden is.
	 */
	private function _getKeyFromValueForChildProperty($property, $value) {
		switch($this->childProperties[$property]['type']) {
			case DataStructJoin::TYPE_EXTENSION:
				return $value[$this->childProperties[$property]['keyField']];
			case DataStructJoin::TYPE_OBJECT:
				//
				return $value->_getKeyValue($this->childProperties[$property]['keyField']);
		}
	}


	/**
	 * Geeft de waarde van de key van $keyName terug.
	 * Deze functie is gemaakt omdat bij joins er door de parent een indexering vereist is. Hiervoor moeten we altijd een key kunnen opvragen
	 * ook al is deze key (waarschijnlijk) niet public (je wil hem immers niet kunnen setten).
	 * @param string $keyName de naam van de key
	 */
	public function _getKeyValue($keyName) {
		if(!$this->_getFieldInfoForField($keyName)) {
			throw new Exception('Definition of key not found: ' . $keyName, EXCEPTION_INVALID_DATA_KEY);
		}
		return $this->$keyName;
	}

	/**
	 * @return DataStructField[]
	 */
	public function _getKeyFields() {
		return DataStructManager::gI()->getKeyFields($this->_getClassName());
	}


	/**
	 * Komt de waarde van $fieldName in deze datastruct voor in de $values?
	 * @param $fieldName
	 * @param $values
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function _isValueIn($fieldName, $values) {
		$field = DataStructManager::gI()->getDataField($this->_getClassName(), $fieldName);
		return $field->valueInArray($this->_getValue($fieldName), $values);
	}


	public $_universalKey;

	/**
	 * Geeft een key terug van hoe het object in het universe geladen is.
	 * @return string
	 * @throws Exception
	 */
	public function getUniversalKey() {
		if($this->_universalKey === NULL) {
			$res = array();
			foreach($this->_getKeyFields() as $field) {
				$res[] = $this->_getKeyValue($field->getName());
			}
			$this->_universalKey = implode('_', $res);
		}
		return $this->_universalKey;
	}

	/**
	 * @param $fieldName
	 * @return DataStructField|null
	 */
	protected function getField($fieldName) {
		if($this->fieldExists($fieldName)) {
			return $this->_getFieldInfoForField($fieldName);
		}
		return NULL;
	}

	/**
	 * Bestaat het veld in de definities?
	 */
	protected function fieldExists($fieldName) {
		return $this->_fieldExists($fieldName);
	}


	/**
	 * Assist functie in het geval dat er ook een JsonObject wordt gebruikt. We kunnen dat met een flag werken (USE_IN_JSON)
	 */
	public function getJsonData() {
		$fields = DataStructManager::gI()->getDataFields($this->_getClassName());

		$res = array();
		foreach($fields as $field) {
			if($field->getFlag(DataStructField::USE_IN_JSON)) {
				$fieldName = $field->getName();
				$res[$fieldName] = $this->$fieldName;
			}
		}
		return $res;
	}

	public function onJsonWakeup($jsonData) {
		$this->initDataStruct();
		foreach(DataStructManager::gI()->getDataFields($this->_getClassName()) as $field) {
			$fieldName = $field->getName();
			if($field->getIsDBKey() && isset($jsonData[$fieldName]) && $field->getFlag(DataStructField::USE_IN_JSON)) {
				$this->$fieldName = $jsonData[$fieldName];
			}
		}
		try {
			$this->_loadDataStruct();
		} catch(Exception $e) {
			// record not found. Maakt niet uit, dan creeren we deze.
		}

		foreach($jsonData as $key => $value) {
			if(DataStructManager::gI()->fieldExists($this->_getClassName(), $key)) {
				$this->set($key, $value);
			}
		}
	}

	/**
	 * @param CF\Tool\XMLWriterClass $x
	 * @param $objNode
	 * @param DataStruct $object
	 * @param DataStruct $parentObject
	 * @param DataStructXMLBackup $backupGenerator
	 */
	static function generateDSExportXML(CF\Tool\XMLWriterClass $x, $objNode, $object, $parentObject) {
		$backupGenerator = new DataStructXMLBackup($object->_getClassName());
		$backupGenerator->addObjectToStructure($x, $objNode, $object, $parentObject);
	}
}
