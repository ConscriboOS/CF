<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:26
 */
namespace CF\DataStruct\Constraint;

use CF\DataStruct\Constraint\DataConstraint;
use CF\Error\ErrorCollection;

class NotEmptyConstraint extends DataConstraint {

	protected  $strict;

	/**
	 * NotEmptyConstraint constructor.
	 * @param bool $strict Is ' ' an empty string? Strict says no, !Strict says yes
	 */
	function __construct($strict = true) {
		$this->errorMessage = '[Label] is verplicht';
		$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		$this->strict = $strict;
		return $this;
	}

	public function assert($value, \CF\Error\ErrorCollection $errors) {

		if(isset($this->field)) {
			if(!$this->field->isEmptyValue($value, $this->strict)) {
				return true;
			}
			$errors->add($this->errorMessage);
			if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
				return false;
			}
			return true;
		}


		if(strlen($value) > 0) {
			return true;
		} else {
			$errors->add($this->errorMessage);
			if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
				return false;
			}
		}
		return true;
	}
}