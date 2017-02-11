<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:47
 */
namespace CF\DataStruct\Field;

use CF\Tool\BankAccount;
use CF\DataStruct\Constraint\BankAccountConstraint;
use ConscriboForm;
use CF\DataStruct\Filter\BankAccountFilter;
use CF\DataStruct\DataStructManager;
use Exception;

class BankAccountField extends \CF\DataStruct\Field\DataStructField {

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return BankAccountField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new BankAccountField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected $dbFieldInfo;

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		// todo
	}

	protected function addDefaultConstraints() {
		$this->addConstraint(new BankAccountConstraint());
	}

	/**
	 * De Bankaccount bestaat uit 3 velden, we richten het object dan ook als zodanig in.
	 */

	/**
	 * $fieldName is een array met 3 elementen : array('iban' => fieldname iban, 'bic' => fieldname bic, 'name' => fieldname name)
	 */
	public function setDatabaseFieldName($tableName, $fieldName) {
		$this->tableName = $tableName;
		if(_DEBUGGING_ && !validateElements($fieldName, array('iban' => array('string', true),
															  'bic' => array('string', true),
															  'name' => array('string', true)))
		) {
			throw new Exception('FieldName not correct', EXCEPTION_INVALID_DATA_TYPE);
		}
		$this->dbFieldInfo = $fieldName;
		return $this;
	}

	public function parseDBFormat($value) {
		// is een array, en hoeft geen speciale formatting;
		return $value;
	}

	/**
	 * @return array met array('iban' => fieldname iban, 'bic' => fieldname bic, 'name' => fieldname name)
	 */
	public function getDatabaseFieldName() {
		return $this->dbFieldInfo;
	}


	protected function initDatabaseProperties() {
		//void
	}

	public function _getDbFieldNameAndValueAsArray($value) {
		if(empty($this->tableName)) {
			// geen db ondersteuning.
			return array();
		}
		if(BankAccount::isEmptyAccount($value)) {
			$value = array('iban' => NULL, 'bic' => NULL, 'name' => NULL);
		}

		return array($this->dbFieldInfo['iban'] => dbStr($value['iban']),
					 $this->dbFieldInfo['bic'] => dbStr($value['bic']),
					 $this->dbFieldInfo['name'] => dbStr($value['name']),
		);
	}

	/**
	 * Geeft terug of de waarden gelijk zijn.
	 * @param mixed $a
	 * @param mixed $b
	 */
	public function valueEquals($a, $b) {
		if(BankAccount::isEmptyAccount($a)) {
			$a = array('iban' => NULL, 'bic' => NULL, 'name' => NULL);
		}
		if(BankAccount::isEmptyAccount($b)) {
			$b = array('iban' => NULL, 'bic' => NULL, 'name' => NULL);
		}

		return BankAccount::equals($a, $b);
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
		if(BankAccount::isEmptyAccount($a)) {
			$a = array('iban' => NULL, 'bic' => NULL, 'name' => NULL);
		}
		if(BankAccount::isEmptyAccount($b)) {
			$b = array('iban' => NULL, 'bic' => NULL, 'name' => NULL);
		}

		$i = $this->strnatCmp($a['iban'], $b['iban']);
		if($i != 0) {
			return $i;
		}

		$i = $this->strnatCmp($a['nr'], $b['nr']);
		if($i != 0) {
			return $i;
		}

		if(isset($a['tnv'])) {
			$a['name'] = $a['tnv'];
		}
		if(isset($b['tnv'])) {
			$b['name'] = $b['tnv'];
		}

		$i = $this->strnatCmp($a['name'], $b['name']);
		return $i;
	}

	private function strnatCmp($a, $b) {
		if(($r = $this->compareEmpty($a, $b)) !== NULL) {
			return $r;
		}
		return strnatcmp($a, $b);
	}

	/**
	 * @return \CF\DataStruct\Filter\BankAccountFilter
	 */
	public function createFilterObject() {
		return new BankAccountFilter($this);
	}

	public function isEmptyValue($value) {
		return BankAccount::isEmptyAccount($value);
	}

	public function formatValue($value, $format = NULL) {
		return BankAccount::formatIBAN($value);
	}

}