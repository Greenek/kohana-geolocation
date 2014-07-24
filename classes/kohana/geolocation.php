<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Geolocation {

	/* Country ISO */
	const COUNTRY_ISO = 'CTRY';

	/* Country code */
	const COUNTRY_CODE = 'CNTRY';

	/* Country name */
	const COUNTRY_NAME = 'COUNTRY';

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

		$this->_set_ip($ip);
	}

	public function get($key)
	{
		return $this->_info->$key;
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
		$query = DB::select()
			->from($this->_config['table_name'])
			->where('IP_FROM', '<=', $this->_ip_value)
			->where('IP_TO', '>=', $this->_ip_value)
			->limit(1)
			->as_object()
			->execute()
			->current();

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