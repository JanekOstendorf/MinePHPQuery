<?php
/**
 * @author    Janek Ostendorf (ozzy) <ozzy2345de@gmail.com>
 * @copyright Copyright (c) 2013 Janek Ostendorf
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */

// Loading should be done by composer
require_once __DIR__.'/vendor/autoload.php';

// If not using composer, use this instead:
// require_once __DIR__.'/src/MinePHPQuery.class.php';

$query = new MinePHPQuery('localhost', 25565);

if($query->isOnline()) {

	echo 'Server is online. '.$query->getPlayerCount().' players are playing currently';

} else {

	echo 'Server is offline :sadface: :(';

}
