<?php

/*
 * Craigslist scrapper that attempts to avoid bot detection.
 * @author Jacob Wolowitz
 * @version 1.0
 */

namespace waffley;

// Config.
$config = array();
$config['cities'] = array(
    'miami' => array(
	  'title' => 'Miami / South Florida, FL',
	  'distance' => 26
    ),
    'keys' => array(
	  'title' => 'Florida Keys, FL',
	  'distance' => 73
    ),
    'treasure' => array(
	  'title' => 'Treasure Coast, FL',
	  'distance' => 84
    ),
    'cfl' => array(
	  'title' => 'Heartland Florida, FL',
	  'distance' => 89
    ),
    'fortmyers' => array(
	  'title' => 'Ft Myers / SW Florida, FL',
	  'distance' => 113
    ),
    'spacecoast' => array(
	  'title' => 'Space Coast, FL',
	  'distance' => 169
    ),
    'sarasota' => array(
	  'title' => 'Sarasota-Bradenton, FL',
	  'distance' => 170
    ),
    'lakeland' => array(
	  'title' => 'Lakeland, FL',
	  'distance' => 172
    ),
    'orlando' => array(
	  'title' => 'Orlando, FL',
	  'distance' => 183
    ),
    'tampa' => array(
	  'title' => 'Tampa Bay Area, FL',
	  'distance' => 190
    ),
    'daytona' => array(
	  'title' => 'Daytona Beach, FL',
	  'distance' => 219
    ),
    'ocala' => array(
	  'title' => 'Ocala, FL',
	  'distance' => 244
    ),
    'gainesville' => array(
	  'title' => 'Gainesville, FL',
	  'distance' => 277
    ),
    'staugustine' => array(
	  'title' => 'St Augustine, FL',
	  'distance' => 291
    ),
    'jacksonville' => array(
	  'title' => 'Jacksonville, FL',
	  'distance' => 304
    ),
    'lakecity' => array(
	  'title' => 'North Central FL, FL',
	  'distance' => 319
    ),
);
if (isset($_GET['debug'])) {
	$config['debug'] = $_GET['debug'];
}

require_once('config.php');

$config['data_folder'] = __DIR__ . '/' . $config['data_folder'];

function log($msg) {
	global $config;
	if ($config['log_level'] == 'verbose') {
		echo $msg . "\n";
	}
}

log('Starting search. Setting php runtime config.');

ini_set('max_execution_time', $config['max_execution']);

// Decides where error output goes.
function reportError($message, $data = false, $stop_exit = false) {
	global $config;
	$message .= "\n";
	echo $message;
	$stderr = fopen('php://stderr', 'w');
	fwrite($stderr, $message);
	$error_log = $config['data_folder'] . '/errors.txt';
	file_put_contents($error_log, 'Date: ' . date('r') . "\n" .
						'Message: ' . $message .
						$data . "\n" .
						str_repeat('_', 50) . "\n\n", FILE_APPEND);
	chmod($error_log, 0776);
	if (!$stop_exit) {
		exit;
	}
}

$cache = array();
$new_cache = array();
$first_run = false;
$cache_fn = $config['data_folder'] . '/cache.txt';
if (!file_exists($cache_fn)) {
	log('Cache file does not exist.');
	if (!file_exists($config['data_folder'])) {
		log('Data folder does not exist.');
		mkdir($config['data_folder']);
		chmod($config['data_folder'], 0777);
	}
	log('Creating cache file.');
	if (!touch($cache_fn)) {
		reportError('Cache file cannot be created.');
	}
	chmod($cache_fn, 0776);
	$first_run = true;
}
else if (!is_readable($cache_fn)) {
	reportError('Cache file exists but cannot be read.');
}
else {
	$cache = (array) @json_decode(file_get_contents($cache_fn));
	log('Cache contents: ' . print_r($cache, true));
	if (!count($cache)) {
		reportError('Cache is empty (unusual).', null, true);
	}
	
	// Sleep random interval.
	if (ini_get('max_execution_time') == $config['max_execution']) {
		if (!$config['debug']) {
			$start_delay = mt_rand(0, $config['start_delay']);
			log('Start delay: ' . $start_delay);
			sleep($start_delay);
		}
	}
	else {
		reportError('Max execution not set properly.', false, true);
	}
}

