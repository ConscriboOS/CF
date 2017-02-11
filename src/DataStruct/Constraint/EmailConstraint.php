<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:34
 */
namespace CF\DataStruct\Constraint;


use CF\ErrorCollection;

class EmailConstraint extends \CF\DataStruct\Constraint\DataConstraint {

	function __construct() {
		$this->errorMessage = '[label] bevat geen geldig E-mailadres.';
		$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		return $this;
	}

	public function assert($value, \CF\ErrorCollection $errors) {
		if(empty($value)) {
			return true;
		} else {
			if(!checkEmail($value)) {
				$errors->add($this->errorMessage);
				if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
					return false;
				}
			}
		}
		return true;
	}
}