# Bitcoin Node Stat

The idea behind this PHP script is to simply have only one file
serving infomation about a bitcoin node.
The use of inline styles, templates and scripts is done 
intentionally.

The script has no dependencies (only from some cdn for 
jquery, glyphicons)

## Live Demo

http://5.45.104.83/

## Requirements

Should run with every php installation above (including) version 5
(not completely tested)

## Configuration

You should set your "node nick name", country and ips below

To gather data automatically from your bitcoin daemon you need to set
up a cronjob like:

```* * * * * /usr/bin/php /var/www/htdocs/bitcoin-node-stat.php```

Another thing you might want is to make the script available to the 
public on your ip/hostname/whatever

For Apache:

  Depending on your configuration you can rename the script or use a 
  .htaccess file:

```apache
RewriteEngine on
 
RewriteCond %{REQUEST_URI} !^/bitcoind-node-stat.php$
RewriteRule / /bitcoind-node-stat.php [NC,L]
```

The data file needs to be writable by the cron user and readable by
the webserver and php.

If you are using systemd on your server beware of the PrivateTmp 
service option when you use /tmp as storage.

### License

Licensed under MIT License
