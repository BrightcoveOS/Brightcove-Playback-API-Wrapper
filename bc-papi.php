<?php

/**
 * Brightcove PHP Playback API Wrapper 1.0.0 (2-1-2017)
 *
 * AUTHOR:
 *	 Theresa Newman <theresa.webdev@gmail.com>
 * Derived from deprecated PHP Media API Wrapper from Brightcove open source library
 * Brightcove Playback API https://docs.brightcove.com/en/video-cloud/playback-api/
 * Handles 4 GET methods: get video by id, get video by reference id, get playlist by id,get playlist by reference id
 * get video by id - pass type=videos and video id to find method
 * get video by reference id - pass type=videos and reference id e.g ref:12345 to find method
 * get playlist by id - pass type=playlists and playlist id  to find method
 * playlist by reference id - pass type=playlists and reference id e.g ref:12345 to find method
 *
 *
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software 
 * and associated documentation files (the "Software"), to deal in the Software without restriction, 
 * including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, 
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, 
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included 
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT 
 *NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. 
 IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH 
 THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 *
**/

class BCPAPI
{
	const ERROR_API_ERROR = 1;
	const ERROR_API_ERROR_UNAUTHORIZED = 401;
	const ERROR_API_ERROR_FORBIDDEN = 403;
	const ERROR_API_ERROR_NOTFOUND = 404;
	const ERROR_API_ERROR_INVALID_METHOD = 405;
	const ERROR_API_ERROR_INTERNAL_SERVER_ERROR = 500;
	const ERROR_API_ERROR_NOT_IMPLEMENTED = 501;
	const ERROR_API_ERROR_BAD_GATEWAY = 502;
	const ERROR_API_ERROR_SERVICE_UNAVAILABLE = 503;
	const ERROR_API_ERROR_GATEWAY_TIMEOUT = 504;		
	const ERROR_INVALID_METHOD = 2;
	const ERROR_INVALID_PROPERTY = 3;
	const ERROR_INVALID_TYPE = 4;


	private $api_calls = 0;
	private $timeout_attempts = 100;
	private $timeout_current = 0;
	private $timeout_delay = 1;
	private $timeout_retry = FALSE;
	private $policy_key = NULL;
	private $bc_account = NULL;
	private $url_read = 'https://edge.api.brightcove.com/playback/v1/accounts/';
	private $valid_types = array(
		'playlists',
		'videos'
	);

	/**
	 * The constructor for the BCPAPI class.
	 * @access Public
	 * @param string [$policy_key] The policy token for Brightcove player
	 * @param string [$bc_account] The Brightcove account id
	 */
	public function __construct($policy_key = NULL,$bc_account=NULL)
	{
		$this->policy_key = $policy_key;
		$this->bc_account = $bc_account;
	}

	/**
	 * Sets a property of the BCPAPI class.
	 * @access Public
	 * @param string [$key] The property to set
	 * @param mixed [$value] The new value for the property
	 * @return mixed The new value of the property
	 */
	public function __set($key, $value)
	{
		if(isset($this->$key) || is_null($this->$key))
		{
			$this->$key = $value;
		} else {
			throw new BCPAPIInvalidProperty($this, self::ERROR_INVALID_PROPERTY);
		}
	}

	/**
	 * Retrieves a property of the BCPAPI class.
	 * @access Public
	 * @param string [$key] The property to retrieve
	 * @return mixed The value of the property
	 */
	public function __get($key)
	{
		if(isset($this->$key) || is_null($this->$key))
		{
			return $this->$key;
		} else {
			throw new BCPAPIInvalidProperty($this, self::ERROR_INVALID_PROPERTY);
		}
	}

	/**
	 * Formats the request for any API 'Find' methods and retrieves the data.
	 * @access Public
	 * @param string [$call] The requested API method
	 * @param mixed [$param] A single value passed in the find method
	 * @return object An object containing all API return data
	 */
	public function find($call, $type = 'videos', $id = NULL)
	{
		$call = strtolower(preg_replace('/(?:find|_)+/i', '', $call));
		switch($call)
		{
			case 'videobyid':
				$method = 'find_video_by_id';
				$default = 'video_id';
				break;
			case 'videobyreferenceid':
				$method = 'find_video_by_reference_id';
				$default = 'reference_id';
				break;
			case 'playlistbyid':
				$method = 'find_playlist_by_id';
				$default = 'playlist_id';
				break;
			case 'playlistbyreferenceid':
				$method = 'find_playlist_by_reference_id';
				$default = 'reference_id';
				break;
			default:
				throw new BCPAPIInvalidMethod($this, self::ERROR_INVALID_METHOD);
				break;
		}
		$this->validType($type);
		$url = $this->url_read . $this->bc_account . '/' . $type . '/'. $id;

		$this->timeout_current = 0;

		return $this->getData($url);
	}


