# ddnss - How to be your own Dynamic DNS provider

*simple dyndns server*

all credits for the [initial version](https://www.knownhost.com/forums/threads/how-to-be-your-own-dynamic-dns-provider.925/) go to [khiltd](http://khiltd.com/).

You might not have a static IP at home or on the road, but your VPS does, and it can allow the rest of the net to find you wherever you are or however you're connected without having to give companies like DynDNS a single dime. Some basic BIND configuration can allow you to access files at home, VNC into grandma's laptop to fix her printer while she's at Starbucks, run a webserver against your ISP's wishes, and even setup a delegate nameserver to logically bridge physically disparate networks together.

The first thing you have to do is setup BIND. In your named.conf file (or in a separate file included from named.conf), define a new zone to handle your dynamic updates. This is necessary because once you enable dynamic updates, that zone's zone file will be mangled to the point that it will no longer be readable by humans or hosting control panels. A sample configuration might look like this:

    zone "ddns.mydomain.com"
    {
    	type master;
    	file "/var/named/ddns.mydomain.com.db";
    	update-policy { grant *.ddns.mydomain.com. self ddns.mydomain.com. A; };
    };

This is not the ONLY way to configure this, but it makes the most sense for the purposes of this howto. We're essentially telling BIND that we want to allow anyone who has a valid key to update their own A record to point to a new IP. What makes a valid key? That's the next step.

There are several ways to make what BIND refers to as a TSIG key, but it's basically just an MD5'ed and Base64 encoded string we've told it to look out for. I like to base my TSIG keys on the MAC address of the client machine's primary NIC, so I generate my keys from the shell thusly:

    echo 00:0b:92:d0:27:92 | openssl md5 | openssl base64

That gives us

    YmM1YWQ0ZTQyNjhjZTRhMjE2ZTZmZDMwNDY1ZjgyMTMK

in return, so now we just have to tell BIND about it.

Back in your named.conf file (or another file included from named.conf) define a key as follows:

    key peppep.ddns.mydomain.com.
    {
    	algorithm hmac-md5;
    	secret "YmM1YWQ0ZTQyNjhjZTRhMjE2ZTZmZDMwNDY1ZjgyMTMK";
    };

The important parts of this declaration are the key name and the "secret." The "secret" is obviously the key we just generated through openssl above, but they key name needs a little explanation.

Back when we specified our update-policy, we told BIND to grant update permissions to a certain zone so long as the name of the user's key matched the zone being updated. In simpler terms, the name you give your key MUST match the pattern specified in the update-policy, in this case *.ddns.mydomain.com. So now, peppep.ddns.mydomain.com can alter its own A record all it likes so long as he provides the right key, but he will not be able to touch nana.ddns.mydomain.com's records no matter what. This is as it should be.

The last thing you need to do before you're up and running is to alter the permissions on /var/named so that named has write access to it. I'm not certain if everybody needs to do this themselves, but I can verify that cPanel installations do not grant named write access by default. If it can't write to its journal files, it can't process dynamic updates; simple as that.

One all that is done, either restart BIND or issue an rndc reconfig command (assuming you've setup your RNDC key of course [which you should by the way]). Now BIND should be ready to accept dynamic updates to ddns.mydomain.com.

But how do we issue these updates? Traditionally, one uses the nsupdate command from the shell, but that's probably a bit over Peppep and Nana's head. It's also difficult to use in practice, because most routers which support DDNS services are only capable of making HTTP requests and provide no means of issuing arbitrary shell commands utilizing tools which are not a part of their firmware. We need a web service to bridge the gap.

I've implemented such a service in PHP, which should be trivial to port to any other language you might prefer. To use it, you simply pass the khi_ddns_process_data function an associative array (such as the $_GET or $_POST array collected from an HTTP request) which contains:

1. The zone to update
2. The TSIG key to use
3. The IP to set

It will optionally accept a mutator callback for the TSIG key, so that your users will never know what their actual keys are on the server. A minor bump in security, but a major bump in typability since Base64 encoded strings aren't exactly memorable.

Place ddnscommon.php within your document root and all the necessary parts are in place.

An example of an extremely simple pseudo-form which utilizes this code is in ddns.php (note that I'm assuming SSL is enabled for the domain in question; a good idea unless you want other people sniffing out your keys and abusing your services).

You would tell your router to trigger that by providing a simple URL containing all of the requisite information, e.g.:

    https://www.mydomain.com/ddns.php?zone=nana.ddns.mydomain.com&key=00:0b:96:d0:23:92&ip=192.168.1.1

And from that point forward, nana.ddns.mydomain.com will resolve to 192.168.1.1. When your ISP gives you a new IP, your router will just update it again.

Alternatively, if your router doesn't support custom DDNS services, you can setup a cron job to request it periodically through curl, or build an actual HTML form where users can fill out the fields themselves.
