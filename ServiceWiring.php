<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	RedisCache::class => static function ( MediaWikiServices $services ) {
		return new RedisCache(
			$services->getMainConfig()->get( 'RedisServers' ),
			LoggerFactory::getInstance( RedisCache::class )
		);
	},
];
