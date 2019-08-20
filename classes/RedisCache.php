<?php
/**
 * Curse Inc.
 * Redis Cache
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2015 Curse Inc.
 * @license		GNU General Public License v3.0 only
 * @package		RedisCache
 * @link		https://gitlab.com/hydrawiki
 *
**/

class RedisCache {
	/**
	 * Up and ready server connections.
	 *
	 * @var		array
	 */
	static protected $servers = [];

	/**
	 * Last exception error.
	 *
	 * @var		string
	 */
	static protected $lastError = '';

	/**
	 * Acquire a Redis connection.
	 *
	 * @access	protected
	 * @param	string	[Optional] Server group key.
	 * 					Example: 'cache' would look up $wgRedisServers['cached']
	 *					Default: Uses the first index of $wgRedisServers.
	 * @param	array	[Optional] Additional options, will merge and overwrite default options.
	 *					- connectTimeout : The timeout for new connections, in seconds.
	 *                      Optional, default is 1 second.
	 *					- persistent     : Set this to true to allow connections to persist across
	 *                      multiple web requests. False by default.
	 *					- password       : The authentication password, will be sent to Redis in clear text.
	 *                      Optional, if it is unspecified, no AUTH command will be sent.
	 *					- serializer     : Set to "php", "igbinary", or "none". Default is "php".
	 * @param	boolean	[Optional] Force a new connection, useful when forking processes.
	 * @return	mixed	Object RedisConnRef or false on failure.
	 */
	static public function getClient($group = null, $options = [], $newConnection = false) {
		global $wgRedisServers;

		if (!extension_loaded('redis')) {
			throw new MWException(__METHOD__." - The PHP Redis extension is not available.  Please enable it on the server to use RedisCache.");
		}

		if (empty($wgRedisServers) || !is_array($wgRedisServers)) {
			MWDebug::log(__METHOD__." - \$wgRedisServers must be configured for RedisCache to function.");
			return false;
		}

		if (empty($group)) {
			$group = 0;
			$server = current($wgRedisServers);
		} else {
			if (!isset($wgRedisServers[$group])) {
				wfDebug(__METHOD__.' - Missing Redis server group: '.$group);
				return false;
			}
			$server = $wgRedisServers[$group];
		}

		if ($newConnection === false && array_key_exists($group, self::$servers)) {
			return self::$servers[$group];
		}

		if (empty($server) || !is_array($server)) {
			throw new MWException(__METHOD__." - An invalid server group key was passed.");
		}

		$pool = \RedisConnectionPool::singleton(array_merge($server['options'], $options));
		$redis = $pool->getConnection($server['host'].":".$server['port']); //Concatenate these together for MediaWiki weirdness so it can split them later.

		if ($redis instanceof RedisConnRef) {
			//Set up any extra options.  RedisConnectionPool does not handle the prefix automatically.
			if (isset($server['options']['prefix']) && !empty($server['options']['prefix'])) {
				$redis->setOption(Redis::OPT_PREFIX, $server['options']['prefix']);
			}
			try {
				$pong = $redis->ping();
				// Prior to redis version 5 ping would return the string +PONG but will now return true
				if ($pong === '+PONG' || $pong === true) {
					self::$servers[$group] = $redis;
				} else {
					$redis = false;
				}
			} catch (RedisException $e) {
				//People using HAProxy will find it will lie about a Redis cluster being healthy when the master is down, but the slaves are up.  Doing a PING will cause an immediate disconnect.
				self::$lastError = $e->getMessage();
				$redis = false;
			}
		}

		return $redis;
	}

	/**
	 * Return the last exception error.
	 *
	 * @access	public
	 * @return	string
	 */
	static public function getLastError() {
		return self::$lastError;
	}
}
