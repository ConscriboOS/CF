<?php
/**
 * User: Dre
 * Date: 17-8-14
 * Time: 15:21
 */
namespace CF\DataStruct;

use CF\DataStruct\DataStructCollection;
use CF\DataStruct\DataStructCollectionDefinition;
use CF\DataStruct\VirtualDataStructCollection;

/**
 * Class ter optimalisatie van de dataStructures (Zodat we dingen maar 1 keer hoeven laden e.d!)
 */
class DataStructCollectionManager {

	const LOAD_TYPE_PARTIAL = 1;
	const LOAD_TYPE_FULL = 2;

	/**
	 * @var DataStructCollectionDefinition[]
	 */
	private $collectionDefinitions;

	/**
	 * Geeft per className aan welke collection hiervoor gedefnieerd is.
	 * @var string[]
	 */

	private $definingCollectionName;

	/**
	 * @return DataStructCollectionManager
	 */
	static function gI() {
		static $manager = NULL;
		if($manager === NULL) {
			$manager = new DataStructCollectionManager();
		}
		return $manager;
	}


	function __construct() {
		$this->collectionDefinitions = array();
	}

	/**
	 * @param $collectionClassName
	 * @return DataStructCollectionDefinition
	 */
	public function getDefinition($collectionClassName) {
		$collectionClassName = DataStructManager::getUniversalClassName($collectionClassName);

		if(!isset($this->collectionDefinitions[$collectionClassName])) {
			if(!VirtualDataStructCollection::isVirtual($collectionClassName)) {
				$this->collectionDefinitions[$collectionClassName] = $collectionClassName::initializeCollectionDefinition();
			} else {
				// create a universal collection suitable for general use:
				$this->collectionDefinitions[$collectionClassName] = VirtualDataStructCollection::initializeCollectionDefinition($collectionClassName);
			}
		}
		return $this->collectionDefinitions[$collectionClassName];
	}

	/**
	 * Geeft een unique blockName terug zodat we geen dbnamespaceconflicten krijgen.
	 * @param $collectionClassName
	 * @return string
	 */
	public function getUniqueDbBlockName($collectionClassName) {
		$collectionClassName = DataStructManager::getUniversalClassName($collectionClassName);
		static $nextNr = 0;

		$nextNr++;

		return $collectionClassName . '_' . $nextNr;

	}

	public function setDefiningCollectionName($className, $collectionName) {
		$this->definingCollectionName[DataStructManager::getUniversalClassName($className)] = DataStructManager::getUniversalClassName($collectionName);
	}

	/**
	 *
	 * Create a new collection suitable for given class
	 * @param $className
	 * @return DataStructCollection
	 */
	public function getNewCollectionForObjectType($className) {
		$className = DataStructManager::getUniversalClassName($className);
		// Find a cached definingCollectionName
		if(isset($this->definingCollectionName[$className])) {
			$collClassName = $this->definingCollectionName[$className];
			if($collClassName == '\\VirtualDataStructCollection') {
				return VirtualDataStructCollection::create($className);
			}
			return new $collClassName();
		}

		// not found? search for the correct classname
		foreach($this->collectionDefinitions as $collectionClassName => $definition) {
			if($definition->getBaseClassName() == $className) {
				if(VirtualDataStructCollection::isVirtual($collectionClassName)) {
					$collection = VirtualDataStructCollection::create($className);
					$this->setDefiningCollectionName($className, '\\VirtualDataStructCollection');
				} else {
					$collection = new $collectionClassName();
					$this->setDefiningCollectionName($className, $collectionClassName);
				}
				return $collection;
			}
		}

		$collection = VirtualDataStructCollection::create($className);
		$this->setDefiningCollectionName($className, '\\VirtualDataStructCollection');
		return $collection;
	}


	/**
	 * Create a new empty DatastructCollection suitable for given class
	 * @param $className
	 * @return \CF\DataStruct\DataStructCollection
	 */
	public function getEmptyCollectionForObjectType($className) {
		$res = $this->getNewCollectionForObjectType($className);
		$res->createFilterNoResultsOnNoFilter();
		return $res;
	}

}