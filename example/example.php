<?php


include './vendor/autoload.php';

include 'Player.php';


/**
 * Basic example. This will create an object of type Player (see Player.php) store and load it from the database)
 */

// Create tables for this to work:

// CREATE TABLE `player` (id int unsigned,
//  name varchar(255),
// other_name varchar(255));

// Autoids is used so there is no dependency on auto-increment columns in sql. and we can make various optimizations.

/*CREATE TABLE `auto_ids` (
`id_type` char(15) NOT NULL DEFAULT '',
  `next_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_type`,`next_id`)
) engine=MYISAM DEFAULT CHARSET=utf8
*/


// Configure The database (only mysqli is currently supported)

\CF\Configuration::gI()->setDatabaseConfiguration('localhost','','','test');

// create a "player":

$player = Player::create();
$player->setName('Hi player');
$id = $player->getId();
$player->store();

unset($player);

//load the player
$player2 = Player::load($id);
echo $player2->getName();

