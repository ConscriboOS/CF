<?php

namespace CF\Tool;
use BehaviourException;
use CF\Configuration;
use CF\Indien;
use CF\Runtime\Runtime;


/**
 * Version 2.1 Dre2002/Conscribo
 *
 * [2009-02-02 Andre] eerste versie
 * [2010-01-20 Andre] geschikt gemaakt voor conscribo
 * [2010-29-09 Andre] UTF-8 support en versneld algoritme
 * [2011-04-09 Benjamin] Mogelijk gemaakt om los te gebruiken
 * [2016-01-26 Andre] Namespaces
 *
 * Combineert templates met inhoud
 *
 * Een voorbeeld template
 *
 * {TABLE}
 * 	<table>
 * 		{ROW}
 * 		<tr><td>%CONTENTS%</td></tr>
 *		{/ROW}
 *	</table>
 * {/TABLE}
 *
 * // de viewlogic:
 * $template = new Template('bla.tpl');
 * for($i=0; $i < 3; $i++) {
 *	$template->addData('CONTENTS', $i);
 *	$template->parseOnSpot('ROW');
 * }
 * echo $template->writeSection('TABLE');
 *
 */
class Template {

	// Bestandsnaam van het template
	private $fileName;
	// Inhoud van het bestand in String formaat
	private $rawContent;
	// Array met de waarde per label. Vb. $dataSet['%naam%'] = 'Kees'
	private $dataSet;
	// Houdt alle labels bij die gewist kunnen worden na het wegschrijven van de sectie
	private $notResident;

	private $startSectionBracket;
	private $endSectionBracket;
	private $startVarSection;
	private $endVarSection;

	private $validVarChars;
	private $validSectionChars;
	/**
	 * Definitie van sections in de template
	 */
	private $sections;

	/**
	 * Constructor van de Template parser
	 * Parsed de template direct in het geheugen
	 *
	 * @param String $fileName bevat het complete pad naar de template (Absoluut of relatief)
	 * @param bool   $compat   compatible met oude template parser?
	 * @throws BehaviourException on File not found
	 *
	 */
	function __construct($fileName, $compat = false) {
		$this->fileName = $fileName;
		if(CF_TEMPLATE_ALLOW_MIXED_CASE_VARS) {
			$this->validVarChars = 'a-zA-Z0-9_';
		} else {
			$this->validVarChars = 'A-Z0-9_';
		}
		if(CF_TEMPLATE_ALLOW_MIXED_CASE_SECTIONS) {
			$this->validSectionChars = 'a-zA-Z0-9_';
		} else {
			$this->validSectionChars = 'A-Z0-9_';
		}

		if($compat) {
			$this->startSectionBracket = '%#SO_(['. $this->validSectionChars .']+)#%';
			$this->endSectionBracket = '%#EO_(['.$this->validSectionChars.']+)#%';
			$this->startVarSection = '%^';
			$this->endVarSection = '^%';
			$this->compat = true;
		} else {

			$this->startSectionBracket = '{(['. $this->validSectionChars .']+)}';
			$this->endSectionBracket = '{\/(['. $this->validSectionChars .']+)}';

			$this->startVarSection = '%';
			$this->endVarSection = '%';

			$this->compat = false;
		}

		if(substr($fileName, 0, 1) != '/') {
			$fileName = Configuration::gI()->getFileRoot() . $fileName;
		}

		if(!is_file($fileName)) {
			$this->raise_error('Bestandsnaam template onbekend : ' . $fileName);
		}
		$this->rawContent = file_get_contents($fileName);
		$this->notResident = array();
		$this->dataSet = array();

		// Auto translations kunnen alleen gebruikt worden als de persoon al is geauthenticeerd
		if(isset($_ENV['autoTranslations'])) {
			foreach($_ENV['autoTranslations'] as $key => $value) {
				$this->addHTMLData($key, $value, true);
			}
		}

		if(function_exists('AddDefaultTemplateKeys')) {
			AddDefaultTemplateKeys($this);
		}

		if(defined("WEB_ROOT")) {
			$this->addData('WEB_ROOT', WEB_ROOT, true);
		}
		if(defined("IMG_ROOT")) {
			$this->addData('IMG_ROOT', IMG_ROOT, true);
		}
	}

	/**
	 * COMPATIBILITY
	 */

	public function write_block($blockName) {
		return $this->writeSection($blockName);
	}

	public function add_var($key, $value) {
		$this->addData($key, $value, true);
	}

	public function clear_var($key) {
		$this->addData($key, '', true);

	}

	public function copy_var($src, $dest) {
		$this->dataSet[$dest] = $this->dataSet[$src];
		if(isset($this->notResident[$src])) {
			$this->notResident[$dest] = $dest;
		}
	}

	/**
	 * Geeft de ingevoerde template terug.
	 *
	 * @return String Pad en bestandsnaam van de gebruikte template
	 */
	public function getFileName() {
		return $this->fileName;
	}

