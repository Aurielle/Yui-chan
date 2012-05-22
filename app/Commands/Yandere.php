<?php

/**
 * This file belongs to Yui-chan!
 * 
 * Copyright (c) 2012 Vaclav Vrbka (aurielle@aurielle.cz)
 * 
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Yui\Commands;

use Yui, Nette, Symfony, Kdyby;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;



class Yandere extends Konachan
{
	/** @var string */
	const BOARD_NAME = 'Yande.re';

	/** @var string */
	const BOARD_POST_URL = 'http://yande.re/post';



	/**
	 * Configure the CLI command.
	 * @return void
	 */
	protected function configure()
	{
		$this->setName('yandere');
		Imageboard::configure();
	}



	/**
	 * Factory for new cURL requests.
	 * @param string $url
	 * @return Kdyby\Extension\Curl\Request
	 */
	protected function newCurlRequest($url)
	{
		$request = new Kdyby\Extension\Curl\Request($url);
		$request->setCertificationVerify(FALSE);
		
		return $request;
	}



	/**
	 * Returns local filename for given image.
	 * @param string $url
	 * @return string
	 */
	protected function getImageLocalName($url)
	{
		$tmp = explode('/', substr($url, 8)); // https://
		$filename = $tmp[2] . '.' . substr($url, -3, 3);

		return $filename;
	}
}