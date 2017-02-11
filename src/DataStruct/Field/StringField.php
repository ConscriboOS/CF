<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:39
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\Filter\StringFilter;

class StringField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return StringField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new StringField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		$form->addTextField($this->code, $default);
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'varchar';
		$this->databaseFieldSuffix = '(255)';
	}

	/**
	 * The Comparison guts:
	 * Compare if Value b equals, is greater than, or is lesser than b
	 * CF states that: unless nullEqualsEmpty is on: NULL < empty
	 * @param $a
	 * @param $b
	 * @return int -1 if a < b, 0 if a == b, 1 if a > b
	 */
	public function compareValueAWithB($a, $b) {
		if(($r = $this->compareEmpty($a, $b)) !== NULL) {
			return $r;
		}
		return strnatcmp($a, $b);
	}

	/**
	 * Maak een filter op dit veld
	 * @return StringFilter
	 */
	public function createFilterObject() {
		return new StringFilter($this);
	}
}