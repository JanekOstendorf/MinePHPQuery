<?php
/**
 * @author    Janek "ozzyfant" Ostendorf <ozzy@ozzyfant.de>
 * @copyright Copyright (c) 2014 Janek Ostendorf
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU General Public License, version 3
 */

/*
 * Nagios plugin to check Minecraft server.
 * Needs the library in a subdirectory to work.
 */

// ## DEFAULT CONFIGURATION ##

$config['host'] = '';
$config['port'] = 25565;
$config['warn'] = 100;       // Warn at 100%

// ## END CONFIGURATION ##

// Global
define('EOL', PHP_EOL);
require_once dirname(__FILE__).'/src/MinePHPQuery.class.php';

// Read arguments
$optString = 'H:p:w:h';

$longOpt = [
	'host:', // Hostname
	'port:', // Port number
	'warn:', // Warn threshold
	'help'   // Help
];

$options = getopt($optString, $longOpt);

if(isset($options['h']) || isset($options['help'])) {
	printHelp();
	return 3;
} else if((isset($options['H']) || isset($options['host']))) {
	$config['host'] = isset($options['host']) ? $options['host'] : $options['H'];

	if(isset($options['p']) || isset($options['port']))
		$config['port'] = isset($options['port']) ? $options['port'] : $options['p'];

	if(isset($options['w']) || isset($options['warn']))
		$config['warn'] = isset($options['warn']) ? $options['warn'] : $options['w'];

	return checkServer($config);

} else {
	printSummary();
	return 3;
}

function checkServer($config) {

	// Check array
	if(!is_array($config) ||
		!isset($config['host']) ||
		!isset($config['port']) ||
		!isset($config['warn'])) {
		print 'Invalid arguments.'.EOL;
		printSummary();
		return 3;
	}

	$query = new MinePHPQuery($config['host'], $config['port']);

	// Server down
	if(!$query->isOnline()) {
		print 'CRITICAL: Server '.$config['host'].':'.$config['port'].' unreachable.'.EOL;
		return 2;
	}

	// Check players
	if(($query->getPlayerCount() / $query->getMaxPlayers()) * 100 >= $config['warn']) {
		print 'WARNING: Player number threshold '.$config['warn'].'% reached: '.$query->getPlayerCount().'/'.$query->getMaxPlayers().EOL;
		return 1;
	}

	// Everything ok
	print 'MINECRAFT OK: '.$query->getPlayerCount().'/'.$query->getMaxPlayers().' players. '.$query->getSoftware().' - Version '.$query->getVersion().EOL;
	return 0;

}

function printHelp() {
	print 'Checks a Minecraft server.'.EOL.EOL;
	printSummary();
	print EOL;

	print '-h, --help'.EOL;
	print '  Prints this help.'.EOL;

	print '-H, --host'.EOL;
	print '  Defines the host to check.'.EOL;

	print '-p, --port'.EOL;
	print '  Defines the port to check (Default: 25565).'.EOL;

	print '-w, --warn'.EOL;
	print '  Warning, when percentage of slots used (Default: 100, full server).'.EOL;

}

function printSummary() {
	print 'Usage: php check_minecraft.php [-h] -H <hostname> [-p <port>]'.EOL;
}