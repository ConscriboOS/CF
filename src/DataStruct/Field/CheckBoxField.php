<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:46
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use CF\DataStruct\Filter\CheckBoxFilter;
use CF\DataStruct\DataStructManager;

class CheckBoxField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return CheckBoxField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new CheckBoxField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		$form->addCheckboxField($this->code, $default);
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'tinyint';
		$this->databaseFieldSuffix = '(1)';
		$this->nullEqualsEmpty = true;
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
		if($a && $b) {
			return 0;
		}
		if($a) {
			return 1;
		}
		return -1;
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

	/**
	 * @param $value
	 * @return bool
	 */
	public function isEmpty($value, $strictCheck = true) {

		if($this->nullEqualsEmpty) {
			return false;
			// Een checkbox is altijd ingevuld, de waarde kan dan ook niet leeg zijn.
		} else {
			return ($value !== NULL);
		}
	}

	/**
	 * @return CheckBoxFilter
	 */
	public function createFilterObject() {
		return new CheckBoxFilter($this);
	}

	public function formatValue($value, $format = NULL) {
		if($format == \CF\DataStruct\Field\DataStructField::VALUE_FORMAT_XML) {
			return ($value) ? '1' : '0';
		}
		return ($value) ? 'Ja' : 'Nee';
	}

	/**
	 * Converteert de waarde vanuit een xmlstring naar de waarde geschikt voor een object
	 * @param $value
	 * @param $newIds : Referentie naar de nieuwe toegewezen ids uit backups van Conscribo. (alleen nuttig in Conscribo velden)
	 */
	public function parseFromXMLValue($value, &$newIds = NULL) {
		return ($value) ? true : false;
	}
}