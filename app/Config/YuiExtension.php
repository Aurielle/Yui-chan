<?php

/**
 * This file belongs to Yui-chan!
 * 
 * Copyright (c) 2012 Vaclav Vrbka (aurielle@aurielle.cz)
 * 
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Yui\Config;

use Yui, Nette;



/**
 * YuiExtension
 */
class YuiExtension extends Nette\Config\CompilerExtension
{
	/** @var array */
	public $defaults = array(
		'commands' => array(
			'Yui\Commands\Konachan',
			'Yui\Commands\Yandere',
		),
	);


	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$app = $container->getDefinition('application')
			->setClass('Yui\Console\Application');

		foreach ($config['commands'] as $cmd) {
			$app->addSetup('add', $cmd);
		}
	}
}