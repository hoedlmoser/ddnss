<?php

	/*!
		@header 	ddnscommon.php
					Backend implementation of a dynamic DNS web service
					to support user triggered zone updates over HTTP. 
		@copyright 	No licenses, no liabilities, no warranties, no support. 
		@updated 	01-13-08
	*/
	
	/*!
		@function 	khi_ddns_nsupdate
		@param		$commands String of newline separated nsupdate commands.
		@abstract	Executes BIND's nsupdate tool with the supplied commands. 
		@discussion	Note that these pipes will be closed as soon as a 'send' 
					command is encountered. If you need to perform multiple
					record updates then you'll need to call this function 
					more than once. 
	*/
	function khi_ddns_nsupdate($commands)
	{						
		$pipes			= array();
		$descriptorspec	= array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
		
		$nsupdate	= proc_open('nsupdate', $descriptorspec, $pipes);
		
		if ( is_resource($nsupdate) )
		{
			fwrite($pipes[0], $commands);
			fclose($pipes[0]);
			
			$stderr = '';
			
			while ( !feof($pipes[2]) )
			{
				$stderr .= fread($pipes[2], 8192);
			}
			
			fclose($pipes[1]);
			fclose($pipes[2]);
		
			proc_close($nsupdate);
			
			return $stderr;
		}
	}
	
	
	/*!
		@function 	khi_ddns_keygen
		@param		$key String to be mutated into a TSIG key.
		@abstract	Basic example of a keygen callback function. 
		@discussion	Whatever the input, a keygen callback must return
					a Base64 encoded string. 
	*/
	function khi_ddns_keygen($key)
	{
		//PHP's md5 and base64_encode functions appear to be doing something
		//differently than OpenSSL's, so if we want them to match, we have to
		//use OpenSSL's.
		return exec("echo $key | tr [:upper:] [:lower:] | openssl md5 | sed 's/^.*= *//' | openssl base64");
	}
	
	
	/*!
		@function 	khi_ddns_process_data
		@param		$data An associative array containing predefined keys
					for processing.
		@param		$keygen Optional callback function for generating TSIG
					keys from user input.
		@abstract	Processes HTTP request variables and performs a 
					dynamic DNS update based on user input and the relevant
					SOA record for the zone being updated. 
		@discussion	The $data array is expected to contain the following keys:
		
					zone:	The hostname for the A record you wish to update

					key:	The TSIG key 
					
					ip:		The IP address for the A record you wish to update (optional)
					
					If the 'ip' key is not supplied, then the IP the HTTP request
					originated from will be assumed to be correct. Proxy servers
					and NAT routers can make this an unsafe assumption, so if
					you want accuracy, supply a valid IP in this array element. 
					
					If you pass a valid function pointer in the $keygen parameter
					then whatever value is provided in the 'key' element will be 
					mutated by this function. If you do not supply a valid function 
					pointer then it is assumed that the 'key' element is already 
					Base64 encoded and ready to send to nsupdate. 
					
					This function assumes that the authoritative nameserver for
					the zone being updated is configured to allow dynamic updates
					and that it will require a valid TSIG key. It does not support
					"keyless entry" as it were, and if you want to run a wide-open
					server where anyone in the world can add or delete records at 
					will, then you're on your own. 
		
	*/
	function khi_ddns_process_data($data, $keygen = null)
	{	
		$zone		= escapeshellcmd(strtolower(@$data['zone']));
		$key		= escapeshellcmd(@$data['key']);
		$ip			= escapeshellcmd(@$data['ip']);
		
		//If a keygen callback was provided, then pass it the 
		//key for further processing. Otherwise we assume
		//that a Base64 encoded key has been provided ready to go. 
		if ( $keygen != null )
		{
			$key = $keygen($key);
		}
		
		//If no IP was provided, then get the IP the 
		//HTTP request came from and use that
		if ( empty($ip) )
		{
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		
		//Figure out what nameserver we should be talking to for this zone
		//by checking the SOA record. If this server is not configured to 
		//allow dynamic updates on this zone, then nothing's going to happen.
		$nameserver = preg_replace('/\.$/', '', exec("dig -t NS -q " . preg_replace('/^[^\.]+\./', '', $zone, 1) . " +noadditional | awk '/^[^;]/ { print $5; } ' "));
		
		//Check to see if we actually need to bother BIND
		//with any of this. 
		$currentIP	= exec("dig @$nameserver $zone A +short");
		
		if ( $ip != $currentIP )
		{
			//Ideally we'd execute both of these nsupdate sequences in one fell swoop,
			//but PHP just won't keep the pipes open long enough for that to work.
			
			//Remove the old record if it exists
			if ( !empty($currentIP) )
			{
				$err = khi_ddns_nsupdate("server $nameserver\nkey $zone. $key\nprereq yxdomain $zone.\nupdate delete $zone. A\nsend\n");
				
				if ( !empty($err) )
				{						
					dd2res("dnserr", "$err");
					
					return false; 
				}
			}
			
			//Then add the new record as long as another one doesn't already exist
			$err = khi_ddns_nsupdate("server $nameserver\nkey $zone. $key\nprereq nxdomain $zone.\nupdate add $zone. 60 A $ip\nsend\n");
			
			if ( !empty($err) )
			{						
				dd2res("dnserr", "$err");
				
				return false; 
			}			
			
			dd2res("good", "$ip", true);
		}
		else
		{
			dd2res("nochg", "$ip", true);
			
			return false;
		}
		
		return true; 
	}


	function dd2res($result, $message, $success = null) {
		$message = trim(preg_replace('/\s+/', ' ', $message));
		if ( preg_match('/TSIG error with server.*NOTAUTH\(BADKEY\)/', $message) ) {
			$result = 'badauth';
		}
		if ( empty($success) ) {
			header("HTTP/1.0 400 Bad Request");
		}
		echo("$result $message");
	}

?>