	/**
	 * Retrieves API data from provided URL.
	 * @access Private
	 * @since 0.1.0
	 * @param string [$url] The complete API request URL
	 * @return object An object containing all API return data
	 */
	private function getData($url)
	{
		if(class_exists('BCPAPICache'))
		{
			$cache = BCPAPICache::get($url);

			if($cache !== FALSE)
			{
				$response_object = json_decode($cache);

				if(isset($response_object->items))
				{
					$data = $response_object->items;
				} else {
					$data = $response_object;
				}

				return $data;
			}
		}

		$this->timeout_current++;

		if(!isset($this->policy_key))
		{
			throw new BCPAPITokenError($this, self::ERROR_READ_TOKEN_NOT_PROVIDED);
		}
		$response = $this->curlRequest($url);

		if($response && $response != 'NULL')
		{
			$response_object = json_decode(preg_replace('/[[:cntrl:]]/u', '', $response));;

			if(isset($response_object->error))
			{
				if($this->timeout_retry && $response_object->code == 103 && $this->timeout_current < $this->timeout_attempts)
				{
					if($this->timeout_delay > 0)
					{
						if($this->timeout_delay < 1)
						{
							usleep($this->timeout_delay * 1000000);
						} else {
							sleep($this->timeout_delay);
						}
					}

					return $this->getData($url);
				} else {
					throw new BCPAPIApiError($this, self::ERROR_API_ERROR, $response_object);
				}
			} else {
				if(class_exists('BCPAPICache'))
				{
					$cache = BCPAPICache::set($url, $response_object);
				}

				if(isset($response_object->items))
				{
					$data = $response_object->items;
				} else {
					$data = $response_object;
				}

				return $data;
			}
		} else {
			throw new BCPAPIApiError($this, self::ERROR_API_ERROR);
		}
	}

	/**
	 * Makes a cURL request.
	 * @access Private
	 * @since 1.0.0
	 * @param mixed [$request] URL to fetch 
	 * @return void
	 */
	private function curlRequest($request)
	{
	$account_policy_key = 'Accept: application/json;pk='.$this->policy_key;	
	$ch = curl_init($request);
	curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST  => "GET",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_HTTPHEADER     => array(
            $account_policy_key
        )
    ));
	
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	

	// Check for errors
	if ($response === FALSE) {
		error_log("Brightcove Playback API error: ".$response);
		error_log( "Brightcove Playback API error #".curl_errno($ch).":". curl_error($ch) );
		die(curl_error($ch));
	}

	if (empty($info['http_code'])) {
		error_log("Brightcove Playback API error: ".$response);
		error_log( "Brightcove Playback API error #".curl_errno($ch).":". curl_error($ch) );
		die(curl_error($ch));		
    } else {
        if ($info['http_code'] == 200) {
			return $response;
		} else if ($info['http_code'] == 401) {
			$response = NULL;
			throw new BCPAPIApiErrorUnauthorized($this, self::ERROR_API_ERROR_UNAUTHORIZED);
		} else if ($info['http_code'] == 403) {
			$response = NULL;
			throw new BCPAPIApiErrorForbidden($this, self::ERROR_API_ERROR_FORBIDDEN);
		} else if ($info['http_code'] == 404) {
			$response = NULL;
			throw new BCPAPIApiErrorNotFound($this, self::ERROR_API_ERROR_NOTFOUND);
		} else if ($info['http_code'] == 405) {
			$response = NULL;
			throw new BCPAPIApiErrorInvalidMethod($this, self::ERROR_API_ERROR_INVALID_METHOD);
		} else if ($info['http_code'] == 500) {
			$response = NULL;
			throw new BCPAPIApiErrorInternalServerError($this, self::ERROR_API_ERROR_INTERNAL_SERVER_ERROR);
		} else if ($info['http_code'] == 501) {
			$response = NULL;
			throw new BCPAPIApiErrorNotImplemented($this, self::ERROR_API_ERROR_NOT_IMPLEMENTED);
		} else if ($info['http_code'] == 502) {
			$response = NULL;
			throw new BCPAPIApiErrorBadGateway($this, self::ERROR_API_ERROR_BAD_GATEWAY);
		} else if ($info['http_code'] == 503) {
			$response = NULL;
			throw new BCPAPIApiErrorServiceUnavailable($this, self::ERROR_API_ERROR_SERVICE_UNAVAILABLE);
		} else if ($info['http_code'] == 504) {
			$response = NULL;
			throw new BCPAPIApiErrorGatewayTimeout($this, self::ERROR_API_ERROR_GATEWAY_TIMEOUT);
		} else {
			$response = NULL;
			throw new BCPAPIApiError($this, self::ERROR_API_ERROR);			
		}
    }		
	curl_close($ch);
	
	}

	/**
	 * Determines if provided type is valid
	 * @access Private
	 * @since 1.0.0
	 * @param string [$type] The type
	 */
	private function validType($type)
	{
		if(!in_array(strtolower($type), $this->valid_types))
		{
			throw new BCPAPIInvalidType($this, self::ERROR_INVALID_TYPE);
		} else {
			return TRUE;
		}
	}

	/**
	 * Converts an error code into a textual representation.
	 * @access public
	 * @since 1.0.0
	 * @param int [$error_code] The code number of an error
	 * @return string The error text
	 * https://docs.brightcove.com/en/video-cloud/playback-api/references/error-reference.html
	 */
	public function getErrorAsString($error_code)
	{
		switch($error_code)
		{
			case self::ERROR_API_ERROR:
				return 'API error';
				break;
			case self::ERROR_API_ERROR_UNAUTHORIZED:
				return 'API error - Unauthorized';
				break;		
			case self::ERROR_API_ERROR_FORBIDDEN:
				return 'API error - Forbidden';
				break;		
			case self::ERROR_API_ERROR_NOTFOUND:
				return 'API error - Not Found';
				break;							
			case self::ERROR_API_ERROR_INVALID_METHOD:
				return 'API error - Invalid Method';
				break;
			case self::ERROR_API_ERROR_INTERNAL_SERVER_ERROR:
				return 'API error - Internal Server Error';
				break;	
			case self::ERROR_API_ERROR_NOT_IMPLEMENTED:
				return 'API error - Feature Not Supported';
				break;	
			case self::ERROR_API_ERROR_BAD_GATEWAY:
				return 'API error - Bad Gateway';
				break;	
			case self::ERROR_API_ERROR_SERVICE_UNAVAILABLE:
				return 'API error - Service Unavailable';
				break;	
			case self::ERROR_API_ERROR_GATEWAY_TIMEOUT:
				return 'API error - Gateway Timeout';
				break;					
			case self::ERROR_INVALID_PROPERTY:
				return 'Requested property not found';
				break;
			case self::ERROR_INVALID_TYPE:
				return 'Type not specified';
				break;
			case self::ERROR_READ_TOKEN_NOT_PROVIDED:
				return 'Read token not provided';
				break;
		}
	}
}