// Get current posts file.
$current_posts_file = '/var/www/reposter/cache/current_posts.json';
$current_posts = [];
if (file_exists($current_posts_file) && is_readable($current_posts_file)) {
	$current_posts = json_decode(file_get_contents('/var/www/reposter/cache/current_posts.json'), true);
}

// Get list of new posts.
$new_posts = array();
$delay_remainder = 0;
$standard_page_delay = round(($config['total_delay'] - $config['start_delay']) / count($config['cities']));
log('Standard page delay: ' . $standard_page_delay);
foreach ($config['cities'] as $subdomain => $city) {
	// Calculate semi-random delays between page visits.
	if (end($config['cities']) != $city) {
		$random_delay = mt_rand(10, $standard_page_delay);
	}
	else {
		$random_delay = $standard_page_delay;
	}
	$page_delay = $random_delay + $delay_remainder;
	log('Page delay: ' . $page_delay);
	if ($page_delay < $standard_page_delay) {
		$delay_remainder = $standard_page_delay - $page_delay;
		log('Delay remainder: ' . $delay_remainder);
	}
	if (!$config['debug']) {
		sleep($page_delay);
		//sleep(2);
	}
	
	// Skip cities too far away.
	if ($city['distance'] > $config['search_distance']) {
		log('Skipping city because outside search radius: ' . $city['distance'] . ' / ' . $config['search_distance']);
		continue;
	}
	
	$url_base = str_replace('[subdomain]', $subdomain, $config['url_base']);
	$url = $url_base . $config['url_path'];
	log('Fetching URL: ' . $url);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Connection: keep-alive',
	    'Pragma: no-cache',
	    'Cache-Control: no-cache',
	    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
	    'DNT: 1',
	    'Accept-Encoding: gzip,deflate,sdch',
	    'Accept-Language: en-US,en;q=0.8',
	));
	if ($config['log_level'] == 'verbose') {
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	}
	$index_data = curl_exec($ch);
	$request_data = curl_getinfo($ch, CURLINFO_HEADER_OUT);
	curl_close($ch);
	if (!$index_data || !strlen($index_data)) {
		reportError('Failed to get post index: ' . $url);
	}
	
	if ($config['log_level'] == 'verbose') {
		$last_index_fn = $config['data_folder'] . '/index_' . $subdomain . '.html';
		file_put_contents($last_index_fn, $request_data . $index_data);
		chmod($last_index_fn, 0776);
	}
	
	$pattern = '/<p[^>]+?class="row"[^>]+?data-pid="(\d+)"[^>]*?>.+?(?:<a[^>]+?data-ids="([^"]+)".+?)?<time[^>]+?datetime="([^"]+)"[^>]*?title="([^"]+)".+?<a[^>]*?href="([^"]+)"[^>]+>(?:<span[^>]*>)([^<]+).+?(?:<small>\s*\(([^)]+).+?)?<\/p>/i';
	$match_result = preg_match_all($pattern, $index_data, $matches);
	if ($match_result === false && !count($matches[1]) && !strstr($index_data, 'Nothing found for that search.')) {
		reportError('Failed to match pattern: ' . $url, $index_data, true);
	}
	if (!count($matches[1])) {
		log('No matches in the results: ' . $url);
	}
	unset($matches[0]);
	
	log('Filtering posts: ' . count($matches[1]));
	for ($i = 0; $i < count($matches[1]); $i++) {
		$post_id = $matches[1][$i];
		$post_image_ids = $matches[2][$i];
		$post_date = $matches[3][$i];
		$post_date_str = $matches[4][$i];
		$post_url = $matches[5][$i];
		$post_title = $matches[6][$i];
		$post_location = $matches[7][$i];

		// If post is one of mine.
		foreach ($current_posts as $dog_posts) { 
			if (in_array($post_id, $dog_posts)) {
				log('Post macthes listing managed by this script.');
				continue;
			}
		}
		
		// If post is too old.
		$post_datetime = new \DateTime($post_date);
		$post_interval = $post_datetime->diff(new \DateTime('now'))->format('%a');
		if ($config['days_old'] <= $post_interval) {
			log('Post too old: ' . $config['days_old'] . ' / ' . $post_interval . ' / ' . $post_id);
			continue;
		}
		
		// If already notified about this post.
		if (!isset($config['cache_disabled']) || $config['cache_disabled'] == true) {
			if (in_array($post_id, $cache)) {
				log('Post already in cache: ' . $post_id);
				$new_cache[] = $post_id;
				continue;
			}
			if (in_array($post_id, $new_cache)) {
				log('Post already found in another site: ' . $post_id);
				continue;
			}
		}
		log('Post passed filters: ' . $post_id);
		
		if (!strstr($post_url, '//')) {
			$fixed_post_url = $url_base . $post_url;
		}
		else {
			$fixed_post_url = 'http:' . $post_url;
		}
		log('URL fix: ' . $post_url . ' / ' . $fixed_post_url);
		
		preg_match('/http:\/\/([^\.]+)\./', $fixed_post_url, $post_subdomain);
		$post_city = $config['cities'][$post_subdomain[1]];

		$post_images = [];
		if (strlen($post_image_ids)) {
			$post_images = array_map(function($val) {
				return 'http://images.craigslist.org/' . substr(trim($val), 2) . '_600x450.jpg';
			}, explode(',', $post_image_ids));
		}

		$is_local = false;
		if (isset($config['local_subdomain']) && $config['local_subdomain'] == $post_subdomain[1]) {
			$is_local = true;
		}
		
		$new_cache[] = $post_id;
		$new_posts[$post_id] = array(
		    'time' => $post_datetime,
		    'time_str' => $post_date_str,
		    'url' => $fixed_post_url,
		    'title' => $post_title,
		    'location' => ($post_location)? $post_location : '(none)',
		    'site' => $post_city['title'] . ' (' . $post_subdomain[1] . ')',
		    'distance' => $post_city['distance'],
		    'images' => $post_images,
		    'is_local' => $is_local
		);
	}
}
log('Posts macthed: ' . print_r($new_posts, true));
log('Posts cached: ' . print_r($new_cache, true));

