<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:36
 */
namespace CF\DataStruct\Field;

use CF\DataStruct\Constraint\DataConstraint;
use CF\DataStruct\Exception;
use CF\Runtime\Runtime;
use CF\Database\Database;
use ConscriboForm;
use CF\DataStruct\DataStruct;
use CF\DataStruct\Filter\DataStructFilter;
use CF\DataStruct\DataStructManager;
use CF\Exception\DeveloperException;
use Form;

abstract class DataStructField {

	const USE_IN_FORMS = 'useInForms';
	const USE_IN_JSON = 'useInJson';
	const USE_HIDDEN_FORMFIELD = 'useHiddenFormField';

	const ORDER_DESC = 'desc';
	const ORDER_ASC = 'asc';

	const VALUE_FORMAT_TXT = 'txt';
	const VALUE_FORMAT_HTML = 'html';
	const VALUE_FORMAT_XML = 'xml';

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var null|string
	 */
	public $code;

	/**
	 * @var DataConstraint[]
	 */
	public $constraints;

	/**
	 * het Label zoals de gebruiker dit kan zien
	 * @var string
	 */
	protected $label;

	protected $tableName;
	protected $databaseFieldName;

	// Het veldtype in de database
	protected $databaseFieldType;

	// eventueel een suffix (b.v. '(255)' in varchar(255) of ' unsigned')
	// Deze typespecificaties worden slechts gebruikt bij het maken van tijdelijke tabellen, en hoeven dus niet overeen te komen met de echte definitie.
	protected $databaseFieldSuffix;

	protected $isKey;
	/**
	 * @var bool Geeft aan of het field kan worden opgeslagen in de database
	 */
	protected $readOnly;

	/*
	 * @var bool Geeft aan of het veld uit een object kan worden gelezen met 'getValue';
	 */
	protected $isReadable;


	// Is dit veld key omdat wij dit hebben gedefinieerd of omdat deze benodigd is in een join met een foreign table?
	protected $isForeignKey;


	// Wordt dit veld gebruikt als index bij gebruik van dit object in een collection?
	protected $isVirtualCollectionKey;

	/**
	 * @var array 'useHiddenFormField' => <false/true>
	 */
	public $flags;

	/**
	 * Attributes die bij het renderen van een form moeten worden ingevuld
	 * @var array 'fieldAttributes'
	 */
	public $formFieldAttributes;

	/**
	 * Is NULL equal to 0 or empty values? Default: false
	 * @var bool
	 */
	protected $nullEqualsEmpty;

	/**
	 * Heeft dit veld een vaste waarde of wordt deze geladen uit de database?
	 * @var bool
	 */
	private $isConstant;

	/**
	 * Indien de waarde constant is, de waarde
	 * @var mixed
	 */
	private $constantValue;

	/**
	 * @var bool Is this field compatible to be used in a memorytable
	 */
	protected $memoryTableCompatible;

