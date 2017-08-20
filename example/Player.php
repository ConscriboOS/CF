<?php


use CF\DataStruct\DataStruct;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\Field;
use CF\DataStruct\Constraint;

/**
 * Created by PhpStorm.
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

	static function create() {
		$obj = new Player();
		$obj->initNew();
		return $obj;
	}

	static function initDataDefinitions() {
		DataStructManager::startDBTable('example_object');

		Field\IntegerField::createAndRegister('id')
			->isVirtualCollectionKey()
			->isDBKey();

		Field\StringField::createAndRegister('name')
			->addConstraint(new Constraint\NotEmptyConstraint());
		Field\StringField::createAndRegister('otherName');
	}

	function __construct($id = NULL) {
		$this->initDataStruct();
		$this->errors = \CF\Runtime\Runtime::gI()->createSystemErrorCollection();

		$this->id = $id;
		if($this->id !== NULL) {
			$this->_loadDataStruct();
		}
	}

	private function initNew() {
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
		$this->validate($this->errors);
		return (!$this->errors->hasErrors());
	}

	public function store() {
		if(!$this->isValid()) {
			throw new Exception('Store called on an invalid object');
		}
		$this->_storeDataStruct();
	}

}

