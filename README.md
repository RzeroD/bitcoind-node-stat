# bitcoind Node Stat

bitcoind-node-stat is a simple php script for displaying information
about a bitcoin node via http.

The idea behind the script is to simply have one file managing and
serving the data gathered from the bitcoin daemon. The use of html code
with inline stylesheets and scripts is done intentionally, just to
keep it very simple.

## Installation

### Requirements

* Web Server able to serve PHP
* PHP Version 5 or higher (Haven't tested with 4, but should work)
* Cron Service (p.e. cronie)

Originally this script was intended to run on linux with apache and
cronie. Nevertheless there should be a way to use it on windows.

The PHP script itself has no dependencies.

The HTML generated refers to several content delivery networks: For
jQuery, glyphicons and fonts. Cross-domain ajax requests are performed 
for reverse dns lookups and GeoIP. 

### Configuration

The configuration file 'config.php' is included at the beginning of the
script. It is therefore possible to just replace the include command 
with the configuration options.

Configuration options are:

```$config["node-name"] = "My Server";``` 

The (nick-)name of the server

```$config["node-country"] = "gb";``` 

Country code of the server

```$config["node-ipv4"] = "12.34.56.78";```

IPv4 Address of the server

```$config["node-ipv4"] = "2a02:DEAD:BEEF:8008::1";```

IPv6 Address of the server

```$config["rpc-command"] = "/usr/local/bin/bitcoin-cli";```

Bitcoin rpc client command path. Can include other commands: p.e.
```sudo -u bitcoin /usr/local/bin/bitcoin-cli```

```$config["data-file"] = "/tmp/bitcoind-node-stat.json";```

Temporary file to store recent node information. The file must be
writable by the cronjob and readable by the webserver.

### Cronjob

Since the website is only reading data from a simple json file,
the script has to grab the data from the bitcoin daemon. This is
usually done with a cronjob.

The cronjob could look like this:

```* * * * * /usr/bin/php /var/www/htdocs/bitcoin-node-stat.php```

It will update the data once a minute.

## Demonstration

I wrote this script for my own bitcoin node, which I recently set up
because my vps was doing almost nothing. Click on the link to see a
demonstration of how it looks like.

http://5.45.104.83/

## License

Licensed under MIT License.

Parts of the software are written or provided by third partys. I don't
claim rights on their products and a notice is included on the rendered
website.

Don't remove the references when you are using the system.


