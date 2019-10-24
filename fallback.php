<?php

$DEBUG = true;

function isAllowed () {
	return $DEBUG || strpos($_SERVER['origin'], 'moz-extension') == 0;
}


function show404 () {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
}


function json_headers () {
	header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Credentials: true");
	header("Access-Control-Max-Age: 1000");
	header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
	header("Access-Control-Allow-Methods: GET, POST");
	header("Content-type: application/json");
}


function getHost($url) {
   $parseUrl = parse_url(trim($url));
   return trim($parseUrl[host] ? $parseUrl[host] : array_shift(explode('/', $parseUrl[path], 2)));
}


function getClosestTo ($icons, $size = 120) {
	$icon = $icons[0];
	$dist = abs($size - $icons[0]['width']);
	for ($i = 0; $i < count($icons); $i++) {
		$d = abs($size - $icons[$i]['width']);
		if ($d < $dist) {
			$dist = $d;
			$icon = $icons[$i];
		}
	}
	return $icon;
}

function getJson ($url) {
	$json = null;
	$res = @file_get_contents($url);
	if ($res) $json = @json_decode($res, true);
	return $json;
}

function getIcon ($url) {
	if (!$url) return '';

	$json = getJson('https://besticon-demo.herokuapp.com/allicons.json?url=' . $url);
	if (!$json) $json = getJson('https://icon-fetcher-go.herokuapp.com/allicons.json?url=' . $url);

	if (!$json) return '';

	$icon = getClosestTo($json['icons']);
	if (!$icon) return '';

	return $icon['url'];
}


function init ($url) {
	json_headers();
	$icon = getIcon($url);
	$json = ['url' => $url, 'icon' => $icon];
	echo json_encode($json);
}

$url = getHost($_GET['url']);
if (isAllowed() && $url) init($url);
else show404();
