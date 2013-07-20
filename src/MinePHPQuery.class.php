<?php
/**
 * @author    Janek Ostendorf (ozzy) <ozzy2345de@gmail.com>
 * @copyright Copyright (c) 2013 Janek Ostendorf
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU General Public License, version 3
 *
 * Inspired by PHP-Minecraft-Query by xPaw
 */

require_once dirname(__FILE__).'/MinecraftQueryException.class.php';

class MinePHPQuery {

	const TYPE_CHALLENGE = 0x09;
	const TYPE_STATISTICS = 0x00;

	/**
	 * @var int
	 */
	protected $playerCount = 0;

	/**
	 * @var array
	 */
	protected $players = [];

	/**
	 * @var string
	 */
	protected $software = '';

	/**
	 * @var array
	 */
	protected $plugins = [];

	/**
	 * @var int
	 */
	protected $port = 0;

	/**
	 * @var string
	 */
	protected $softwareRaw = '';

	/**
	 * @var int
	 */
	protected $maxPlayers = 0;

	/**
	 * @var bool
	 */
	protected $online = false;

	/**
	 * @var resource
	 */
	protected $socket = null;

	/**
	 * @var float
	 */
	protected $duration = 0;

	/**
	 * @param string $ip      IP of the MC server
	 * @param int    $port    Port of the MC server
	 * @param int    $timeout Timeout
	 * @throws InvalidArgumentException
	 */
	public function __construct($ip, $port = 25565, $timeout = 5) {
		$time = microtime(true);
		if(!is_int($timeout) ||$timeout < 0) {
			throw new InvalidArgumentException('Timeout must be an integer.');
		}

		// Connect to the server
		$this->socket = @fsockopen('udp://'.$ip, (int)$port, $errorNumber, $errorString, $timeout);

		// Failure?
		if($errorNumber || $this->socket === false) {
			$this->online = false;
			return;
		}
		stream_set_blocking($this->socket, true);
		stream_set_timeout($this->socket, (int)$timeout);

		try {
			$challenge = $this->fetchChallenge();
			$this->fetchStatus($challenge);
		} catch(MinecraftQueryException $e) {
			fclose($this->socket);
			$this->online = false;
			return;
		}

		fclose($this->socket);
		$this->duration = microtime(true) - $time;
	}

	protected function fetchStatus($challenge) {
		$data = $this->writeData(self::TYPE_STATISTICS, $challenge.pack('c*', 0x01, 0x03, 0x03, 0x07));

		if(!$data) {
			throw new MinecraftQueryException('Failed to receive status.');
		}

		$data = substr($data, 11); // splitnum + 2 integers

		// Move players to seperate array
		$data = explode("\x00\x00\x01player_\x00\x00", $data);
		$players = substr($data[1], 0, -2);

		$serverData = explode("\x00", $data[0]);

		// translate the server's response to our properties
		$keys = [
			'hostname' => 'server_name',
			'gametype' => 'game_type',
			'version' => 'version',
			'plugins' => 'mod_plugins',
			'map' => 'map',
			'numplayers' => 'player_count',
			'maxplayers' => 'max_players',
			'hostport' => 'port',
			'hostip' => 'ip',
		];

		$last = '';
		$info = [];

		foreach($serverData as $key => $value) {
			/*
			 * Go through the array and remember the last value, as it is the
			 * key for the value that comes next
			 */
			if($key % 2 == 0) {
				if(!array_key_exists($value, $keys)) {
					$last = false;
					continue;
				}

				$last = $keys[$value];
				$info[$last] = '';
			}
			elseif($last != false) {
				$info[$last] = $value;
			}
		}

		/* Parse the values */
		// Integers
		$this->playerCount = intval($info['player_count']);
		$this->maxPlayers  = intval($info['max_players']);
		$this->port        = intval($info['port']);

		// Parse plugins and mod/software
		if($info['mod_plugins']) {
			$data = explode(': ', $info['mod_plugins'], 2);
			$this->softwareRaw = $info['mod_plugins'];
			$this->software = $data[0];

			// Are there plugins?
			if(count($data) == 2) {
				$this->plugins = explode('; ', $data[1]);
			}
		}
		else
			$this->software = 'Vanilla';
		
		if($players) {
			$this->players = explode("\x00", $players);
		}

		$this->online = true;
	}

	/**
	 * @return string Challenge for this query
	 * @throws MinecraftQueryException
	 */
	protected function fetchChallenge() {
		$data = $this->writeData(self::TYPE_CHALLENGE);
		if($data === false) {
			throw new MinecraftQueryException('Failed to receive challenge');
		}

		return pack('N', $data);
	}

	/**
	 * Write to the server and fetch the wanted data
	 * @param string $type Type byte, TYPE_CHALLANGE or TYPE_STATISTICS
	 * @param string $append
	 * @throws MinecraftQueryException
	 * @return bool|string False on failure, data else
	 */
	protected function writeData($type, $append = '') {
		// Prepare data
		$send = pack('c*', 0xFE, 0xFD, $type, 0x01, 0x03, 0x03, 0x07).$append;
		$length = strlen($send);

		// Try to write
		if($length !== fwrite($this->socket, $send, $length)) {
			throw new MinecraftQueryException('Failed to write to socket.');
		}

		// Read from socket
		$data = fread($this->socket, 2048);
		if($data === false) {
			throw new MinecraftQueryException('Failed to read from socket.');
		}

		// There will always be 5 bytes, when we encounter no error
		// Type byte will be in the response
		if(strlen($data) < 5 || $data[0] != $send[2]) {
			return false;
		}

		// Everything's OK, return the data
		return substr($data, 5);
	}

	/**
	 * @return int
	 */
	public function getMaxPlayers() {
		return $this->maxPlayers;
	}

	/**
	 * @return int
	 */
	public function getPlayerCount() {
		return $this->playerCount;
	}

	/**
	 * @return array
	 */
	public function getPlayers() {
		return $this->players;
	}

	/**
	 * @return array
	 */
	public function getPlugins() {
		return $this->plugins;
	}

	/**
	 * @return int
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @return resource
	 */
	public function getSocket() {
		return $this->socket;
	}

	/**
	 * @return string
	 */
	public function getSoftware() {
		return $this->software;
	}

	/**
	 * @return string
	 */
	public function getSoftwareRaw() {
		return $this->softwareRaw;
	}

	/**
	 * @return bool
	 */
	public function isOnline() {
		return $this->online === true;
	}

	/**
	 * @return float
	 */
	public function getDuration() {
		return $this->duration;
	}
}
