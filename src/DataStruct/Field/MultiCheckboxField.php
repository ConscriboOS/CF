<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:47
 */
namespace CF\DataStruct\Field;

use ConscriboForm;
use DataStructField;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\Filter\MultiCheckboxFilter;
use EnumField;
use Only;

class MultiCheckboxField extends \CF\DataStruct\Field\DataStructField {
	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return \CF\DataStruct\Field\EnumField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new MultiCheckboxField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	/**
	 * @var array <bitNr> => <label>
	 */
	public $options;

	public function addOption($bitNr, $label) {
		$this->options[$bitNr] = $label;
		return $this;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		//TODO:
		//$form->addDropdownField($this->code, $this->options, $default);
	}

	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'int';
		$this->databaseFieldSuffix = '(20)';
		$this->nullEqualsEmpty = true;
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

		$rA = str_pad(decbin($a), 64, 0, STR_PAD_LEFT);
		$rB = str_pad(decbin($a), 64, 0, STR_PAD_LEFT);

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
	 * Geeft terug of de waarde van dit veld leeg is.
	 * @param $value
	 * @return bool
	 */
	public function isEmptyValue($value, $strictCheck = true) {
		return $value == 0;
	}

	public function createFilterObject() {
		return new MultiCheckboxFilter($this);
	}

	public function formatValue($value, $format = NULL) {
		if($this->isEmptyValue($value)) {
			return '';
		}
		if($format == DataStructField::VALUE_FORMAT_XML) {
			return $value;
		}

		return self::formatBitmaskWithOptions($value, $this->options[$value]);
	}

	/**
	 * @param int   $value
	 * @param array $options (elnr [0..63] => [label]
	 * @return string
	 */
	public static function formatBitmaskWithOptions($value, $options, $short = false) {
		$values = self::explodeBitmask($value, true);
		$res = '';
		if(count($values) == 0) {
			return '';
		} else {
			foreach($values as $index => $value) {
				if($short) {
					if($res !== '') {
						$res .= ', ';
					}
					$res .= $index + 1;
				} else {
					if($res !== '') {
						$res .= "\n";
					}
					if(isset($options[$index])) {
						$res .= ($index + 1) . ': ' . $options[$index];
					} else {
						$res .= $index + 1;
					}
				}
			}
		}
		return $res;
	}


	public static function isBitSet($bitIndex, $bitmask) {
		return (pow(2, $bitIndex) & $bitmask) > 0;
	}

	/**
	 * @param $value
	 * @param $onlySet Only return elements that are set/(1)
	 * @return array <bit> => <bool set or not>
	 */
	public static function explodeBitmask($value, $onlySet = false) {
		$res = array();

		for($i = 0; $i <= 64; $i++) {
			$v = ($value & pow(2, $i)) > 0;
			if($onlySet && !$v) {
				continue;
			}
			$res[$i] = $v;
		}
		return $res;
	}

	/**
	 * @param array $value
	 * @return int
	 */
	public static function implodeBitmask(array $value) {
		if($value === NULL) {
			return 0;
		}
		$res = 0;
		foreach($value as $index => $el) {
			if($el) {
				$res |= pow(2, $index);
			}
		}
		return intval($res);
	}

	public static function removeBitFromBitmask($bitmask, $bitIndex) {
		return (($bitmask >> $bitIndex) << ($bitIndex - 1)) + ($bitmask & (pow(2, $bitIndex) - 1));
	}
}