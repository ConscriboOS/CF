<?php
namespace CF\DataStruct;

/**
 * User: Dre
 * Date: 2-1-2017
 * Time: 10:37
 */


interface DataStructInterface {

	/**
	 * @param mixed[] $keyValues
	 * @param bool $forceReload
	 * @return DataStructInterface
	 */
	static function _load($keyValues, $forceReload = false);


	/**
	 * @param string $collectionClassName
	 * @return void
	 */
	static function setDefiningCollectionClassName($collectionClassName);

	/**
	 * Functie om het object te kunnen laden met een datastruct record.
	 * Omdat we in een trait zitten weten we niets van de constructor. Daarom moet als de constructor er
	 * anders uit ziet, deze functie worden overschreven
	 * (Ik had liever een abstracte functie ervan gemaakt, maar dat kan niet)
	 * @param string $className de class van het object wat we instantieren
	 * @param array $dbRecord , het base dbRecord van het object
	 * @param NULL | DataStruct $governingObject , vanuit welk object wordt dit object geladen
	 * @param NULL | array $childRecords
	 * @return DataStructInterface
	 */
	function loadWithDBRecord($className, $dbRecord, $governingObject, $childRecords = NULL);

	/**
	 * @param DataStructManager $manager
	 * @return void
	 */
	static function initDataDefinitions(DataStructManager $manager);

	/**
	 * @return void
	 */
	function dataStructCloned();

	/**
	 * Geeft aan of dit object in de database bestaat
	 * @return bool
	 */
	function getObjectExistsInDb();

	/**
	 * Controleert de datastruct op fouten.
	 * @param \CF\Error\ErrorCollection $errors
	 * @return \CF\Error\ErrorCollection errors
	 */
	function validate($errors = NULL);

	/**
	 * Geeft een waarde terug uit het object (gegeven dat deze readable is)
	 * @param $property
	 * @param $_internal = false
	 */
	function _getValue($property, $_internal = false);

	/**
	 * @param string $property
	 * @param null $format Nog te implementeren
	 * @return mixed
	 */
	function _getValueFormatted($property, $format = NULL);

	static function _createDBSelectBlockForLocalData($className, $blockName);

	static function _createDBSelectBlockFromDataStructDefinition($struct, $blockName);

	/**
	 * Vul dit object met uit de DB geladen data.
	 */
	function _hidrateWithDBRecord(array $record);

	/**
	 * Slaat de data uit het object op in de database (Inclusief alle gekoppelde tebellen en objecten!)
	 *
	 * @recursive
	 * @param array $sqlStatements om aan te vullen (deze functie kan vanuit een parent worden aangeroepen:
	 *                             array('insert' => array(<className> => <tableName> => fields, 'update' =>,'delete' => )
	 *
	 */
	function _storeDataStruct(&$sqlStatements = NULL);

	/**
	 * Update $this->recordStatus zodat de storeroutine weet wat er gewijzigd is.
	 * De functie mengt twee bronnen bij elkaar:
	 *  -de recordStatus zoals deze is gebruikt en aangepast (als deze b.v. op changed is gezet hoeven we nergens meer naar te kijken)
	 *  -de oude en nieuwe waarden van de properties voor de gevallen dat de recordstatus unchanged zegt.
	 * @return string new /changed / unchanges / pendingDelete Als er 1 sub is gewijzigd, is deze ook gewijzigd.
	 */
	function _updateRecordDBStatus();

	/**
	 * Geeft terug of een elementaire property in het object is gewijzigd.
	 * @param $fieldName
	 * @return bool
	 */
	function _isValueChanged($fieldName);


	/**
	 * Returns the original value as retreived from the database, or current value if no changes where detected.
	 * @param string $fieldName
	 * @return mixed
	 */
	function _getOriginalValue($fieldName);

	/**
	 * Geeft de waarde van de key van $keyName terug.
	 * Deze functie is gemaakt omdat bij joins er door de parent een indexering vereist is. Hiervoor moeten we altijd een key kunnen opvragen
	 * ook al is deze key (waarschijnlijk) niet public (je wil hem immers niet kunnen setten).
	 * @param string $keyName de naam van de key
	 */
	function _getKeyValue($keyName);

	/**
	 * Geeft een key terug van hoe het object in het universe geladen is.
	 * @return string
	 */
	function getUniversalKey();


	function _loadDataStruct($localRecord = NULL, $childRecords = NULL, $forceExecuting = false);

	function afterDataStructLoad();

	function beforeDataStructStore();





}