<?php

namespace CF\DataStruct;

use CF\Exception\DeveloperException;

trait SimpleDataStructSkeleton {

	protected $id;
	protected $errors;

	// we reserveren een id voor deze debitor, maar als deze niet goed blijkt te zijn, dan geven we het id weer terug.
	protected $unusedId;

	static function createWithData($data) {
		$className = get_class();
		$obj = new $className();

		$obj->newObject();
		//TODO: sanitize:
		foreach($data as $key => $value) {
			$obj->setValue($key, $value);
		}
		return $obj;
	}

	static function load($id) {
		$className = get_class();
		$obj = new $className($id);
		$obj->loadWithId();
		return $obj;
	}

	function __construct($id = NULL) {
		$this->initDataStruct();
		$this->errors = \CF\Runtime::gI()->createErrorCollection();
		$this->id = $id;
		$this->unusedId = true;
	}

	protected function loadWithId() {
		$this->_loadDataStruct();
		$this->unusedId = false;
	}

	public function getId() {
		return $this->id;
	}

	protected function newObject() {
		$this->id = autoIdReserveId(get_class($this));
		$this->unusedId = true;

	}

	protected function setValue($key, $value) {
		if(!$this->fieldExists($key)) {
			return;
		}
		$this->touchField($key);
		$this->$key = $value;
	}

	public function getErrors() {
		return $this->errors;
	}

	/**
	 * Functie controleert de validiteit van een debitor
	 * @return true/false
	 * @sets errorMessages
	 */
	public function isValid() {
		$this->errors->clear();
		$this->errors->mergeErrors($this->validate());
		return !$this->errors->hasErrors();
	}

	/**
	 * Slaat het object op.
	 * @pre het object is gevalideerd bevonden door isValid();
	 * @throws DeveloperException EXCEPTION_PRECONDITIONS_NOT_MET
	 * @return void
	 */
	public function store() {
		if(!$this->isValid()) {
			throw new exception('Attempting to store an invalid object');
		}
		$this->_storeDataStruct();
	}

	protected function afterDataStructStore() {
		$this->unusedId = false;
	}

	function __destruct() {
		if($this->unusedId) {
			autoIdFreeId(get_class($this), $this->id);
		}
	}


}
