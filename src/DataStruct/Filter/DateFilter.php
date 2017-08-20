<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 12:08
 */
namespace CF\DataStruct\Filter;

class DateFilter extends \CF\DataStruct\Filter\StringFilter {
	public function from($value) {
		$this->operator = '>=';
		$this->value = $value;
		return $this;
	}

	public function to($value) {
		$this->operator = '<=';
		$this->value = $value;
		return $this;
	}

	public function between($from, $to) {
		$this->operator = 'between';
		$this->value = array('from' => $from,
								'to' => $to);
	}


	public function applyFilterToSqlResult($resource) {
		$dbFieldName = $this->field->_getFullDatabaseFieldName();
		if($dbFieldName === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}
		if($this->caseInsensitive) {
			$dbFieldName = 'LOWER(' . $dbFieldName . ')';
		}
		$operatorApplied = false;
		switch($this->operator) {
			case '>=':
			case'<=':
				$operatorApplied = true;
				\CF\Runtime\Runtime::gI()->db()->addWhere($resource, $dbFieldName, $this->operator, $this->escapeElementaryValue($this->value));
				break;
			case 'between':
				\CF\Runtime\Runtime::gI()->db()->addWhere($resource, $dbFieldName, '>=', $this->escapeElementaryValue($this->value['from']));
				\CF\Runtime\Runtime::gI()->db()->addWhere($resource, $dbFieldName, '<=', $this->escapeElementaryValue($this->value['to']));
				$operatorApplied = true;
				break;
		}

		if(!$operatorApplied) {
			parent::applyFilterToSqlResult($resource);
		}
	}
}