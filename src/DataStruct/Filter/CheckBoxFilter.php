<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 12:09
 */
namespace CF\DataStruct\Filter;


class CheckBoxFilter extends \CF\DataStruct\Filter\DataStructFilter {

	public function applyFilterToSqlResult($resource) {
		$dbFieldNames = $this->field->getDatabaseFieldName();
		if($dbFieldNames === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}

		$dbFieldName = $this->field->_getFullDatabaseFieldName();
		if($dbFieldName === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}

		switch($this->operator) {
			case '=':
				if($this->value) {
					db()->addWhere($resource, $dbFieldName, '=', $this->escapeElementaryValue($this->value));
				} else {
					db()->addComplexWhere($resource, '(' . $dbFieldName . ' IS NULL OR ' . $dbFieldName . ' = 0)');
				}
				break;
			default:
				parent::applyFilterToSqlResult($resource);
		}
	}

}