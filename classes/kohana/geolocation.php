<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Geolocation {

	/* Country ISO */
	const COUNTRY_ISO = 'country_iso';

	/* Country code */
	const COUNTRY_CODE = 'country_code';

	/* Country name */
	const COUNTRY_NAME = 'country_name';

	/* Cache update key */
	const CACHE_UPDATE_KEY = 'geolocation.up-to-date';

	/* Object IP */
	protected $_ip;

	/* IP as an array */
	protected $_ip_segments;

	/* IP value */
	protected $_ip_value;

	/* IP values */
	protected $_info;

	/* Config */
	protected $_config;

	public static function factory($ip = NULL, $key = NULL)
	{
		$geolocation = new Geolocation($ip);

		if ($key !== NULL)
		{
			$geolocation = $geolocation->get($key);
		}

		return $geolocation;
	}

	public function __construct($ip = NULL)
	{
		$this->_config = Kohana::$config->load('geolocation');

		if ($this->_config['auto_update'] > 0)
		{
			$this->check_database();
		}

		$this->_set_ip($ip);
	}

	public function check_database()
	{
		if (Kohana::cache(Geolocation::CACHE_UPDATE_KEY) === NULL)
		{
			if ($this->update_database() === FALSE)
			{
				Kohana::cache(Geolocation::CACHE_UPDATE_KEY, FALSE, Date::HOUR);
			}
		}
	}

	public function get($key)
	{
		return (is_object($this->_info) AND isset($this->_info->$key)) ? $this->_info->$key : NULL;
	}

	public function update_database()
	{
		$tmp_file = '/tmp/geoip.gz';

		// Open connection to Geo-IP database
		$output = fopen($tmp_file, 'wb');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->_config['database_url']);
		curl_setopt($curl, CURLOPT_FILE, $output);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);

		$result = curl_exec($curl);

		curl_close($curl);
		fclose($output);

		if ($result === FALSE)
			return FALSE;

		$output = gzopen($tmp_file, 'rb');

		// Probably connection limit has been reached
		if (gzgets($output)[0] !== '<')
		{
			$rows = array();
			gzrewind($output);

			while ($line = gzgets($output))
			{
				if ($line[0] === '#')
					continue;

				$rows[] = '('.trim($line).')';
			}
		}

		gzclose($output);
		@unlink($tmp_file);

		if (empty($rows))
			return FALSE;

		// Truncate table
		DB::query(Database::DELETE, 'TRUNCATE TABLE '.$this->_config['table_name'])->execute();

		// Split rows into groups and insert
		$rows = array_chunk($rows, 300);
		array_walk($rows, array($this, '_insert_rows'));

		Kohana::cache(Geolocation::CACHE_UPDATE_KEY, TRUE, $this->_config['auto_update']);

		return TRUE;
	}

	protected function _calc_ip_value($ip)
	{
		$ip_segments = array_map('intval', explode('.', $ip));

		$value = ($ip_segments[3])
		       + ($ip_segments[2] * 256)
		       + ($ip_segments[1] * 256 * 256)
		       + ($ip_segments[0] * 256 * 256 * 256);

		return $value;
	}

	protected function _get_ip_row($ip = NULL)
	{
		if ($ip === NULL)
		{
			$ip = $this->_ip;
		}

		$ip_value = $this->_calc_ip_value($ip);

		try
		{
			$query = DB::select()
				->from($this->_config['table_name'])
				->where('ip_from', '<=', $ip_value)
				->where('ip_to', '>=', $ip_value)
				->limit(1)
				->as_object()
				->execute()
				->current();
		}
		catch (Database_Exception $e)
		{
			if (preg_match('/Table \'([a-z0-9._]+)\' doesn\'t exist/', $e->getMessage()))
				throw new Geolocation_Exception('Geolocation module not initialized. Please import SQL from '.MODPATH.'geolocation/schema/country_ip.sql.');
		}

		return $query;
	}

	protected function _insert_rows(array $rows)
	{
		$query = 'INSERT INTO `'.$this->_config['table_name'].'` (`ip_from`, `ip_to`, `registry`, `assigned`, `country_iso`, `country_code`, `country_name`) VALUES'
		       . implode(',', $rows);

		return DB::query(Database::INSERT, $query)->execute();
	}

	protected function _set_ip($ip = NULL)
	{
		if ($ip === NULL)
		{
			$ip = Request::$client_ip;
		}

		if ( ! Valid::ip($ip))
		{
			throw new Kohana_Exception('IP address is not valid.');
		}

		$this->_ip = $ip;

		if ($this->_config['cache_ip'] === TRUE)
		{
			$cache_ip = 'geolocation.ip.'.$ip;
			$info = Kohana::cache($cache_ip);
		}

		if (empty($info))
		{
			$info = $this->_get_ip_row();
		}

		$this->_info = $info;

		if (isset($cache_ip))
		{
			Kohana::cache($cache_ip, $info, Date::HOUR);
		}
	}

}