	public function getCompatibilityModus() {
		return $this->compat;
	}

	/**
	 * Stelt vast dat de waarde $value gekoppeld wordt aan de label $key. Indien  $resident==true, dan wordt de waarde behouden na het wegeschrijven van de template
	 *
	 * @param String  $key      Label naam ZONDER '%' encodering, vb. '%naam%' wordt aangeduid met 'naam'
	 * @param String  $value    Eigenlijk mixed value, maar het wordt behandeld als string
	 * @param boolean $resident (Default: false) Indien true, dan wordt de $value gekoppeld aan de template totdat iets anders wordt gezegd. Anders niet
	 *
	 */
	public function addData($key, $value, $resident = false) {

		if(_DEBUGGING_) {
			if(is_object($value)) {
				$this->raise_error('Cannot add object to template ' . $key, NOTICE);
				return;
			}
			if(is_array($value)) {
				$this->raise_error('Cannot add array to template ' . $key, NOTICE);
				return;
			}
		}

		$this->dataSet[$key] = $value;
		if(!$resident) {
			$this->notResident[$key] = $key;
		}
	}

	public function addHTMLData($key, $value, $resident = false) {
		$this->addData($key, nl2br(htmlspecialchars($value)), $resident);
	}


	public function concatData($key, $value, $residentOnCreate = false) {
		if(!isset($this->dataSet[$key])) {
			$this->addData($key, $value, $residentOnCreate);
			return;
		}
		$this->dataSet[$key] .= $value;
	}


	/**
	 * Schrijf de section, en concateneer deze in een variabele
	 * @param String $section          de te schrijven section
	 * @param String $label            aan welk stuk data moet het geplakt worden
	 * @param        $residentOnCreate Indien $key nog niet bestaat moet deze dan resident zijn?
	 */
	public function writeAndConcat($key, $section, $residentOnCreate = false) {
		$data = $this->writeSection($section);
		$this->concatData($key, $data, $residentOnCreate);
		return;
	}

	public function parseOnSpot($section, $residentOnCreate = false) {
		$this->writeAndConcat('__' . $section, $section, $residentOnCreate);
	}

	/**
	 * Parseert een sectie in de template en geeft deze sectie als string terug.
	 *
	 * @param String $sectionName De sectie in de template die moet worden geparseerd.
	 * @return String Geparseerde sectie.
	 * @throws BehaviourException on Section not found
	 */
	public function writeSection($sectionName) {

		if(!isset($this->sectionOffset) || is_null($this->sectionOffset)) {
			$this->loadSections();
		}

		if(!isset($this->sectionOffset[$sectionName])) {
			Runtime::gI()->addWarning('Section ' . $sectionName . ' not found in template:' . $this->fileName);
			return;
		}

		if(!isset($this->sectionContents[$sectionName])) {
			$this->loadSectionContents($sectionName);
		}
		// Filter dataset:
		$contents = $this->sectionContents[$sectionName];
		foreach($this->sectionVariables[$sectionName] as $key => $contentPositions) {
			if(isset($this->dataSet[$key])) {
				foreach($contentPositions as $position) {
					$contents[$position] = $this->dataSet[$key];
				}
				if(isset($this->notResident[$key])) {
					unset($this->notResident[$key]);
					unset($this->dataSet[$key]);
				}
			}
		}

		return implode('', $contents);
	}

	/**
	 * Initialiseert $this->sectionOffset met
	 * $this->sectionOffset['<naam van sectie>'] = array(Beginpositie tekst,
	 *                                            Eindpositie tekst)
	 *
	 * @throws BehaviourException on Duplicate end-tags detected
	 * @throws BehaviourException on No corresponging start-tag detected
	 * @throws BehaviourException on Start tag does not precede the end tag
	 */

