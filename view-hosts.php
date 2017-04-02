<?php

$hs = json_decode(file_get_contents("hosts.json"), true);

foreach ($hs as $h) {
	echo str_pad($h["ip"], 40) . " - " . str_pad($h["port"], 7) . " - " . $h["host"] . "\n";
}
