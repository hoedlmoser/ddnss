<?php
	
	require_once("ddnscommon.php");
	
	if ( $_SERVER['HTTPS'] === 'on' )
	{
		ProcessData($_REQUEST);
	}
		
	function verify_zone($data)
	{
		if ( preg_match('/.*\.ddns\.mydomain\.com/', $data) )
		{
			return true;
		}
		else
		{
			echo 'You must supply a valid zone to update.';
			
			return false; 
		}
	}
	
	function verify_key($data)
	{
		if ( preg_match('/(\w{2}:){5}\w{2}/', $data) )
		{
			return true;
		}
		else
		{
			echo 'You must supply a valid MAC address e.g. 00:0b:96:d0:23:92';
			
			return false; 
		}
	}
	
	function verify_ip($data)
	{
		if ( preg_match('/(\d{1,3}\.){3}\d{1,3}/', $data) || empty($data) )
		{
			return true;
		}
		else
		{
			echo 'You must either enter a valid IP address or leave that field blank.';
			
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
