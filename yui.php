<?php

/**
 * This file belongs to Yui-chan!
 * 
 * Copyright (c) 2012 Vaclav Vrbka (aurielle@aurielle.cz)
 * 
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/nette/nette/Nette/loader.php';
require_once __DIR__ . '/vendor/Aurielle/phpquery/phpQuery/phpQuery.php';

// Configuration
$configurator = new Nette\Config\Configurator();
define('YUI_DIR', __DIR__);

// Error visualization & logging
$configurator->setDebugMode(TRUE);
$configurator->enableDebugger(__DIR__ . '/log');

// Autoloader and cache
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->createRobotLoader()
	->addDirectory(__DIR__ . '/app')
	->register();

// Config.neon
$configurator->addConfig(__DIR__ . '/app/config.neon');
$configurator->onCompile[] = function($configurator, $compiler) {
	$compiler->addExtension('yui', new Yui\Config\YuiExtension);
};
$container = $configurator->createContainer();

// Run it
$container->application->run();