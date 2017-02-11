<?php
/**
 * User: Dre
 * Date: 17-8-14
 * Time: 19:17
 */
namespace CF\DataStruct;

use ArrayAccess;
use CF\DataStruct\DataStructCollection;
use CF\DataStruct\DataStructCollectionDefinition;
use CF\DataStruct\DataStructCollectionManager;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\DataStructOverviewCollectionInterface;
use Countable;
use CF\Exception\DeveloperException;
use Exception;
use IteratorAggregate;

/**
 * Class om voor eenvoudige collections geen aparte class te hoeven aanmaken
 * Deze class gebruikt de definities uit de baseclass, en zet alle gevonden objecten in de variabele 'rows'.
 * Het is niet mogelijk de array 'rows' te voorzien van keys uit de objecten.
 * Indien dit nodig is dan moet er een normale collectionClass worden aangemaakt welke in de initializeCollectionDefinition useFieldAsKey gebruikt.
 *
 * Class VirtualDataStructCollection
 */
class VirtualDataStructCollection implements IteratorAggregate, ArrayAccess, Countable, DataStructOverviewCollectionInterface {

	const BASE_CLASS_PREFIX = 'virtualCollection_';

	use \CF\DataStruct\DataStructCollection;

	public $rows;

	/**
	 * Maak een virtual collection
	 * @param $baseClass
	 * @return VirtualDataStructCollection
	 */
	static function create($baseClass) {
		$baseClass = DataStructManager::getUniversalClassName($baseClass);
		$collection = new VirtualDataStructCollection($baseClass);
		return $collection;
	}

	/**
	 * Geef aan of de gevraagde collection Virtual is, of niet.
	 * @param $className
	 * @return bool
	 */
	static function isVirtual($className) {
		list($nameSpace, $className) = splitClassNameIntoNamespaceAndClassName(DataStructManager::getUniversalClassName($className));
		if(strpos($className, VirtualDataStructCollection::BASE_CLASS_PREFIX) === 0) {
			return true;
		}
		return false;
	}

	/**
	 * Maak een collectionDefinition van een dataStructBaseObject
	 * @param $baseObject
	 */
	static function initializeCollectionDefinition($collectionClassName) {

		list($nameSpace, $rawCollectionClassName) = splitClassNameIntoNamespaceAndClassName(DataStructManager::getUniversalClassName($collectionClassName));

		$baseClass = $nameSpace. ucfirst(substr($rawCollectionClassName, strlen(self::BASE_CLASS_PREFIX)));

		$virtualKey = DataStructManager::gI()->getVirtualKeyForClass($baseClass);

		if($virtualKey === NULL) {
			throw new Exception('No suitable key found for virtual collection: ' . $baseClass);
		}
		$def = DataStructCollectionDefinition::create()
			->collectionOf($baseClass)
			->keptInVariable('rows')
			->useFieldAsKey($virtualKey);
		return $def;
	}

	function __construct($baseClassName) {
		list($nameSpace, $rawClassName) = splitClassNameIntoNamespaceAndClassName(DataStructManager::getUniversalClassName($baseClassName));

		$this->collectionClassName = $nameSpace . self::BASE_CLASS_PREFIX . $rawClassName;

		if(!class_exists($baseClassName)) {
			throw new DeveloperException('Invalid baseClassName: ' . $baseClassName . ' while constructing virtualCollection');
		}

		$this->baseClassName = $baseClassName;
	}

	/**
	 * Expirimental: Gebruik een andere key in de collection, zodat er op een andere manier wordt doorheen gelopen
	 * Indien de key niet unique is, dan worden waarden overschreven
	 * Er is geen check op fieldtype van de key, gebruik een raar veldtype, en het gaat waarschijnlijk stuk.
	 * @param String $fieldName
	 * @return VirtualDataStructCollection
	 * @throws \CF\Exception\DeveloperException
	 */
	public function useOtherIndexField($fieldName) {
		DataStructCollectionManager::gI()->getDefinition($this->getCollectionClassName())->useFieldAsKey($fieldName);
		return $this;
	}


}