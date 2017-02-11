<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:40
 */
namespace CF\DataStruct\Field;

use CF\DataStruct\Constraint\DateConstraint;
use ConscriboForm;
use CF\DataStruct\Filter\DateFilter;
use CF\DataStruct\DataStructManager;

class DateField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return DateField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new DateField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		//todo
		$form->addTextField($this->code, $default);
	}

	protected function addDefaultConstraints() {
		$this->addConstraint(new DateConstraint());
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'date';
		$this->databaseFieldSuffix = '';
	}


	/**
	 * Maak een filter op dit veld
	 * @return DateFilter($this)
	 */
	public function createFilterObject() {
		return new DateFilter($this);
	}

	public function isEmptyValue($value, $strictCheck = true) {
		return (empty($value) || $value == '0000-00-00');
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

		return strcmp($a, $b);
	}

	/**
	 * Vergelijk een value van dit datatype, en kijk of deze in de haystack voorkomt
	 * @param $needle
	 * @param $haystack
	 * @return bool
	 */
	public function valueInArray($needle, $haystack) {
		// shortcut omdat deze dingen makkelijker te vergelijken zijn
		return in_array($needle, $haystack);
	}

	public function formatValue($value, $format = NULL) {
		if($this->isEmptyValue($value)) {
			return '';
		}
		if($format == \CF\DataStruct\Field\DataStructField::VALUE_FORMAT_XML) {
			return $value;
		}
		return dateFormat($value);
	}

}