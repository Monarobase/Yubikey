<?php
 /*
 * This file is part of Monarobase-Yubikey
 *
 * (c) 2013 Monarobase
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 *
 * @author      Yubico
 * @author      Monarobase
 * @package     Yubikey
 * @copyright   (c) 2013 Monarobase <jonathan@monarobase.net>
 * @link        http://monarobase.net
 */

namespace Monarobase\Yubikey;

use Config;

class Yubikey {

	/**#@+
	 * @access private
	 */

	/**
	 * Yubico client ID
	 * @var string
	 */
	var $_id;

	/**
	 * Yubico client key
	 * @var string
	 */
	var $_key;

	/**
	 * URL part of validation server
	 * @var string
	 */
	var $_url;

	/**
	 * List with URL part of validation servers
	 * @var array
	 */
	var $_url_list;

	/**
	 * index to _url_list
	 * @var int
	 */
	var $_url_index;

	/**
	 * Last query to server
	 * @var string
	 */
	var $_lastquery;

	/**
	 * Response from server
	 * @var string
	 */
	var $_response;

	/**
	 * Flag whether to use https or not.
	 * @var boolean
	 */
	var $_https;

	/**
	 * Flag whether to verify HTTPS server certificates or not.
	 * @var boolean
	 */
	var $_httpsverify;

	/**
	 * Constructor
	 *
	 * Sets up the object
	 * @param    array  $config     The client configuration
	 * @access public
	 */
	public function __construct( $config = array() )
	{
		$this->_id          = (isset($config['id']) && !empty($config['id'])) ? $id : Config::get('yubikey::CLIENT_ID');
		$this->_key         = base64_decode((isset($config['key']) && !empty($config['key'])) ? $key : Config::get('yubikey::SECRET_KEY'));
		$this->_https       = (isset($config['https'])) ? $config['https'] : 0;
		$this->_httpsverify = (isset($config['httpsverify'])) ? $config['httpsverify'] : 1;

		if (!$this->_id)
		{
			throw new \Exception('Check your CLIENT_ID');
		}

		if (!$this->_key)
		{
			throw new \Exception('Check your SECRET_KEY');
		}

		if ($this->_https)
		{
			$this->test_curl_ssl_support();
		}
	}

	/**
	 * Test if Curl support SSL
	 * Will throw exception if curl was not complied with SSL support
	 */
	private function test_curl_ssl_support()
	{
		if (!($version = curl_version()) || !($version['features'] & CURL_VERSION_SSL))
		{
			throw new \Exception('HTTPS requested while Curl not compiled with SSL');
		}
	}

	/**
	 * Specify to use a different URL part for verification.
	 * The default is "api.yubico.com/wsapi/verify".
	 *
	 * @param  string $url  New server URL part to use
	 * @access public
	 */
	public function setURLpart( $url )
	{
		$this->_url = $url;
	}

	/**
	 * Get URL part to use for validation.
	 *
	 * @return string  Server URL part
	 * @access public
	 */
	public function getURLpart()
	{
		if ($this->_url) {
			return $this->_url;
		} else {
			return "api.yubico.com/wsapi/verify";
		}
	}


	/**
	 * Get next URL part from list to use for validation.
	 *
	 * @return mixed string with URL part of false if no more URLs in list
	 * @access public
	 */
	public function getNextURLpart()
	{
		if ($this->_url_list)
		{
			$url_list = $this->_url_list;
		}
		else
		{
			$url_list = Config::get('yubikey::URL_LIST');
		}

		if ($this->_url_index >= count($url_list))
		{
			return false;
		}
		else
		{
			return $url_list[$this->_url_index++];
		}
	}

	/**
	 * Resets index to URL list
	 *
	 * @access public
	 */
	public function URLreset()
	{
		$this->_url_index = 0;
	}

	/**
	 * Add another URLpart.
	 *
	 * @access public
	 */
	public function addURLpart( $URLpart ) 
	{
		$this->_url_list[] = $URLpart;
	}
	
	/**
	 * Return the last query sent to the server, if any.
	 *
	 * @return string  Request to server
	 * @access public
	 */
	public function getLastQuery()
	{
		return $this->_lastquery;
	}

	/**
	 * Return the last data received from the server, if any.
	 *
	 * @return string  Output from server
	 * @access public
	 */
	public function getLastResponse()
	{
		return $this->_response;
	}

