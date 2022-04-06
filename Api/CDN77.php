<?php
/**
 * @package         Cache Cleaner
 * @version         8.1.0
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            http://regularlabs.com
 * @copyright       Copyright © 2022 Regular Labs All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

/*
 * Library for the KeyCDN API
 *
 * @author Tobias Moser
 * @version 0.1
 *
 */

use Joomla\CMS\Factory as JFactory;
use RegularLabs\Plugin\System\CacheCleaner\Cache;

class CDN77
{
	public $api = 'https://api.cdn77.com/v2.0/data/purge-all';
	public $login;
	public $passwd;

	public function __construct($login, $passwd)
	{
		$this->login  = $login;
		$this->passwd = $passwd;
	}

	public function purge($id)
	{
		$params = [
			'login'  => $this->login,
			'passwd' => $this->passwd,
			'cdn_id' => $id,
		];

		// start with curl and prepare accordingly
		$ch = curl_init();

		// send query-str within url or in post-fields
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

		// url
		curl_setopt($ch, CURLOPT_URL, $this->api);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		// retrieve headers
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

		// set curl timeout
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);

		// Proxy configuration
		$config = JFactory::getConfig();

		if ($config->get('proxy_enable'))
		{
			curl_setopt($ch, CURLOPT_PROXY, $config->get('proxy_host') . ':' . $config->get('proxy_port'));

			$user = $config->get('proxy_user');

			if ($user)
			{
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user . ':' . $config->get('proxy_pass'));
			}
		}

		// make the request
		$result     = curl_exec($ch);
		$headers    = curl_getinfo($ch);
		$curl_error = curl_error($ch);

		curl_close($ch);

		// get json_output out of result (remove headers)
		$json_output = substr($result, $headers['header_size']);

		// error catching
		if ( ! empty($curl_error) || empty($json_output))
		{
			Cache::writeToLog('cdn77', 'Error: ' . $curl_error . ', Output: ' . $json_output);

			return 'CDN77-Error: ' . $curl_error . ', Output: ' . $json_output;
		}

		return $json_output;
	}
}