if (isset($config['debug']) && $config['debug'] == true) {
	log('Exiting due to test mode. Skipping notifications');
	die(json_encode($new_posts));
}
else if ($first_run) {
	log('Skipped notifications due to first run, building cache.');
}
else {
	// Notify about new posts.
	log('Starting notifications.');
	$mail_log = $config['data_folder'] . '/mail.log';
	if (!file_exists($mail_log)) {
		log('Mail log not does not exist. Creating.');
		touch($mail_log);
		chmod($mail_log, 0776);
	}
	foreach ($new_posts as $post) {
		log('Starting message.');
		$message = '';
		$message .= "<br />Time: {$post['time_str']}<br />"
					. "Site: {$post['site']}<br />"
					. "Distance: {$post['distance']}<br />"
					. "Location: {$post['location']}<br />"
					. '<br />';
		
		if ($post['images']) {
			foreach($post['images'] as $image) {
				$message .= "<a href='{$post['url']}'><img src='{$image}' /></a>";
			}
			log('Images found.');
		}
		else {
			$message .= "<a href='{$post['url']}'>{$post['url']}</a>";
			log('No images found.');
		}
		
		$subject = 'CL: ' . (($post['is_local'])? 'Local: ' : '') . $post['title'];
		$log_entry =	'Subject: ' . $subject . "\n" .
					'Date: ' . date('r') . "\n" .
					'Message: ' . $message . "\n" .
					 str_repeat('_', 50) . "\n";
		log('Message (' . $subject . '): ' . $message);
		if (!(@file_put_contents($mail_log, $log_entry, FILE_APPEND))) {
			reportError('Could not write to mail log: ' . $subject, $message);
		}
		if (!mail($config['notify_email'], $subject, $message,	"From: CL Notifier <{$config['from_email']}>\r\n" .
											"MIME-Version: 1.0\r\n" . 
											"Content-type: text/html; charset=iso-8859-1\r\n")) {
			reportError('Email could not be sent: ' . $subject, $message);
		}
	}
}

if (!(@file_put_contents($cache_fn, json_encode($new_cache)))) {
	reportError('Could not save cache.', false, true);
}
