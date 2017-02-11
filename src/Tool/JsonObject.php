<?php
namespace CF;

/**
 * Deze nieuwe trait is ontworpen voor gebruik met 'dataobjecten' Deze objecten worden een vervanging van de vele structs die nu Conscribo verlossen van los argumetnen heen en weer fietsen.
 * een Dataobject kan beter controle houden op de interne datastruct.
 *
 * Het nadeel van een object is dat de Json representatie niet meer dan een array bevat. en dat deze niet weer terug decode naar dat dataobject. Met deze trait kan een object worden voorzien van een json representatie die dat wel kan.
 */

trait JsonObject {

	private $jsonIgnorePropertyList;

	static function createFromJson($constructorArgs, $jsonData) {
        $objReflection = new \ReflectionClass(get_called_class());
        $obj = $objReflection->newInstanceArgs($constructorArgs);
        $obj->setJsonData($jsonData);
        return $obj;
    }

    protected function setJsonData($jsonData) {
        foreach($jsonData as $key => $value) {
            $this->$key = $value;
        }

		if(method_exists($this, 'onJsonWakeup')) {
			$this->onJsonWakeup($jsonData);
		}
    }


	public function jsonIgnoreProperty($propertyName) {
		$this->jsonIgnorePropertyList[$propertyName] = $propertyName;
	}
/*
	public function getJsonData() {
		$jsonObject = clone $this;
		$jsonObject->_jsonUnsetIgnoreProperties();
		return $jsonObject;
	}
*/
	private function _jsonUnsetIgnoreProperties() {
		foreach($this->jsonIgnorePropertyList as $propName) {
			unset($this->$propName);
		}
		unset($this->jsonIgnorePropertyList);
	}


}


?>
