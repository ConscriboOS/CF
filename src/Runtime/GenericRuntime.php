<?php
/**
 * User: Dre
 * Date: 1-2-2017
 * Time: 14:44
 */

namespace CF\Tool;

use CF\Runtime;

class GenericRuntime extends Runtime {

	static public function _gI() {
		static $runTime = NULL;

		if ($runTime === NULL) {
			$runTime = new GenericRuntime();
		}
		return $runTime;
	}

	protected function init() {
		parent::init();
		$this->db = new Database();
		$this->db->connect();
	}

	/**
	 * vul de gegevens van de runtime
	 */
	public function stopExecution() {
		exit;
	}

	/**
	 * Create an errorcollection compatible with this environment
	 * @return \CF\ErrorCollection
	 */
	public function createErrorCollection() {
		return new GenericErrorCollection($this);
	}

	/**
	 * @param string $message
	 * @param string $type Geeft het type logMessage terug
	 */
	public function logAction($message, $type = 'action') {
		echo $message;
	}
}