<?php

$config['query'] = 'horrible trash';
$config['host'] = 'craigslist.org';
$config['url_base'] = 'http://[subdomain].' . $config['host'];
$config['url_path'] = '/search/zip?query=' . $config['query'];

$config['search_distance'] = 400;
$config['search_interval'] = 600;
$config['randomize_interval'] = true;
$config['days_old'] = 7;
$config['max_execution'] = 900;
$config['total_delay'] = 400;
$config['start_delay'] = 150;
$config['data_folder'] = 'cl_data';
$config['user_agent'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36';
$config['verbose'] = true;

$config['notify_email'] = 'sample@example.com';
$config['from_email'] = 'support@example.com';