<?php

namespace CF\Database;

use CF\Database\ConscriboFrameworkDatabase;
use CF\Error\ErrorCollection;
use CF\Exception\DeveloperException;
use CF\Tool\CF;

class Database implements ConscriboFrameworkDatabase{

	const DEFAULT_RESULT = 'default';

	protected $db;
	protected $result;
	protected $cur_query;
	protected $numrows;
	protected $connected;
	protected $ignoreEnvironment;
	protected $tablesWithArchive;

	protected $currentDbName;
	public $qbs;

	private $nameSpace;
	private $nameSpaceStack;
	protected $errorLevel;

	function __construct() {
		$this->connected = false;
		$this->output = array();
		$this->startTime = microtime(true);
		$this->errorLevel = ErrorCollection::FATAL_ERROR;
		$this->ignoreEnvironment = false;

		// Deze tabellen bevatten blob of text velden en kunnen daarom niet in het geheugen geladen worden:
		$this->result = array();
		$this->cur_query = array();
		$this->nameSpace = Database::DEFAULT_RESULT;
		$this->nameSpaceStack = array($this->nameSpace);
	}

	/**
	 * When a field uses the text / blob datatype, mysql is not able to create memory/temporary tables from this table.
	 * The database layer has no complete knowledge about the datatypes and needs to be notified about these tables:
	 */
	static function notifyTableIncapableToUseMemoryEngine($tableName = NULL) {
		static $tableNames = NULL;
		if($tableNames === NULL) {
			$tableNames = array();
		}
		if($tableName !== NULL) {
			$tableNames[$tableName] = $tableName;
		}
		return $tableNames;
	}

	// Zorgt ervoor dat de database in dezelfde
	public function pushNameSpace($nameSpace) {
		$this->nameSpaceStack[] = $this->nameSpace;
		$this->nameSpace = $nameSpace;
	}

	public function popNameSpace($currentNameSpace) {
		if($this->nameSpace !== $currentNameSpace) {
			\CF\Runtime\Runtime::gI()->addError('Incorrect namespace given. Stackerror! : given: ' . $currentNameSpace . ', actual: ' . $this->nameSpace);
		}
		$nameSpace = array_pop($this->nameSpaceStack);
		$this->nameSpace = $nameSpace;
	}

	public function startTransaction($result = NULL) {
		$this->query('SET autocommit = 0', $result);
		$this->query('START TRANSACTION', $result);
	}

	public function commit($result = NULL) {
		$this->query('COMMIT', $result);
		$this->query('SET autocommit = 1', $result);
	}

	public function rollback($result = NULL) {
		$this->query('ROLLBACK', $result);
		$this->query('SET autocommit = 1', $result);
	}

	public function setTransactionControl($val) {
		if(($val) == 'none') {
			$this->errorLevel = ErrorCollection::NON_FATAL_ERROR;
		}
	}

	public function _db() {
		return $this->db;
	}

	/**
	 * Set a connection stored in the configuration.
	 * @param string $which
	 */
	public function connect($which = 'default') {
		
		$config = CF\Configuration::gI()->getDatabaseConfiguration($which);

		return $this->connectCustomDataBase($config['hostName'],
											$config['userName'],
											$config['password'],
											$config['dbName'],
											$config['port']);
	}


	public function connectCustomDataBase($host, $user, $password, $database = NULL, $port = NULL) {
		$this->startTime = microtime(true);
		if($this->connected) {
			return;
		}
		if($port === NULL) {
			$port = 3306;
		}

		if(!$this->db = mysqli_connect($host, $user, $password, NULL, $port)) {
			\CF\Runtime\Runtime::gI()->addError('Mysql connect: Unable to connect to Database!');
		}

		$this->startTime = microtime(true);
		$this->connected = true;

		mysqli_set_charset($this->db, 'utf8');

		if($database !== NULL) {
			if(!mysqli_select_db($this->db, $database)) {
				\CF\Runtime\Runtime::gI()->addError('Mysql connect: Unable to select database');
			}
			$this->currentDbName = $database;
		}

		$this->startTime = microtime(true);
		$this->connected = true;
	}

	/**
	 * Choose another database for this connection
	 * @param $newDbName
	 * @return bool
	 */
	public function switchDatabase($newDbName) {
		$res = mysqli_select_db($this->db, $newDbName);
		$this->currentDbName = $newDbName;
		return $res;
	}

