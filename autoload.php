<?php

/*
|--------------------------------------------------------------------------
| Telescope PSR-4 Autoloader
|--------------------------------------------------------------------------
|
| The package is registered under "HungLv\Telescope\\" in composer.json, but this
| lets it work before anyone runs `composer dump-autoload` (which needs the
| PHP 7.0 container, not the host). Once composer knows about the package
| this loader simply never fires, because the class is already loaded.
|
*/

spl_autoload_register(function($class)
{
	$prefix = 'HungLv\Telescope\\';

	if (strpos($class, $prefix) !== 0) return;

	$relative = substr($class, strlen($prefix));

	$path = __DIR__.'/src/'.str_replace('\\', '/', $relative).'.php';

	if (file_exists($path)) require_once $path;
});
