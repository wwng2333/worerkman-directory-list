<?php
ini_set('memory_limit','-1');
use Workerman\Worker;
use Workerman\Protocols\Http;
require_once __DIR__ . '/vendor/Autoloader.php';

date_default_timezone_set('PRC');
$GLOBALS['path'] = '/';
if(substr($GLOBALS['path'], '-1') !== '/') $GLOBALS['path'].= '/';

function formatsize($size, $key = 0) {
	if($size < 0) {
		return '0B';
	} else {
		$danwei = array('B','K','M','G','T','P');
		while($size > 1024) {
			$size = $size / 1024;
			$key++;
		}
		return round($size, 1).$danwei[$key];
	}
}

function get_ver($data) {
	$_d = $data['server'];
	$time_usage = round((microtime(true) - $GLOBALS['time_start']) * 1000, 4);
	$mem_usage = round(memory_get_usage()/1024/1024, 2);
	$_s = "Processed in {$time_usage} ms , {$mem_usage} MB memory used , {$GLOBALS['queries']} queries.</br>\n";
	return sprintf($_s.'%s Server at %s Port %s', $_d['SERVER_SOFTWARE'], $_d['SERVER_NAME'], $_d['SERVER_PORT']);
}

function read_dir($dir, $sort = 'name', $order = SORT_DESC) {
	$list = scandir($dir);
	unset($list[0], $list[1]);
	foreach($list as $k => $name) {
		$file_name[] = $name;
		$real_path = $dir.$name;
		$is_dir[] = @scandir($real_path) ? true : false;
		$file_size[] = filesize($real_path);
		$file_mtime[] = filemtime($real_path);
	}
	switch($sort) {
		case 'name':
			array_multisort($file_name, $order, $file_size, $file_mtime, $is_dir);
		break;
		case 'size':
			array_multisort($file_size, $order, $file_name, $file_mtime, $is_dir);
		break;
		case 'mtime':
			array_multisort($file_mtime, $order, $file_size, $file_name, $is_dir);
		break;
		default: break;
	}
	return (isset($file_name)) ? array('name' => $file_name, 'size' => $file_size, 'mtime' => $file_mtime, 'dir' => $is_dir) : false;
}

function make_list($dir, $array, $path) {
	if(!$array) return false;
	$path = rtrim($path, '/');
	$str = '';
	$GLOBALS['total_files'] = 0;
	$GLOBALS['total_size'] = 0;
	if(!empty($path)) {
		$tmp = explode('/', $path);
		var_dump($path,$tmp);
		if(count($tmp) == 2) {
			$tmp = '/';
		} else {
			$count = count($tmp) - 1;
			unset($tmp[$count]);
			$tmp = implode('/', $tmp);
		}
		$str .= "<tr><td valign=\"top\"><img src=\"?gif=parentdir\" alt=\"[PARENTDIR]\"></td><td><a href=\"?dir=$tmp\">Parent Directory</a></td><td>&nbsp;</td><td align=\"right\">  - </td><td>&nbsp;</td></tr>";
	}
	for($i=0;$i<count($array['name']);$i++) {
		$name = $array['name'][$i];
		$real_path = str_replace('//', '/', $dir.$name);
		if($array['dir'][$i]) {
			$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
			$str .= "<tr>\n";
			$str .= "<td><img src=\"?gif=dir\" alt=\"[DIR]\"></td><td> <a href=\"?dir=$real_path\">$name/</a></td>\n";
			$str .= "<td align=\"right\"> $mtime_now</td>\n";
			$str .= "<td align=\"right\"> -</td><td>&nbsp;</td>\n";
			$str .= "<td align=\"right\"><a href=\"?delete=$name\" onclick=\"return confirm('确定要删除吗？')\"> 删除</a></td>\n";
			$str .= "</tr>\n";
			$GLOBALS['total_files']++;
		} else {
			$size_now = formatsize($array['size'][$i]);
			$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
			$str .= "<tr>\n";
			$str .= "<td><img src=\"?gif=blank\" alt=\"[   ]\"></td><td> <a href=\"?download=$real_path\">$name</a></td>\n";
			$str .= "<td align=\"right\"> $mtime_now</td>\n";
			$str .= "<td align=\"right\"> $size_now</td><td>&nbsp;</td>\n";
			$str .= "<td align=\"right\"><a href=\"?delete=$name\" onclick=\"return confirm('确定要删除吗？')\"> 删除</a></td>\n";
			$str .= "</tr>\n";
			$GLOBALS['total_files']++;
			$GLOBALS['total_size'] += $array['size'][$i];
		}
		$GLOBALS['queries']++;
	}
	return $str;
}

