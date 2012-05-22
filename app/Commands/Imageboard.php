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



abstract class Imageboard extends Command
{
	/** @var string */
	const BOARD_NAME = '';

	/** @var string */
	const BOARD_POST_URL = '';



	/**
	 * Configure the CLI command.
	 * @return void
	 */
	protected function configure()
	{
		// The name of the board.
		$board = static::BOARD_NAME;

		$this
			->setDescription("Download images from $board")
			->setHelp('Run without parameters to be asked for them.' . PHP_EOL . '<comment>IMPORTANT WARNING!</comment> If you\'re using characters that have special meaning in command line (such as > or <), enclose your tags in quoutes. Otherwise you\'ll get unexpected results.')
            ->addArgument('tag', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, "Tags you want to download, separated by space. (Same as in search at $board.) (optional)")
            ->addOption('page', 'p', InputOption::VALUE_OPTIONAL, 'The page range you want to download. Example: --page=5-12 (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Downloads all pictures. (optional)')
            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'Directory in local filesystem which you want to download into. Tag will get appended. Use with caution, optional.')
            ->addOption('timeout', NULL, InputOption::VALUE_OPTIONAL, 'Permitted time in seconds, after which will the request time out. Default is 30, optional.', 30)
        ;
	}



	/**
	 * Execute the command.
	 * @param \Symfony\Component\Console\Input\InputInterface $input
	 * @param \Symfony\Component\Console\Input\OutputInterface $output
	 * @return void
	 */
	final protected function execute(InputInterface $input, OutputInterface $output)
	{
		$tags = $input->getArgument('tag');
		$pages = $input->getOption('page');
		$all = $input->getOption('all');
		$dialog = $this->getHelperSet()->get('dialog');

		// Tags specified by argument
		if ($tags) {

			// Were the tags in quoutes?
			if (count($tags) === 1 && strpos($tags[0], ' ') !== FALSE) {
				$tags = explode(' ', $tags[0]);
			}

		// Tags will be specified by user here
		} else {
			$tags = $dialog->ask($output, '<question>Enter tag(s), which you want to download:</question>' . PHP_EOL);
			if (!$tags) {
				$output->writeln('<comment>No tags entered! Aborting.</comment>');
				return;
			}

			$tags = explode(' ', trim($tags, '"'));	// yeah, users can be stupid
		}

		// Page range?
		if ($pages) {
			if (is_numeric($pages)) {
				$pages = array((int) $pages);
			
			} elseif (Nette\Utils\Strings::match($pages, '#[0-9]+\-[0-9]+#')) {
				$pages = explode('-', $pages);
				array_walk($pages, function(&$val) {
					$val = (int) $val;
				});
			
			} else {
				$output->writeln('<comment>Invalid page format! Aborting.</comment>');
				return;
			}
		}

		// User inputs the page range or chooses to download all
		if (!$all && !$pages) {
			if (!$dialog->askConfirmation($output, '<question>Do you want to download all pictures?</question>' . PHP_EOL)) {
				$pages = $dialog->askAndValidate(
					$output,
					'<question>Enter page number or page range to download.</question> Use dash to specify range (i.e. 5-12).' . PHP_EOL,
					function($value) {
						if (is_numeric($value)) {
							return array((int) $value);
						
						} elseif(Nette\Utils\Strings::match($value, '#[0-9]+\-[0-9]+#')) {
							$value = explode('-', $value);
							array_walk($value, function(&$val) {
								$val = (int) $val;
							});

							return $value;
						
						} else {
							throw new Nette\InvalidArgumentException('Invalid format, try again.');
						}
					}
				);

			} else {
				$all = TRUE;
			}
		}

		// Both all and page specified
		if ($pages && $all) {
			if (!$dialog->askConfirmation($output, '<comment>Warning!</comment> Both page and all option was entered. <question>Do you wish to continue?</question> ', false)) {
				return;
			}

			if ($dialog->askAndValidate(
					$output,
					'<question>Which option do you want to use?</question> Answer with \'all\' or \'range\'.' . PHP_EOL,
					function($value) { 
						if (!in_array(strtolower($value), array('all', 'range'))) {
							throw new Nette\InvalidArgumentException("Invalid value entered. Use only 'all' or 'range'."); 
						}

						return $value;
					}
				) === 'range') {
				
				$all = FALSE;
			
			} else {
				$pages = NULL;
			}
		}

		
		// Perform the download
		try {
			$this->download($input, $output, $tags, $pages, $all);
		}
		catch (Nette\Application\AbortException $e) {
			$output->writeln(PHP_EOL . '<error>Forcing exit:</error> ' . $e->getMessage());
			return $e->getCode();
		}
		catch (\Exception $e) {
			Nette\Diagnostics\Debugger::log($e, Nette\Diagnostics\Debugger::ERROR);
			$output->writeln(PHP_EOL . '<error>ERROR:</error> ' . $e->getMessage());
			return $e->getCode();	
		}

		// The end message ^^
		$output->writeln(PHP_EOL . '<info>Yui has finished! ^_^</info>');
	}



	/**
	 * Returns information about selected pages formatted as string.
	 * @param array|NULL $pages
	 * @param bool $all
	 * @return string
	 */
	protected function getPageInfo(array $pages = NULL, $all = TRUE)
	{
		if ($all) {
			$pageInfo = 'all';
		
		} elseif(count($pages) > 1) {
			$pageInfo = reset($pages) . '-' . end($pages);

		} else {
			$pageInfo = end($pages);
		}

		return $pageInfo;
	}



	/**
	 * Returns first common (non-meta) tag.
	 * @param array $tags
	 * @return string
	 */
	protected function getFirstTag(array $tags)
	{
		$commonTags = $this->getCommonTags($tags);
		return reset($commonTags);
	}



	/**
	 * Returns all common (non-meta) tags.
	 * @param array $tags
	 * @return array
	 */
	protected function getCommonTags(array $tags)
	{
		$commonTags = array_filter($tags, function($value) {
			return strpos($value, ':') === FALSE;
		});

		return $commonTags;
	}



	/**
	 * Returns the name of directory to download into.
	 * @param string $tag
	 * @param string|NULL $custom
	 * @return string
	 */
	protected function getDir($tag, $custom = NULL)
	{
		return $custom ? rtrim($custom, '/\\') . '/' . $tag : YUI_DIR . '/download/' . $tag;
	}



	/**
	 * Finds out and checks if the destination directory exists. If not, attempt to create it.
	 * @param string $tag
	 * @param string|NULL $custom
	 * @return string
	 * @throws \Nette\IOException
	 */
	protected function checkDir($tag, $custom = NULL)
	{
		$dir = $this->getDir($tag, $custom);
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, TRUE);
		}

		if (!is_writable($dir)) {
			throw new Nette\IOException("Directory '$dir' is not writable.");
		}

		return $dir;
	}



	/**
	 * Factory for new cURL requests.
	 * @param string $url
	 * @return Kdyby\Extension\Curl\Request
	 */
	protected function newCurlRequest($url)
	{
		return new Kdyby\Extension\Curl\Request($url);
	}



	/**
	 * Fetches imageboard page through cURL. GET parameters are passed through an array.
	 * @param array $params
	 * @return string
	 * @throws \Kdyby\Extension\Curl\CurlException
	 */
	protected function fetchPage(array $params)
	{
		$request = $this->newCurlRequest(static::BOARD_POST_URL);
		$response = $request->get($params);
		return $response->getResponse();
	}



	/**
	 * Returns number of items for given tag.
	 * @param \phpQueryObject $dom
	 * @param string $tag
	 * @return int
	 */
	protected function getItemCount(\phpQueryObject $dom, $tag)
	{
		return (int) pq("#tag-sidebar li", $dom)->find("a:contains('" . str_replace('_', ' ', $tag) . "')")->parent()->find('span.post-count')->text();
	}



	/**
	 * Returns number of pages for current search query.
	 * @param \phpQueryObject $dom
	 * @return int
	 */
	protected function getPageCount(\phpQueryObject $dom)
	{
		$text = trim(pq("#paginator .pagination a", $dom)->text());
		if (empty($text)) { // Only one page
			return 1;
		
		} else {
			$tmp = explode("\n", $text);
			return (int) $tmp[count($tmp) - 2]; // indexes are from zero, we want to retrieve last but one item
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
		foreach (pq('#post-list-posts li', $dom) as $item) {
			$counter++;
			$output->writeln("Downloading image #$counter from page $currentPage.");

			$url = pq($item, $dom)->find('a.directlink')->attr('href');
			$filename = $this->getImageLocalName($url);
			
			try {
				$fh = fopen('safe://' . $dir . '/' . $filename, 'wb');
				$ch = $this->newCurlRequest($url);
				$ch->setTimeout($timeout);
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
		$tmp = explode('/', substr($url, 7)); // http://
		$filename = $tmp[2] . '.' . substr($url, -3, 3);

		return $filename;
	}



	/**
	 * Downloads specified images from the imageboard to local folder.
	 * @param \Symfony\Component\Console\Input\InputInterface $input
	 * @param \Symfony\Component\Console\Input\OutputInterface $output
	 * @param array[string] $tags
	 * @param array[int] $pages
	 * @param bool $all
	 */
	abstract protected function download(InputInterface $input, OutputInterface $output, array $tags, array $pages, $all);
}