<?php
	
	require_once("config.php");
	require_once("ddnscommon.php");
	
	if ( ($_SERVER['HTTPS'] === 'on') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') )
	{
		ProcessData($_REQUEST);
	}
	else
	{
		dd2res("badauth", "You must connect via HTTPS!");
		
		return false;
	}
		
	function verify_zone($data)
	{
		if ( preg_match('/' . $GLOBALS['cfg']['zones'] . '/', $data) )
		{
			return true;
		}
		else
		{
			dd2res("notfqdn", "The host you specified has not an allowed valid fully-qualified domain name.");
			
			return false; 
		}
	}
	
	function verify_key($data)
	{
		if ( preg_match('/([\da-fA-F]{2}:){5}[\da-fA-F]{2}/', $data) )
		{
			return true;
		}
		else
		{
			dd2res("badauth", "You must supply a valid MAC address e.g. 00:0b:96:d0:23:92.");
			
			return false; 
		}
	}
	
	function verify_ip($data)
	{
		if ( preg_match('/((\d{1,3}\.){3}\d{1,3}|([\da-fA-F]{1,4}:){1,7}((:[\da-fA-F]{1,4}){1,6}|[\da-fA-F]{1,4}|:))/', $data) || empty($data) )
		{
			return true;
		}
		else
		{
			dd2res("nohost", "You must either enter a valid IP address or leave that field blank.");
			
			return false;
		}
	}
	
	function ProcessData($data)
	{
		if ( verify_zone(@$data['zone']) && verify_key(@$data['key']) && verify_ip(@$data['ip']) )
		{
			return khi_ddns_process_data($data, 'khi_ddns_keygen');
		}
	}	
	
?>
