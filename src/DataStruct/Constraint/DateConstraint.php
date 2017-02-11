<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:34
 */
namespace CF\DataStruct\Constraint;


use CF\ErrorCollection;

class DateConstraint extends \CF\DataStruct\Constraint\DataConstraint {

	function __construct() {
		$this->errorMessage = '[label] bevat geen geldige datum.';
		$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		return $this;
	}

	public function assert($value, \CF\ErrorCollection $errors) {

		if(empty($value) || $value == '0000-00-00') {
			return true;
		} else {
			if(!parseDate($value)) {
				$errors->add($this->errorMessage);
				if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
					return false;
				}
			}
		}
		return true;
	}

}