<?php


namespace CF\DataStruct\Constraint;

use CF\Error\ErrorCollection;

class PregConstraint extends \CF\DataStruct\Constraint\DataConstraint {

	private $regularExpression;

	function __construct($regEx) {
		$this->errorMessage = '[label] is ongeldig';
		$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		$this->regularExpression = $regEx;
		return $this;
	}

	public function assert($value, \CF\Error\ErrorCollection $errors) {
		if(!preg_match($this->regularExpression, $value)) {
			$errors->add($this->errorMessage);
			if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
				return false;
			}
		}
		return true;
	}
}

?>
