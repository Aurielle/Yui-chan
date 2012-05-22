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



class Pixiv extends Imageboard
{
	/** @var string */
	const BOARD_NAME = 'Pixiv.net';

	/** @var string */
	const BOARD_POST_URL = 'http://www.pixiv.net/search.php';



	/**
	 * Configure the CLI command.
	 * @return void
	 */
	protected function configure()
	{
		$board = static::BOARD_NAME;

		$this
			->setName('pixiv')
			->setDescription("Download images from $board")
			->setHelp('Run without parameters to be asked for them.' . PHP_EOL . '<comment>IMPORTANT WARNING!</comment> If you\'re using characters that have special meaning in command line (such as > or <), enclose your tags in quoutes. Otherwise you\'ll get unexpected results.')
            ->addArgument('tag', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, "Tags you want to download, separated by space. (Same as in search at $board.) (optional)")
            ->addOption('asc', NULL, InputOption::VALUE_NONE, 'Changes the result ordering to ascending.')
            ->addOption('page', 'p', InputOption::VALUE_OPTIONAL, 'The page range you want to download. Example: --page=5-12 (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Downloads all pictures. (optional)')
            ->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Directory in local filesystem which you want to download into. Tag will get appended. Use with caution, required.')
            ->addOption('timeout', NULL, InputOption::VALUE_OPTIONAL, 'Permitted time in seconds, after which will the request time out. Default is 30, optional.', 30)
		;
	}



	/**
	 * Returns the name of directory to download into.
	 * @param string $dir
	 * @param NULL $foo
	 * @return string
	 */
	protected function getDir($dir, $foo = NULL)
	{
		return rtrim($dir, '/\\');
	}



