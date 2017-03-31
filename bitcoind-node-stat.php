<?php
/**
 * Copyright (C) 2017 Jan Peter Koenig
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE X CONSORTIUM BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 */

/**
 * The idea behind this PHP script is to simply have only one file
 * serving infomation about a bitcoin node.
 * The use of inline styles, templates and scripts is done 
 * intentionally.
 * 
 * The script has no dependencies (only from some cdn for 
 * jquery, glyphicons)
 * 
 * Should run with every php installation above (including) version 5
 * (not completely tested)
 * 
 * You should set your "node nick name", country and ips below
 * 
 * To gather data automatically from your bitcoin daemon you need to set
 * up a cronjob like:
 * 
 * "* * * * * /usr/bin/php /var/www/htdocs/bitcoind-node-stat.php"
 * 
 * Another thing you might want is to make the script available to the 
 * public on your ip/hostname/whatever
 * 
 * For Apache:
 * 
 *   Depending on your configuration you can rename the script or use a 
 *   .htaccess file:
 * 
 *   --
 *   RewriteEngine on
 *
 *   RewriteCond %{REQUEST_URI} !^/bitcoind-node-stat.php$
 *   RewriteRule / /bitcoind-node-stat.php [NC,L]
 *   --
 * 
 * The data file needs to be writable by the cron user and readable by
 * the webserver and php.
 * 
 * If you are using systemd on your server beware of the PrivateTmp 
 * service option when you use /tmp as storage.
 */

// configuration

// node settings
$config["node-name"] = "Your Bitcoin Node";
$config["node-country"] = "ck";
$config["node-ipv4"] = "123.45.67.89";
$config["node-ipv6"] = "2a02:dead:beed:affe::1";

// base command for bitcoin-cli
$config["rpc-command"] = "sudo -u bitcoin bitcoin-cli";

// data file
$config["data-file"] = "/tmp/bitcoin-node-stat.json";

// CRONJOB

function exec_command($command, $json = true)
{
	global $config;
	
	ob_start();
	system($config["rpc-command"] . " " . $command);
	$data = ob_get_clean();
	
	if ($json)
		return json_decode($data, true);
	else
		return $data;
}

if (php_sapi_name() == "cli")
{

	$dataToWrite = [
		"peerinfo" => exec_command("getpeerinfo"),
		"connectioncount" => exec_command("getconnectioncount", false),
		"networkinfo" => exec_command("getnetworkinfo"),
		"info" => exec_command("getinfo"),
		"time" => time()
	];
	
	file_put_contents($config["data-file"], json_encode($dataToWrite));
	
	exit(0);
}

// WEBSITE

if (!file_exists($config["data-file"]))
	die("No data file, Is the cronjob running?");
	
$data = json_decode(file_get_contents($config["data-file"]), true);

// network height
$heights = [];

$peers = [];

