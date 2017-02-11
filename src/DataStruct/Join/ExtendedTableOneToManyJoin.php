<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:56
 */
namespace CF\DataStruct\Join;

use CF\DataStruct\Field\DataStructField;
use CF\DataStruct\DataStructManager;

class ExtendedTableOneToManyJoin extends \CF\DataStruct\Join\DataStructJoin {

	/**
	 * Register a join with the datastructmanager
	 * @return ExtendedTableOneToManyJoin
	 */
	static function createAndRegister() {
		$obj = new ExtendedTableOneToManyJoin();
		DataStructManager::gI()->registerJoin($obj);
		return $obj;
	}

	private $parentPropertyName;
	private $keyFieldName;
	/**
	 * Zelfde fieldInfo als in een datastruct, maar voor de childs.
	 * @var array(<fieldName> => DataField <field>,...)
	 */
	private $fieldInfo;

	function __construct() {
		$this->fieldInfo = array();
	}

	/**
	 * Geef de join de velddefinitie van de records.
	 * @param DataStructField[] $fields array(DataField <field>,...)
	 */
	public function setFieldDefinitions(array $fields) {
		foreach($fields as $field) {
			$this->fieldInfo[$field->name] = $field;
		}
		return $this;
	}

	/**
	 * @return DataStructField[]
	 */
	public function getFieldInfo() {
		return $this->fieldInfo;
	}

	public function getFieldDefinitionByName($fieldName) {
		if(isset($this->fieldInfo[$fieldName])) {
			return $this->fieldInfo[$fieldName];
		}
		throw new \DeveloperException('Unkown fieldname '. $fieldName. ' in join');
	}
	/**
	 * @param array $keys (foreignkey => ourkey)
	 * @return $this
	 */
	public function setForeignKey(array $keys) {
		parent::setForeignKey($keys);
		// de velden worden in dit geval bij ons opgeslagen. daarom zitten de foreign keys in ons beheer:
		foreach($keys as $parentFieldName => $ourFieldName) {
			if(!$this->fieldInfo[$ourFieldName]->getIsDBKey()) {
				$this->fieldInfo[$ourFieldName]->isDBKey();
				$this->fieldInfo[$ourFieldName]->isForeignKey();
			}
		}
		return $this;
	}


	public function setParentProperty($propertyName, $keyFieldName) {
		$this->parentPropertyName = $propertyName;
		$this->keyFieldName = $keyFieldName;
		if(!$this->fieldInfo[$keyFieldName]->getIsDBKey()) {
			$this->fieldInfo[$keyFieldName]->isDBKey();
		}
		return $this;
	}

	public function getParentPropertyName() {
		return $this->parentPropertyName;
	}

	public function getKeyFieldName() {
		return $this->keyFieldName;
	}

	public function getJoinType() {
		return \CF\DataStruct\Join\DataStructJoin::TYPE_EXTENSION;
	}

	public function getJoinOrder() {
		return \CF\DataStruct\Join\DataStructJoin::ORDER_ONE_TO_MANY;
	}

	/**
	 * Geeft alle foreign tabelnamen terug die in deze join gebruikt wordt.
	 */
	public function getForeignTableNames() {
		$ret = array();
		foreach($this->fieldInfo as $field) {
			$ret[$field->getTableName()] = true;
		}
		return array_keys($ret);
	}

}