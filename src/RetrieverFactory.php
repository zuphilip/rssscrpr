<?php

require_once 'src/Session.php';
require_once 'src/Retriever.php';
require_once 'src/fetcher/HttpFetcher.php';
require_once 'src/fetcher/CachingHttpFetcher.php';
require_once 'src/fetcher/FacebookFetcher.php';
require_once 'src/parser/HTMLParser.php';
require_once 'src/parser/XMLParser.php';
require_once 'src/parser/AtomParser.php';
require_once 'src/parser/CrawlOutgoingHTMLParser.php';
require_once 'src/scraper/MHonArcScraper.php';
require_once 'src/scraper/XpathScraper.php';
require_once 'src/scraper/TwitterScraper.php';
require_once 'src/scraper/RektoratScraper.php';
require_once 'src/scraper/RSSScraper.php';
require_once 'src/scraper/FacebookScraper.php';
require_once 'src/Utils.php';

class RetrieverFactory
{

    var $scraperHints = array();

    private function __construct() 
    {
    }

    public function reflectComponents()
    {
        $comp = array();
        $superClasses = array(
            'Scraper',
            'Fetcher',
            'Parser'
        );
        foreach ($superClasses as $superClass)
        {
            $comp[$superClass] = array();
            foreach(get_declared_classes() as $candidate)
            {
                $reflect = new ReflectionClass($candidate);
                if (!$reflect->isAbstract() && 
                    ($candidate === $superClass || $reflect->isSubclassOf($superClass)))
                {
                    $comp[$superClass][$candidate]['name'] = $reflect->name;
                    $comp[$superClass][$candidate]['attr'] = array();
                    foreach ($reflect->getProperties() as $prop)
                    {
                        $comp[$superClass][$candidate]['attr'][$prop->name] = array(
                            'name' => $prop->name,
                            'default' => $reflect->getDefaultProperties()[$prop->name]
                        );
                    }
                }
            }
        }

        return json_encode(array('api'=>$comp), JSON_PRETTY_PRINT);
    }


    public function createHtmlScraperFromQueryParams($queryParams)
    {
        if (! $queryParams['url'])
        {
            Utils::throw400("Must set 'url'.");
        }

        // Create the session
        $session = new Session($queryParams['url']);

        // create a Retriever
        $retriever = new Retriever($session);
        foreach (array('scraper', 'fetcher', 'parser') as $component)
        {
            if (!array_key_exists('scraper', $queryParams) || ! $queryParams[$component])
            {
                throw Utils::throw400("Must set '$component'.");
            }
            $retriever->$component = new $queryParams[$component]();
        }

        // Setup filter
        if (array_key_exists('noanswers', $queryParams))
        {
            $retriever->filter->exclude['title'][] = 'Re: ';
        }

        if (array_key_exists('nojobs', $queryParams))
        {
            $retriever->filter->exclude['title'][] = 'Stellenanzeige';
            $retriever->filter->exclude['title'][] = 'Stellenangebot';
            $retriever->filter->exclude['title'][] = 'Stellenausschreibung';
        }

        if (array_key_exists('nofb', $queryParams)) 
        {
            // e.g. exclude automatic posting in twitter from facebook
            $retriever->filter->exclude['title'][] = 'fb.me';
        }

        if (array_key_exists('noretweet', $queryParams))
        {
            $retriever->filter->exclude['test'][] = 'retweet';
        }

        foreach ($queryParams as $k => $v)
        {
            error_log("$k => $v");
            if (! $v)
            {
                continue;
            }
            if (Utils::contains($k, '_'))
            {
                $tokens = explode("_", $k, 2);
                $component = $tokens[0];
                // error_log("{$component} -> {$tokens[1]} = $v");
                if (Utils::contains($tokens[1], '_'))
                {
                    $tokens = explode("_", $tokens[1], 2);
                    // error_log("{$component} -> {$tokens[0]} -> {$tokens[1]}} [] = $v");
                    $retriever->$component->{$tokens[0]}[$tokens[1]][] = $v;
                }
                else
                {
                    $retriever->$component->{$tokens[1]} = $v;
                }
            }
        }

        return $retriever;
    }

}

// error_log ( RetrieverFactory::reflectComponents());

?>
