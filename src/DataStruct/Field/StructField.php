<?php

namespace CF\DataStruct\Field;
use CF\DataStruct;
use CF\DataStruct\Constraint;
use ConscriboForm;
use CF\DataStruct\DataStructManager;
use CF\DataStruct\Filter\StringFilter;


class StructField extends DataStruct\Field\DataStructField {

	const TRANSCODING_JSON = 'json';
	const TRANSCODING_SERIALIZE = 'serialize';

	public $transCoding;

	protected $maxRecordSize;

	/**
	 * Register a field with the datastructmanager
	 * @param string      $name Fieldname
	 * @param string|null $code
	 * @param string|null $label
	 * @return StructField
	 */
	static function createAndRegister($name, $code = NULL, $label = NULL) {
		$obj = new StructField($name, $code, $label);
		DataStructManager::gI()->registerField($obj);
		return $obj;
	}

	protected function addSpecializedFieldToForm(ConscriboForm $form, $default = NULL) {
		// todo
		$form->addTextField($this->code, $default);
	}

	public function __construct($name, $code = NULL, $label = NULL) {
		$this->maxRecordSize = 1000;
		parent::__construct($name, $code, $label);
	}

	/**
	 * Geef aan hoe een struct in de db wordt opgeslagen. Default is in JSON formaat, valid options zijn: DatastructField::TRANSCODING_JSON, DataStructField::TRANSCODING_SERIALIZE
	 * @param $transCoding
	 *
	 * @return $this
	 */
	public function setInternalTranscoding($transCoding) {
		$this->transCoding = $transCoding;
		return $this;
	}

	/**
	 * Set the maximum nr of chars that are stored in te db field. This value is used in optimizations using temporary tables
	 * @param int $charSize
	 * @return $this
	 */
	public function setMaximumRecordSize($charSize = 1000) {
		$this->maxRecordSize = $charSize;
		return $this;
	}


	/**
	 * Wat voor type data gebruiken we in dit veld:
	 */
	protected function initDatabaseProperties() {
		$this->databaseFieldType = 'varchar';
		$this->databaseFieldSuffix = '('. dbInt($this->maxRecordSize) .')';
	}


	/**
	 * Hoe gaan zaken de database in:
	 */
	public function dbFormat($value) {
		if($this->transCoding == StructField::TRANSCODING_SERIALIZE) {
			return dbStr(serialize($value));
		} else {
			return dbStruct($value);
		}
	}

	public function parseDBformat($value) {
		if($this->transCoding == StructField::TRANSCODING_SERIALIZE) {
			if(is_unserializable($value)) {
				return unserialize($value);
			} else {
				return NULL;
			}
		} else {
			return parseDbStruct($value);
		}
	}


	public function compareValueAWithB($a, $b) {
		if(($r = $this->compareEmpty($a, $b)) !== NULL) {
			return $r;
		}

		if(json_encode($a) == json_encode($b)) {
			return 0;
		}

		// You cannot compare struct in a sane manner. don't attempt it:
		return -1;
	}

	public function createFilterObject() {
		return new StringFilter($this);
	}

	public function formatValue($value, $format = NULL) {
		if($format == DataStruct\Field\DataStructField::VALUE_FORMAT_XML) {
			return serialize($value);
		}
		return '';
	}

	/**
	 * Converteert de waarde vanuit een xmlstring naar de waarde geschikt voor een object
	 * @param $value
	 * @param $newIds : Referentie naar de nieuwe toegewezen ids uit backups van Conscribo. (alleen nuttig in Conscribo velden)
	 */
	public function parseFromXMLValue($value, &$newIds = NULL) {
		if(is_unserializable($value)) {
			return unserialize($value);
		}
		return NULL;
	}
	/**
	 * Geeft terug of de waarde van dit veld leeg is.
	 * @param $value
	 * @param $strictCheck when is a value empty?
	 * @return bool
	 */
	public function isEmptyValue($value, $strictCheck = true) {
		if($strictCheck) {
			return $value === NULL;
		} else {
			return (!is_array($value) || count($value) == 0);
		}
	}


}

?>
