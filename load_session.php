<?php

if (!isset($b_load_session))
{
	$b_load_session = TRUE;
	ini_set("session.gc_maxlifetime", 2592000);
	ini_set("session.cookie_lifetime", 2592000);
	session_start();
$s_at_set = (isset($_SESSION['access_token']) ? "true" : "false");
$s_aat_set = (isset($_SESSION['access_acquire_time']) ? "true" : "false");
error_log('isset($_SESSION[\'access_token\']) && isset($_SESSION[\'access_acquire_time\']): ' . $s_at_set .  $s_aat_set);
}

?>