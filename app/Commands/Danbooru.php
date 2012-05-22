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



class Danbooru extends Imageboard
{
	/** @var string */
	const BOARD_NAME = 'Danbooru';

	/** @var string */
	const BOARD_POST_URL = 'http://danbooru.donmai.us/post';

	/** @var string */
	const BOARD_URL_DOMAIN = 'http://danbooru.donmai.us';

	/** @var string */
	const IMAGE_URL_DOMAIN = 'http://hijiribe.donmai.us/data';



	/**
	 * Configure the CLI command.
	 * @return void
	 */
	protected function configure()
	{
		$this->setName('danbooru');
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

		if (pq('.content p:contains("Nobody here but us chickens!")')->text() === 'Nobody here but us chickens!') {
			throw new Nette\Application\AbortException("Your search query matched no results.", 1);
		}

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

		$output->writeln("----- Found <info>~$itemCount pictures</info> on <info>~$pageCount pages</info>. -----");
		$output->writeln("(this is not accurate, both tag counter and paginator are broken)" . PHP_EOL);

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
			
			if (pq('.content p:contains("Nobody here but us chickens!")')->text() === 'Nobody here but us chickens!') {
				$output->writeln("Reached page $currentPage, but there are no more images...");
				break;
			}

			// Download the page
			$this->downloadImages($output, $dom, $dir, $currentPage, $timeout);
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
	protected function downloadImages(OutputInterface $output, \phpQueryObject $dom, $dir, $currentPage, $timeout = 30)
	{
		$output->writeln("--- Downloading images from <info>page $currentPage</info> ---");

		$counter = 0;
		foreach (pq('.content span.thumb a', $dom) as $item) {
			$counter++;
			$output->writeln("Trying to download image #$counter from page $currentPage...");

			$url = pq($item, $dom)->find('img')->attr('src');
			if (trim($url) === 'http://hijiribe.donmai.us/download-preview.png') {
				continue;	// no, we won't download flash shit. only images
			}

			$filename = $this->getImageLocalName($url);
			$url = static::IMAGE_URL_DOMAIN . '/' . $filename;
			
			try {
				$ch = $this->newCurlRequest($url);
				$ch->setTimeout($timeout);
				$res = $ch->send();
				$contents = $res->getResponse();
				$fh = fopen('safe://' . $dir . '/' . $filename, 'wb');
				fwrite($fh, $contents);
				fclose($fh);
				$output->writeln("Image #$counter (page $currentPage) downloaded.");
			
			} catch (Kdyby\Extension\Curl\BadStatusException $e) {
				// now we really open the page and look for the correct extension
				if($e->getCode() != 404) { // intentionally ==
					// yeah, this doesn't follow DRY, but what can we do...
					$output->writeln("Downloading image #$counter (page $currentPage) failed. Error: " . $e->getMessage());
					Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
				}

				$output->writeln("Guess download of image #$counter failed. Opening image's page, this can take a little bit longer.");
				$url = static::BOARD_URL_DOMAIN . pq($item, $dom)->attr('href');
				$html = $this->newCurlRequest($url)->send()->getResponse();
				$dom2 = \phpQuery::newDocument($html);

				$url = pq('#image')->attr('src');
				$filename = $this->getImageLocalName($url);
				$contents = $this->newCurlRequest($url)->send()->getResponse();
				$fh = fopen('safe://' . $dir . '/' . $filename, 'wb');
				fwrite($fh, $contents);
				fclose($fh);
				$output->writeln("Image #$counter (page $currentPage) downloaded.");

			} catch (\Exception $e) {
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
		$filename = substr($url, -36, 36);	// hash (32) + extension (4)
		return $filename;
	}
}