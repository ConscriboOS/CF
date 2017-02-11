<?php


namespace CF\DataStruct\Join;

use CF\DataStruct;

use CF\DataStruct\DataStructManager;

// Verbind 1 parent object met 1 of meerdere childObjecten

// breidt een object uit met een property met de inhoud van een tabel

// breidt een object uit met extra properties uit een andere tabel (al gedefinieerd in het object)
class ExtendedTableOneToOneLeftJoin extends DataStruct\Join\DataStructJoin {

	/**
	 * Register a join with the datastructmanager
	 * @return ExtendedTableOneToOneLeftJoin
	 */
	static function createAndRegister() {
		$obj = new ExtendedTableOneToOneLeftJoin();
		DataStructManager::gI()->registerJoin($obj);
		return $obj;
	}

	function __construct() {
	}

	public function getJoinType() {
		return DataStruct\Join\DataStructJoin::TYPE_EXTENSION;
	}

	public function getJoinOrder() {
		return DataStruct\Join\DataStructJoin::ORDER_ONE_TO_ONE;
	}

	public function getJoinStructure() {
		return DataStruct\Join\DataStructJoin::STRUCTURE_LEFT;
	}
}


?>
