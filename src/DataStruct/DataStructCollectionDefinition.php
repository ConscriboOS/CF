<?php
namespace CF\DataStruct;

use CF\Exception\DeveloperException;

/**
 * Created by PhpStorm.
 * User: Dre
 * Date: 17-8-14
 * Time: 14:55
 */
class DataStructCollectionDefinition {

	/**
	 * @var string ClassName on which this collection is based, in universal format.
	 */
	public $className;

	/**
	 * @var string Name of the variable that contains the collected objects
	 */
	public $variableName;

	/**
	 * @var string fieldName in the baseClass used as index for the collection.
	 */
	public $indexField;

	/**
	 * @return DataStructCollectionDefinition
	 */
	static function create() {
		return new DataStructCollectionDefinition();
	}

	/**
	 * Returns className on which this class is based, in universal format.
	 * @return string
	 */
	public function getBaseClassName() {
		return $this->className;
	}

	/**
	 * @param string $objectType
	 * @return DataStructCollectionDefinition
	 */
	public function collectionOf($objectType) {
		$objectType = DataStructManager::getUniversalClassName($objectType);
		$this->className = $objectType;
		return $this;
	}

	/**
	 * @param $variableName
	 * @return DataStructCollectionDefinition
	 */
	public function keptInVariable($variableName) {
		$this->variableName = $variableName;
		return $this;
	}

	/**
	 * Use the field in the baseClass as key/index for objects in this collection
	 * @param string $fieldName
	 * @return DataStructCollectionDefinition
	 */
	public function useFieldAsKey($fieldName) {
		if(!DataStructManager::gI()->fieldExists($this->getBaseClassName(), $fieldName)) {
			throw new DeveloperException('Field ' . $fieldName . ' not found in collection definition');
		}
		$this->indexField = $fieldName;
		return $this;
	}


}