<?php

require_once(dirname(__FILE__) . "/load_session.php");

$api_key = parse_ini_file("secret.ini");
$s_api_id = $api_key['client_id'];
$s_api_secret = $api_key['client_secret'];
$as_scopes = ['user-library-read', 'playlist-read-collaborative', 'playlist-read-private', 'playlist-modify-private', 'streaming', 'app-remote-control', 'user-read-currently-playing'];

$b_loggedin = FALSE;
$s_loginerr = "";

error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
$s_at_set = (isset($_SESSION['access_token']) ? "true" : "false");
$s_aat_set = (isset($_SESSION['access_acquire_time']) ? "true" : "false");
error_log('isset($_SESSION[\'access_token\']) && isset($_SESSION[\'access_acquire_time\']): ' . $s_at_set .  $s_aat_set);
if (isset($_SESSION['access_token']) && isset($_SESSION['access_acquire_time']))
{
	$i_current = time();
	$i_expires_in = $_SESSION['expires_in'] - ($i_current - $_SESSION['access_acquire_time']);
	error_log('$i_expires_in (' . $i_expires_in . ') > 60: ' . (($i_expires_in > 60) ? "true" : "false"));
	if ($i_expires_in > 60)
	{
		exit();
		error_log("<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<");
	}

	$s_auth = base64_encode("${s_api_id}:${s_api_secret}");
	$options = array(
	    'http' => array( // use key 'http' even if you send the request to https://...
	        'header'  => "Authorization: Basic $s_auth\r\nContent-type: application/x-www-form-urlencoded\r\n",
	        'method'  => 'POST',
	        'ignore_errors' => TRUE,
	        'content' => http_build_query(array(
				'grant_type' => 'refresh_token',
				'refresh_token' => $_SESSION['refresh_token']
			)),
	    )
	);
	error_log('options: ' . print_r($options, TRUE));
	$context  = stream_context_create($options);
	$sb_result = file_get_contents('https://accounts.spotify.com/api/token', FALSE, $context);
	error_log('sb_result: ' . $sb_result);
	if ($sb_result !== FALSE && strstr($sb_result, 'access_token') && strstr($sb_result, 'expires_in')) {
		$a_result = json_decode($sb_result, TRUE);
		error_log('a_result: ' . print_r($a_result, TRUE));
		error_log('isset($a_result[\'access_token\']): ' . (isset($a_result['access_token']) ? 'true' : 'false'));
		if (isset($a_result['access_token']))
		{
			$_SESSION['access_token']        = $a_result['access_token'];
			$_SESSION['expires_in']          = $a_result['expires_in'];
			error_log("new access token: ${_SESSION['access_token']}");
			echo "access_token=${_SESSION['access_token']}";
		}
	} else {
		echo $sb_result;
	}
	error_log("<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<");
}

?>