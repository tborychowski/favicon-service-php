<?php
include_once('./simple_html_dom.php');

$DEBUG = false;


if ($DEBUG) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}




function is_allowed () {
	if ($GLOBALS['DEBUG']) return true;
	if ($_SERVER['HTTP_HOST'] === 'localhost') return true;
	if (!isset($_SERVER['HTTP_ORIGIN'])) return false;
	return strpos($_SERVER['HTTP_ORIGIN'], 'moz-extension') === 0;
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


function fetch ($url) {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,      // return web page
		CURLOPT_HEADER         => true,      // return headers
		CURLOPT_FOLLOWLOCATION => true,      // follow redirects
		CURLOPT_USERAGENT      => "spider",  // who am i
		CURLOPT_AUTOREFERER    => true,      // set referer on redirect
		CURLOPT_CONNECTTIMEOUT => 60,        // timeout on connect
		CURLOPT_TIMEOUT        => 60,        // timeout on response
		CURLOPT_MAXREDIRS      => 10         // stop after 10 redirects
	]);
	$res = curl_exec($ch);
	$curl_info = curl_getinfo($ch);
	$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);

	$header_size = $curl_info['header_size'];
	$header = substr($res, 0, $header_size);
	$body = substr($res, $header_size);

	return ['url' => $finalUrl, 'body' => $body];
}



function get_host($url, $with_path = false) {
	$parsed = parse_url(trim($url));
	if (isset($parsed['path'])) {
		$path = explode('/', $parsed['path'], 2);
		$path = array_shift($path);
	}
	else $path = '';

	$res = isset($parsed['host']) ? $parsed['host'] : $path;
	$protocol = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'https://';
	$res = $protocol . trim($res);
	if ($with_path && isset($parsed['path'])) $res .= rtrim($parsed['path'], '/');
	return $res;
}


function get_closest_to ($icons, $size = 120) {
	$icon = $icons[0];
	$dist = abs($size - $icons[0]['size']);
	for ($i = 0; $i < count($icons); $i++) {
		$d = abs($size - $icons[$i]['size']);
		if ($d < $dist) {
			$dist = $d;
			$icon = $icons[$i];
		}
	}
	return $icon;
}


function get_icon_url_from_meta ($meta, $baseUrl) {
	$iconUrl = $meta->href;
	if (empty($iconUrl)) $iconUrl = $meta->content;
	if (empty($iconUrl)) return '';
	if (strpos($iconUrl, 'http') === 0) return $iconUrl;
	if (strpos($iconUrl, '//') === 0) return 'https:' . $iconUrl;
	if (strpos($iconUrl, 'data:image/') === 0) return $iconUrl;
	return trim(get_host($baseUrl), '/') . '/' . preg_replace('/^\//', '', $iconUrl);
}


function get_icon_size_from_meta ($meta) {
	$size = $meta->sizes;
	if (empty($size)) {
		$url = $meta->href;
		if (empty($url)) $url = $meta->content;
		preg_match('/\d{2,3}x\d{2,3}/' , $url, $matches);
		if (count($matches) > 0) $size = $matches[0];
	}
	if (!empty($size)) return intval(explode('x', $size)[0]);
	return 0;
}



function get_icons ($head, $url) {
	$links = [];
	foreach ($head as $meta) {
		$type = '';
		if (isset($meta->rel)) $type = $meta->rel;
		elseif (isset($meta->property)) $type = $meta->property;
		if (empty($type)) continue;

		$icon = [];
		if (strpos($type, 'icon') !== false) $icon['type'] = 'icon';
		elseif (strpos($type, 'apple-touch')) $icon['type'] = 'apple';
		elseif (strpos($type, 'msapplication-TileImage')) $icon['type'] = 'ms';
		elseif ($type == 'og:image') $icon['type'] = 'og';
		elseif ($type == 'og:image:width') {
			$icon['type'] = 'og';
			$size = $meta->content;
			if (isset($size)) $links[count($links) - 1]['size'] = intval($size);
			continue;
		}
		if (!empty($icon['type'])) {
			$icon['url'] = get_icon_url_from_meta($meta, $url);
			$icon['size'] = get_icon_size_from_meta($meta);
			$links[] = $icon;
		}
	}
	return $links;
}