function get_full_html($path, $sort, $data) {
	$real_path = str_replace('//', '/', $GLOBALS['path'].$path);
	$table = make_list($real_path, read_dir($real_path, $sort), $path);
	$GLOBALS['total_size'] = formatsize($GLOBALS['total_size']);
	$header = "<!DOCTYPE html PUBLIC \"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" \"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<title>Index of /</title>\n<style type=\"text/css\" media=\"screen\">pre{background:0 0}body{margin:2em}tb{width:600px;margin:0 auto}</style>\n<script>if(window.name!=\"bencalie\"){location.reload();window.name=\"bencalie\"}else{window.name=\"\"}</script>\n</head>\n<body>\n<strong>$real_path 的索引</strong>\n";
	$footer = "<address>%s</address>\n</body>\n</html>";
	$template_a = $header.'<p>没有文件</p>'.$footer;
	$template = $header."<table><th><img src=\"?gif=ico\" alt=\"[ICO]\"></th><th><a href=\"?dir=$path&sort=name\">名称</a></th><th><a href=\"?dir=$path&sort=mtime\">最后更改</a></th><th><a href=\"?dir=$path&sort=size\">大小</a></th></tr><tr><th colspan=\"6\"><hr></th></tr>%s<tr><th colspan=\"6\"><hr></th></tr></table>".$footer;
	if(!$table) return sprintf($template_a, get_ver($data));
	return sprintf($template, $table, "{$GLOBALS['total_files']} files, total {$GLOBALS['total_size']}.</br>".get_ver($data));
}

$http_worker = new Worker("http://0.0.0.0:12101");
$http_worker->count = 4;
$http_worker->onMessage = function($connection, $data) {
	$GLOBALS['time_start'] = microtime(true);
	$GLOBALS['queries'] = 0;
	if(isset($_GET['gif'])) {
		switch($_GET['gif']) {
			case 'parentdir':
				$gif = 'R0lGODlhFAAWAMIAAP///8z//5mZmWZmZjMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAADSxi63P4jEPJqEDNTu6LO3PVpnDdOFnaCkHQGBTcqRRxuWG0v+5LrNUZQ8QPqeMakkaZsFihOpyDajMCoOoJAGNVWkt7QVfzokc+LBAA7';
			break;
			case 'dir':
				$gif = 'R0lGODlhFAAWAMIAAP/////Mmcz//5lmMzMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAACACwAAAAAFAAWAAADVCi63P4wyklZufjOErrvRcR9ZKYpxUB6aokGQyzHKxyO9RoTV54PPJyPBewNSUXhcWc8soJOIjTaSVJhVphWxd3CeILUbDwmgMPmtHrNIyxM8Iw7AQA7';
			break;
			case 'ico':
				$gif = 'R0lGODlhFAAWAKEAAP///8z//wAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAACE4yPqcvtD6OctNqLs968+w+GSQEAOw==';
			break;
			case 'blank':
				$gif = 'R0lGODlhFAAWAMIAAP///8z//8zMzJmZmTMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAADaUi6vPEwEECrnSS+WQoQXSEAE6lxXgeopQmha+q1rhTfakHo/HaDnVFo6LMYKYPkoOADim4VJdOWkx2XvirUgqVaVcbuxCn0hKe04znrIV/ROOvaG3+z63OYO6/uiwlKgYJJOxFDh4hTCQA7';
			break;
			default:
				Http::header("HTTP/1.1 404 Not Found");
			break;
		}
		Http::header("Content-type: image/gif");
		$connection->send(base64_decode($gif));
		Worker::stopAll();
	}
	if(!isset($_GET['sort'])) $_GET['sort'] = false;
	if(!isset($_GET['dir'])) {
		$_GET['dir'] = '';
	} else {
		if(substr($_GET['dir'], '-1') !== '/') $_GET['dir'] .= '/';
	}
	if(isset($_GET['download'])) {
		$tmp = explode('/', $_GET['download']);
		$file_name = end($tmp);
		if(is_readable($_GET['download'])) {
			Http::header("Content-type: text/plain");
			Http::header("Accept-Ranges: bytes");
			Http::header("Content-Disposition: attachment; filename=".$file_name);
			$connection->send(file_get_contents($_GET['download']));
		} else {
			Http::header("HTTP/1.1 404 Not Found");
		}
	} elseif(isset($_GET['delete'])) {
		unlink($GLOBALS['path'].$_GET['delete']);
		$connection->send('<script>history.go(-1)</script>');
	} else {
		$connection->send(get_full_html($_GET['dir'], $_GET['sort'], $data));
	}
};

Worker::runAll();