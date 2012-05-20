<?php

/**
 * This file belongs to Yui-chan!
 * 
 * Copyright (c) 2012 Vaclav Vrbka (aurielle@aurielle.cz)
 * 
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Yui\Console;

use Yui, Nette, Symfony,
		Symfony\Component\Console;



/**
 * Console application
 * @author Vaclav Vrbka
 */
class Application extends Nette\Application\Application
{
	/** @var Symfony\Component\Console\Input\ArgvInput */
	private $consoleInput;

	/** @var Symfony\Component\Console\Output\ConsoleOutput */
	private $consoleOutput;

	/** @var array */
	protected $commands = array();



	/**
	 * @return integer
	 */
	public function run()
	{
		$this->consoleInput = new Console\Input\ArgvInput();
		$this->consoleOutput = new Console\Output\ConsoleOutput();

		// package errors should not be handled by console life-cycle
		$cli = $this->createApplication();

		$exitCode = 1;
		try {
			// run the console
			$exitCode = $cli->run($this->consoleInput, $this->consoleOutput);

		} catch (\Exception $e) {
			// fault barrier
			$this->onError($this, $e);
			$this->onShutdown($this, $e);

			// log
			Nette\Diagnostics\Debugger::log($e, 'console');
			Yui\Diagnostics\ConsoleDebugger::_exceptionHandler($e);

			// render exception
			$cli->renderException($e, $this->consoleOutput);
			return $exitCode;
		}

		$this->onShutdown($this, isset($e) ? $e : NULL);
		return $exitCode;
	}



	/**
	 * @return Symfony\Component\Console\Application
	 */
	protected function createApplication()
	{
		// create
		$cli = new Console\Application(
			Yui\YuiChan::NAME . ": The Imageboard downloader ^^",
			Yui\YuiChan::VERSION
		);

		// override error handling
		$cli->setCatchExceptions(FALSE);
		$cli->setAutoExit(FALSE);

		foreach ($this->commands as $cmd) {
			$cli->add($cmd);
		}

		return $cli;
	}



	/**
	 * Adds command.
	 * @param Console\Command\Command
	 * @return Yui\Application\Console
	 */
	public function add($cmd)
	{
		if (is_string($cmd) && class_exists($cmd)) {
			$cmd = new $cmd;
		}

		if (!$cmd instanceof Console\Command\Command) {
			throw new Nette\InvalidArgumentException('Command must be either a class name or instance of Symfony\Component\Console\Command\Command.');
		}

		$this->commands[] = $cmd;
		return $this;
	}
}