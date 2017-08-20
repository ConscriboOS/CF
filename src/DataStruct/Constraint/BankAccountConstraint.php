<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:34
 */
namespace CF\DataStruct\Constraint;

use CF\Error\ErrorCollection;
use CF\Tool\BankAccount;

class BankAccountConstraint extends \CF\DataStruct\Constraint\DataConstraint {

	/**
	 * De value van een bankAccount is een array met een element iban, bic en name
	 */

	function __construct() {
		$this->errorMessage = '[label] is niet geldig: [error]';
		$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		return $this;
	}

	public function assert($value, \CF\Error\ErrorCollection $errors) {
		if(BankAccount::isEmptyAccount($value)) {
			return true;
		} else {
			$errMsg = BankAccount::getErrorMessagesWithInvalidAccount($value);
			if($errMsg !== NULL) {
				$errors->add(str_replace('[error]', $errMsg, $this->errorMessage));
				if($this->errorLevel == ErrorCollection::FATAL_ERROR) {
					return false;
				}
			}
		}
		return true;
	}
}