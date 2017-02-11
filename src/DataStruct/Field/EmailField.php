<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:46
 */
namespace CF\DataStruct\Field;

use CF\DataStruct\Constraint\EmailConstraint;
use CF\DataStruct\Filter\EmailFilter;
use CF\DataStruct\DataStructManager;

class EmailField extends \CF\DataStruct\Field\StringField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return EmailField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new EmailField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected function addDefaultConstraints() {
		$this->addConstraint(new EmailConstraint());
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'varchar';
		$this->databaseFieldSuffix = '(200)';
	}

	/**
	 * @return EmailFilter
	 */
	public function createFilterObject() {
		return new EmailFilter($this);
	}

}