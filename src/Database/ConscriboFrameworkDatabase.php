<?php
namespace CF;

Interface ConscriboFrameworkDatabase {


	public function pushNameSpace($nameSpace);
	public function popNameSpace($currentNameSpace);

	public function startTransaction($result = NULL);

	public function commit($result = NULL);

	public function rollback($result = NULL);

	public function setTransactionControl($val);

	public function connect($which = 'default');
	public function getLink();

	public function select($query, $result = NULL);

	public function query($query, $result = NULL);

	public function fetchRow($result = NULL);

	public function fetchAssoc($result = NULL);
	public function fetchAllAssoc($keyFieldName = NULL, $result = NULL);


	public function fetchAllKeyValue($keyFieldName, $valueFieldName, $result = NULL);

	public function fetchAllIds($distinct = true, $result = NULL);

	public function getNumRows($result = NULL);
	public function affectedRows();

	public function getWarnings($result = NULL);

	public function lastInsertId($result = NULL);


	/**
	 * @param string $tableName welke Tabel moeten we update
	 * @param array  $columns   welke kolommen krijgen de nieuwe waarden
	 * @param array  $keys      welke keys worden geselecteerd
	 * @param array  $values    associatief per record de keys en nieuwe values
	 * @param string $result    optional namespace
	 */
	public function multipleUpdate($tableName, $columns, $keys, $values, $result = NULL);

	/**
	 * Verwijderd meerdere records uit een tabel met een multiple key, zonder hier trage constructies voor nodig te hebben.
	 * @param string $tableName in welke Tabel moeten records worden verwijderd
	 * @param array  $keys      welke keys wordt op geselecteerd
	 * @param array  $values    associatief per record de keys die moeten worden verwijderd.
	 * @param string $result    optional namespace
	 */
	public function multipleDelete($tableName, $keyNames, &$values, $result = NULL);

	/**
	 * Synchroniseert de records in de database (insert, update, delete waar nodig). $SQLData bevat de nieuwe records. op basis van de keys met keyTypes wordt een match gemaakt
	 * @param string $tableName        de tabel die moet worden bijgewerkt
	 * @param string $scopeWherePhrase de scope die geselecteerd wordt in de tabel om aan te geven waarmee vergeleken moet worden b.v. (process_id = 12345 and id = 132) (buiten deze scope worden geen records verwijderd).
	 * @param array  $keyTypes         array met key veldnamen en hun SQL type. b.v. array('process_id' => 'int(11)', 'task_id' => 'int(11))
	 * @param array  $SQLdata          array met de nieuwe SQL waarden (inclusief keys) b.v. array(0 => array ('process_id'= > 1, 'task_id' => 3, 'val_a' => 'NULL'))
	 * @param string $result
	 */
	public function synchronizeTable($tableName, $scopeWherePhrase, $keyTypes, $SQLdata, $result = NULL);

	public function closeConnection();

	public function isConnected();


}