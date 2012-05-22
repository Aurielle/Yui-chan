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



class Konachan extends Imageboard
{
	/** @var string */
	const BOARD_NAME = 'Konachan.com';

	/** @var string */
	const BOARD_POST_URL = 'http://konachan.com/post';



	/**
	 * Configure the CLI command.
	 * @return void
	 */
	protected function configure()
	{
		$this->setName('konachan');
		parent::configure();
	}



	/**
	 * Downloads specified images from Konachan to local folder.
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param array[string] $tags
	 * @param array[int] $pages
	 * @param bool $all
	 */
	protected function download(InputInterface $input, OutputInterface $output, array $tags, array $pages = NULL, $all = TRUE)
	{
		// Setup
		$pageInfo = $this->getPageInfo($pages, $all);
		$tag = $this->getFirstTag($tags);
		$dir = $this->checkDir($tag, $input->getOption('dir'));
		$timeout = (int) $input->getOption('timeout');

		$output->writeln('<info>Okay! Yui-chan will now download your pictures!</info>');
		$output->writeln('<info>Selected tags:</info> ' . implode(' ', $tags) . ', pages: ' . $pageInfo);
	
		// Build URL
		$params = array(
			'tags' => implode(' ', $tags),
			'page' => $all ? 1 : reset($pages),
		);

		// Get the cURL
		$output->writeln('Fetching tag information...');
		$html = $this->fetchPage($params);
		$dom = \phpQuery::newDocument($html);

		// Number of images and pages
		$itemCount = $this->getItemCount($dom, $tag);
		$pageCount = $this->getPageCount($dom);

		// Set page information
		if ($all) {
			$currentPage = $firstPage = 1;
			$lastPage = $pageCount;

		} else {
			$currentPage = $firstPage = reset($pages);
			$lastPage = end($pages);
			$lastPage = $lastPage > $pageCount ? $pageCount : $lastPage;
		}

		$output->writeln("----- Found <info>~$itemCount pictures</info> on <info>$pageCount pages</info>. -----" . PHP_EOL);

		// Download first page
		$this->downloadImages($output, $dom, $dir, $currentPage, $timeout);

		// Only one page?
		if ($firstPage === $lastPage) {
			return;
		}
		
		// Other pages
		for ($i = $firstPage + 1; $i <= $lastPage; $i++) {
		 	
		 	// New request
		 	$currentPage = $params['page'] = $i;
		 	$output->writeln("--- Fetching page #$i ---");
		 	$html = $this->fetchPage($params);
		 	$dom = \phpQuery::newDocument($html);
		 	
		 	// Download the page
		 	$this->downloadImages($output, $dom, $dir, $currentPage, $timeout);
		}	

		
	}
}