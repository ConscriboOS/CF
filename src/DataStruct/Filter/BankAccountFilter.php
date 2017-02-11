<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 12:10
 */
namespace CF\DataStruct\Filter;

use Exception;

class BankAccountFilter extends \CF\DataStruct\Filter\DataStructFilter {

	public function applyFilterToSqlResult($resource) {
		$dbFieldNames = $this->field->getDatabaseFieldName();
		if($dbFieldNames === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}
		$dbFieldName = $dbFieldNames['iban'];
		$dbFieldName = $this->field->getTableName() . '.`' . $dbFieldName . '`';

		switch($this->operator) {
			case 'IS':
				db()->addWhere($resource, $dbFieldName, 'IS', $this->escapeElementaryValue($this->value));
				break;
			case '=':
				db()->addWhere($resource, $dbFieldName, '=', $this->escapeElementaryValue($this->value));
				break;
			case 'in':
				$sqlEl = array();
				foreach($this->value as $el) {
					$sEl = $this->escapeElementaryValue($el);
					$sqlEl[$sEl] = $this->escapeElementaryValue($sEl);
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
					$sqlEl[$sEl] = $this->escapeElementaryValue($sEl);
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
		return dbStr($value);
	}
}