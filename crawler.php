<?php

$_CONFIG['domain']      = ''; // ie: www.youdomain.com
$_CONFIG['host']        = 'localhost'; // database server
$_CONFIG['username']    = ''; // database user
$_CONFIG['password']    = ''; // database password
$_CONFIG['database']    = ''; // database Magento uses
$_CONFIG['concurrency'] = 8; // default is 8
$_CONFIG['store_id']    = '1'; // Magento store id for the store to be crawled
                             

/*** YOU DO NOT NEED TO EDIT BELOW THIS LINE ***/

if(isset($_GET['start'])) {
    $crawler = new crawler();
    $crawler->connect($_CONFIG['host'], $_CONFIG['username'], $_CONFIG['password'], $_CONFIG['database']);
    $crawler->start($_CONFIG['concurrency'], 'http://' . $_CONFIG['domain'] . '/', $_CONFIG['store_id']);    
} else {
    echo 'For best results, you should run System > Cache Management > Catalog Rewrites Refresh first<br />';
    echo '<a href="?start=1">Click here</a> to start crawling';
}

class Crawler {
    private $urls = array();
    private $numOfUrls = 0;
    private $numOfChunks = 0;
    private $totalTime = 0;
    private $chunkCount = 0;

    public function __construct() {
        set_time_limit(0);
        ob_start();
        $this->flushOutput();
    }
    
    public function connect($host, $user, $pass, $database) {
        mysql_connect($host, $user, $pass) or exit('Could not connect to the database');
        mysql_select_db($database) or exit('Could not connect to the database');
    }
    
    public function start($concurrency, $baseUrl, $storeId) {
		// Crawl CMS pages    	
    	$this->startCmsCrawl($concurrency, $baseUrl, $storeId);

    	// Crawl categories
    	$this->startCategoryCrawl($concurrency, $baseUrl, $storeId);

    	// Crawl products
    	$this->startProductCrawl($concurrency, $baseUrl, $storeId);

    }
    
    public function startCmsCrawl($concurrency, $baseUrl, $storeId) {
    	$this->urls = array();

        //cms urls
        $result = mysql_query("SELECT p.identifier as url from cms_page p, cms_page_store s where p.page_id=s.page_id and s.store_id=" . $storeId);
        while($row = mysql_fetch_assoc($result)) $this->urls[] = $row['url'];
        
        $chunks = array_chunk($this->urls, $concurrency, true);
        $this->numOfUrls = count($this->urls);
        $this->numOfChunks = count($chunks);
        $this->totalTime = 0;
        $this->chunkCount = 0;

        echo '<pre>';
        echo "CRAWLING CMS: \n";
        echo "--------------------------------------------------\n";
        foreach($chunks as $chunk) {
            $this->getChunk($chunk, $baseUrl);
        }
        
        echo "\n--------------------------------------------------\n";
        echo 'TOTAL URLS: ' . $this->numOfUrls . "\n";
        echo 'TOTAL SETS: ' . $this->numOfChunks . "\n";
        echo 'TOTAL TIME: ' . round($this->totalTime, 3) . " seconds\n";
        echo 'TIME / SET: ' . round($this->totalTime/$this->numOfChunks, 3) . " seconds\n";
        echo 'TIME / URL: ' . round($this->totalTime/$this->numOfUrls, 3) . " seconds\n";
        echo '</pre>';
        $this->flushOutput();
    }
    
    public function startCategoryCrawl($concurrency, $baseUrl, $storeId) {
    	$this->urls = array();

        //category urls
        $result = mysql_query("SELECT request_path AS url FROM core_url_rewrite WHERE category_id IS NOT NULL AND product_id IS NULL and store_id=" . $storeId . " GROUP BY category_id ORDER BY request_path");
        while($row = mysql_fetch_assoc($result)) $this->urls[] = $row['url'];        
        
        $chunks = array_chunk($this->urls, $concurrency, true);
        $this->numOfUrls = count($this->urls);
        $this->numOfChunks = count($chunks);
        $this->totalTime = 0;
        $this->chunkCount = 0;

        echo '<pre>';
        echo "CRAWLING CATEGORIES: \n";
        echo "--------------------------------------------------\n";
        foreach($chunks as $chunk) {
            $this->getChunk($chunk, $baseUrl);
        }
        
        echo "\n--------------------------------------------------\n";
        echo 'TOTAL URLS: ' . $this->numOfUrls . "\n";
        echo 'TOTAL SETS: ' . $this->numOfChunks . "\n";
        echo 'TOTAL TIME: ' . round($this->totalTime, 3) . " seconds\n";
        echo 'TIME / SET: ' . round($this->totalTime/$this->numOfChunks, 3) . " seconds\n";
        echo 'TIME / URL: ' . round($this->totalTime/$this->numOfUrls, 3) . " seconds\n";
        echo '</pre>';
        $this->flushOutput();
    }

    public function startProductCrawl($concurrency, $baseUrl, $storeId) {
    	$this->urls = array();

        //product urls
        $result = mysql_query("SELECT request_path AS url FROM core_url_rewrite WHERE product_id IS NOT NULL and store_id=" . $storeId . " GROUP BY product_id ORDER BY category_id, request_path");
        while($row = mysql_fetch_assoc($result)) $this->urls[] = $row['url'];
        
        $chunks = array_chunk($this->urls, $concurrency, true);
        $this->numOfUrls = count($this->urls);
        $this->numOfChunks = count($chunks);
        $this->totalTime = 0;
        $this->chunkCount = 0;

        echo '<pre>';
        echo "CRAWLING PRODUCTS: \n";
        echo "--------------------------------------------------\n";
        foreach($chunks as $chunk) {
            $this->getChunk($chunk, $baseUrl);
        }
        
        echo "\n--------------------------------------------------\n";
        echo 'TOTAL URLS: ' . $this->numOfUrls . "\n";
        echo 'TOTAL SETS: ' . $this->numOfChunks . "\n";
        echo 'TOTAL TIME: ' . round($this->totalTime, 3) . " seconds\n";
        echo 'TIME / SET: ' . round($this->totalTime/$this->numOfChunks, 3) . " seconds\n";
        echo 'TIME / URL: ' . round($this->totalTime/$this->numOfUrls, 3) . " seconds\n";
        echo '</pre>';
        $this->flushOutput();
    }

    private function getChunk($chunk, $baseUrl) {
        
        $mh = curl_multi_init();
        $time = microtime(true);
        $this->chunkCount++;
        
        echo "\n";
        foreach($chunk as $x=>$URL) {
            $url = $baseUrl . $URL;
            echo $url . "\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); 
            curl_multi_add_handle($mh, $ch);
        }
        
        $running = 0;
        do { curl_multi_exec($mh, $running); } while ($running > 0);
        curl_multi_close($mh);
        unset($mh);
        
        $time = microtime(true) - $time;
        $this->totalTime += $time;
        echo 'SET ' . $this->chunkCount . ' OF ' . $this->numOfChunks . ' FINISHED: ' . round($time, 3) . ' seconds';
        $this->flushOutput();
    }

    private function flushOutput() {
        echo "\n<script type='text/javascript'> setTimeout('document.body.scrollTop = document.body.scrollHeight;', 100); </script>";
        ob_end_flush(); 
        ob_flush(); 
        flush(); 
        ob_start();
    }    
}

