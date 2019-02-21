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

if (function_exists('wfLoadExtension')) {
	wfLoadExtension('RedisCache');
	wfWarn(
		'Deprecated PHP entry point used for RedisCache extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die('This version of the RedisCache extension requires MediaWiki 1.29+');
}
