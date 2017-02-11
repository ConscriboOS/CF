<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:46
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use CF\DataStruct\Filter\EnumFilter;
use CF\DataStruct\DataStructManager;

class EnumField extends \CF\DataStruct\Field\DataStructField {
	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return EnumField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new EnumField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	/**
	 * @var array <key> => <value>
	 */
	public $options;

	/**
	 * @param string $key
	 * @param string $value An optional label
	 * @return EnumField
	 */
	public function addOption($key, $value = NULL) {
		if($value === NULL) {
			$value = $key;
		}
		$this->options[$key] = $value;
		return $this;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		$form->addDropdownField($this->code, $this->options, $default);
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'varchar';
		$this->databaseFieldSuffix = '(100)';
		$this->nullEqualsEmpty = true;
	}

	public function isEmptyValue($value, $strictCheck = true) {
		if($this->nullEqualsEmpty) {
			return $value == NULL;
		}
		return $value === NULL;
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

		if($a == $b) {
			return 0;
		}

		foreach($this->options as $key => $value) {
			if($a == $key) {
				return -1;
			}
			if($b == $key) {
				return 1;
			}
		}
		return 0;
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


	public function createFilterObject() {
		return new EnumFilter($this);
	}

	public function formatValue($value, $format = NULL) {
		if($this->isEmptyValue($value)) {
			return '';
		}
		if($format == \CF\DataStruct\Field\DataStructField::VALUE_FORMAT_XML) {
			return $value;
		}
		if(isset($this->options[$value])) {
			return $this->options[$value];
		}
		return $value;
	}
}