function format_bytes($size, $precision = 2)
{
    $base = log($size, 1024);
    $suffixes = array('b', 'kB', 'Mb', 'Gb', 'Tb');   

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = DateTime::createFromFormat("U", $datetime);

    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function ping_class($ping) {
	if ($ping < 40) return "text-ok";
	else if ($ping < 1000) return "text-warning";
	else return "text-danger";
}

foreach ($data["peerinfo"] as $peer) 
{
	$heights[] = $peer["synced_headers"];
	
	$addr = "";
	if (strpos($peer["addr"], "[") !== false) {
		$addr = str_replace([ "[", "]" ], [ "", "" ], $peer["addr"]); 
		$plainaddr = substr($peer["addr"], 1, strpos($peer["addr"], "]") - 1);
	} else {
		$addr = str_replace(":8333", "", $peer["addr"]);
		$plainaddr = substr($peer["addr"], 0, strpos($peer["addr"], ":"));
	}
	
	$peers[] = [
		"addr" => $addr,
		"plainaddr" => $plainaddr,
		"activity" => time_elapsed_string(min([ $peer["lastsend"], $peer["lastrecv"] ])),
		"conntime" => time_elapsed_string($peer["conntime"]),
		"ping" => $peer["pingtime"] * 1000,
		"version" => $peer["subver"],
		"banscore" => $peer["banscore"],
		"tx" => $peer["bytessent"],
		"rx" => $peer["bytesrecv"],
		"inbound" => $peer["inbound"],
		"height" => $peer["synced_headers"]
	];
}

// template following

$template = [
	"node-ipv4" => $config["node-ipv4"],
	"node-ipv6" => $config["node-ipv6"],
	"node-name" => $config["node-name"],
	"node-country" => $config["node-country"],
	"node-updated" => time_elapsed_string($data["time"]),
	"node-version" => $data["networkinfo"]["subversion"] . " (" . $data["info"]["version"] . ")",
	"node-height" => $data["info"]["blocks"],
	"network-height" => max($heights),
	"network-difficulty" => $data["info"]["difficulty"],
	"network-connections" => $data["connectioncount"],
	"peers" => $peers
];

?>
<!DOCTYPE html>
<html>
	<head>
		<title>bitcoind - <?php echo $template["node-name"]; ?> Node Statistics</title>
		<meta charset="utf-8">
		<link href="https://fonts.googleapis.com/css?family=Titillium+Web" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/2.8.0/css/flag-icon.css" rel="stylesheet">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		
		<style>
* {
	font-family: "Titillium Web", sans-serif;
	margin: 0px;
	padding: 0px;
}

.header {
	background-color: #181F58;
	height: 80px;
	text-align: center;
}

div.centered {
	display: inline-block;
	width: 1024px;
	text-align: left;
	padding: 0px;
}

.header div.title {
	color: #FFF;
	padding: 18px;
	font-size: 28px;	
}

.content {
	text-align: center;
}

.wrapper {
	background-color: #394286;
}

.node-info {
	color: #FFF;
	text-align: center;
	padding-bottom: 60px !important;
}

.node-info .left-info {
	width: 500px;
	display: inline-block;
	vertical-align: top;
}

.node-info .left-info .node-details {
	margin-top: 40px;
	margin-bottom: 20px;
	
}
.node-info .left-info .node-details div {
	display: inline-block;
}

.node-info .left-info .node-details div:first-child {
	width: 28px;
	height: 20px;
	vertical-align: top;
	margin-top: 7px;
	margin-right: 10px;
}

.node-info .left-info .node-details div:last-child {
	font-size: 22px;
}

.node-info .left-info .indent {
	margin-left: 43px;
	margin-top: 10px;
}

.node-info .left-info .indent span:first-child {
	display: inline-block;
	width: 200px;
}

.node-info .right-info div {
	margin-top: 10px;
}

.node-info .right-info div span:first-child {
	display: inline-block;
	width: 200px;
}

.node-info .right-info .network-connections {
	margin-top: 92px;
}

.node-info .right-info {
	width: 500px;
	vertical-align: top;
	display: inline-block;
}

.peer-info {
	margin-top: 30px;
}

.peer-info .title {
	font-size: 24px;
	margin-bottom: 20px;
}

.peer-info .connections {
	position: relative;
}

.peer-info .connections table {
	width: 100%;
}

.flag-icon {
	border: 1px solid #eeeeee;
}

.glyphicon.inbound {
	color: #1B811B;
}

.glyphicon.outbound {
	color: #EC161B;
}

.footer {
	text-align: center;
	margin-top: 80px;
}

.footer .centered {
	margin-left: -20px;
	margin-right: -20px;
	border-top: 1px solid #ccc;
	padding: 20px;
}

.footer .centered div {
	margin-top: 5px;
}

.text-danger { color: #B4090D; }
.text-warning { color: #B67609; }
.text-ok { color: #005800; }

		</style>
	</head>
	<body>
		<div class="header">
			<div class="centered">
				<div class="title">bitcoin - Node Statistics</div>
			</div>
		</div>
		<div class="content">
			<div class="wrapper">
				<div class="node-info centered">
					<div class="left-info">
						<!-- info about the node -->
						<div class="node-details">
							<div class="flag-icon flag-icon-<?php echo $template["node-country"]; ?>"></div>
							<div><?php echo $template["node-name"]; ?></div>
						</div>
						<div class="node-ipv4 indent">
							<span>IPv4:</span>
							<span><?php echo $template["node-ipv4"] != "" ? $template["node-ipv4"] : "N/A"; ?></span>	
						</div>
						<div class="node-ipv6 indent">
							<span>IPv6:</span>
							<span><?php echo $template["node-ipv6"] != "" ? $template["node-ipv6"] : "N/A"; ?></span>
						</div>
						<div class="node-updated indent">
							<span>Last Status Update:</span>
							<span><?php echo $template["node-updated"]; ?></span>
						</div>
						<div class="node-version indent">
							<span>Version:</span>
							<span><?php echo $template["node-version"]; ?></span>
						</div>
					</div>
					<div class="right-info">
						<!-- info about the network -->
						<div class="network-connections">
							<span>Connections:</span>
							<span><?php echo $template["network-connections"]; ?></span>
						</div>
						<div class="network-height">
							<span>Network Height:</span>
							<span><?php echo $template["network-height"]; ?></span>
						</div>
						<div class="node-height">
							<span>Node Height:</span>
							<span><?php echo $template["node-height"]; ?></span>
						</div>
						<div class="network-difficulty">
							<span>Difficulty:</span>
							<span><?php echo $template["network-difficulty"]; ?></span>
						</div>
					</div>
				</div>
			</div>
			<div>
				<div class="peer-info centered">
					<div class="title">Connections</div>
					<div class="connections">
						<table>
							<tr>
								<th></th>
								<th></th>
								<th>IP</th>
								<th>Time Connected</th>
								<th>Version</th>
								<th>Last Activity</th>
								<th>Ping (ms)</th>
								<th>TX/RX (bytes)</th>
								<th>Height</th>
								<th>Banscore</th>
							</tr>
							<?php foreach ($template["peers"] as $peer) { ?>
							<tr>
								<td><span class="glyphicon glyphicon-<?php echo $peer["inbound"] ? "menu-left inbound" : "menu-right outbound" ?>"></span></td>
								<td data-ip="<?php echo $peer["plainaddr"] ?>"></td>
								<td data-dns="<?php echo $peer["plainaddr"] ?>"><?php echo $peer["addr"]; ?></td>
								<td><?php echo $peer["conntime"]; ?></td>
								<td><?php echo $peer["version"]; ?></td>
								<td><?php echo $peer["activity"]; ?></td>
								<td class="<?php echo ping_class($peer["ping"]) ?>"><?php echo $peer["ping"]; ?></td>
								<td><?php echo format_bytes($peer["tx"]); ?> / <?php echo format_bytes($peer["tx"]); ?></td>
								<td class="<?php if ($peer["height"] < $template["network-height"]) echo "text-danger"; else echo "text-ok"; ?>"><?php echo $peer["height"]; ?></td>
								<td class="<?php if ($peer["banscore"] > 0.01) echo "text-danger"; else echo "text-ok"; ?>"><?php echo $peer["banscore"] ?></td>
							</tr>
							<?php } ?> 
						</table>
					</div>
				</div>
			</div>
		</div>
		<div class="footer">
			<div class="centered">
				<div>Made with love by Jan Peter Koenig &lt;<a target="_blank" href="mailto://public@janpeterkoenig.com">public@janpeterkoenig.com</a>&gt;. Hosted on <a href="https://github.com/suthernfriend/bitcoind-node-stat">GitHub</a></div>
				<div>If you want to buy me a cola - i love cola - especially cherry cola - here is my address: <a target="_blank" href="bitcoin:1N2RLARDak5ZbTB4LzuDvHut7om97PWDUF">1N2RLARDak5ZbTB4LzuDvHut7om97PWDUF</a></div>
				<div>This software is licensed under the <a target="_blank" href="http://directory.fsf.org/wiki/License:X11">X11 License</a>. Feel free to use, redistribute and change everything.</div>
				<div>
					Glyphicons by <a href="https://glyphicons.com">Jan Kovarik</a>, licensed under <a target="_blank" href="http://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>. 
					Flags by <a href="http://flag-icon-css.lip.is/">flag-icon-css</a>.
					GeoIP by <a href="https://freegeoip.net/">freegeoip.net</a>.
				</div>	
			</div>
		</div>
		<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
		<script>
$(document).ready(function () {


$("[data-dns]").each(function () {
	var self = this;
	var ip = $(this).attr("data-dns");
	
	$.getJSON("http://5.45.104.83/reverselookup.php?ip=" + ip, function (data) {
		if (data.error === undefined)
			$(self).text(data.host);
	});

});

$("[data-ip]").each(function () {
	var self = this;
	$.getJSON("https://freegeoip.net/json/" + $(this).attr("data-ip"), function (data) {
		$(self).html("<span class=\"flag-icon flag-icon-" + data.country_code.toLowerCase() + "\"></span>");
	});
});

});
		</script>
	</body>
</html>
