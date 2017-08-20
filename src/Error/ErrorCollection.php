<?php
namespace CF\Error;

use CF\Configuration;
use CF\Runtime\RunTime;
use CF\Tool\JsonObject;

abstract class ErrorCollection {

	/**
	 * Definition voor Errors
	 */
	const FATAL_ERROR = 1;
	const NON_FATAL_ERROR = 0;
	const NOTICE = -1;
	const INFO = -2;
	const USER_ERROR = -3;

	const FORMAT_TXT = 'txt';
	const FORMAT_HTML = 'html';
	const FORMAT_JS = 'js';

	use JsonObject;

	/**
	 * @var string[]
	 */
	public $errors;

	/**
	 * @var string Melding indien er geen fouten zijn.
	 */
	public $infoMessage;

	/**
	 * @var String Als er nu fouten optreden, dan worden universele foutmeldingen voorzien van deze context (Een fieldLabel b.v.);
	 */
	public $dynamicContextLabel;

	/**
	 * @var RunTime
	 */
	protected $runTime;

	/**
	 * @var bool Wordt een toegevoegde fout direct weggeschreven in de errorlog? default false
	 */
	protected $autoLogEnabled;

	/**
	 * @var bool Worden foutmeldingen direct wanneer ze worden toegevoegd op het scherm getoond? default false
	 */
	protected $autoDisplayEnabled;


	protected $lastStackTraceTxt;
	protected $lastStackTraceHTML;

	public function __construct(\CF\Runtime\Runtime $runTime) {
		$this->errors = array();
		$this->infoMessage = NULL;
		$this->runTime = $runTime;
		$this->autoLogEnabled = false;
		$this->autoDisplayEnabled = false;
	}

	public function setDynamicContextLabel($label) {
		$this->dynamicContextLabel = $label;
	}

	public function setAutoLogEnabled($toggle) {
		$this->autoLogEnabled = $toggle;
	}

	public function setAutoDisplayEnabled($toggle) {
		$this->autoDisplayEnabled = $toggle;
	}

	/**
	 * @param String $errorMsg
	 */
	public function add($errorMsg) {

		if(!empty($this->dynamicContextLabel)) {
			$errorMsg = str_replace(array('[label]', '[Label]') , array($this->dynamicContextLabel, ucfirst($this->dynamicContextLabel)), $errorMsg);
		} else {
			$errorMsg = str_replace(array('[label]', '[Label]') , array('de waarde', 'De waarde'), $errorMsg);
		}

		$this->errors[] = $errorMsg;
		if($this->autoLogEnabled) {
			$this->writeSingleEntryToLog($errorMsg);
		}

		if($this->autoDisplayEnabled) {

			$this->outputError($errorMsg);

		}

	}

	/**
	 * Geeft terug of er fouten zijn
	 * @return bool
	 */
	public function hasErrors() {
		return count($this->errors) > 0;
	}

	public function returnTrueOrErrors() {
		if(count($this->errors) == 0) {
			return true;
		} else {
			return $this;
		}
	}

	/**
	 * Combineer twee foutobjecten
	 * @param ErrorCollection $errors
	 */
	public function mergeErrors(ErrorCollection $errors) {
		foreach($errors->getErrorStruct() as $msg) {
			$this->add($msg);
		}
		$this->infoMessage = $errors->infoMessage;
	}

	public function clear() {
		$this->errors = array();
	}

	/**
	 * Representeert de fouten op een gebruikersvriendelijke manier
	 * @return String
	 */
	abstract public function outputFormatted($outputFormat = NULL);

	/**
	 * Formateer een systeem foutmelding
	 * @param $errorMsg
	 */
	public function outputError($errorMsg) {
		if(\CF\Runtime\Runtime::gI()->getSapi() == 'cli') {
			print('Error: '. $errorMsg ."\n");
		} else {
			print($errorMsg);
		}
	}

	/**
	 * Geeft een array terug met alle fouten
	 * @return string[]
	 */
	public function getErrorStruct() {
		return $this->errors;
	}

