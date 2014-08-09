<?php defined('SYSPATH') or die('No direct script access.');

return array(
	// Database will be updated automatically after $value seconds. Set FALSE to disable.
	'auto_update'		=> Date::DAY,

	// URL to database
	'database_url'		=> 'https://raw.githubusercontent.com/magorski/php-ip-2-country/master/ip_to_country.sql',

	// 'Country-IP' table name
	'table_name'		=> 'country_ip'
);