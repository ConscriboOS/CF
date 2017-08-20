<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 12:06
 */
namespace CF\DataStruct\Filter;

class StringFilter extends \CF\DataStruct\Filter\DataStructFilter {
	public $caseInsensitive;

	public function caseInsensitive() {
		$this->caseInsensitive = true;
		return $this;
	}

	public function applyFilterToSqlResult($resource) {
		$dbFieldName = $this->field->_getFullDatabaseFieldName();
		if($dbFieldName === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}
		if($this->caseInsensitive) {
			$dbFieldName = 'LOWER('. $dbFieldName .')';
		}
		switch($this->operator) {
			case 'IS NOT':
			case 'IS':
			case '=':
			case '<>':
				if($this->caseInsensitive && $this->value !== NULL) {
					$this->value = mb_strtolower($this->value);
				}
				\CF\Runtime\Runtime::gI()->db()->addWhere($resource, $dbFieldName , $this->operator,  $this->escapeElementaryValue($this->value));
				break;
			case 'in':
				$sqlEl = array();

				foreach($this->value as $el) {
					if($this->caseInsensitive && $el !== NULL) {
						$el = mb_strtolower($el);
					}
					$sEl = $this->escapeElementaryValue($el);
					$sqlEl[$sEl] = $sEl;
				}

				if(count($sqlEl) == 0) {
					// lege resultset:
					db()->addComplexWhere($resource, '1 = 2');
					return;
				}


				db()->addComplexWhere($resource, $dbFieldName .' IN ('. implode(',', $sqlEl) .')');


				break;
			case 'not in':
				$sqlEl = array();
				foreach($this->value as $el) {
					if($this->caseInsensitive && $el !== NULL) {
						$el = mb_strtolower($el);
					}

					$sEl = $this->escapeElementaryValue($el);
					$sqlEl[$sEl] = $sEl;
				}

				if(count($sqlEl) == 0) {
					// niets in not in, alles teruggeven
					return;
				}
				db()->addComplexWhere($resource, $dbFieldName .' NOT IN ('. implode(',', $sqlEl) .')');
				break;
			case 'like':
				db()->addComplexWhere($resource, $dbFieldName .' LIKE ('. $this->escapeElementaryValue($this->value) .')');
				break;
			default:
				throw new \Exception('Unknown operator '. $this->operator);
		}
	}

}