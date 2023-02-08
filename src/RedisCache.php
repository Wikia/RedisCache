<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;

/**
 * Curse Inc.
 * Redis Cache
 *
 * @author        Alexia E. Smith
 * @copyright    (c) 2015 Curse Inc.
 * @license        GNU General Public License v3.0 only
 * @package        RedisCache
 * @link        https://gitlab.com/hydrawiki
 *
 **/
class RedisCache {
	/**
	 * Up and ready server connections.
	 */
	private array $servers = [];

	/**
	 * Last exception error.
	 */
	private string $lastError = '';

	public function __construct( private ?array $redisServers, private LoggerInterface $logger ) { }

	/**
	 * @param ?string $group [Optional] Server group key.
	 *                    Example: 'cache' would look up $wgRedisServers['cached']
	 *                    Default: Uses the first index of $wgRedisServers.
	 * @param ?array $options [Optional] Additional options, will merge and overwrite default options.
	 *                    - connectTimeout : The timeout for new connections, in seconds.
	 *                      Optional, default is 1 second.
	 *                    - persistent     : Set this to true to allow connections to persist across
	 *                      multiple web requests. False by default.
	 *                    - password       : The authentication password, will be sent to Redis in clear text.
	 *                      Optional, if it is unspecified, no AUTH command will be sent.
	 *                    - serializer     : Set to "php", "igbinary", or "none". Default is "php".
	 * @param    ?boolean $newConnection [Optional] Force a new connection, useful when forking processes.
	 * @return RedisConnRef|null RedisConnRef or null on failure.
	 * @throws MWException
	 */
	public function getConnection(
		?string $group = null,
		?array $options = [],
		?bool $newConnection = false
	): ?RedisConnRef {
		Assert::precondition(
			extension_loaded( 'redis' ),
			__METHOD__ .
			" - The PHP Redis extension is not available.  Please enable it on the server to use RedisCache."
		);

		if ( empty( $this->redisServers ) ) {
			$this->logger->error( "redisServers must be configured for RedisCache to function." );

			return null;
		}

		if ( empty( $group ) ) {
			$group = 0;
			$server = current( $this->redisServers );
		} else {
			if ( !isset( $this->redisServers[$group] ) ) {
				$this->logger->error( 'Missing Redis server group: ' . $group );

				return null;
			}
			$server = $this->redisServers[$group];
		}

		if ( $newConnection === false && array_key_exists( $group, $this->servers ) ) {
			return $this->servers[$group];
		}

		if ( empty( $server ) || !is_array( $server ) ) {
			throw new MWException( __METHOD__ . " - An invalid server group key was passed." );
		}

		$pool = RedisConnectionPool::singleton( array_merge( $server['options'], $options ?? [] ) );
		/** @var RedisConnRef|Redis|bool $redis */
		$redis = $pool->getConnection(
		// Concatenate these together for MediaWiki weirdness so it can split them later.
			$server['host'] . ":" . $server['port']
		);

		if ( !$redis ) {
			return null;
		}

		if ( $redis instanceof RedisConnRef ) {
			// Set up any extra options. RedisConnectionPool does not handle the prefix
			// automatically.
			if ( isset( $server['options']['prefix'] ) && !empty( $server['options']['prefix'] ) ) {
				$redis->setOption( Redis::OPT_PREFIX, $server['options']['prefix'] );
			}
			try {
				$pong = $redis->ping();
				// Prior to redis version 5 ping would return the string +PONG but will now return true
				if ( $pong === '+PONG' || $pong === true ) {
					$this->servers[$group] = $redis;
				} else {
					return null;
				}
			} catch ( RedisException $e ) {
				// People using HAProxy will find it will lie about a Redis cluster being healthy
				// when the master is down, but the slaves are up.  Doing a PING will cause an immediate disconnect.
				$this->lastError = $e->getMessage();

				return null;
			}
		}

		return $redis;
	}

	/**
	 * Acquire a Redis connection.
	 * @throws MWException
	 * @deprecated swap static class usage for injecting RedisCache and calling getConnection directly
	 */
	public static function getClient( ?string $group = null ): ?RedisConnRef {
		return MediaWikiServices::getInstance()->getService( self::class )->getConnection(
			$group
		);
	}

	/**
	 * Return the last exception error.
	 */
	public function getLastError(): string {
		return $this->lastError;
	}
}
