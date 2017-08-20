<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:27
 */
namespace CF\DataStruct\Constraint;

use CF\DataStruct\Field;
use CF\DataStruct\Field\DataStructField;

abstract class DataConstraint {

	public $errorMessage;
	public $errorLevel;
	/**
	 * @var \CF\DataStruct\Field\DataStructField $field
	 */
	public $field;

	public function setErrorMsg($msg) {
		$this->errorMessage = $msg;
		return $this;
	}

	public function setErrorLevel($level) {
		$this->errorLevel = $level;
		return $this;
	}

	public function setField(Field\DataStructField $field) {
		$this->field = $field;
	}

	/**
	 * Kijkt of de contraint houdt met de huidige waarde. Zo niet, wordt $errors gevuld
	 * @param                     $value
	 * @param \CF\Error\ErrorCollection $errors
	 * @return bool canContinue;
	 */
	abstract public function assert($value, \CF\Error\ErrorCollection $errors);
}