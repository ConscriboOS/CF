<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:41
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\Filter\TextAreaFilter;

class TextAreaField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return TextAreaField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new TextAreaField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL, $cols = 50, $rows = 3) {
		$form->addTextArea($this->code, $default, $cols, $rows);
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'varchar';
		$this->databaseFieldSuffix = '(1000)';
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
	 * @return \CF\DataStruct\Filter\TextAreaFilter
	 */
	public function createFilterObject() {
		return new TextAreaFilter($this);
	}
}