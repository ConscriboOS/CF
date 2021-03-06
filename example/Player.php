<?php

use CF\DataStruct\DataStruct;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\Field;
use CF\DataStruct\Constraint;

/**
 * Example of an Object under Datastruct management.
 * As Datastruct is a trait, the normal class hierarchy stays intact.
 * To not interfere with a class own functions, datastruct functions start with _
 * User: dre
 * Date: 22-2-15
 * Time: 22:43
 */

class Player {

	use DataStruct;

	/**
	 * I am the primary key
	 * @var int
	 */
	protected $id;

	/**
	 * I am a testfield
	 * @var String
	 */
	protected $name;
	/**
	 * I am another testfield
	 * @var String
	 */
	protected $otherName;

	/**
	 * Errorhandler for this object
	 * @var \CF\Error\ErrorCollection
	 */
	protected $errors;

	/**
	 * Optional factory function to create a player.
	 * @return Player
	 */
	static function create() {
		$obj = new Player();
		$obj->initNew();
		return $obj;
	}

	/**
	 * Optional factory function to load a player.
	 * @param $id
	 * @return DataStruct|null
	 */
	static function load($id) {
		return self::_load(array('id' => $id));
	}


	/**
	 * Obligatory datadefinition function. Datastruct uses this static function to define what needs to be stored/ loaded
	 */
	static function initDataDefinitions() {

		// we start with a shorthand for the used table in the db.
		// alternatively, we can call ->setDatabaseFieldName('example_object',<fieldName>) on all fields.

		DataStructManager::startDBTable('example_object');

		// create an integer id
		// Use this field as keyfield in arrays / collections (obligatory on max 1 field)
		// Mark this as a part of the primary key (isDBKey)

		Field\IntegerField::createAndRegister('id')
			->isVirtualCollectionKey()
			->isDBKey();

		// create a non empty 'name' stringfield;
		Field\StringField::createAndRegister('name')
			->addConstraint(new Constraint\NotEmptyConstraint());

		// create a 'otherName' stringfield. If no databasename is given,
		// a translation from camelcase to lowercaseUnderscore is performed (otherName => `other_name`)
		Field\StringField::createAndRegister('otherName');

		// end our shorthand
		DataStructManager::endDBTable();
	}

	function __construct() {
		// obligatory to make the datastruct work it's magic
		$this->initDataStruct();

		// The errorObject will contain validation errormessages generated by e.g. constraints.
		$this->errors = \CF\Runtime\Runtime::gI()->createSystemErrorCollection();
	}

	private function initNew() {
		// We do not rely on sql to supply an id. This ensures we have an unique id even before the object is stored.
		$this->id = autoIdReserveId('exampleObject');
	}

	/**
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}
	/**
	 * @param String $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return String
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param String $otherName
	 */
	public function setOtherName($otherName) {
		$this->otherName = $otherName;
	}

	/**
	 * @return String
	 */
	public function getOtherName() {
		return $this->otherName;
	}


	public function isValid() {
		// validate the object and place validationerrors in this->errors
		$this->validate($this->errors);
		return (!$this->errors->hasErrors());
	}

	public function store() {
		if(!$this->isValid()) {
			throw new Exception('Store called on an invalid object');
		}
		// hand over storing the object to the datastruct.
		$this->_storeDataStruct();
	}



}

