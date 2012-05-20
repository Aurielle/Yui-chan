<?php

/**
 * This file belongs to Yui-chan!
 * 
 * Copyright (c) 2012 Vaclav Vrbka (aurielle@aurielle.cz)
 * 
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Yui;

use Nette;



final class YuiChan
{
	/** Yui-chan version identification */
	const NAME = 'Yui-chan',
		VERSION = '1.0.0';



	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new Nette\StaticClassException;
	}
}