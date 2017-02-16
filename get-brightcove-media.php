<?php
// Include the BCPAPI SDK
require_once('bc-papi.php');
require_once('bc-papi-cache.php');
// Instantiate the class, passing it Policy Token from a player in your account and the account number
//sample brightcove values inserted below
$bc = new BCPAPI(
    'BCpkADawqM0NK0Rq8n6sEQyWykemrqeSmIQqqVt3XBrdpl8TYlvqN3hwKphBJRnkPgx6WAbozCW_VgTOBCNf1AQRh8KnmXSXfveQalRc5-pyNlSod5XzP99If2U',
	'57838016001'
);
if (class_exists('BCPAPICache')) {
	$bc_cache = new BCPAPICache('file', 600, '/tmp/bc-cache/', '.cache');
	// Using Memcached
	//$bc_cache = new BCPAPICache('memcached', 600, 'memcached-location', NULL, 11211);
}
?>