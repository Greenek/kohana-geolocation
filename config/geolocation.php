<?php defined('SYSPATH') or die('No direct script access.');

return array(
	// Database will be updated automatically after $value seconds. Set FALSE to disable.
	'auto_update'		=> Date::DAY,

	// Cache user IP
	'cache_ip'			=> TRUE,

	// URL to database
	'database_url'		=> 'http://software77.net/geo-ip/?DL=1&x=Download',

	// 'Country-IP' table name
	'table_name'		=> 'country_ip'
);