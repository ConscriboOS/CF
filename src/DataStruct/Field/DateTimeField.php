<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:40
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use CF\DataStruct\Filter\IntegerFilter;
use CF\DataStruct\DataStructManager;

class DateTimeField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return DateTimeField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new DateTimeField($name, $code, $label);
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
		$this->databaseFieldType = 'int';
		$this->databaseFieldSuffix = '(12)';
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

		$rA = round($a);
		$rB = round($b);

		if($rA == $rB) {
			return 0;
		} elseif ($rA > $rB) {
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
	 * Converteert de waarde vanuit een xmlstring naar de waarde geschikt voor een object
	 * @param $value
	 * @param $newIds : Referentie naar de nieuwe toegewezen ids uit backups van Conscribo. (alleen nuttig in Conscribo velden)
	 */
	public function parseFromXMLValue($value, &$newIds = NULL) {
		return round($value);
	}

	public function formatValue($value, $format = NULL) {
		return timeFormat($value);
	}

	/**
	 * @return IntegerFilter
	 */
	public function createFilterObject() {
		return new IntegerFilter($this);
	}
}