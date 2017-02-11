<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:33
 */
namespace CF\DataStruct\Constraint;
use CF;


class PositiveConstraint extends \CF\DataStruct\Constraint\DataConstraint {
	function __construct() {
		$this->errorMessage = '[Label] is geen postifief getal';
		$this->errorLevel = \CF\ErrorCollection::NON_FATAL_ERROR;
		return $this;
	}

	public function assert($value, \CF\ErrorCollection $errors) {
		if($value >= 0) {
			return true;
		} else {
			$errors->add($this->errorMessage);
			if($this->errorLevel == CF\ErrorCollection::FATAL_ERROR) {
				return false;
			}
		}
		return true;
	}
}