<?php
// CSV file reader
// Andre de Jong
// versie 0.20081004
// can alter the delimiter

namespace CF\Tool;

use CF\ErrorCollection;
use CF\Runtime;

define('_PARSE_OK_', 1);
define('_PARSE_ERROR_', 0);

class CsvReader {

	const PARSE_ERROR = 0;
	const PARSE_OK = 1;

	var $fixedColumnCount;
	var $result;        // is _PARSE_OK_ of _PARSE_ERROR_
	var $relaxQuoteCycle;
	var $delimiter;
	var $detectDelimiter;

	var $maxColumnCount;

	/**
	 * @var ErrorCollection
	 */
	private $errors;

	function __construct() {
		$this->fixedColumnCount = false;
		$this->relaxQuoteCycle = false;
		$this->result = _PARSE_OK_;
		$this->delimiter = ',';
		$this->detectDelimiter = false;
		$this->maxColumnCount = 0;
		$this->errors = Runtime::gI()->createErrorCollection();
		// we willen het gedrag in de gaten kunnen houden, maar niet automatisch loggen.
		$this->errors->setAutoLogEnabled(false);
	}

	function assertColumnCount($count) {
		$this->fixedColumnCount = 1;
		$this->columnCount = $count;
	}

	function assertMinColumnCount($count) {
		$this->fixedColumnCount = 2;
		$this->columnCount = $count;
	}

	function changeDelimiter($newDelimiter) {
		$this->delimiter = $newDelimiter;
	}

	// de postbank levert geen valide csv aan (quotes in het laatste veld worden niet gedubbeld), met relaxQuotes wordt er niet verder gelezen in een " fdsfds"fdsfdf ", veld
	// DOE!!! : (Gebruik In combinatie met assertColumnCount zodat foute csv in ieder geval herkend wordt.
	function relaxQuotes() {
		$this->relaxQuoteCycle = true;
	}

	function autoDetectDelimiter($toggle = true) {
		$this->detectDelimiter = $toggle;
	}

	private function detectDelimiter($asciiContent) {
		$cp = substr($asciiContent, 0, 40000);
		$charsUsed = count_chars($cp);
		$separatorCandidates = array(',', ';', "\t");
		$maxCandidate = $this->delimiter;
		foreach($separatorCandidates as $candidate) {
			if($charsUsed[ord($candidate)] > $charsUsed[ord($maxCandidate)]) {
				$maxCandidate = $candidate;
			}
		}
		$this->delimiter = $maxCandidate;
	}

	function parseData($content, $attemptMultiline = false, $inputCharset = 'ASCII') {
		$output = array();
		// transform encoding:
		$content = str_replace(array("\n\r", "\r\n", "\r"), "\n", $content);
		if($inputCharset !== 'ASCII') {
			$asciiContent = mb_convert_encoding($content, 'ASCII', $inputCharset);
		} else {
			$asciiContent = $content;
		}
		$content = mb_convert_encoding($content, 'UTF-8', $inputCharset);

		if($this->detectDelimiter) {
			$this->detectDelimiter($asciiContent);
		}
		if($attemptMultiline) {
			$quoteUp = false;

			$split = str_split($asciiContent);
			$index = 0;
			$replaceCount = 0;
			foreach($split as $byte) {
				if($byte == '"') {
					$quoteUp = !$quoteUp;
				}
				if($quoteUp && $byte == "\n") {
					$content = mb_substr($content, 0, $index + ($replaceCount * 11)) . '<<[return]>>' . mb_substr($content, $index + 1 + ($replaceCount * 11)); // (11 = aantal chars MEER dan \n)
					$replaceCount++;
				}
				$index++;
			}
		}
		$contentLines = explode("\n", $content);

		foreach($contentLines as $line) {
			$_line = trim($line);
			if(empty($_line)) {
				continue;
			}

			if($attemptMultiline) {
				$line = str_replace('<<[return]>>', "\n", $line);
			}
			$lineLength = mb_strlen($line);
			$fields = array();
			$fieldOutput = '';
			$quoteUp = false;
			$quoteCycle = ($this->relaxQuoteCycle) ? 0 : 3; // als deze groter dan 0 wordt geinit dan wordt deze nooit 2
			for($pointer = 0; $pointer < $lineLength; $pointer++) {
				$currentChar = mb_substr($line, $pointer, 1);
				if(($currentChar != $this->delimiter) && $quoteCycle == 2) {
					continue;
				}
				switch($currentChar) {
					case '"':
						if(!$quoteUp) {
							$quoteUp = true;
							$quoteCycle++;
							continue;
						}

						if(((($pointer + 1) < $lineLength) && mb_substr($line, $pointer + 1, 1) == '"')) {
							$fieldOutput .= '"';
							$pointer++;
							continue;
						}

						$quoteCycle++;
						$quoteUp = false;

						continue;
					case $this->delimiter:
						if($quoteUp) {
							$fieldOutput .= $this->delimiter;
							continue;
						}
						$fields[] = trim($fieldOutput);
						$fieldOutput = '';
						$quoteCycle = ($this->relaxQuoteCycle) ? 0 : 3; // als deze groter dan 0 wordt geinit dan wordt deze nooit 2
						continue;
					default:
						$fieldOutput .= $currentChar;

				}
			}

			if($quoteUp) {
				$this->result = _PARSE_ERROR_;
				$this->errors->add('Quote up parserror parsing line: ' . $line);
				return false;
			}

			$fields[] = trim($fieldOutput);
			if($this->fixedColumnCount == 1 && (count($fields) != $this->columnCount) ||
				$this->fixedColumnCount == 2 && (count($fields) < $this->columnCount)
			) {
				//echo 'Assert fixedColumnCount failed';
				$this->errors->add('Failed: fixedColumnCount line: ' . $line);
				$this->result = _PARSE_ERROR_;
				return false;
			}

			if(count($fields) > $this->maxColumnCount) {
				$this->maxColumnCount = count($fields);
			}
			$output[] = $fields;
		}
		return $output;
	}

	public function getMaxColumnCount() {
		return $this->maxColumnCount;
	}

	/**
	 * @return ErrorCollection
	 */
	public function getErrors() {
		return $this->errors;
	}

} // eo class


?>