	/**
	 * Als er geen fouten zijn, kan er op deze wijze ook een infomessage worden geset.
	 * Zet een inforMessage
	 * @param $message
	 */
	public function setInfoMessage($message) {
		$this->infoMessage = $message;
	}


	public function writeToLog() {
		foreach($this->errors as $errorMessage) {
			$this->writeSingleEntryToLog($errorMessage);
		}
	}


	public function writeSingleEntryToLog($errorMessage) {
		\CF\Runtime\Runtime::gI()->logAction($errorMessage, 'error');
	}



	public function getStackTrace() {

		// We maken een text en een HTML representatie

		$table = array();
		$out = '';

		$trace = debug_backtrace(false);

		unset($trace[0]); // formatbacktraceEntry
		if(isset($trace[1]) && $trace[1]['function'] == 'add') {	// Add function
			unset($trace[1]); // raise_error
		}
		$count = 0;

		foreach($trace as $row) {
			$line = array();
			$line['line'] = $count;

			if(!isset($row['file'])) {
				$line['file'] = 'Evalled code';
				$out .= $count .': '. 'Evalled code: ';
			} else {
				$path = $this->replacePathInStr($row['file']);
				$line['file'] = $path . ' line '. $row['line'];
				$out .= $count .': '. $path . ' line '. $row['line'] .': ';
			}
			if(isset($row['class'])) {
				$line['class'] = $row['class'] . $row['type'];
				$out .= $row['class'] .$row['type'];
			} else {
				$line['class'] = '';
			}
			$line['function'] = '<b>'. $row['function'] .'</b>(';
			$out .= ''. $row['function'] .'(';

			$args = array();
			if(isset($row['args'])) {
				foreach($row['args'] as $arg) {
					if(is_object($arg)) {
						if(\CF\Runtime\Runtime::gI()->getSapi() != 'cli') {
							$argStr = '<small>Class:</small><b>'. get_class($arg) .'</b>';
						} else {
							$argStr = 'Class: '. get_class($arg) .'';
						}
					} elseif(is_array($arg)) {
						if(\CF\Runtime\Runtime::gI()->getSapi() != 'cli') {
							$argStr = '<small>Array:(disabled)</small>';//<b>'. print_r($arg, true). '</b>';
						} else {
							$argStr = 'Array: (disabled)';
						}
					} else {
						if(\CF\Runtime\Runtime::gI()->getSapi() != 'cli') {
							$argStr = '<small>'.getType($arg) .':</small><b>'. $arg .'</b>';
						} else {
							$argStr = ''.getType($arg) .': "'. $arg .'"';
						}
					}

					$argStr = str_replace(Configuration::gI()->getFileRoot(), 'FileRoot/', $argStr);

					if(strlen($argStr) > 153) {
						$argStr = mb_substr($argStr, 0,75) .' ... '. substr($argStr,-75);
					}
					$args[] = $argStr;
				}
			}
			$out .= implode(', ', $args).')'. "\n";
			$line['function'] .= implode(', ', $args) . ')';
			$table[] = '<tr><td>' . implode('</td><td>', $line) .'</td></tr>';

			$count ++;
		}

		$this->lastStackTraceHTML = '<table><tr><th></th><th>File</th><th>Class</th><th><th>Function</th></tr>'. implode("\n", $table) .'</table>';
		$this->lastStackTraceTxt = $out ."\n";

		if(\CF\Runtime\Runtime::gI()->getSapi() != 'cli') {
			return $this->lastStackTraceHTML;
		} else {
			return $this->lastStackTraceTxt;
		}
	}

	protected function replacePathInStr($path) {

		$replacement = array(Configuration::gI()->getLibraryRoot() => '[LibRoot]/',
								Configuration::gI()->getFileRoot() => '[FileRoot]/');

		$path = substr(str_replace(array_keys($replacement),$replacement, $path), -80);
		if(strlen($path) == 80) {
			$path = '...'. $path;
		}
		return $path;
	}

}

?>