	/**
	 * Maak een veld aan
	 * @param string $name  veldnaam zoals gebruikt in het object. CaseSensitive!
	 * @param string $code  naam van het veld zoals gebruikt in formulierwaarden (zonder hoofdletters b.v.)
	 * @param string $label label van het veld, gebruikt in foutmeldingen, captions e..d
	 * @return DataStructField
	 */
	function __construct($name, $code = NULL, $label = NULL) {

		$this->name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

		if($code === NULL) {
			$this->code = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $this->name));
		} else {
			$this->code = $code;
		}
		if($label === NULL) {
			$this->label = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $this->name);
		} else {
			$this->label = $label;
		}

		$this->default = NULL;
		$this->constraints = array();
		$this->formFieldAttributes = array();
		$this->isKey = false;
		$this->isReadOnly = false;
		$this->isReadable = true;
		$this->isForeignKey = false;
		$this->isVirtualCollectionKey = false;
		$this->isConstant = false;

		$this->memoryTableCompatible = true;

		// standaard is NULL een andere waarde dan '' of 0:
		$this->nullEqualsEmpty = false;

		$this->flags = array();
		$this->flags[DataStructField::USE_IN_JSON] = true;

		$this->addDefaultConstraints();
		$this->initDatabaseProperties();
		return $this;
	}

	protected $className;
	/**
	 * Geeft het veldType terug (de className).
	 * In het kader van designpatterns moeten we goed nadenken wanneer deze functie wordt gebruikt.
	 * Het gebruik van de functie impliceert typespecifiekgedrag. Dit kan vaak beter in de specialisatie van het veld plaatsvinden.
	 * @return string
	 */
	public function getFieldType() {
		if($this->className === NULL) {
			$this->className = get_class($this);
		}
		return $this->className;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	public function validateValue($value, \CF\Error\ErrorCollection $errors) {
		$errors->setDynamicContextLabel($this->label);

		foreach($this->constraints as $constraint) {
			if(!$constraint->assert($value, $errors)) {
				$errors->setDynamicContextLabel(NULL);
				return false;
			}
		}
		$errors->setDynamicContextLabel(NULL);
		return true;
	}

	public function addConstraint(DataConstraint $constraint) {
		$constraint->setField($this);
		$this->constraints[] = $constraint;
		return $this;
	}

	public function setFlag($flag, $toggle = true) {
		$this->flags[$flag] = $toggle;
		return $this;
	}

	public function addFormFieldAttribute($key, $value) {
		$this->formFieldAttributes[$key] = $value;
		return $this;
	}

	/**
	 * @param $value
	 * @return $this
	 */
	public function setDefault($value) {
		$this->default = $value;
		return $this;
	}

	public function getDefault() {
		if($this->getIsConstant()) {
			return $this->getConstantValue();
		}

		return $this->default;
	}

	/**
	 * Voegt het veld toe aan een object
	 * @param DataStruct $object
	 * @return DataStructField
	 */
	public function setOwner($object) {
		$object->addField($this);
		return $this;
	}

	/**
	 * @param boolean $toggle
	 * @return DataStructField
	 */
	public function isVirtualCollectionKey($toggle = true) {
		$this->isVirtualCollectionKey = $toggle;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getIsVirtualCollectionKey() {
		return $this->isVirtualCollectionKey;
	}


	/**
	 * @param String $label
	 * @return $this
	 */
	public function setLabel($label) {
		$this->label = $label;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * Geef aan of het veld kan worden gelezen met b.v. een getValue statement.
	 * @param $toggle
	 * @return $this
	 */
	public function setIsReadable($toggle) {
		$this->isReadable = ($toggle) ? true : false;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getIsReadableProperty() {
		return $this->isReadable;
	}

	/**
	 * Definieer de veldnaam van het veld (alleen op het moment dat deze in de database voorkomt)
	 * @param string $tableName
	 * @param string $fieldName
	 * @return DataStructField
	 */
	public function setDatabaseFieldName($tableName, $fieldName) {
		$this->tableName = $tableName;
		$this->databaseFieldName = $fieldName;
		if(!$this->memoryTableCompatible) {
			// When a field uses the text / blob datatype, mysql is not able to create memory/temporary tables from this table.
			// The database layer has no complete knowledge about the datatypes and needs to be notified about these tables:
			Database::notifyTableIncapableToUseMemoryEngine($tableName);
		}
		return $this;
	}

	/**
	 * Call this function if the datatype is a blob /text, or if you get an error 'CREATE TEMPORARY TABLE ... failed .. The used table type doesn't support BLOB/TEXT columns'
	 * @return $this
	 */
	public function usesBlobField() {
		$this->memoryTableCompatible = false;
		if($this->tableName !== NULL) {
			Database::notifyTableIncapableToUseMemoryEngine($this->tableName);
		}
		return $this;
	}


	public function getTableName() {
		return $this->tableName;
	}

	/**
	 * Geeft de databaseVeldnam(en) terug of NULL indien deze niet zijn geregistreerd
	 * @return NULL: geen db adapter, String veldnaam, array('veldnaam1','veldnaam2',...)
	 * De Array weergave wordt gebruikt bij b.v. de bankrekening welke uit 3 velden bestaat.
	 */
	public function getDatabaseFieldName() {
		return $this->databaseFieldName;
	}

	/**
	 * Geeft de volle databasebenaming terug b.v. `transaction`.`id` of NULL indien het geen dbVeld is
	 * @return string|NULL
	 */
	public function _getFullDatabaseFieldName() {
		if($this->databaseFieldName === NULL) {
			return NULL;
		}
		return '`' . $this->tableName . '`.`' . $this->databaseFieldName . '`';
	}

	/**
	 * Geeft een array terug ten behoeve van het vullen van insert, update en where clauses in sql statements van de datastruct.
	 * die er b.v. als volgt uitziet:
	 * @param mixed $value waarde zoals deze in de datastruct opgeslagen.
	 * @return array('`'.<dbFieldName>.'`' => dbStr($value), ...)
	 */
	public function _getDbFieldNameAndValueAsArray($value) {
		if(empty($this->databaseFieldName)) {
			// geen db ondersteuning.
			return array();
		}
		return array($this->databaseFieldName => $this->dbFormat($value));
	}

	public function isDBKey($isKey = true) {
		$this->isKey = $isKey;
		return $this;
	}

	public function getIsDBKey() {
		return $this->isKey;
	}

	public function isForeignKey($toggle = true) {
		$this->isForeignKey = $toggle;
	}

	public function getIsForeignKey() {
		return $this->isForeignKey;
	}


	public function isReadOnly($isReadOnly = true) {
		$this->isReadOnly = $isReadOnly;
		return $this;
	}

	public function getIsReadOnly() {
		return $this->isReadOnly;
	}

	public function useInJSON($toggle = true) {
		$this->flags[DataStructField::USE_IN_JSON] = $toggle;
		return $this;
	}

	/**
	 * current support: DataStructField::USE_IN_JSON, DataStructField::USE_IN_FORM
	 * @param $flagName
	 * @return bool
	 */
	public function getFlag($flagName) {
		if(isset($this->flags[$flagName])) {
			return $this->flags[$flagName];
		}
		return NULL;
	}

	/**
	 * Voegt deze waarde toe aan een formulier.
	 * @param ConscriboForm $form
	 * @param mixed              $default
	 * @registeredHooks: onCreateFormField(form, default)
	 */
	public function addToForm($form, $default = NULL) {

		if(isset($this->flags[DataStructField::USE_HIDDEN_FORMFIELD]) && $this->flags[DataStructField::USE_HIDDEN_FORMFIELD]) {
			$form->addHiddenField($this->code, $default);
		} else {
			$this->addSpecializedFieldToForm($form, $default);
		}
		foreach($this->formFieldAttributes as $key => $value) {
			$form->setFieldAttribute($this->code, $key, $value);
		}

	}


	/**
	 * Voegt default constraints toe aan dit veld.
	 * @return bool canContinue
	 */
	protected function addDefaultConstraints() {
	}

	/**
	 * maakt een database string van de value
	 * @param mixed $value
	 * @throws
	 */
	public function dbFormat($value) {
		switch($this->databaseFieldType) {
			case 'varchar':
			case 'date':
				return dbStr($value);
			case 'int':
			case 'tinyint':
				return dbInt($value);
			case 'decimal':
				return dbFloat($value);
			default:
				throw new Exception('Unknown format :' . $this->databaseFieldType, EXCEPTION_SITUATION_UNKNOWN);
		}
		return NULL;
	}

	/**
	 * maakt een te lezen waarde van een db waarde.
	 * @param mixed $value
	 * @throws Exception EXCEPTION_SITUATION_UNKNOWN
	 */
	public function parseDBFormat($value) {
		switch($this->databaseFieldType) {
			case 'int':
			case 'tinyint':
				if($value === NULL) {
					return NULL;
				}
				return intval($value);
			case 'decimal':
			case 'varchar':
			case 'date':
				// geen conversie vanuit de database benodigd.
				return $value;
			default:
				throw new Exception('Unknown format :' . $this->databaseFieldType, EXCEPTION_SITUATION_UNKNOWN);
		}
	}

	/**
	 * Is the value NULL equal to an empty value (0, '', ...). Default false;
	 * @return \CF\DataStruct\Field\StringField
	 */
	public function setNullEqualsEmpty($toggle = true) {
		$this->nullEqualsEmpty = $toggle;
		return $this;
	}


	/**
	 * Geeft terug of de waarde van dit veld leeg is.
	 * @param $value
	 * @param $strictCheck when is a value empty?
	 * @return bool
	 */
	public function isEmptyValue($value, $strictCheck = true) {
		if($strictCheck) {
			return (strlen($value) == 0);
		} else {
			return empty($value) && (trim($value) !== '0');
		}
	}


	/**
	 * return if a comparison of two values can be made based on one (or both) value being NULL
	 * CF states that: unless nullEqualsEmpty is on:
	 *					###########################
	 * 					#####  NULL < empty  ######
	 *					###########################
	 *
	 * @param $a
	 * @param $b
	 * @return 1 if a > b, -1 if a < b, 0 if a === b, NULL if undetermined
	 */
	protected function compareEmpty($a, $b) {
		if($a === $b) {
			return 0;
		}

		if(!$this->nullEqualsEmpty) {
			if($a === NULL && $b === NULL) {
				return 0;
			}
			if($a === NULL) {
				return -1;
			}
			if($b === NULL) {
				return 1;
			}
			return NULL;
		}

		// both are unequal to null, (or that is irrelevant)
		// now compare empty
		$eA = $this->isEmptyValue($a);
		$eB = $this->isEmptyValue($b);
		if($eA && $eB) {
			return 0;
		}
		if($eA) {
			return -1;
		}
		if($eB) {
			return 1;
		}
	}

	/**
	 * The Comparison guts:
	 * Compare if Value b equals, is greater than, or is lesser than b
	 * @param $a
	 * @param $b
	 * @return int -1 if a < b, 0 if a == b, 1 if a > b
	 */
	//abstract public function compareValueAWithB($a, $b);

	/**
	 * Geeft terug of de waarden gelijk zijn.
	 * @param mixed $a
	 * @param mixed $b
	 * @return bool
	 */
	public  function valueEquals($a, $b) {
		return $this->compareValueAWithB($a, $b) == 0;
	}

	/**
	 * Vergelijk een value van dit datatype, en kijk of deze in de haystack voorkomt
	 * @param $needle
	 * @param $haystack
	 * @return bool
	 */
	public function valueInArray($needle, $haystack) {
		foreach($haystack as $value) {
			if($this->valueEquals($needle, $value)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Compare a with b and return: a > b
	 * @param $a
	 * @param $b
	 * @return bool
	 */
	public final function isValueAGreaterThanB($a, $b) {
		return $this->compareValueAWithB($a, $b) > 0;
	}

	/**
	 * Compare a with b and return: a < b
	 * @param $a
	 * @param $b
	 * @return bool
	 */
	public final function isValueASmallerThanB($a, $b) {
		return $this->compareValueAWithB($a, $b) < 0;
	}

	/**
	 * Formatteert een waarde en geeft deze terug
	 * @param mixed $value
	 * @param null  $format
	 * @return mixed
	 */
	public function formatValue($value, $format = NULL) {

		if($format === NULL) {
			$format = DataStructField::VALUE_FORMAT_TXT;
		}

		switch($format) {
			case DataStructField::VALUE_FORMAT_HTML:
				return nl2br(htmlspecialchars($value));
				break;
			case DataStructField::VALUE_FORMAT_XML:
			case DataStructField::VALUE_FORMAT_TXT:
			default:
				return $value;
		}
	}

	/**
	 * Converteert de waarde vanuit een xmlstring naar de waarde geschikt voor een object
	 * @param $value
	 * @param $newIds : Referentie naar de nieuwe toegewezen ids uit backups van Conscribo. (alleen nuttig in Conscribo velden)
	 */
	public function parseFromXMLValue($value, &$newIds = NULL) {
		return $value;
	}

	public function addOrderSql($dbBlockName, $order) {
		db()->addOrder($dbBlockName, $this->getDatabaseFieldName() . ' ' . $order);
	}

	abstract protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL);

	abstract protected function initDatabaseProperties();

	/**
	 * @return DataStructFilter
	 */
	abstract public function createFilterObject();


	public function getIsConstant() {
		return $this->isConstant;
	}

	/**
	 * Definieer dit veld als constante
	 * Let op! Een constante is gedefinieerd op class/veldniveau. Ga niet proberen hier instancespecieke waarden in te zetten.
	 * @param mixed $value de constante waarde van het veld.
	 * @return DataStructField
	 */
	public function setAsConstant($value) {
		if(!DataStructManager::gI()->getIsDefinitionsLoading()) {
			throw new DeveloperException('Constant assigned outside definition loading stage');
		}
		$this->isConstant = true;
		$this->constantValue = $value;
		return $this;
	}

	public function getConstantValue() {
		return $this->constantValue;
	}

	/**
	 * @return boolean
	 */
	public function isMemoryTableCompatible() {
		return $this->memoryTableCompatible;
	}


}