<?php
namespace CF\DataStruct\Filter;

use CF\DataStruct\Field\DataStructField;
use Exception;
use MultiCheckboxField;


//StringFilter|DataStructTextAreaField|IntegerFilter|AmountFilter|FloatFilter|EmailFilter|CheckBoxFilter|EnumFilter|BankAccountFilter|MultiCheckboxFilter

class MultiCheckboxFilter extends \CF\DataStruct\DataStructIntegerFilter {


	public function containsBitmask($value) {
		$this->operator = 'contains';
		$this->value = $value;
		return $this;
	}

	public function contains($value) {
		return $this->containsBitmask(MultiCheckboxField::implodeBitmask($value));
	}

	public function atLeastBitmask($value) {
		$this->operator = 'atleast';
		$this->value = $value;
		return $this;
	}

	public function atLeast($value) {
		return $this->atLeastBitmask(MultiCheckboxField::implodeBitmask($value));
	}

	public function containsNot($value) {
		return $this->containsNotBitmask(MultiCheckboxField::implodeBitmask($value));
	}

	public function containsNotBitmask($value) {
		$this->operator = 'not';
		$this->value = $value;
		return $this;
	}


	public function applyFilterToSqlResult($resource) {

		$dbFieldName = $this->field->_getFullDatabaseFieldName();
		if($dbFieldName === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}

		switch($this->operator) {
			case 'contains':
				\CF\Runtime\Runtime::gI()->db()->addComplexWhere($resource, $dbFieldName . ' & ' . $this->escapeElementaryValue($this->value) . ' > 0');
				return;
			case 'atleast':
				\CF\Runtime\Runtime::gI()->db()->addComplexWhere($resource, $dbFieldName . ' & ' . $this->escapeElementaryValue($this->value) . ' = ' . $this->escapeElementaryValue($this->value));
				return;
			case 'not':
				\CF\Runtime\Runtime::gI()->db()->addComplexWhere($resource, $dbFieldName . ' & ' . $this->escapeElementaryValue($this->value) . ' = 0');
				return;
			case '=':
				\CF\Runtime\Runtime::gI()->db()->addWhere($resource, $dbFieldName, '=', $this->escapeElementaryValue($this->value));
		}
	}

}