	/**
	 * Parse input string into password, yubikey prefix,
	 * ciphertext, and OTP.
	 *
	 * @param  string    Input string to parse
	 * @param  string    Optional delimiter re-class, default is '[:]'
	 * @return array     Keyed array with fields
	 * @access public
	 */
	public function parsePasswordOTP( $str, $delim = '[:]' )
	{
		if (!preg_match("/^((.*)" . $delim . ")?(([cbdefghijklnrtuvCBDEFGHIJKLNRTUV]{0,16})([cbdefghijklnrtuvCBDEFGHIJKLNRTUV]{32}))$/", $str, $matches))
		{
			/* Dvorak? */
			if (!preg_match("/^((.*)" . $delim . ")?(([jxe.uidchtnbpygkJXE.UIDCHTNBPYGK]{0,16})([jxe.uidchtnbpygkJXE.UIDCHTNBPYGK]{32}))$/", $str, $matches))
			{
				return false;
			}
			else
			{
				$ret['otp'] = strtr($matches[3], "jxe.uidchtnbpygk", "cbdefghijklnrtuv");
			}
		}
		else
		{
			$ret['otp'] = $matches[3];
		}

		$ret['password'] = $matches[2];
		$ret['prefix'] = $matches[4];
		$ret['ciphertext'] = $matches[5];

		return $ret;
	}

	/**
	 * Parse parameters from last response
	 *
	 * @return array  parameter array from last response
	 * @access public
	 */
	public function getParameters()
	{
		$params = explode("\n", trim($this->_response));

		foreach ($params as $param)
		{
			list($key, $val) = explode('=', $param, 2);
			$param_array[$key] = $val;
		}

		$param_array['identity'] = substr($param_array['otp'], 0, 12);

		return $param_array;
	}

	/**
	 * Get one parameter from last response
	 *
	 * @return mixed  Exception on error, string otherwise
	 * @access public
	 */
	public function getParameter( $parameter )
	{
		$param_array = $this->getParameters();

		if (!empty($param_array) && array_key_exists($parameter, $param_array))
		{
			return $param_array[$parameter];
		}
		else
		{
			throw new \Exception('UNKNOWN_PARAMETER');
		}
	}