function get_head ($body) {
	$start = strpos($body, '<head>');
	$len = strpos($body, '</head>') + 7 - $start;
	$body = substr($body, $start, $len);
	if ($body) return str_get_html($body);
	return false;
}


function url_exists ($url) {
	$headers = get_headers($url);
	if (!$headers || strpos($headers[0], '404') !== false || strpos($headers[0], '403') !== false) return false;
	return true;
}



function init ($url) {
	json_headers();

	$url_with_path = get_host($url, true);
	$url = get_host($url);

	if (!is_allowed() || !$url) return show404();

	// some hardcoded icons, that are hard to find

	if (strpos($url, 'alexa.amazon.') !== false) {
		$icon = 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cc/Amazon_Alexa_App_Logo.png/220px-Amazon_Alexa_App_Logo.png';
	}
	elseif (strpos($url, 'amazon.') !== false) {
		$icon = 'https://images-eu.ssl-images-amazon.com/images/G/02/anywhere/a_smile_120x120._CB368246590_.png';
	}
	elseif (strpos($url, 'google') !== false && strpos($url, 'maps') !== false) {
		$icon = 'https://www.google.com:443/images/branding/product/2x/maps_ios_32dp.png';
	}
	elseif (strpos($url, 'google') !== false) {
		$icon = 'https://www.google.com/images/branding/product_ios/3x/gsa_ios_60dp.png';
	}
	elseif (strpos($url, 'facebook.com') !== false) {
		$icon = 'https://static.xx.fbcdn.net/rsrc.php/v3/ya/r/O2aKM2iSbOw.png';
	}
	elseif (strpos($url, 'trello.com') !== false) {
		$icon = 'https://trello.com/favicon.ico';
	}
	elseif (strpos($url, 'followshows.com') !== false) {
		$icon = 'https://followshows.com/images/icon_120.png';
	}
	elseif (strpos($url, 'home.nest.com') !== false) {
		$icon = 'https://home.nest.com/images/magma/app_icon/icon_72-JR_20x.png';
	}
	elseif (strpos($url, 'arlo.netgear.com') !== false) {
		$icon = 'http://lh3.googleusercontent.com/gguhHHtgGQrpdd0eaSD6K9rmutp-_kJMwECgRc_jzoHtGNwotRve0F_rQoYkO4yuxQ=w300';
	}
	elseif (strpos($url, 'meethue.com') !== false) {
		$icon = 'https://upload.wikimedia.org/wikipedia/en/a/a1/Philips_hue_logo.png';
	}
	elseif (strpos($url, 'tpbship.org') !== false || strpos($url, 'piratebay.') !== false) {
		$icon = 'https://www.wired.com/images_blogs/threatlevel/2009/11/picture-49.png';
	}
	elseif (strpos($url, 'inoreader.com') !== false) {
		$icon = 'https://www.inoreader.com/images/icons/apple-touch-ipad-retina.png';
	}
	elseif (strpos($url, 'wattpad.com') !== false) {
		$icon = 'https://www.wattpad.com/image/icon_144.png';
	}
	elseif (strpos($url, 'wix.com') !== false) {
		$icon = 'https://blog.addthiscdn.com/wp-content/uploads/2015/11/wix-icon1.png';
	}
	else {
		$res = fetch($url);
		$url = trim($res['url'], '/');
		$url = get_host($url);

		$head = get_head($res['body']);
		if (!$head) return show404();

		$head = $head->find('link,meta');
		$links = get_icons($head, $url);

		$icon = $url_with_path . '/favicon.ico';
		if (url_exists($icon)) array_push($links, [ 'url' => $icon, 'type' => 'icon', 'size' => 0]);

		$icon = $url . '/favicon.ico';
		if (url_exists($icon)) array_push($links, [ 'url' => $icon, 'type' => 'icon', 'size' => 0]);

		if (count($links) > 0) $icon = get_closest_to($links);
		if (!empty($icon)) $icon = $icon['url'];
	}


	// last resort - guess :-)
	if (!isset($icon)) $icon = $url . '/apple-touch-icon.png';
	if (!url_exists($icon)) $icon = $url . '/apple-touch-icon-precomposed.png';
	if (!url_exists($icon)) $icon = $url_with_path . '/apple-touch-icon.png';
	if (!url_exists($icon)) $icon = '';

	echo json_encode([ 'url' => $url, 'icon' => $icon ]);
}

if (!isset($_GET['url'])) return show404();
init($_GET['url']);
