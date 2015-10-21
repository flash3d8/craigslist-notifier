<?php

/*
 * Craigslist scrapper that attempts to avoid bot detection.
 * @author Jacob Wolowitz
 * @version 1.0
 */

// Config.
$config = array();
$config['cities'] = array(
    'Miami / South Florida, FL' => array(
	  'subdomain' => 'miami',
	  'distance' => 26
    ),
    'Florida Keys, FL' => array(
	  'subdomain' => 'keys',
	  'distance' => 73
    ),
    'Treasure Coast, FL' => array(
	  'subdomain' => 'treasure',
	  'distance' => 84
    ),
    'Heartland Florida, FL' => array(
	  'subdomain' => 'cfl',
	  'distance' => 89
    ),
    'Ft Myers / SW Florida, FL' => array(
	  'subdomain' => 'fortmyers',
	  'distance' => 113
    ),
    'Space Coast, FL' => array(
	  'subdomain' => 'spacecoast',
	  'distance' => 169
    ),
    'Sarasota-bradenton, FL' => array(
	  'subdomain' => 'sarasota',
	  'distance' => 170
    ),
    'Lakeland, FL' => array(
	  'subdomain' => 'lakeland',
	  'distance' => 172
    ),
    'Orlando, FL' => array(
	  'subdomain' => 'orlando',
	  'distance' => 183
    ),
    'Tampa Bay Area, FL' => array(
	  'subdomain' => 'tampa',
	  'distance' => 190
    ),
    'Daytona Beach, FL' => array(
	  'subdomain' => 'daytona',
	  'distance' => 219
    ),
    'Ocala, FL' => array(
	  'subdomain' => 'ocala',
	  'distance' => 244
    ),
    'Gainesville, FL' => array(
	  'subdomain' => 'gainesville',
	  'distance' => 277
    ),
    'St Augustine, FL' => array(
	  'subdomain' => 'staugustine',
	  'distance' => 291
    ),
    'Jacksonville, FL' => array(
	  'subdomain' => 'jacksonville',
	  'distance' => 304
    ),
    'North Central FL, FL' => array(
	  'subdomain' => 'lakecity',
	  'distance' => 319
    ),
);

require_once('config.php');

ini_set('max_execution_time', $config['max_execution']);

// Decides where error output goes.
function reportError($message, $data = false, $stop_exit = false) {
	$message .= "\n";
	echo $message;
	// $stderr = fopen('php://stderr', 'w');
	// fwrite($stderr, $message);
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
	mkdir($config['data_folder']);
	chmod($config['data_folder'], 0777);
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
	if (!count($cache)) {
		reportError('Cache is empty (unusual).');
	}
	
	// Sleep random interval.
	if (ini_get('max_execution_time') == $config['max_execution']) {
		if (!$_GET['debug']) {
			sleep(mt_rand(0, $config['start_delay']));
		}
	}
	else {
		reportError('Max execution not set properly.', false, true);
	}
}