	public function getSelectedDbName() {
		return $this->currentDbName;
	}
	public function getLink() {
		return $this->db;
	}

	/**
	 * @deprecated
	 */
	public function exec_query($query) {
		return $this->select($query);
	}

	public function select($query, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}

		$this->query($query, $result);

		if($this->numrows != 0) {
			return $this->fetchRow($result);
		} else {
			\CF\Runtime\Runtime::gI()->addWarning('select function, but 0 rows returned in query :' . $query);
		}
	}

	/**
	 * @deprecated
	 */
	public function exec_noresult($query, $result = NULL) {
		return $this->query($query, $result);
	}

	public function query($query, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}

		if(!$this->connected) {
			\CF\Runtime\Runtime::gI()->addError('Query: Not connected to database');
			return 0;
		}

		$this->lastInsertId[$result] = '';

		if(_DEBUGGING_) {
			if(!isset($this->output[$result])) {
				$this->output[$result] = '';
			}
			$startTime = microtime(true);
		}

		$attempts = 15;

		while(!($this->result[$result] = mysqli_query($this->db, $query))) {
			switch(mysqli_errno($this->db)) {
				case '1213': /// deadlock
					$attempts--;
					if($attempts >= 0) {
						// retry
						continue;
					}
			}

			\CF\Runtime\Runtime::gI()->addError('Error in query : ' . "\n\"" . $query . "\"\nMessage: ==# " . mysqli_error($this->db) . ' #==');

			unset($this->lastInsertId[$result]);
			return 0;
		}

		$this->cur_query[$result] = $query;
		if(!is_bool($this->result[$result])) {
			$this->numrows[$result] = mysqli_num_rows($this->result[$result]);
			$this->teller[$result] = -1;
		} else {
			$this->numrows[$result] = 0;
		}
		if(_DEBUGGING_) {
			if((microtime(true) - $startTime) > 0.2) {
				//\CF\Runtime::gI()->addNotice('Slow query: '. (microtime(true) - $startTime) . $query );
			}
			$this->output[$result] .= (microtime(true) - $startTime) . "\n" . $query . "\n\n";
		}
		return 1;

	}

	function isError() {
		return (mysqli_error($this->db));
	}

	public function fetchRow($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		$this->teller[$result]++;

		if($this->teller[$result] < $this->numrows[$result]) {
			return mysqli_fetch_row($this->result[$result]);
		} else {
			\CF\Runtime\Runtime::gI()->addWarning('WARNING : Fetched behind last row. Amount of results: ' . $this->numrows[$result] . ' in query: ' . $this->cur_query[$result]);
			return 0;
		}
	}

	/**
	 * @deprecated use fetchRow
	 * @param string $result
	 * @return array
	 */
	public function next_row($result = NULL) {
		return $this->fetchRow($result);
	}

	public function fetchAssoc($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		$this->teller[$result]++;
		if($this->teller[$result] < $this->numrows[$result]) {
			return mysqli_fetch_assoc($this->result[$result]);
		} else {
			return false;
		}
	}


	/**
	 * @deprecated
	 */
	public function fetch_assoc($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		return $this->fetchAssoc($result);
	}

	public function fetchAllAssoc($keyFieldName = NULL, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}

		$out = array();
		for($i = 0; $i < $this->numrows[$result]; $i++) {
			if($keyFieldName === NULL) {
				$out[] = $this->fetchAssoc($result);
			} else {
				$row = $this->fetchAssoc($result);
				if($row[$keyFieldName] === NULL) {
					\CF\Runtime\Runtime::gI()->addNotice('fetchAllAssoc returned a key-value NULL');
				}
				$out[$row[$keyFieldName]] = $row;
			}

		}
		return $out;
	}


	public function fetchAllKeyValue($keyFieldName, $valueFieldName, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		$out = array();
		for($i = 0; $i < $this->numrows[$result]; $i++) {
			$row = $this->fetchAssoc($result);
			if($row[$keyFieldName] === NULL) {
				\CF\Runtime\Runtime::gI()->addNotice('fetchAllAssoc returned a key-value NULL');
			}
			$out[$row[$keyFieldName]] = $row;
		}
		return $out;
	}

	public function fetchAllIds($distinct = true, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		$out = array();
		for($i = 0; $i < $this->numrows[$result]; $i++) {
			list($row) = $this->fetchRow($result);
			if($row === NULL) {
				\CF\Runtime\Runtime::gI()->addNotice('fetchAllIds returned a key-value NULL');
			}
			if($distinct) {
				$out[$row] = $row;
			} else {
				$out[] = $row;
			}
		}
		return $out;
	}

	public function getNumRows($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		return $this->numrows[$result];
	}

	/**
	 * @param string $result
	 * @return mixed
	 * @deprecated
	 */
	public function numRows($result = NULL) {
		return $this->getNumRows($result);
	}

	/**
	 * @deprecated
	 */
	public function num_rows($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		return $this->getNumRows($result);
	}

	/**
	 * @deprecated
	 */
	public function affected_rows() {
		return $this->affectedRows();
	}

	public function affectedRows() {
		return mysqli_affected_rows($this->db);
	}

	public function getWarnings($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		$this->query('SHOW WARNINGS', $result);
		return $this->fetchAllAssoc($result);
	}

	/**
	 * @deprecated
	 */
	public function last_insert_id($result = NULL) {
		return $this->lastInsertId($result);
	}

	public function lastInsertId($result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		if(!empty($this->lastInsertId[$result])) {
			return $this->lastInsertId[$result];
		}

		$this->lastInsertId[$result] = @mysqli_insert_id($this->db);

		if(!empty($this->lastInsertId[$result])) {
			return $this->lastInsertId[$result];
		}
		\CF\Runtime\Runtime::gI()->addWarning('WARNING : last insert id not retreived in query: ' . $this->cur_query[$result]);
	}

	/**
	 * @deprecated use closeConnection
	 */
	public function disconnect() {
		$this->closeConnection();
	}

	// Block queries:

	/**
	 * $db->startBlock('bq',array('post','amount'),'boeking_regels','post');
	 * @param      $name
	 * @param bool $what
	 * @param bool $from
	 * @param bool $where
	 * @param bool $group
	 * @param bool $order
	 * @param bool $limit
	 * @return $this
	 *
	 */
	function startBlock($name, $what = false, $from = false, $where = false, $group = false, $order = false, $limit = false) {
		$this->qbs[$name] = array('what' => array(),
								  'from' => array(),
								  'where' => array(),
								  'having' => array(),
								  'group' => '',
								  'order' => array(),
								  'limit' => '',
								  'sqlCalcFoundRows' => false);

		if($what !== false) {
			$this->addWhat($name, $what);
		}
		if($from !== false) {
			$this->addFrom($name, $from);
		}

		if(is_array($where) && (count($where) == 3)) { // enkele where
			$this->addWhere($name, $where[0], $where[1], $where[2]);
		}

		if($group !== false) {
			$this->setGroup($name, $group);
		}
		if($order !== false) {
			$this->addOrder($name, $order);
		}
		if($limit !== false) {
			$this->setLimit($name, $limit);
		}
		return $this;
	}


	/**
	 * boolean, sets the SQL_CALC_FOUND_ROWS option in the query.
	 * @param $name
	 * @param $calcFoundRows
	 * @return $this
	 */
	function setCalcFoundRows($name, $calcFoundRows) {
		$this->qbs[$name]['sqlCalcFoundRows'] = $calcFoundRows;
		return $this;
	}


	/**
	 * $db->addWhat('q','boekingen.id');
	 * $db->addWhat('q',array('boekingen.id','posten.name as name'));
	 * @param      $name
	 * @param      $what
	 * @param null $alias
	 * @return $this
	 */
	function addWhat($name, $what, $alias = NULL) {
		if($alias !== NULL) {
			$this->addBlock('what', $name, $what . ' AS ' . '`' . $alias . '`');
		} else {
			$this->addBlock('what', $name, $what);
		}
		return $this;
	}


	public function clearWhat($name) {
		$this->qbs[$name]['what'] = array();
	}


	/**
	 * $db->addFrom('q','boekingen');
	 * $db->addFrom('q',array('boekingen','posten'));
	 * @param $name
	 * @param $from
	 * @return $this
	 */
	function addFrom($name, $from) {
		$this->addBlock('from', $name, $from);
		return $this;
	}


	/**
	 * $db->addJoin('q','left', 'relations', 'customfieldvalues', 'id', 'recordid');
	 * results in  FROM relations LEFT JOIN customfieldsvalues ON relations.id = customfieldvalues.recordid
	 * $db->addJoin('q','left', 'relations', 'customfieldvalues', array('id','klant'), ('recordid','klant'));
	 * @param $name
	 * @param $joinType
	 * @param $tableA
	 * @param $tableB
	 * @param $fieldA
	 * @param $fieldB
	 * @return $this
	 */
	function addJoin($name, $joinType, $tableA, $tableB, $fieldA, $fieldB) {
		$this->addBlock('from', $name, array(array($joinType, $tableA, $tableB, $fieldA, $fieldB)));
		return $this;
	}


	/**
	 * $db->addWhere('q','boekingen.post','=','posten.id');
	 * $db->addWhere('q','boekingen.referentie','=','"'.mysqli_real_escape_string($ref).'"');
	 * @param $name
	 * @param $a
	 * @param $operator
	 * @param $b
	 * @return $this
	 */
	function addWhere($name, $a, $operator, $b) {
		$where = array('a' => $a, 'operator' => $operator, 'b' => $b);
		$this->qbs[$name]['where'][] = $where;
		return $this;
	}

	/**
	 * use in combination with $db->archivePrepend();
	 * $db->addComplexWhere('q',$db->archivePrepend().'boekingen.date > 123858889 or '.$db->archivePrepend().'boekingen.date < 124343243');
	 * $db->addComplexWhere('q',$db->archivePrepend()."boekingen.omschrijving like ('%".mysqli_real_escape_string($desc)."%')");
	 * @param $name
	 * @param $where
	 * @return $this
	 */
	function addComplexWhere($name, $where) {
		$this->addBlock('where', $name, $where);
		return $this;
	}

	/**
	 * @param $name
	 * @param $a
	 * @param $operator
	 * @param $b
	 * @return $this
	 */
	function addHaving($name, $a, $operator, $b) {
		$having = array('a' => $a, 'operator' => $operator, 'b' => $b);
		$this->qbs[$name]['having'][] = $having;
		return $this;

	}


	/**
	 * use in combination with $db->archivePrepend();
	 * $db->addComplexWhere('q',$db->archivePrepend().'boekingen.date > 123858889 or '.$db->archivePrepend().'boekingen.date < 124343243');
	 * $db->addComplexWhere('q',$db->archivePrepend()."boekingen.omschrijving like ('%".mysqli_real_escape_string($desc)."%')");
	 * @param $name
	 * @param $having
	 * @return $this
	 */
	function addComplexHaving($name, $having) {
		$this->addBlock('having', $name, $having);
		return $this;
	}


	/**
	 * $db->setGroup('q','boekingen.id');
	 * @param $name
	 * @param $group
	 * @return $this
	 */
	function setGroup($name, $group) {
		$this->qbs[$name]['group'] = $group;
		return $this;
	}

	/**
	 * @param $name
	 * @return $this
	 */
	function setDistinct($name) {
		$this->qbs[$name]['distinct'] = true;
		return $this;
	}

	/**
	 * $db->setLimit('q','100,10');
	 * @param $name
	 * @param $limit
	 * @return $this
	 */
	function setLimit($name, $limit) {
		$this->qbs[$name]['limit'] = $limit;
		return $this;
	}


	/**
	 * $db->addOrder('q','boekingen.date asc');
	 * $db->addOrder('q',array('boekingen.date asc','posten.name desc');
	 * @param $name
	 * @param $order
	 * @return $this
	 */
	function addOrder($name, $order) {
		$this->addBlock('order', $name, $order);
		return $this;
	}

	/**
	 * @param $name
	 * @return $this
	 */
	function clearOrder($name) {
		$this->qbs[$name]['order'] = array();
		return $this;
	}


	/**
	 * Dit is een tijdelijke functie totdat we een fatsoenlijke overerving hebben voor de DB in Conscribo
	 * @param $qbs
	 * @param $name
	 * @return mixed
	 */
	protected function updateQbs($qbs, $name) {
		return $qbs;
	}

	function formatBlockQuery($name) {
		// first create a copy so the blocks are reusable;
		$qbs = $this->qbs[$name];
		if(!isset($qbs)) {
			\CF\Runtime\Runtime::gI()->addWarning('Unkown blockQuery: ' . $name . ' in $Database::formatBlockQuery()');
			return '';
		}

		if(!isset($qbs['what']) || count($qbs['what']) == 0) {
			\CF\Runtime\Runtime::gI()->addWarning('At least a "what" criteria should be present in blockQuery: ' . $name . '. in $Database::formatBlockQuery()');
			return '';
		}

		// Compatibility
		$qbs = $this->updateQbs($qbs, $name);

		//FROM syntax:
		$fromStr = '';
		$fromParts = array();
		$nonFromParts = array();
		foreach($qbs['from'] as $table) {
			if(!is_array($table)) {
				if(!isset($fromParts[$table])) {
					$fromParts[$table] = $table;
				} // else : er staat al een tabelentry dus negeren of er staat een join. dan ook negeren
			} else {
				// JOIN Syntax
				list($joinType, $tableA, $tableB, $fieldA, $fieldB) = $table;
				if($tableA === $tableB) {
					throw new DeveloperException('Cannot join table with the same table' . $tableA);
				}
				$nonFromParts[$tableB] = $tableB;
				$joinParts = array();
				if(is_array($fieldA)) {
					foreach($fieldA as $_index => $_partA) {
						$joinParts[] = $tableA . '.`' . $_partA . '` = ' . $tableB . '.`' . $fieldB[$_index] . '`';
					}
				} else {
					$joinParts[] = $tableA . '.`' . $fieldA . '` = ' . $tableB . '.`' . $fieldB . '`';
				}
				if(isset($fromParts[$tableA]) && $fromParts[$tableA] != $tableA) {
					// er is een andere join aanwezig op deze tabel
					// aan elkaar knopen:
					$fromParts[$tableA] .= ' ' . mb_strtoupper($joinType) . ' JOIN ' . $tableB . ' ON ' . implode(' AND ', $joinParts);
				} else {
					// elementaire from of geen info. gewoon overschrijven.
					$fromParts[$tableA] = $tableA . ' ' . mb_strtoupper($joinType) . ' JOIN ' . $tableB . ' ON ' . implode(' AND ', $joinParts);
				}
			}
		}
		// Tabellen die in een join gebruikt worden niet apart nogmaals benoemen.
		foreach($nonFromParts as $nonFromPart) {
			unset($fromParts[$nonFromPart]);
		}

		$fromStr = implode(',', $fromParts);

		$whereStr = '';
		if(count($qbs['where']) > 0) {
			$whereStr = ' WHERE ';
			$whereStr .= $this->createWhereString($name, 'AND', $qbs);
		}


		$havingStr = '';
		$first = true;
		foreach($qbs['having'] as $havingElement) {
			if(!$first) {
				$havingStr .= ' AND';
			} else {
				$havingStr .= ' HAVING';
			}

			if(is_array($havingElement)) {
				$havingStr .= ' ' . $havingElement['a'] . ' ' . $havingElement['operator'] . ' ' . $havingElement['b'];
			} else {
				$havingStr .= ' ' . $havingElement;
			}
			$first = false;
		}


		$group = (!empty($qbs['group'])) ? ' GROUP BY ' . $qbs['group'] : '';

		$order = '';
		if(count($qbs['order']) > 0) {
			$order = ' ORDER BY ' . implode(', ', $qbs['order']);
		}

		$limit = (!empty($qbs['limit'])) ? ' LIMIT ' . $qbs['limit'] : '';

		$options = ($qbs['sqlCalcFoundRows'] == true) ? 'SQL_CALC_FOUND_ROWS ' : '';

		$options = (isset($qbs['distinct'])) ? $options . 'DISTINCT ' : $options;

		$query = 'SELECT ' . $options . implode(',', $qbs['what']) .
			' FROM ' . $fromStr . $whereStr . $group . $havingStr . $order . $limit;

		return $query;
	}

	/**
	 * Maakt een sql string uit een wherephrase in een blockQuery
	 * @param $blockName
	 * @return string
	 */
	public function createWhereString($blockName, $groupingType = 'AND', $qbs = NULL) {
		if($qbs === NULL) {
			$qbs = $this->qbs[$blockName];
		}

		$whereStr = '';
		$first = true;
		foreach($qbs['where'] as $whereElement) {
			if(!$first) {
				$whereStr .= ' ' . $groupingType;
			} else {
				$whereStr .= '';
			}

			if(is_array($whereElement)) {
				$whereStr .= ' ' . $whereElement['a'] . ' ' . $whereElement['operator'] . ' ' . $whereElement['b'];
			} else {
				$whereStr .= ' ' . $whereElement;
			}
			$first = false;
		}

		return $whereStr;
	}

	public function queryBlock($name, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		return $this->query($this->formatBlockQuery($name), $result);
	}

	private function addBlock($type, $name, $value) {
		if(!is_array($value)) {
			$this->qbs[$name][$type][] = $value;
		} else {
			foreach($value as $valuePart) {
				$this->qbs[$name][$type][] = $valuePart;
			}
		}
	}

	/**
	 * @param string $tableName welke Tabel moeten we update
	 * @param array  $columns   welke kolommen krijgen de nieuwe waarden
	 * @param array  $keys      welke keys worden geselecteerd
	 * @param array  $values    associatief per record de keys en nieuwe values
	 * @param string $result    optional namespace
	 */
	public function multipleUpdate($tableName, $columns, $keys, $values, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}
		if(count($columns) == 0) {
			return true;
		}
		if(count($values) == 0) {
			return true;
		}

		if(count($values) == 1) {

			$value = reset($values);

			$keyFields = array();
			foreach($keys as $key) {
				$keyFields[] = $tableName . '.`' . $key . '` = ' . $value[$key];
			}

			$updateFields = array();

			foreach($columns as $column) {
				$updateFields[] = $tableName . '.`' . $column . '` = ' . $value[$column];
			}

			$query = 'UPDATE `' . $tableName . '` SET ' . implode(',', $updateFields) . ' WHERE ' . implode(' AND ', $keyFields);

			$this->query($query, $result);
			return;

		}

		$tmpTableName = '`' . $tableName . '_tmp_' . uniqid() . '`';

		$allFields = array_unique(array_merge($columns, $keys));
		if(in_array($tableName, self::notifyTableIncapableToUseMemoryEngine())) {
			$engine = '';
		} else {
			$engine = 'ENGINE = MEMORY';
		}

		// Maak een tijdelijke tabel aan met de zelfde definitie als de oorspronkelijke tabel (Zonder dat de keys worden overgenomen)
		$query = 'CREATE TEMPORARY TABLE ' . $tmpTableName . ' ' . $engine . ' AS ' .
			'SELECT `' . implode('`,`', $allFields) . '` FROM ' . $tableName . ' LIMIT 0';
		$this->query($query, $result);

		// index creeren:
		if(count($values) > 500) {
			// alleen voor 'grotere' sets. Dit resulteert in een snellere update
			$query = 'CREATE INDEX idx_tmp ON ' . $tmpTableName . ' (`' . implode('`,`', $keys) . '`)';
			$this->query($query, $result);
		}

		// insert voorbereiden:
		$first = true;
		$insertValues = array();
		foreach($values as $value) {
			if($first) {
				$fieldOrder = array_keys($value);
			}
			$insertValues[] = '(' . implode(',', $value) . ')';
		}

		$insertValues = array_chunk($insertValues, 10000, true);
		foreach($insertValues as $insertSection) {
			$query = 'INSERT INTO ' . $tmpTableName . '(`' . implode('`,`', $fieldOrder) . '`) VALUES ' . implode(',', $insertSection);
			$this->query($query, $result);
		}

		$keyFields = array();
		foreach($keys as $key) {
			$keyFields[] = $tableName . '.`' . $key . '` = ' . $tmpTableName . '.`' . $key . '`';
		}

		$updateFields = array();
		foreach($columns as $column) {
			$updateFields[] = $tableName . '.`' . $column . '` = ' . $tmpTableName . '.`' . $column . '`';
		}

		// Update de kolommen in de originele tabel met behulp van de tijdelijke tabel
		$query = 'UPDATE ' . $tableName . ', ' . $tmpTableName . ' SET ' . implode(',', $updateFields) .
			' WHERE ' . implode(' AND ', $keyFields);
		$this->query($query, $result);

		$query = 'DROP TEMPORARY TABLE ' . $tmpTableName;
		$this->query($query, $result);
		return true;
	}


	/**
	 * Verwijderd meerdere records uit een tabel met een multiple key, zonder hier trage constructies voor nodig te hebben.
	 * @param string $tableName in welke Tabel moeten records worden verwijderd
	 * @param array  $keys      welke keys wordt op geselecteerd
	 * @param array  $values    associatief per record de keys die moeten worden verwijderd.
	 * @param string $result    optional namespace
	 */
	public function multipleDelete($tableName, $keyNames, &$values, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}

		if(count($values) == 0) {
			return true;
		}

		$tmpTableName = '`' . $tableName . '_tmp_' . uniqid() . '`';

		if(in_array($tableName, self::notifyTableIncapableToUseMemoryEngine())) {
			$engine = '';
		} else {
			$engine = 'ENGINE = MEMORY';
		}

		// Maak een tijdelijke tabel aan met de zelfde definitie als de oorspronkelijke tabel (Zonder dat de keys worden overgenomen)
		$query = 'CREATE TEMPORARY TABLE ' . $tmpTableName . ' ' . $engine . ' AS ' .
			'SELECT `' . implode('`,`', $keyNames) . '` FROM ' . $tableName . ' LIMIT 0';
		$this->query($query, $result);

		// index creeren:
		if(count($values) > 500) {
			// alleen voor 'grotere' sets. Dit resulteert in een snellere update
			$query = 'CREATE INDEX idx_tmp ON ' . $tmpTableName . ' (`' . implode('`,`', $keyNames) . '`)';
			$this->query($query, $result);
		}

		// insert voorbereiden:
		$first = true;
		$insertValues = array();
		foreach($values as $value) {
			if($first) {
				$fieldOrder = array_keys($value);
			}
			$insertValues[] = '(' . implode(',', $value) . ')';
		}

		$insertValues = array_chunk($insertValues, 10000, true);
		foreach($insertValues as $insertSection) {
			$query = 'INSERT INTO ' . $tmpTableName . '(`' . implode('`,`', $fieldOrder) . '`) VALUES ' . implode(',', $insertSection);
			$this->query($query, $result);
		}

		$keyFields = array();
		foreach($keyNames as $key) {
			$keyFields[] = $tableName . '.`' . $key . '` = ' . $tmpTableName . '.`' . $key . '`';
		}

		// deleter de rijen in de originele tabel met behulp van de tijdelijke tabel
		$query = 'DELETE ' . $tableName . ' FROM ' . $tableName . ', ' . $tmpTableName
			. ' WHERE ' . implode(' AND ', $keyFields);
		$this->query($query, $result);

		$query = 'DROP TEMPORARY TABLE ' . $tmpTableName;
		$this->query($query, $result);
		return true;
	}


	/**
	 * Synchroniseert de records in de database (insert, update, delete waar nodig). $SQLData bevat de nieuwe records. op basis van de keys met keyTypes wordt een match gemaakt
	 * @param string $tableName        de tabel die moet worden bijgewerkt
	 * @param string $scopeWherePhrase de scope die geselecteerd wordt in de tabel om aan te geven waarmee vergeleken moet worden b.v. (process_id = 12345 and id = 132) (buiten deze scope worden geen records verwijderd).
	 * @param array  $keyTypes         array met key veldnamen en hun SQL type. b.v. array('process_id' => 'int(11)', 'task_id' => 'int(11))
	 * @param array  $SQLdata          array met de nieuwe SQL waarden (inclusief keys) b.v. array(0 => array ('process_id'= > 1, 'task_id' => 3, 'val_a' => 'NULL'))
	 * @param string $result
	 */
	function synchronizeTable($tableName, $scopeWherePhrase, $keyTypes, $SQLdata, $result = NULL) {
		if($result === NULL) {
			$result = $this->nameSpace;
		}

		if(count($SQLdata) > 0) {
			$updateFieldNames = array_keys(reset($SQLdata));
		}

		$stringKeys = array();
		foreach($keyTypes as $keyField => $sqlType) {
			if(stripos($sqlType, 'char') !== false) {
				$stringKeys[$keyField] = $keyField;
			}
		}

		$sqlWhere = '';
		if(!empty($scopeWherePhrase)) {
			$sqlWhere = ' WHERE ' . $scopeWherePhrase;
		}
		$keyFieldsSQL = '`' . implode('`, `', array_keys($keyTypes)) . '`';

		$this->query('SELECT CONCAT_WS("=", ' . $keyFieldsSQL . ') as tmp_id ,' . $keyFieldsSQL . '  FROM `' . $tableName . '`' . $sqlWhere, $result);

		$existingRows = array();
		while($row = $this->fetchAssoc($result)) {
			// keys in charvorm zijn niet leuk:
			foreach($stringKeys as $_key) {
				$row[$_key] = dbStr($row[$_key]);
			}
			$existingRows[$row['tmp_id']] = $row;
		}

		$newRowSQL = array();
		$updateRecords = array();

		foreach($SQLdata as $record) {
			$tmpKeyAr = array();
			foreach($keyTypes as $field => $type) {
				$tmpKeyAr[] = $record[$field];
			}
			$tmpId = implode('=', $tmpKeyAr);

			// Insert:
			if(!isset($existingRows[$tmpId])) {
				$newRowArray = array();
				foreach($updateFieldNames as $fieldName) {
					$newRowArray[] = $record[$fieldName];
				}
				$newRowSQL[] = '(' . implode(',', $newRowArray) . ')';
				continue;
			}

			// Update:
			$updateRecords[] = $record;
			unset($existingRows[$tmpId]);
		}

		//Delete:
		$deleteTable = array();
		foreach($existingRows as $tmpId => $keyValues) {
			foreach($keyTypes as $fieldName => $fieldType) {
				$deleteTable[$tmpId][$fieldName] = $keyValues[$fieldName];
			}
		}

		//EXECUTE:

		//Delete:
		if(count($deleteTable) > 0) {
			$tmpTableName = $this->createTempTableFromRecords($deleteTable, $keyTypes, $result);

			$where = array();
			foreach($keyTypes as $fieldName => $fieldType) {
				$where[] = $tableName . '.`' . $fieldName . '` = ' . $tmpTableName . '.`' . $fieldName . '`';
			}

			$this->query('DELETE ' . $tableName . ' FROM ' . $tableName . ',' . $tmpTableName . ' WHERE ' . implode(' AND ', $where), $result);

			$this->query('DROP TABLE ' . $tmpTableName, $result);
		}

		//update
		if(count($updateRecords) > 0) {
			$updateColumns = $updateFieldNames;
			foreach($updateColumns as $index => $fieldName) {
				if(isset($keyTypes[$fieldName])) {
					unset($updateColumns[$index]);
				}
			}
			$this->multipleUpdate($tableName, $updateColumns, array_keys($keyTypes), $updateRecords, $result);
		}

		//insert
		if(count($newRowSQL) > 0) {
			$this->query('INSERT INTO ' . $tableName . ' (' . implode(',', $updateFieldNames) . ') VALUES ' . implode(',', $newRowSQL), $result);
		}

	}

	/**
	 * @param array  $records    SQL geformatteerde data (b.v. ('id' => 23, 'val_a' => 'NULL', 'val_b' => 324)
	 * @param array  $fieldTypes veldtypen van de data: b.v. array('id' => 'int(11)', 'val_a' => varchar(100), ...)
	 * @param string $result     ;
	 * @return string $tmpTableName;
	 */
	private function createTempTableFromRecords($records, $fieldTypes, $result) {

		if(count($records) == 0) {
			return;
		}

		$tmpTableName = '`tmp_' . uniqid() . '`';

		$updateFieldNames = array_keys($fieldTypes);
		$fieldSql = array();
		foreach($fieldTypes as $fieldName => $sqlType) {
			$fieldSql[] = '`' . $fieldName . '` ' . $sqlType;
		}
		$engine = 'ENGINE = MEMORY';

		// Maak een tijdelijke tabel aan met de zelfde definitie als de oorspronkelijke tabel (Zonder dat de keys worden overgenomen)
		$query = 'CREATE TEMPORARY TABLE ' . $tmpTableName . ' (' . implode(',', $fieldSql) . ') ' . $engine;

		$this->query($query, $result);

		// insert voorbereiden:
		$first = true;
		$insertValues = array();
		foreach($records as $record) {
			if($first) {
				$fieldOrder = array_keys($record);
			}
			$insertValues[] = '(' . implode(',', $record) . ')';
		}

		$insertValues = array_chunk($insertValues, 10000, true);
		foreach($insertValues as $insertSection) {
			$query = 'INSERT INTO ' . $tmpTableName . '(`' . implode('`,`', $fieldOrder) . '`) VALUES ' . implode(',', $insertSection);
			$this->query($query, $result);
		}
		return $tmpTableName;
	}


	public function closeConnection() {
		if($this->connected) {
			mysqli_close($this->db);
			$this->connected = false;
		}
	}

	public function isConnected() {
		return $this->connected && is_resource($this->db);
	}
}

?>