	/**
	 * Verify Yubico OTP against multiple URLs
	 * Protocol specification 2.0 is used to construct validation requests
	 *
	 * @param string $token        Yubico OTP
	 * @param int $use_timestamp   1=>send request with &timestamp=1 to
	 *                             get timestamp and session information
	 *                             in the response
	 * @param boolean $wait_for_all  If true, wait until all
	 *                               servers responds (for debugging)
	 * @param string $sl           Sync level in percentage between 0
	 *                             and 100 or "fast" or "secure".
	 * @param int $timeout         Max number of seconds to wait
	 *                             for responses
	 * @return mixed               Exception on error, true otherwise
	 * @access public
	 */
	public function verify( $token, $use_timestamp = null, $wait_for_all = false, $sl = null, $timeout = null )
	{
		/* Construct parameters string */
		$ret = $this->parsePasswordOTP($token);

		if (!$ret)
		{
			throw new \Exception('Could not parse Yubikey OTP');
		}

		$params = array('id' => $this->_id, 'otp' => $ret['otp'], 'nonce' => md5(uniqid(rand())));

		/* Take care of protocol version 2 parameters */
		if ($use_timestamp)
		{
			$params['timestamp'] = 1;
		}

		if ($sl)
		{
			$params['sl'] = $sl;
		}

		if ($timeout)
		{
			$params['timeout'] = $timeout;
		}

		ksort($params);

		$parameters = '';

		foreach ($params as $p => $v)
		{
			$parameters .= "&" . $p . "=" . $v;
		}

		$parameters = ltrim($parameters, "&");
		
		/* Generate signature. */
		if ($this->_key <> "")
		{
			$signature = base64_encode(hash_hmac('sha1', $parameters, $this->_key, true));
			$signature = preg_replace('/\+/', '%2B', $signature);
			$parameters .= '&h=' . $signature;
		}

		/* Generate and prepare request. */
		$this->_lastquery = null;
		$this->URLreset();
		$mh = curl_multi_init();
		$ch = array();

		while ($URLpart = $this->getNextURLpart()) 
		{
			/* Support https. */
			if ($this->_https)
			{
				$query = "https://";
			}
			else
			{
				$query = "http://";
			}

			$query .= $URLpart . "?" . $parameters;

			if ($this->_lastquery)
			{
				$this->_lastquery .= " ";
			}

			$this->_lastquery .= $query;

			$handle = curl_init($query);
			curl_setopt($handle, CURLOPT_USERAGENT, Config::get('yubikey::USER_AGENT'));
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			if (!$this->_httpsverify)
			{
				curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
			}
			curl_setopt($handle, CURLOPT_FAILONERROR, true);

			/* If timeout is set, we better apply it here as well
				 in case the validation server fails to follow it. 
			*/ 
			if ($timeout)
			{
				curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
			}

			curl_multi_add_handle($mh, $handle);

			$ch[(int)$handle] = $handle;
		}

		/* Execute and read request. */
		$this->_response = null;
		$replay = false;
		$valid = false;

		do
		{
			/* Let curl do its work. */
			while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);

			while ($info = curl_multi_info_read($mh))
			{
				if ($info['result'] == CURLE_OK)
				{

					/* We have a complete response from one server. */

					$str = curl_multi_getcontent($info['handle']);
					$cinfo = curl_getinfo ($info['handle']);
					
					if ($wait_for_all)
					{ # Better debug info
						$this->_response .= 'URL=' . $cinfo['url'] ."\n" . $str . "\n";
					}

					if (preg_match("/status=([a-zA-Z0-9_]+)/", $str, $out))
					{
						$status = $out[1];

						/* 
						 * There are 3 cases.
						 *
						 * 1. OTP or Nonce values doesn't match - ignore
						 * response.
						 *
						 * 2. We have a HMAC key.  If signature is invalid -
						 * ignore response.  Return if status=OK or
						 * status=REPLAYED_OTP.
						 *
						 * 3. Return if status=OK or status=REPLAYED_OTP.
						 */
						if (!preg_match("/otp=".$params['otp']."/", $str) || !preg_match("/nonce=".$params['nonce']."/", $str))
						{
							/* Case 1. Ignore response. */
						}
						elseif ($this->_key <> "")
						{
							/* Case 2. Verify signature first */
							$rows = explode("\r\n", trim($str));
							$response = array();
							while (list($key, $val) = each($rows))
							{
								/* = is also used in BASE64 encoding so we only replace the first = by # which is not used in BASE64 */
								$val = preg_replace('/=/', '#', $val, 1);
								$row = explode("#", $val);
								$response[$row[0]] = $row[1];
							}

							$parameters = array('nonce','otp', 'sessioncounter', 'sessionuse', 'sl', 'status', 't', 'timeout', 'timestamp');
							sort($parameters);
							$check = null;

							foreach ($parameters as $param)
							{
								if (array_key_exists($param, $response))
								{
									if ($check)
									{
										$check = $check . '&';
									}

									$check = $check . $param . '=' . $response[$param];
								}
							}

							$checksignature = base64_encode(hash_hmac('sha1', utf8_encode($check), $this->_key, true));

							if ($response['h'] == $checksignature)
							{
								if ($status == 'REPLAYED_OTP')
								{
									if (!$wait_for_all)
									{
										$this->_response = $str;
									}

									$replay = true;
								}

								if ($status == 'OK')
								{
									if (!$wait_for_all)
									{
										$this->_response = $str;
									}

									$valid = true;
								}
							}
						}
						else
						{
							/* Case 3. We check the status directly */
							if ($status == 'REPLAYED_OTP')
							{
								if (!$wait_for_all)
								{
									$this->_response = $str;
								}

								$replay = true;
							}

							if ($status == 'OK')
							{
								if (!$wait_for_all)
								{
									$this->_response = $str;
								}

								$valid = true;
							}
						}
					}

					if (!$wait_for_all && ($valid || $replay)) 
					{
						/* We have status=OK or status=REPLAYED_OTP, return. */
						foreach ($ch as $h)
						{
							curl_multi_remove_handle($mh, $h);
							curl_close($h);
						}

						curl_multi_close($mh);
						if ($replay)
						{
							throw new \Exception('REPLAYED_OTP');
						}

						if ($valid)
						{
							return true;
						}

						throw new \Exception($status);
					}

					curl_multi_remove_handle($mh, $info['handle']);
					curl_close($info['handle']);
					unset($ch[(int)$info['handle']]);
				}
				curl_multi_select($mh);
			}
		}
		while ($active);

		/* Typically this is only reached for wait_for_all=true or
		 * when the timeout is reached and there is no
		 * OK/REPLAYED_REQUEST answer (think firewall).
		 */

		foreach ($ch as $h)
		{
			curl_multi_remove_handle ($mh, $h);
			curl_close ($h);
		}

		curl_multi_close ($mh);

		if ($replay)
		{
			throw new \Exception('REPLAYED_OTP');
		}

		if ($valid)
		{
			return true;
		}

		throw new \Exception('NO_VALID_ANSWER');
	}

}