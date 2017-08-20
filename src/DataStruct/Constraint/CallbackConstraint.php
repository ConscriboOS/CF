<?php
/**
 * User: Dre
 * Date: 27-1-2016
 * Time: 11:35
 */
namespace CF\DataStruct\Constraint;


class CallbackConstraint extends \CF\DataStruct\Constraint\DataConstraint {

	private $callBack;

	/**
	 *
	 * @param array $callback De uit te voeren callback voor validatie. Deze dient een boolean terug te geven of de execution door moet gaan. Argumenten zijn value, errors
	 */
	function __construct($callback) {
		$this->callBack = $callback;
		return $this;
	}

	public function assert($value, \CF\Error\ErrorCollection $errors) {
		$continue = true;
		if(isset($this->field)) {
			$continue = call_user_func($this->callBack, $value, $errors);
		}
		return $continue;
	}
}