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

/**
 * Library for the KeyCDN API
 *
 * @author  Sven Baumgartner
 * @version 0.3
 */
class KeyCDN
{
	/**
	 * @var string
	 */
	private $apiKey;

	/**
	 * @var string
	 */
	private $endpoint;

	/**
	 * @param string      $apiKey
	 * @param string|null $endpoint
	 */
	public function __construct($apiKey, $endpoint = null)
	{
		if ($endpoint === null)
		{
			$endpoint = 'https://api.keycdn.com';
		}

		$this->setApiKey($apiKey);
		$this->setEndpoint($endpoint);
	}

	/**
	 * @param string $selectedCall
	 * @param array  $params
	 *
	 * @return string
	 * @throws Exception
	 */
	public function delete($selectedCall, array $params = [])
	{
		return $this->execute($selectedCall, 'DELETE', $params);
	}

	/**
	 * @param string $selectedCall
	 * @param array  $params
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get($selectedCall, array $params = [])
	{
		return $this->execute($selectedCall, 'GET', $params);
	}

	/**
	 * @return string
	 */
	public function getApiKey()
	{
		return $this->apiKey;
	}

	/**
	 * @param string $apiKey
	 *
	 * @return $this
	 */
	public function setApiKey($apiKey)
	{
		$this->apiKey = (string) $apiKey;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEndpoint()
	{
		return $this->endpoint;
	}

	/**
	 * @param string $endpoint
	 *
	 * @return $this
	 */
	public function setEndpoint($endpoint)
	{
		$this->endpoint = (string) $endpoint;

		return $this;
	}

	/**
	 * @param string $selectedCall
	 * @param array  $params
	 *
	 * @return string
	 * @throws Exception
	 */
	public function post($selectedCall, array $params = [])
	{
		return $this->execute($selectedCall, 'POST', $params);
	}

	/**
	 * @param string $selectedCall
	 * @param array  $params
	 *
	 * @return string
	 * @throws Exception
	 */
	public function put($selectedCall, array $params = [])
	{
		return $this->execute($selectedCall, 'PUT', $params);
	}

	/**
	 * @param string $selectedCall
	 * @param        $methodType
	 * @param array  $params
	 *
	 * @return string
	 * @throws Exception
	 */
	private function execute($selectedCall, $methodType, array $params)
	{
		$endpoint = rtrim($this->endpoint, '/') . '/' . ltrim($selectedCall, '/');

		// start with curl and prepare accordingly
		$ch = curl_init();

		// create basic auth information
		curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':');

		// return transfer as string
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		// set curl timeout
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);

		// retrieve headers
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

		// set request type
		if ( ! in_array($methodType, ['POST', 'GET']))
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methodType);
		}

		$queryStr = http_build_query($params);
		// send query-str within url or in post-fields
		if (in_array($methodType, ['POST', 'PUT', 'DELETE']))
		{
			$reqUri = $endpoint;
			curl_setopt($ch, CURLOPT_POSTFIELDS, $queryStr);
		}
		else
		{
			$reqUri = $endpoint . '?' . $queryStr;
		}

		// url
		curl_setopt($ch, CURLOPT_URL, $reqUri);

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
			Cache::writeToLog('keycdn', 'Error: ' . $curl_error . ', Output: ' . $json_output);

			return 'CURL ERROR: ' . $curl_error . ', Output: ' . $json_output;
//			throw new Exception("KeyCDN-Error: {$curl_error}, Output: {$json_output}");
		}

		return $json_output;
	}
}