	private function loadSections() {
		$ready = false;

		// File ook als sectie aanmerken:

		$this->rawContent = str_replace('(['. $this->validSectionChars .']+)', '_FILE', $this->startSectionBracket) . $this->rawContent . str_replace('(['. $this->validSectionChars .']+)', '_FILE', $this->endSectionBracket);
		$this->asciiContent = mb_convert_encoding($this->rawContent, 'ASCII', 'UTF-8');
		//Eerste iteratie, haal elke start tag zijn [naam] and [positie] op
		$startMatches = array();
		$startTagsMatched = array();

		preg_match_all('/' . $this->startSectionBracket . '/', $this->asciiContent, $startMatches, PREG_OFFSET_CAPTURE);

		foreach($startMatches[0] as $sectionId => $match) {
			if(isset($startMatches[1][$sectionId][0])) {
				$sectionsOffset[$startMatches[1][$sectionId][0]] = array('start' => $startMatches[0][$sectionId][1] + mb_strlen($startMatches[0][$sectionId][0]),
																		 'end' => NULL);
				$startTagsMatched[$startMatches[1][$sectionId][0]] = $startMatches[0][$sectionId][0];
			}
		}
		$endMatches = array();
		preg_match_all('/' . $this->endSectionBracket . '/', $this->asciiContent, $endMatches, PREG_OFFSET_CAPTURE);

		foreach($endMatches[0] as $sectionId => $match) {
			if(isset($endMatches[1][$sectionId][0])) {
				// Is er al een begin tag van de sectienaam?
				if(isset($sectionsOffset[$endMatches[1][$sectionId][0]])) {
					// En is deze begin tag al eerder gekoppeld aan een eind tag?
					if($sectionsOffset[$endMatches[1][$sectionId][0]]['end'] != NULL) {
						$this->raise_error('Meerdere dezelfde eindtags gedetecteerd: ' . $endMatches[0][$sectionId][0] . ' zonder begintag');
					} else {
						$sectionsOffset[$endMatches[1][$sectionId][0]]['end'] = $endMatches[0][$sectionId][1];

						unset($startTagsMatched[$endMatches[1][$sectionId][0]]);
					}
					// Zo niet, dan hebben we een eind tag zonder begintag
				} else {
					$this->raise_error('Eindtag: ' . $endMatches[0][$sectionId][0] . ' zonder begintag');
				}
			}
		}

		unset($startTagsMatched['_FILE']);
		if(count($startTagsMatched) > 0) {
			Runtime::gI()->addWarning('Startags ' . implode(', ', $startTagsMatched) . ' zonder eindtag');
		}

		/* - output information -
		 * $sectionsOffset[$sectionname_without_brackets] = ['start' => $offset_with_brackets + strlen($sectionname_with_brackets),
		 * 													'end' => $offset_end_tag_with_brackets]
		 */

		$this->sectionOffset = $sectionsOffset;
	}

	private function loadSectionContents($sectionName) {
		if(!isset($this->sectionOffset[$sectionName])) {
			Runtime::gI()->addWarning('Sectie ' . $sectionName . ' bestaat niet');
			return;
		}
		$content = mb_substr($this->rawContent, $this->sectionOffset[$sectionName]['start'], $this->sectionOffset[$sectionName]['end'] - $this->sectionOffset[$sectionName]['start']);
		$asciiContent = substr($this->asciiContent, $this->sectionOffset[$sectionName]['start'], $this->sectionOffset[$sectionName]['end'] - $this->sectionOffset[$sectionName]['start']);
		// subsections omzetten naar variabelen:
		$startMatches = array();
		preg_match_all('/' . $this->startSectionBracket . '/', $asciiContent, $startMatches, PREG_OFFSET_CAPTURE);
		foreach($startMatches[0] as $sectionId => $match) {
			$_sectionName = $startMatches[1][$sectionId][0];
			$pos1 = strpos($asciiContent, '{' . $_sectionName . '}');
			$pos2 = strpos($asciiContent, '{/' . $_sectionName . '}');
			if($pos1 !== false && $pos2 !== false) {
				$content = mb_substr($content, 0, $pos1) . '%__' . $_sectionName . '%' . mb_substr($content, $pos2 + mb_strlen($_sectionName) + 3);
				$asciiContent = substr($asciiContent, 0, $pos1) . '%__' . $_sectionName . '%' . substr($asciiContent, $pos2 + strlen($_sectionName) + 3);
			}
		}

		$this->sectionContents[$sectionName] = array();
		$this->sectionVariables[$sectionName] = array();

		preg_match_all('/' . preg_quote($this->startVarSection) . '(['. $this->validVarChars .']+)' . preg_quote($this->endVarSection) . '/', $asciiContent, $startMatches, PREG_OFFSET_CAPTURE);

		$currentIndex = 0;
		if(count($startMatches[1]) == 0) {
			$this->sectionContents[$sectionName][$currentIndex] = $content;
			$currentIndex++;
		} else {
			$offsetUnit = $startMatches[0][0][1];
			$this->sectionContents[$sectionName][$currentIndex] = mb_substr($content, 0, $offsetUnit);
			$currentIndex++;

			foreach($startMatches[1] as $index => $match) {
				if(!isset($this->sectionVariables[$sectionName][$match[0]])) {
					$this->sectionVariables[$sectionName][$match[0]] = array();
				}
				$this->sectionVariables[$sectionName][$match[0]][] = $currentIndex;
				$this->sectionContents[$sectionName][$currentIndex] = '';
				$currentIndex++;


				$offset = $startMatches[0][$index][1] + mb_strlen($startMatches[0][$index][0]);
				if($index < (count($startMatches[1]) - 1)) {
					$this->sectionContents[$sectionName][$currentIndex] = mb_substr($content, $offset, $startMatches[0][$index + 1][1] - $offset);
				} else {
					$this->sectionContents[$sectionName][$currentIndex] = mb_substr($content, $offset);
				}
				$currentIndex++;
			}
		}
	}

	private function raise_error($error, $errorLevel = NULL) {
		\CF\Runtime\Runtime::gI()->addError($error);
	}
}

?>
