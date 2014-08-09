<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Geolocation {

	/* Country ISO */
	const COUNTRY_ISO = 'country_iso';

	/* Country code */
	const COUNTRY_CODE = 'country_code';

	/* Country name */
	const COUNTRY_NAME = 'country_name';

	/* Cache key name */
	const CACHE_KEY = 'geolocation.up-to-date';

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
		$geolocation = new Geolocation($ip, $key);

		if ($key === NULL)
		{
			return $geolocation;
		}
		else
		{
			return $geolocation->get($key);
		}
	}

	public function __construct($ip = NULL)
	{
		$this->_config = Kohana::$config->load('geolocation');

		if ($ip === NULL)
		{
			$ip = Request::$client_ip;
		}

		if ( ! Valid::ip($ip))
		{
			throw new Kohana_Exception('IP address is not valid.');
		}

		if ($this->_config['auto_update'] > 0)
		{
			$this->check_database();
		}

		$this->_set_ip($ip);
	}

	public function check_database()
	{
		if (Kohana::cache(Geolocation::CACHE_KEY) === NULL)
		{
			if ($this->update_database() === FALSE)
			{
				Kohana::cache(Geolocation::CACHE_KEY, FALSE, Date::HOUR);
			}
		}
	}

	public function get($key)
	{
		return (is_object($this->_info) AND isset($this->_info->$key)) ? $this->_info->$key : NULL;
	}

	public function update_database()
	{
		$request = Request::factory($this->_config['database_url'])
			->execute();

		if ($request->status() !== 200)
			return FALSE;

		// Truncate table
		DB::query(Database::DELETE, 'TRUNCATE TABLE '.$this->_config['table_name'])->execute();

		// Refactor names
		$refactor = array(
			'ip_to_country' => $this->_config['table_name'],
			'IP_FROM'		=> 'ip_from',
			'IP_TO'			=> 'ip_to',
			'REGISTRY'		=> 'registry',
			'ASSIGNED'		=> 'assigned',
			'CTRY'			=> 'country_iso',
			'CNTRY'			=> 'country_code',
			'COUNTRY'		=> 'country_name'
		);

		// Read response line by line
		$db = explode(PHP_EOL, $request->body());

		while (($line = current($db)) !== FALSE)
		{
			if (strpos($line, 'INSERT INTO') !== FALSE)
			{
				$query = str_replace(array_keys($refactor), array_values($refactor), $line);

				do
				{
					$line = next($db);
					$query .= PHP_EOL.$line;
				} while(substr(trim($line), -1) !== ';');

				$insert = DB::query(Database::INSERT, $query)->execute();
			}

			next($db);
		}

		Kohana::cache(Geolocation::CACHE_KEY, TRUE, $this->_config['auto_update']);

		return TRUE;
	}

	protected function _calc_ip_value(array $ip_segments)
	{
		$value = $ip_segments[3]
		       + ($ip_segments[2] * 256)
		       + ($ip_segments[1] * 256 * 256)
		       + ($ip_segments[0] * 256 * 256 * 256);

		return $value;
	}

	protected function _get_ip_row()
	{
		try
		{
			$query = DB::select()
				->from($this->_config['table_name'])
				->where('ip_from', '<=', $this->_ip_value)
				->where('ip_to', '>=', $this->_ip_value)
				->limit(1)
				->as_object()
				->execute()
				->current();
		}
		catch (Database_Exception $e)
		{
			if (preg_match('/Table \'([a-z0-9._]+)\' doesn\'t exist/', $e->getMessage()))
			{
				throw new Geolocation_Exception('Geolocation module not initialized. Please import SQL from '.MODPATH.'geolocation/schema/country_ip.sql.');
			}
		}

		return $query;
	}

	protected function _set_ip($ip)
	{
		$this->_ip = $ip;

		// Split to segments
		$this->_ip_segments = array_map('intval', explode('.', $ip));

		// Calc value
		$this->_ip_value = $this->_calc_ip_value($this->_ip_segments);

		// Get info
		$this->_info = $this->_get_ip_row();
	}

}