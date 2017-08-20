<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:33
 */
namespace CF\DataStruct\Constraint;


use CF\Error\ErrorCollection;

class LengthConstraint extends \CF\DataStruct\Constraint\DataConstraint {

	public $min;
	public $max;

	function __construct($min = NULL, $max = NULL) {
		if($min !== NULL && $max !== NULL) {
			$this->errorMessage = '[label] dient een lengte te hebben tussen de ' . $min . ' en ' . $max . ' karakters';
		}
		$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		return $this;
	}

	public function assert($value, \CF\Error\ErrorCollection $errors) {

		if(empty($value)) {
			return true;
		}

		if($this->min !== NULL && mb_strlen($value) < $this->min) {
			$errors->add($this->errorMessage);
			if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
				return false;
			}
		}
		if($this->max !== NULL && mb_strlen($value) > $this->max) {
			$errors->add($this->errorMessage);
			if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
				return false;
			}
		}
		return true;
	}
}