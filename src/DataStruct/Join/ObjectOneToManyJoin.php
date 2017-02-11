<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:56
 */
namespace CF\DataStruct\Join;

use CF\DataStruct\DataStructManager;
use enum;

class ObjectOneToManyJoin extends \CF\DataStruct\Join\DataStructJoin {

	/**
	 * Register a join with the datastructmanager
	 * @param $joinWhat
	 * @param $howMany
	 * @param $joinWith
	 * @return ObjectOneToManyJoin
	 */
	static function createAndRegister($joinWhat, $howMany, $joinWith) {
		$obj = new ObjectOneToManyJoin($joinWhat, $howMany, $joinWith);
		DataStructManager::gI()->registerJoin($obj);
		return $obj;
	}

	protected $localTable;

	protected $foreignTable;

	/**
	 * De ONE side van de Join
	 * @var string ClassName
	 */
	protected $parentClassName;
	/**
	 * De MANY side van de Join
	 * @var string ClassName
	 */
	protected $childClassName;


	/**
	 * De kant aan wie we op dit moment hangen.
	 * @var enum ONE/MANY
	 */
	protected $ourSide;

	protected $parentPropertyName;
	protected $keyFieldName;

	/**
	 * Verbindt een class met een andere class
	 * @param string $joinWhat , Onze className
	 * @param string $howMany  (ONE / MANY)
	 * @param string $joinWith , ClassName with which the object is joined
	 */
	function __construct($joinWhat, $howMany, $joinWith) {

		if($howMany == \CF\DataStruct\Join\DataStructJoin::ONE) {
			// Wij zijn zelf de many kant:
			$this->childClassName = $joinWhat;
			$this->parentClassName = $joinWith;
			$this->ourSide = \CF\DataStruct\Join\DataStructJoin::MANY;
		} else {
			// Wij zijn zelf de one kant:
			$this->parentClassName = $joinWhat;
			$this->childClassName = $joinWith;
			$this->ourSide = \CF\DataStruct\Join\DataStructJoin::ONE;

			// We moeten nu de foreign keys uit de many kant destileren:

			$joinDef = DataStructManager::gI()->getObjectJoinFromForeignSide($joinWhat, $joinWith);

			// we construeren nu een reversed foreign key:
			$ourForeignKey = array();
			foreach($joinDef->getForeignKey() as $foreignFieldName => $ourFieldName) {
				$ourForeignKey[$ourFieldName] = $foreignFieldName;

			}
			$this->setForeignKey($ourForeignKey);
			$this->setTables($joinDef->getForeignTable(), $joinDef->getLocalTable());
		}
	}

	public function getJoinType() {
		return \CF\DataStruct\Join\DataStructJoin::TYPE_OBJECT;
	}

	public function getJoinOrder() {
		return \CF\DataStruct\Join\DataStructJoin::ORDER_ONE_TO_MANY;
	}

	/**
	 * Administreer waar de gejoinde object moeten worden opgeslagen
	 * @param string $propertyName Propertyname in onze class
	 * @param string $keyFieldName
	 * @return ObjectOneToManyJoin
	 */
	public function setParentProperty($propertyName, $keyFieldName = NULL) {
		$this->parentPropertyName = $propertyName;
		$this->keyFieldName = $keyFieldName;
		return $this;
	}

	public function getParentPropertyName() {
		return $this->parentPropertyName;
	}

	public function getKeyFieldName() {
		return $this->keyFieldName;
	}

	/**
	 * Geeft terug of we aan de One Of Many side van de join staan:
	 * @return enum ONE / MANY
	 */
	public function getOurSide() {
		return $this->ourSide;
	}

	public function setTables($localTable, $foreignTable) {
		$this->localTable = $localTable;
		$this->foreignTable = $foreignTable;
	}

	public function getLocalTable() {
		return $this->localTable;
	}

	public function getForeignTable() {
		return $this->foreignTable;
	}

	public function getForeignClassName() {
		if($this->ourSide == \CF\DataStruct\Join\DataStructJoin::ONE) {
			return $this->childClassName;
		} else {
			return $this->parentClassName;
		}
	}
}