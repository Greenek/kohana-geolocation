CREATE TABLE IF NOT EXISTS `country_ip` (
	`ip_from` bigint(20) unsigned NOT NULL,
	`ip_to` bigint(20) unsigned NOT NULL,
	`registry` char(7) NOT NULL,
	`assigned` bigint(20) NOT NULL,
	`country_iso` char(2) NOT NULL,
	`country_code` char(3) NOT NULL,
	`country_name` varchar(100) NOT NULL,
	PRIMARY KEY  (`ip_from`,`ip_to`)
) ENGINE=MyISAM;