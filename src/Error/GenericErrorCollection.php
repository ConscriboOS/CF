<?php
namespace CF\Error;

class GenericErrorCollection extends \CF\Error\ErrorCollection {

	/**
	 * @return String
	 */
	public function outputFormatted($outputFormat = NULL) {
		return implode(PHP_EOL, $this->errors);
	}
}