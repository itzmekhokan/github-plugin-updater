<?php
/**
 * PHPUnit bootstrap: load Composer autoload and Brain Monkey.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

// Some source constants are WP-defined; stub the ones used at include time.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
