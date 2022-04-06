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

use Joomla\CMS\Factory as JFactory;
use RegularLabs\Plugin\System\CacheCleaner\Cache;

class CloudFlare
{
	public  $api = 'https://api.cloudflare.com/client/v4';
	private $auth_key;
	private $email;
	private $token;

	public function __construct($email, $auth_key, $token = '')
	{
		$this->email    = $email;
		$this->auth_key = $auth_key;
		$this->token    = $token;
	}

	public function purge($zone)
	{
		$result = $this->checkToken();

		if ($result !== true)
		{
			return json_encode($result);
		}

		$zone_id = $this->getZoneId($zone);

		if ( ! $zone_id)
		{
			return json_encode((object) ['messages' => ['Could not find Zone ID for Zone: ' . $zone]]);
		}

		$data = [
			'purge_everything' => true,
		];

		return $this->getResponse(
			'zones/' . $zone_id . '/purge_cache',
			$data,
			'POST'
		);
	}

	private function checkToken()
	{
		if ( ! $this->token)
		{
			return true;
		}

		$response = json_decode($this->getResponse('user/tokens/verify'));

		if (empty($response->success))
		{
			return $response;
		}

		return true;
	}

	private function getResponse($task, $data = [], $type = 'GET')
	{
		$url = $this->api . '/' . $task;

		if ( ! empty($data) && $type == 'GET')
		{
			$url .= '?' . http_build_query($data);
		}

		$headers = [
			'User-Agent: ' . __FILE__,
			'Content-type: application/json',
		];

		if ($this->token)
		{
			$headers[] = 'Authorization: Bearer ' . $this->token;
		}
		else
		{
			$headers[] = 'X-Auth-Email: ' . $this->email;
			$headers[] = 'X-Auth-Key: ' . $this->auth_key;
		}

		// start with curl and prepare accordingly
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if ( ! empty($data) && $type == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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

		$json_output = curl_exec($ch);
		$curl_error  = curl_error($ch);

		curl_close($ch);

		if ( ! empty($curl_error) || empty($json_output))
		{
			Cache::writeToLog('cloudflare', 'Error: ' . $curl_error . ', Output: ' . $json_output);

			return json_encode((object) ['messages' => [$curl_error . ', Output: ' . $json_output]]);
		}

		return $json_output;
	}

	private function getTopDomain($url)
	{
		$url_parts = parse_url($url);

		if ( ! empty($url_parts['host']))
		{
			$url = $url_parts['host'];
		}

		$domain_parts = explode('.', $url);

		while (count($domain_parts) > 2)
		{
			array_shift($domain_parts);

			$hostname = implode('.', $domain_parts);

			if (checkdnsrr($hostname, 'MX'))
			{
				return $hostname;
			}
		}

		return false;
	}

	private function getZoneId($name)
	{
		$response = json_decode($this->getResponse(
			'zones',
			[
				'status' => 'active',
				'name'   => $name,
			]
		));

		if (empty($response->result) && $topdomain = $this->getTopDomain($name))
		{
			return $this->getZoneId($topdomain);
		}

		if (empty($response->result) || empty($response->result[0]->id))
		{
			return false;
		}

		return $response->result[0]->id;
	}
}
