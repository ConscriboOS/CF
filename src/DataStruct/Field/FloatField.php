<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:45
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use CF\DataStruct\Filter\FloatFilter;
use CF\DataStruct\DataStructManager;

class FloatField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return FloatField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new FloatField($name, $code, $label);
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
		$this->databaseFieldType = 'decimal';
		$this->databaseFieldSuffix = '(11,6)';
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

		// TODO: precision is low at this moment. Maybe we should make it definable
		$rA = round($a * 1000000);
		$rB = round($b * 1000000);

		if($rA == $rB) {
			return 0;
		} elseif ($rA > $rB) {
			return 1;
		}
		return -1;
	}
	/**
	 * Hoe gaan zaken de database in:
	 */
	public function dbFormat($value) {
		return dbFloat($value);
	}

	public function parseDBformat($value) {
		return floatval($value);
	}

	/**
	 * @return \CF\DataStruct\Filter\FloatFilter
	 */
	public function createFilterObject() {
		return new FloatFilter($this);
	}

	public function formatValue($value, $format = NULL) {
		if($format == \CF\DataStruct\Field\DataStructField::VALUE_FORMAT_XML) {
			number_format(floatval($value), 6, ',', '');
		}
		return number_format(floatval($value), 4, ',', '.');
	}

	/**
	 * Converteert de waarde vanuit een xmlstring naar de waarde geschikt voor een object
	 * @param $value
	 * @param $newIds : Referentie naar de nieuwe toegewezen ids uit backups van Conscribo. (alleen nuttig in Conscribo velden)
	 */
	public function parseFromXMLValue($value, &$newIds = NULL) {
		return floatval($value);
	}
}