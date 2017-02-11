<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:57
 */
namespace CF\DataStruct\Join;

use CF\DataStruct\DataStructManager;

class ExtendedTableOneToOneJoin extends \CF\DataStruct\Join\DataStructJoin {

	/**
	 * Register a join with the datastructmanager
	 * @return ExtendedTableOneToOneJoin
	 */
	static function createAndRegister() {
		$obj = new ExtendedTableOneToOneJoin();
		DataStructManager::gI()->registerJoin($obj);
		return $obj;
	}

	function __construct() {
	}

	public function getJoinType() {
		return \CF\DataStruct\Join\DataStructJoin::TYPE_EXTENSION;
	}

	public function getJoinOrder() {
		return \CF\DataStruct\Join\DataStructJoin::ORDER_ONE_TO_ONE;
	}

}