<?php
/**
CREATE TABLE `auto_ids` (
`id_type` char(15) NOT NULL DEFAULT '',
  `next_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_type`,`next_id`)
) engine=MYISAM DEFAULT CHARSET=utf8
*/
/**
 * Reserveer een id in de database
 * @param string $idType
 * @return int
 */
function autoIdReserveId($idType) {
	$res = autoIdReserveIds($idType, 1);
	return reset($res);
}

/**
 * Reserves $amount unique ids in the database for entity
 * @param string $idType
 * @param int $amount (non negative)
 * @return array with reserved ids
 */
function autoIdReserveIds($idType, $amount = 1) {
	if($amount == 0) {
		return array();
	}
	if($amount < 0) {
		throw DeveloperException('Negative amount of autoIds requested');
	}

	$indexesReserved = false;
	while($indexesReserved === false) {
		db()->query('SELECT next_id FROM auto_ids WHERE id_type = '. dbStr($idType), 'indexes');
		if(db()->numRows('indexes') > 0 ){
			list($nextFree) = db()->fetchRow('indexes');

			$newNextFree = $nextFree + $amount;
			db()->query('UPDATE auto_ids SET next_id = '. dbInt($newNextFree) .' WHERE next_id = '. dbInt($nextFree) .' AND id_type = '. dbStr($idType), 'indexes');
			if(db()->affectedRows() > 0) {
				$indexesReserved = true;
			}
		} else {
			$nextFree = 1;
			$newNextFree = $nextFree + $amount;
			db()->query('INSERT INTO auto_ids (id_type, next_id)  VALUES ('. dbStr($idType) .', '. $newNextFree .') ON DUPLICATE KEY UPDATE next_id = next_id');
			if(db()->affectedRows() > 0) {
				$indexesReserved = true;
			}
		}
	}
	return range($nextFree, $newNextFree -1, 1);
}

/**
 * Functie om zuiniger om te gaan met ids. Op het moment dat een id niet meer gebruikt gaat worden, wordt de idset verlaagd in de db (alleen als dit mogelijk is)
 * @param type $idType
 * @param type $id
 */
function autoIdFreeId($idType, $id) {
	$id = round($id);
	if(db()->isConnected()) {
		// als we hierna nog geen id's hebben uitgegeven, verlagen we het id weer.
		db()->query('UPDATE auto_ids SET next_id = '. dbInt($id) .' WHERE next_id = '. dbInt($id + 1) .' AND id_type = '. dbStr($idType), 'indexes');
	}
}
