<?php
namespace CF\Tool;

class GenericErrorCollection extends \CF\ErrorCollection {

	/**
	 * @return String
	 */
	public function outputFormatted($outputFormat = NULL) {
		return implode(PHP_EOL, $this->errors);
	}
}