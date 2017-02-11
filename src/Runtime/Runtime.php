<?php
namespace CF;

/**
 * Abstracte laag die de runtime omgeving voor het Conscribo framework omschrijft.
 * Deze class zorgt ervoor dat het Conscribo zoals het hoort haar dependencies scheidt. Een implementatie dient de runtime te implementeren
 * De runtime bevat zaken zoals hoe om te gaan met foutafhandeling, database connecties, datum en tijd, en alle dependencies die het framework kan nodig hebben.
 * Het framework (maar het is te adviseren om dit in de implementatie zelf ook door te voeren bij hoofdzakelijk gebruik van het framework) is slechts afhankelijk van de interface van deze runtime.
 *
 * Uit gemak voor implementatie en limitatie van PHP's strongtyping en extensions is dit een class en geen interface.
 * User: Dre
 * Date: 1-1-2015
 * Time: 15:26
 */

abstract class Runtime {

	/**
	 * vul de gegevens van de runtime
	 */

	abstract public function stopExecution();

	/**
	 * getInstance zorgt ervoor dat het framework een singleton implementatie kan gebruiken om bij het framework te komen. Hierbij wordt uit optimalisatie de instance ref gecached.
	 * @see shorthand procedure: gR(); (getRuntime) in tools welke een implementatie een snellere schrijfwijze geeft voor \CF\Runtime::gI();
	 * @return static
	 */
	static public function gI() {
		static $currentRuntime = NULL;
		if($currentRuntime === NULL) {
			$className = CONSCRIBO_RUNTIME_CLASSNAME;
			$currentRuntime = $className::_gI();
		}
		return $currentRuntime;
	}



	/**
	 * @return ConscriboFrameworkDatabase
	 */
	public function db() {
		return $this->db;
	}

	public function __construct() {
		$this->init();
	}

	/**
	 * @var \CF\ErrorCollection
	 */
	protected $systemErrors;

	/**
	 * @var \CF\ErrorCollection
	 */
	protected $systemWarnings;



	/**
	 * @var \CF\ErrorCollection
	 */
	protected $systemNotices;

	/**
	 * @var \CF\ErrorCollection
	 */
	public $userErrors;

	/**
	 * @var ConscriboFrameworkDatabase
	 */
	protected $db;

	/**
	 * Create an errorcollection compatible with this environment
	 * @return \CF\ErrorCollection
	 */
	abstract public function createErrorCollection();


	/**
	 * Create an Errorcollection for system errors (notices, warnings ....)
	 * @return ErrorCollection
	 */
	public function createSystemErrorCollection() {
		return $this->createErrorCollection();
	}

	/**
	 * Create an Errorcollection for user errors (validation errors, preconditions that the user needs to know about ..)
	 * @return ErrorCollection
	 */
	public function createUserErrorCollection() {
		return $this->createErrorCollection();
	}

	/**
	 * You should override the init function so you can implement custom handling per errorlevel.
	 */
	protected function init() {
		$this->systemErrors = $this->createSystemErrorCollection();
		$this->systemErrors->setAutoLogEnabled(true);
		$this->systemErrors->setAutoDisplayEnabled(true);

		$this->systemWarnings = $this->createSystemErrorCollection();
		$this->systemWarnings->setAutoLogEnabled(true);
		$this->systemWarnings->setAutoDisplayEnabled(true);

		$this->systemNotices = $this->createSystemErrorCollection();
		$this->systemNotices->setAutoLogEnabled(true);
		$this->systemNotices->setAutoDisplayEnabled(true);

		$this->userErrors = $this->createUserErrorCollection();
	}

	/**
	 * Fatal error
	 * @param $msg
	 */
	public function addError($msg) {
		$this->systemErrors->add($msg);
		$this->stopExecution();
	}

	/**
	 * Non Fatal error
	 * @param $msg
	 */
	public function addWarning($msg) {
		$this->systemWarnings->add($msg);
	}

	/**
	 * Notice
	 * @param $msg
	 */
	public function addNotice($msg) {
		$this->systemNotices->add($msg);
	}

	public function addSystemNotices(\CF\ErrorCollection $errors) {
		$this->systemNotices->mergeErrors($errors);
	}

	/**
	 * Notice
	 * @param $msg
	 */
	public function addUserError($msg) {
		$this->userErrors->add($msg);
	}

	public function addUserErrors(\CF\ErrorCollection $errors) {
		$this->userErrors->mergeErrors($errors);
	}

	public function addInfoMessage($msg) {
		$this->userErrors->setInfoMessage($msg);
	}

	public function formatUserErrors() {
		return $this->userErrors->outputFormatted();
	}

	/**
	 * @param string $message
	 * @param string $type Geeft het type logMessage terug
	 */
	abstract public function logAction($message, $type = 'action');


	/**
	 * Geeft de Server Application Interface terug
	 * @return string
	 */
	public function getSapi() {
		return PHP_SAPI;
	}

	/**
	 * Returns the hostname of the server for the current request (if available), otherwise the servername or 'localhost'
	 */
	public function getHostName() {
		if(!empty($_SERVER['HTTP_HOST'])) {
			return $_SERVER['HTTP_HOST'];
		}
		if(!empty($_SERVER['SERVER_NAME'])) {
			return $_SERVER['SERVER_NAME'];
		}
		return 'localhost';
	}

	/**
	 * Temporarily disable notices, to for example test 'unserializable'
	 * @param $toggle
	 */
	public function toggleIgnoreNotices($toggle) {
		// Default implementation does not support this yet , but can be called.
	}

	public function is32bitSystem() {
		return PHP_INT_SIZE < 8;
	}

	/**
	 * Makes it possible to uniquely identify multiple scopes within one session (For example two authenticated users in different situations)
	 * @return string
	 */
	public function getSessionScopeId() {
		return 's';
	}
}