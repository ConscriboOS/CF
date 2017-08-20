<?php


include './vendor/autoload.php';

include 'Player.php';

\CF\Configuration::gI()->setDatabaseConfiguration('localhost','','','test');

$player = Player::create();


$player->setName('bla');

$player->store();

echo $player->getName();