	/**
	 * Finds out and checks if the destination directory exists. If not, attempt to create it.
	 * @param string $dir
	 * @param NULL $foo
	 * @return string
	 * @throws \Nette\IOException
	 */
	protected function checkDir($dir, $foo = NULL)
	{
		$dir = $this->getDir($dir);
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, TRUE);
		}

		if (!is_writable($dir)) {
			throw new Nette\IOException("Directory '$dir' is not writable.");
		}

		return $dir;
	}



	/**
	 * Returns number of items for given tag.
	 * @param \phpQueryObject $dom
	 * @param NULL $foo
	 * @return int
	 */
	protected function getItemCount(\phpQueryObject $dom, $foo = NULL)
	{
		return (int) pq("#page-search .count", $dom)->text();
	}



	/**
	 * Returns number of pages for current search query.
	 * @param \phpQueryObject $dom
	 * @return int
	 */
	protected function getPageCount(\phpQueryObject $dom)
	{
		throw new Nette\NotSupportedException;
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
		array_walk($tags, function(&$value) {
			if (strpos($value, '%') !== FALSE) {
				$value = urldecode($value);
			}
		});

		if (!$input->getOption('dir')) {
			throw new Nette\Application\AbortException('Destination directory not specified.');
		}

		// Setup
		$pageInfo = $this->getPageInfo($pages, $all);
		$tag = $this->getFirstTag($tags);
		$dir = $this->checkDir($input->getOption('dir'));
		$timeout = (int) $input->getOption('timeout');

		$output->writeln('<info>Okay! Yui-chan will now download your pictures!</info>');
		$output->writeln('<comment>!!! Warning !!!</comment> Kanji output may look corrupted on Windows. It doesn\'t affect Yui\'s functionality.');
		$output->writeln('<info>Selected tags:</info> ' . implode(' ', $tags) . ', pages: ' . $pageInfo);
		
		if (!$this->getHelperSet()->get('dialog')->askConfirmation($output, 'Is this information correct?' . PHP_EOL, false)) {
			throw new Nette\Application\AbortException('Aborted by user.');
		}
	
		// Build URL
		$params = array(
			's_mode' => 's_tag',
			'word' => implode(' ', $tags),
			'manga' => 0,	// search only for illustrations
			'order' => $input->getOption('asc') ? 'date' : 'date_d',
			'p' => $all ? 1 : reset($pages),
		);

		// Get the cURL
		$output->writeln('Fetching tag information...');
		$html = $this->fetchPage($params);
		$dom = \phpQuery::newDocument($html);

		if (pq('.no-item:contains("Sorry, no results were found")')->text() === 'Sorry, no results were found') {
			throw new Nette\Application\AbortException("Your search query matched no results.", 1);
		}

		// Number of images
		$itemCount = $this->getItemCount($dom);

		// Set page information
		if ($all) {
			$currentPage = $firstPage = 1;

		} else {
			$currentPage = $firstPage = reset($pages);
		}

		$output->writeln("----- Found <info>~$itemCount pictures</info> on unknown number of pages. -----" . PHP_EOL);
		$referer = static::BOARD_POST_URL . '?' . http_build_query($params);

		// Download first page
		$this->downloadImages($output, $dom, $dir, $currentPage, $timeout, $referer);

		// Only one page?
		if (!pq('nav.pager')->text()) {
			return;
		}
		
		// Other pages - infinite loop is intentional
		for ($i = $firstPage + 1; ; $i++) {
			
			// Check if next page exists
			if (!pq('nav.pager li.next')->text()) {
				break;
			}

			// Are we done yet?
			if ($i > end($pages)) {
				break;
			}

			// New request
			$currentPage = $params['p'] = $i;
			$output->writeln("--- Fetching page #$i ---");
			$html = $this->fetchPage($params);
			$dom = \phpQuery::newDocument($html);
			$referer = static::BOARD_POST_URL . '?' . http_build_query($params);

			// Download the page
			$this->downloadImages($output, $dom, $dir, $currentPage, $timeout, $referer);
		}
	}



	/**
	 * Downloads images from the current page based on given settings.
	 * @param \Symfony\Component\Console\Input\OutputInterface $output
	 * @param \phpQueryObject $dom
	 * @param string $dir
	 * @param int $currentPage
	 * @param int $timeout
	 * @return void
	 */
	protected function downloadImages(OutputInterface $output, \phpQueryObject $dom, $dir, $currentPage, $timeout = 30, $referer = NULL)
	{
		$output->writeln("--- Downloading images from <info>page $currentPage</info> ---");

		$counter = 0;
		foreach (pq('#search-result li', $dom) as $item) {
			$counter++;
			$output->writeln("Downloading image #$counter from page $currentPage.");

			$url = pq($item, $dom)->find('img')->attr('src');
			$filename = $this->getImageLocalName($url);

			try {
				$fh = fopen('safe://' . $dir . '/' . $filename, 'wb');
				$ch = $this->newCurlRequest($this->getImageRemoteUrl($url));
				$ch->setTimeout($timeout);
				$ch->setReferer($referer);
				$res = $ch->send();
				fwrite($fh, $res->getResponse());
				fclose($fh);
			
			} catch(\Exception $e) {
				$output->writeln("Downloading image #$counter (page $currentPage) failed. Error: " . $e->getMessage());
				Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			}
		}
	}



	/**
	 * Returns local filename for given image.
	 * @param string $url
	 * @return string
	 */
	protected function getImageLocalName($url)
	{
		if (($pos = strpos($url, '?')) !== FALSE) {
			$url = substr($url, 0, $pos);
		}

		$tmp = explode('/', substr($url, 7)); // http://
		$filename = end($tmp);
		$ext = substr($filename, -3, 3);

		return substr($filename, 0, -6) . ".$ext";
	}



	/**
	 * Returns remote filename for given image.
	 * @param string $url
	 * @return string
	 */
	protected function getImageRemoteUrl($url)
	{
		if (($pos = strpos($url, '?')) !== FALSE) {
			$url = substr($url, 0, $pos);
		}

		$ext = substr($url, -3, 3);
		return substr($url, 0, -6) . ".$ext";
	}
}