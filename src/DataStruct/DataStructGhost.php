<?php
/**
 * User: Dre
 * Date: 6-2-2017
 * Time: 12:00
 */

namespace CF\DataStruct;

/**
 * Class DataStructGhost
 * Used to handle runtime extendability for classes. This class is instantiated and mimicks an actual instantiation,
 * until datastruct determines which specialization of the actual class needs to be instantiated
 * @package CF\DataStruct
 */

class DataStructGhost {
	use DataStruct;

	private $baseUponClass;

	private $localRecord;

	function __construct($baseUponClass) {
		// load definitions:
		$this->baseUponClass = $baseUponClass;
		$this->initDataStruct();
		$this->localRecord = NULL;
	}

	/**
	 * Function, helper to help determine which instance should be created
	 * @param $keyValues
	 */
	public function loadBaseData($keyValues) {
		//
		foreach($keyValues as $keyName => $value) {
			$this->set($keyName, $value);
		}
		$this->localRecord = $this->_loadLocalRecord();
	}

	/**
	 * Using the local loaded data, determine the appropriate class and return it
	 * @return string
	 */
	public function determineClassName() {
		if($this->localRecord === NULL) {
			// We cannot know the class if no data is found.
			return $this->baseUponClass;
		}

		$extendabilityInfo = DataStructManager::gI()->getExtendabilityInfo($this->baseUponClass);

		$callBackClass = $this->baseUponClass;
		$callBackFunction = $extendabilityInfo['determinerFunctionName'];
		$value = $this->_getValue($extendabilityInfo['fieldName']);
		$className = $callBackClass::$callBackFunction($value);
		return $className;
	}

	/**
	 * @return NULL| array The local loaded record.
	 */
	public function getLocalRecord() {
		return $this->localRecord;
	}



	/**
	 * Overriden, for ghosting
	 * Because we are ghosting another class, we should return the basedupon class
	 */
	protected function _getClassName() {
		return $this->baseUponClass;
	}

	/**
	 * Overridden for ghosting
	 * Extended function to simulate baseClass
	 */
	protected function initDataStruct() {

		$struct = DataStructManager::gI()->getDataDefinitionsByClassName($this->baseUponClass);

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

}