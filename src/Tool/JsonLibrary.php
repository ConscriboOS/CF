<?php
namespace CF\Tool;

use CF\Runtime;

class JsonLibrary {

	static function decodeObject() {

		$numArgs = func_num_args();
		$str = func_get_arg(0);
		$constructorArgs = array();

		for($i = 1; $i < $numArgs; $i++) {
			$constructorArgs[] = func_get_arg($i);
		}

		$decoded = json_decode($str, true);
		if(!is_array($decoded)) {
			return NULL;
		}
		if(!isset($decoded['className']) || !isset($decoded['contents'])) {
			return NULL;
		}
		$className = $decoded['className'];
		$contents = $decoded['contents'];
		if(class_exists($className)) {
			return $className::createFromJson($constructorArgs, $contents);
		} else {
			Runtime\Runtime::gI()->addError('Class '. $className .' not found '. var_export($decoded, true));
		}
	}

	/**
	 * Decode a JSON object and cast an interface/class to a specialization
	 * e.g arg[0] = array('Conscribo\\General\\Error\\ErrorCollection' => 'WordpressUserErrorCollection')
	 * arg[1] = <jsonString>
	 * @param array $casting
	 * @param String $str
	 * @return null
	 */
	static function decodeAndCastObject() {
		$numArgs = func_num_args();
		$casting = func_get_arg(0);
		$str = func_get_arg(1);
		$constructorArgs = array();

		for($i = 2; $i < $numArgs; $i++) {
			$constructorArgs[] = func_get_arg($i);
		}

		$decoded = json_decode($str, true);
		if(!is_array($decoded)) {
			return NULL;
		}
		if(!isset($decoded['className']) || !isset($decoded['contents'])) {
			return NULL;
		}
		$className = $decoded['className'];
		if(isset($casting[$className])) {
			$className = $casting[$className];
		}
		$contents = $decoded['contents'];
		if(class_exists($className)) {
			return $className::createFromJson($constructorArgs, $contents);
		} else {
			Runtime\Runtime::gI()->addError('Class '. $className .' not found '. var_export($decoded, true));
		}
	}

	static  function encodeObject($obj) {
		$className = get_class($obj);
		if(method_exists($obj, 'onJsonSleep')) {
			$obj->onJsonSleep();
		}

		if(method_exists($obj, 'getJsonData')) {
			$contents = json_encode(array('className' => $className,
										  'contents' => $obj->getJsonData()));
		} else {
			$contents = json_encode(array('className' => $className,
										  'contents' => $obj));
		}
		return $contents;
	}
}