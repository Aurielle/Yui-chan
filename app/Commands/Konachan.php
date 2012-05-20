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



class Konachan extends Command
{
    const KONACHAN_POST_URL = 'http://konachan.com/post';

	protected function configure()
    {
        $this
            ->setName('konachan')
            ->setDescription('Download images from Konachan.com')
            ->setHelp('Run without parameters to be asked for them.' . PHP_EOL . '<comment>IMPORTANT WARNING!</comment> If you\'re using characters that have special meaning in command line (such as > or <), enclose your tags in quoutes. Otherwise you\'ll get unexpected results.')
            ->addArgument('tag', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Tags you want to download, separated by space. (Same as in Konachan\'s search.) (optional)')
            ->addOption('page', 'p', InputOption::VALUE_OPTIONAL, 'The page range you want to download. Example: --page=5-12 (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Downloads all pictures. (optional)')
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, 'Directory in local filesystem which you want to download into. Use with caution.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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

        	$tags = explode(' ', $tags);
        }

    	// Page range?
    	if ($pages) {
    		if (is_numeric($pages)) {
    			$pages = (int) $pages;
    		
    		} elseif (Nette\Utils\Strings::match($pages, '#[0-9]+\-[0-9]+#')) {
	    		$pages = explode('-', $pages);
	    		foreach ($pages as &$page) {
	    			$page = (int) $page;
	    		}
	    	
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
    						return (int) $value;
    					
    					} elseif(Nette\Utils\Strings::match($value, '#[0-9]+\-[0-9]+#')) {
    						$value = explode('-', $value);
    						foreach ($value as &$page) {
    							$page = (int) $page;
    						}

    						return $value;
    					
    					} else {
    						throw new Nette\InvalidArgumentException('Invalid format.');
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

    	
    	$this->download($input, $output, $tags, $pages, $all);
    }



    /**
     * Downloads specified images from Konachan to local folder.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $tags
     * @param int|array[int] $pages
     * @param bool $all
     */
    protected function download(InputInterface $input, OutputInterface $output, array $tags, $pages, $all)
    {
        if (!is_array($pages)) {
            $pages = array($pages);
        }

        $pageInfo = count($pages) > 1 ? reset($pages) . '-' . end($pages) : end($pages);
        $pageInfo = $all ? 'all' : $pageInfo;

        $output->writeln('<info>Okay! Yui-chan will now download your pictures!</info>');
        $output->writeln('<info>Selected tags:</info> ' . implode(' ', $tags) . ', pages: ' . $pageInfo);

        foreach($tags as $tag) {
            if (strpos($tag, ':') !== FALSE) 
                continue;

            break;
        }

        // Create the directory
        $dir = $input->getOption('output-dir') ? $input->getOption('output-dir') : YUI_DIR . '/download/' . $tag;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, TRUE);
        }

        // Build URL
        $params = array(
            'tags' => implode(' ', $tags),
            'page' => reset($pages),
        );

        // Get the cURL
        $output->writeln('Fetching first page...');
        $request = new Kdyby\Extension\Curl\Request(static::KONACHAN_POST_URL);
        
        // Send the request for the first page
        $response = $request->get($params);
        $dom = \phpQuery::newDocument($response->getResponse());
        $itemCount = (int) pq("#tag-sidebar li")->find("a:contains('" . str_replace('_', ' ', $tag) . "')")->parent()->find('span.post-count')->text();
        
        // bug here
        $tmp = count(pq("#paginator .pagination a")) + 1; // hack because of "..."
        $pageCount = (int) pq("#paginator .pagination a:nth-child($tmp)")->text();
        $currentPage = $firstPage = reset($pages);
        $lastPage = end($pages);

        $output->writeln("----- Found <info>$itemCount pictures</info> on <info>$pageCount pages</info>. -----" . PHP_EOL);

        // Download images
        $downloadImages = function() use($output, $dir, $currentPage) {
            $output->writeln("--- Downloading images from <info>page $currentPage</info> ---");

            $counter = 0;
            foreach (pq('#post-list-posts li') as $item) {
                $counter++;
                $output->writeln("Downloading image #$counter from page $currentPage.");

                $url = pq($item)->find('a.directlink')->attr('href');
                $tmp = explode('/', substr($url, 7));
                $filename = $tmp[2] . '.' . substr($url, -3, 3);
                
                $fh = fopen($dir . '/' . $filename, 'wb');
                $ch = new Kdyby\Extension\Curl\Request($url);
                $res = $ch->send();
                fwrite($fh, $res->getResponse());
                fclose($fh);
            }
        };

        $downloadImages();


        // Download other pages
        if ($firstPage + 1 > $lastPage) {
            return;
        }
        
        // bug here
        for ($i = $firstPage + 1; $i <= $lastPage; $i++) {
            $currentPage = $i;
            $downloadImages();
        }

        $output->writeln(PHP_EOL . '<info>Yui has finished! ^_^</info>');
    }
}