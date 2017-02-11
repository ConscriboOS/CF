<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 12:01
 */
namespace CF\DataStruct\Filter;
use CF\DataStruct\AmountFilter;
use CF\DataStruct\BankAccountFilter;
use CF\DataStruct\CheckBoxFilter;
use CF\DataStruct\EmailFilter;
use CF\DataStruct\EnumFilter;
use CF\DataStruct\Field\DataStructField;
use CF\DataStruct\Filter;
use CF\DataStruct\FloatFilter;
use CF\DataStruct\IntegerFilter;
use CF\DataStruct\StringFilter;
use CF\DataStruct\TextAreaFilter;
use CF\DataStruct\DataStructManager;
use Exception;

/**
 * User: dre
 * Date: 13-7-14
 * Time: 21:11
 */
abstract class DataStructFilter {

	const GROUP_AND = 'and';
	const GROUP_OR = 'or';

	protected $operator;

	protected $value;


	protected $grouping;

	/**
	 * @var DataStructFilter[]
	 */
	protected $subFilters;


	/**
	 * @var DataStructField
	 */
	protected $field;

	/**
	 * @var String, een id waarmee je het filter kunt identificeren
	 */
	protected $filterIdentifier;

	/**
	 * @param array             $descriptor
	 * @param DataStructField[] $fieldDefinitions
	 * @return DataStructFilter
	 */
	static function createWithDescriptor($descriptor, $fieldDefinitions) {

		$className = $descriptor['type'];
		$filter = new $className($fieldDefinitions[$descriptor['fieldName']]);

		$filter->_setOperator($descriptor['operator']);
		$filter->_setValue($descriptor['value']);
		$filter->_setIdentifier($descriptor['identifier']);
		return $filter;
	}

	/**
	 * Geeft een description waarmee de filter opnieuw kan worden aangemaakt.
	 * @return array
	 */
	public function getDescriptor() {
		$res = array('type' => get_class($this),
					 'fieldName' => $this->field->getName(),
					 'operator' => $this->operator,
					 'value' => $this->value,
					 'identifier' => $this->filterIdentifier);
		return $res;
	}

	/**
	 * Maak een filter aan
	 * @todo: filter is not always applicable and should be removed from the constructor
	 * @param DataStructField
	 */
	public function __construct(DataStructField $field) {
		$this->field = $field;
		$this->_setIdentifier(DataStructManager::gI()->getUniqueId());
	}

	/**
	 * @internal
	 */
	public function _setOperator($operator) {
		$this->operator = $operator;
	}

	/**
	 * @internal
	 */
	public function _setValue($value) {
		$this->value = $value;
	}

	public function _setIdentifier($value) {
		$this->filterIdentifier = $value;
	}

	/**
	 * @param $value
	 * @return DataStructFilter|StringFilter|\CF\DataStruct\Filter\TextAreaFilter|\CF\DataStruct\Filter\IntegerFilter|AmountFilter|FloatFilter|EmailFilter|\CF\DataStruct\Filter\CheckBoxFilter|EnumFilter|BankAccountFilter
	 */
	public function equals($value) {
		if($value === NULL) {
			$this->operator = 'IS';
		} else {
			$this->operator = '=';
		}
		$this->value = $value;
		return $this;
	}

	/**
	 * @param $value
	 * @return DataStructFilter|\CF\DataStruct\Filter\StringFilter|\CF\DataStruct\Filter\TextAreaFilter|\CF\DataStruct\Filter\IntegerFilter|AmountFilter|FloatFilter|EmailFilter|\CF\DataStruct\Filter\CheckBoxFilter|EnumFilter|BankAccountFilter
	 */
	public function notEquals($value) {
		if($value === NULL) {
			$this->operator = 'IS NOT';
		} else {
			$this->operator = '<>';
		}
		$this->value = $value;
		return $this;
	}

	public function in($values) {
		$this->operator = 'in';
		$this->value = $values;
		return $this;
	}

	public function notIn(Array $values) {
		$this->operator = 'not in';

		$this->value = $values;
		return $this;
	}

	/**
	 * @param $value
	 * @return DataStructFilter|\CF\DataStruct\Filter\StringFilter|TextAreaFilter|\CF\DataStruct\Filter\IntegerFilter|AmountFilter|\CF\DataStruct\Filter\FloatFilter|\CF\DataStruct\Filter\EmailFilter|\CF\DataStruct\Filter\CheckBoxFilter|EnumFilter|BankAccountFilter
	 */
	public function like($value) {
		$this->operator = 'like';
		$this->value = $value;
		return $this;
	}

	public function applyFilterToSqlResult($resource) {
		$dbFieldName = $this->field->_getFullDatabaseFieldName();
		if($dbFieldName === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}
		switch($this->operator) {
			case 'IS NOT':
			case 'IS':
			case '>':
			case '>=':
			case '<':
			case '<=':
			case '<>':
			case '=':
				gR()->db()->addWhere($resource, $dbFieldName, $this->operator, $this->escapeElementaryValue($this->value));
				break;
			case 'in':
				$sqlEl = array();
				foreach($this->value as $el) {
					$sEl = $this->escapeElementaryValue($el);
					$sqlEl[$sEl] = $sEl;
				}

				if(count($sqlEl) == 0) {
					// lege resultset:
					db()->addComplexWhere($resource, '1 = 2');
					return;
				}
				db()->addComplexWhere($resource, $dbFieldName . ' IN (' . implode(',', $sqlEl) . ')');
				break;
			case 'not in':
				$sqlEl = array();
				foreach($this->value as $el) {
					$sEl = $this->escapeElementaryValue($el);
					$sqlEl[$sEl] = $sEl;
				}

				if(count($sqlEl) == 0) {
					// niets in not in, alles teruggeven
					return;
				}
				db()->addComplexWhere($resource, $dbFieldName . ' NOT IN (' . implode(',', $sqlEl) . ')');
				break;
			case 'like':
				db()->addComplexWhere($resource, $dbFieldName . ' LIKE (' . $this->escapeElementaryValue($this->value) . ')');
		}
	}

	protected function escapeElementaryValue($value) {
		return $this->field->dbFormat($value);
	}

	/**
	 * @return DataStructField
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * @param String $identifier Geef een naam aan deze filter
	 */
	public function setIdentifier($identifier) {
		$this->filterIdentifier = $identifier;
	}

	public function getIdentifier() {
		return $this->filterIdentifier;
	}


	public function andGroup($identifier = NULL) {
		$this->grouping = DataStructFilter::GROUP_AND;
		return $this;
	}

	public function orGroup($identifier = NULL) {
		$this->grouping = DataStructFilter::GROUP_OR;
		return $this;
	}

}