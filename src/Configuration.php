<?php
namespace CF;

//Configuration object

class Configuration {
	
	private $runtimeName;

	/**
	 * @var array with per name a configuration;
	 */
	private $databaseConfigurations;
	
	static function gI() {
		static $configuration = NULL;
		if($configuration === NULL) {
			$configuration = new Configuration();
		} 
		return $configuration;
	}
	
	private function __construct() {
	}

	/**
	 * @return string
	 */
	public function getRuntimeName() {
		if($this->runtimeName === NULL) {
			return '\\CF\\Tool\\GenericRuntime';
		}
		return $this->runtimeName;
	}

	/**
	 * Set the full CF runtime classname (including namespace)
	 * @param string $runtimeName
	 * @return Configuration
	 */
	public function setRuntimeName($runtimeName) {
		$this->runtimeName = $runtimeName;
		return $this;
	}

	public function setDatabaseConfiguration($hostName, $userName, $password, $dbName = NULL, $port = 3306, $name = 'default') {
		$this->databaseConfigurations[$name] = array('hostName' => $hostName,
													'userName' => $userName,
													'password' => $password,
													'dbName' => $dbName,
													'port' => $port);
	}

	public function getDatabaseConfiguration($name) {
		// TODO: this should be protected (maybe this function can callback the db instance so the pass is not requestable)
		return $this->databaseConfigurations[$name];
	}
}