// Get list of new posts.
$new_posts = array();
$delay_remainder = 0;
$standard_page_delay = round(($config['total_delay'] - $config['start_delay']) / count($config['cities']));
foreach ($config['cities'] as $city_name => $city) {
	// Calculate semi-random delays between page visits.
	if (end($config['cities']) != $city) {
		$random_delay = mt_rand(10, $standard_page_delay);
	}
	else {
		$random_delay = $standard_page_delay;
	}
	$page_delay = $random_delay + $delay_remainder;
	if ($page_delay < $standard_page_delay) {
		$delay_remainder = $standard_page_delay - $page_delay;
	}
	if (!$_GET['debug']) {
		sleep($page_delay);
	}
	
	// Skip cities too far away.
	if ($city['distance'] > $config['search_distance']) {
		continue;
	}
	
	$url_base = str_replace('[subdomain]', $city['subdomain'], $config['url_base']);
	$url = $url_base . $config['url_path'];
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
	if ($config['verbose']) {
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	}
	$index_data = curl_exec($ch);
	$request_data = curl_getinfo($ch, CURLINFO_HEADER_OUT);
	curl_close($ch);
	if (!$index_data || !strlen($index_data)) {
		reportError('Failed to get post index: ' . $url);
	}
	
	if ($config['verbose']) {
		$last_index_fn = $config['data_folder'] . '/index_' . $city['subdomain'] . '.txt';
		file_put_contents($last_index_fn, $request_data . $index_data);
		chmod($last_index_fn, 0776);
	}
	
	$pattern = '/<p\s+class="row"\s+data-pid="(\d+)">.+?(?:<a[^>]+?data-id="0:([^"]+)".+?)?<time\s+datetime="([^"]+)"\s+title="([^"]+)".+?<a.+?href="([^"]+)"[^>]+>([^<]+).+?<small>\s*\(([^)]+)/i';
	$match_result = preg_match_all($pattern, $index_data, $matches);
	if ($match_result  === false) {
		reportError('Failed to match pattern: ' . $url, $index_data);
	}
	if (!$match_result || !count($matches[0])) {
		reportError('No matches in the results: ' . $url, $index_data);
	}
	
	for ($i = 0; $i < count($matches[0]); $i++) {
		$post_id = $matches[1][$i];
		$post_image_id = $matches[2][$i];
		$post_date = $matches[3][$i];
		$post_date_str = $matches[4][$i];
		$post_url = $matches[5][$i];
		$post_title = $matches[6][$i];
		$post_location = $matches[7][$i];
		
		// If post is too old.
		$post_datetime = new DateTime($post_date);
		$post_interval = $post_datetime->diff(new DateTime('now'));
		if ($config['days_old'] <= $post_interval->format('%a')) {
			continue;
		}
		
		// If already notified about this post.
		if ((in_array($post_id, $cache) || in_array($post_id, $new_cache)) && !isset($_GET['full'])) {
			$new_cache[] = $post_id;
			continue;
		}
		
		if (!strstr($post_url, 'http')) {
			$post_url = $url_base . $post_url;
		}
		
		preg_match('/http:\/\/([^\.]+)\./', $post_url, $post_city);
		
		$new_cache[] = $post_id;
		$new_posts[$post_id] = array(
		    'time' => $post_datetime,
		    'time_str' => $post_date_str,
		    'url' => $post_url,
		    'title' => $post_title,
		    'location' => $post_location,
		    'site' => $post_city[1],
		    'distance' => $city['distance'],
		    'image' => ($post_image_id)? 'http://images.craigslist.org/' . $post_image_id . '_600x450.jpg' : false
		);
	}
}

if (!(@file_put_contents($cache_fn, json_encode($new_cache)))) {
	reportError('Could not save cache.', false, true);
}

if ($first_run && !isset($_GET['test'])) {
	exit;
}
else if (isset($_GET['test'])) {
	die(json_encode($new_posts));
}

// Notify about new posts.
foreach($new_posts as $post) {
	$message = '';
	if ($post['image']) {
		$message .= "<a href='{$post['url']}'><img src='{$post['image']}' /></a>";
	}
	else {
		$message .= "<a href='{$post['url']}'>{$post['url']}</a>";
	}
	$message .= "<br />Time: {$post['time_str']}<br />"
				. "Site: {$post['site']}<br />"
				. "Distance: {$post['distance']}<br />"
				. "Location: {$post['location']}<br />";
	$mail_log = $config['data_folder'] . '/mail.log';
	$subject = 'CL: ' . $post['title'];
	$log_entry =	'Subject: ' . $subject . "\n" .
				'Date: ' . date('r') . "\n" .
				'Message: ' . $message . "\n" .
				 str_repeat('_', 50) . "\n";
	if (!(@file_put_contents($mail_log, $log_entry, FILE_APPEND))) {
		reportError('Could not write to mail log: ' . $subject, $message);
	}
	chmod($mail_log, 0776);
	if (!mail($config['notify_email'], $subject, $message,	"From: CL Notifier <{$config['from_email']}>\r\n" .
										"MIME-Version: 1.0\r\n" . 
										"Content-type: text/html; charset=iso-8859-1\r\n")) {
		reportError('Email could not be sent: ' . $subject, $message);
	}
}
