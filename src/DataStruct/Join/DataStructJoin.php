<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:55
 */
namespace CF\DataStruct\Join;


abstract class DataStructJoin {

	const TYPE_OBJECT = 'object';
	const TYPE_EXTENSION = 'extension';

	const ORDER_ONE_TO_MANY = 'oneToMany';
	const ORDER_ONE_TO_ONE = 'oneToOne';

	const STRUCTURE_INNER = 'inner';
	const STRUCTURE_LEFT = 'left';

	const ONE = 'one';
	const MANY = 'many';


	/**
	 * @var array(<localFieldName> => <foreignFieldName>, ...)
	 */
	protected $foreignKey;

	/**
	 * @var string You can assign an identifier to a join, so you can retreive it with DataStructManager::getJoinByIdentifier
	 */
	protected $identifier;

	/**
	 * Geeft terug of de join uit aparte objecten bestaat (object), of dat deze in velden in de datastruct zitten(extension)
	 * @return string <object/extension>
	 */
	abstract public function getJoinType();


	/**
	 * Geeft terug of de join een 1-1 of 1-n is
	 * @return DataStructJoin::ORDER...
	 */
	abstract public function getJoinOrder();


	public function setForeignKey(array $keys) {
		$this->foreignKey = $keys;
		return $this;
	}


	/**
	 * Geeft de foreign keys terug
	 * @return array(<localFieldName> => <foreignFieldName>, ...)
	 */
	public function getForeignKey() {
		return $this->foreignKey;
	}

	public function getJoinStructure() {
		return DataStructJoin::STRUCTURE_INNER;
	}

	/**
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * @param string $identifier
	 * @return DataStructJoin
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;
		return $this;
	}



}