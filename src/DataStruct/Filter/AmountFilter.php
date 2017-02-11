<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 12:09
 */
namespace CF\DataStruct\Filter;

class AmountFilter extends \CF\DataStruct\Filter\IntegerFilter {

	/**
	 * met behulp van een phrase kun je zoeken op bedrag met daarin verschillende waarden. Dit wordt vooral gebruikt in combinatie met userinput
	 * Voorbeelden van value: '<40' '50,30' , '50.30' , '>5 && <= 100.30', '6|4'
	 * @param String $value
	 */
	public function phrase($value) {
		$this->operator = 'phrase';
		$this->value = $value;
	}


	public function applyFilterToSqlResult($resource) {
		if($this->operator != 'phrase') {
			return parent::applyFilterToSqlResult($resource);
		}
		$dbFieldName = $this->field->_getFullDatabaseFieldName();
		if($dbFieldName === NULL) {
			throw new Exception('Filtering Non-databasefields not implemented yet');
		}

		/**
		 * Deze routine kan niet zo veel aan als je wellicht zou wensen (haakjes b.v.)  maar was goedkoop gekopieerd uit de collection class
		 * Wellicht kan deze ooit mooier worden gemaakt.
		 */

		$operatorOut = '=';

		$value = $this->value;

		// geen kommas
		$_bQuery = str_replace(array(',', ' '), array('.', ''), $value);
		$orElements = explode('|', $_bQuery);
		$bOrQuery = array();

		foreach($orElements as $element) {
			$_number = '(';
			$andElements = explode('&', $element);
			$bAndQuery = array();
			foreach($andElements as $andElement) {
				$_andNumber = '(';
				if(is_numeric($andElement)) {
					$_andNumber .= $dbFieldName . '=' . $andElement;
				} else {
					$validModifiers = array('<', '>', '<=', '=<', '>=', '=>', '<>', 'N');
					$correct = false;
					foreach($validModifiers as $mod) {
						if($mod == 'N' && mb_substr($andElement, 0, mb_strlen($mod)) == 'N') {
							// NULL
							$correct = true;
							$_andNumber .= $dbFieldName . ' IS NULL';
							break;
						}
						$numericValue = mb_substr($andElement, mb_strlen($mod));
						if((mb_substr($andElement, 0, mb_strlen($mod)) == $mod) && is_numeric($numericValue)) {
							$correct = true;

							switch($mod) {
								case '=<':
									$mod = '<=';
									break;
								case '=>':
									$mod = '>=';
									break;
							}
							$_andNumber .= $dbFieldName . ' ' . $mod . ' ' . dbFloat($numericValue);
							break;
						}
					}
					if(!$correct) {
						continue;
					}
				}
				$_andNumber .= ')';
				$bAndQuery[] = $_andNumber;
			}
			$_number .= implode(' AND ', $bAndQuery);
			$_number .= ')';

			if($_number != '()') {
				$bOrQuery[] = $_number;
			}
		}

		if(count($bOrQuery) > 0) {
			$sqlValue = '(' . implode(' OR ', $bOrQuery) . ')';
			db()->addComplexWhere($resource, $sqlValue);

		} else {
			// kan geen nummer maken
			db()->addComplexWhere($resource, '1 = 2');
		}

	}


}