class BCPAPIException extends Exception
{
	/**
	 * The constructor for the BCPAPIException class
	 * @access Public
	 * @since 1.0.0
	 * @param object [$obj] A pointer to the BCPAPI class
	 * @param int [$error_code] The error code
	 * @param string [$raw_error] Any additional error information
	 */
	public function __construct(BCPAPI $obj, $error_code, $raw_error = NULL)
	{
		$error = $obj->getErrorAsString($error_code);

		if(isset($raw_error))
		{
			if(isset($raw_error->error) && isset($raw_error->error->message) && isset($raw_error->error->code))
			{
				$raw_error = $raw_error->error;
			}
			
			$error .= "'\n";
			$error .= (isset($raw_error->message) && isset($raw_error->code)) ? '== ' . $raw_error->message . ' (' . $raw_error->code . ') ==' . "\n" : '';
			$error .= isset($raw_error->errors[0]) ? '== ' . $raw_error->errors[0]->error . ' (' . $raw_error->errors[0]->code . ') ==' . "\n" : '';
		}

		parent::__construct($error, $error_code);
	}
}
	
class BCPAPIApiError extends BCPAPIException{}
class BCPAPIApiErrorUnauthorized extends BCPAPIException{}
class BCPAPIApiErrorForbidden extends BCPAPIException{}
class BCPAPIApiErrorNotFound extends BCPAPIException{}
class BCPAPIApiErrorInvalidMethod extends BCPAPIException{}
class BCPAPIApiErrorInternalServerError extends BCPAPIException{}
class BCPAPIApiErrorNotImplemented extends BCPAPIException{}
class BCPAPIApiErrorBadGateway extends BCPAPIException{}
class BCPAPIApiErrorServiceUnavailable extends BCPAPIException{}
class BCPAPIApiErrorGatewayTimeout extends BCPAPIException{}
class BCPAPIInvalidProperty extends BCPAPIException{}
class BCPAPIInvalidType extends BCPAPIException{}
class BCPAPITokenError extends BCPAPIException{}

?>