<?php
/**
 * User: Dre
 * Date: 21-8-14
 * Time: 11:08
 */

namespace CF\DataStruct;
/**
 * Voor Datastructs die van de DatastructCollectionOverview gebruik willen maken.
 * Interface DataStructOverviewCollectionInterface
 */
interface DataStructOverviewCollectionInterface {
	static function createWithDescriptor($descriptor);

	public function getDescriptor();

	/**
	 * @param bool $toggle
	 */
	public function calculateNumberOfRows($toggle);

	public function setLimit($limit);

	public function setOffset($offset);

	public function clearOrder();

	public function addOrder($fieldName, $order);

	public function getBaseClassName();

	public function offsetGet($offset);

	/**
	 * @return array()
	 */
	public function getIds();

	public function getValue($fieldName, $recordId);

	public function getValueFormatted($fieldName, $recordId, $format = NULL);

	public function getNumRows();

}