<?php
ini_set('memory_limit','-1');
use Workerman\Worker;
use Workerman\Protocols\Http;
require_once __DIR__ . '/vendor/Autoloader.php';

date_default_timezone_set('PRC');
$GLOBALS['path'] = '/etc';
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

function read_dir($dir, $sort = 'mtime', $order = SORT_DESC) {
	$list = scandir($dir);
	foreach($list as $k => $name) {
		$file_name[] = $name;
		$real_path = $dir.$name;
		$is_dir[] = is_dir($name);
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

function make_list($array) {
	#var_dump($array);
	if(!$array) return false;
	$str = '';
	$GLOBALS['total_files'] = 0;
	$GLOBALS['total_size'] = 0;
	for($i=0;$i<count($array['name']);$i++) {
		$name = $array['name'][$i];
		if($array['dir'][$i]) {
			$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
			$str .= "<tr>\n";
			$str .= "<td><img src=\"data:image/gif;base64,R0lGODlhFAAWAMIAAP/////Mmcz//5lmMzMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAACACwAAAAAFAAWAAADVCi63P4wyklZufjOErrvRcR9ZKYpxUB6aokGQyzHKxyO9RoTV54PPJyPBewNSUXhcWc8soJOIjTaSVJhVphWxd3CeILUbDwmgMPmtHrNIyxM8Iw7AQA7\" alt=\"[DIR]\"></td><td> <a href=\"?dir=$name\">$name</a></td>\n";
			$str .= "<td align=\"right\"> $mtime_now</td>\n";
			$str .= "<td align=\"right\"> -</td><td>&nbsp;</td>\n";
			$str .= "<td align=\"right\"><a href=\"?delete=$name\" onclick=\"return confirm('确定要删除吗？')\"> 删除</a></td>\n";
			$str .= "</tr>\n";
			$GLOBALS['total_files']++;
		} else {
			$size_now = formatsize($array['size'][$i]);
			$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
			$str .= "<tr>\n";
			$str .= "<td><img src=\"data:image/gif;base64,R0lGODlhFAAWAMIAAP///8z//8zMzJmZmTMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAADaUi6vPEwEECrnSS+WQoQXSEAE6lxXgeopQmha+q1rhTfakHo/HaDnVFo6LMYKYPkoOADim4VJdOWkx2XvirUgqVaVcbuxCn0hKe04znrIV/ROOvaG3+z63OYO6/uiwlKgYJJOxFDh4hTCQA7\" alt=\"[   ]\"></td><td> <a href=\"?download=$name\">$name</a></td>\n";
			$str .= "<td align=\"right\"> $mtime_now</td>\n";
			$str .= "<td align=\"right\"> $size_now</td><td>&nbsp;</td>\n";
			$str .= "<td align=\"right\"><a href=\"?delete=$name\" onclick=\"return confirm('确定要删除吗？')\"> 删除</a></td>\n";
			$str .= "</tr>\n";
			$GLOBALS['total_files']++;
			$GLOBALS['total_size'] += $array['size'][$i];
		}
	}
	return $str;
}

function get_full_html($path, $sort, $data) {
	$table = make_list(read_dir($path, $sort));
	$GLOBALS['total_size'] = formatsize($GLOBALS['total_size']);
	$header = "<!DOCTYPE html PUBLIC \"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" \"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<title>Index of /</title>\n<style type=\"text/css\" media=\"screen\">pre{background:0 0}body{margin:2em}tb{width:600px;margin:0 auto}</style>\n<script>if(window.name!=\"bencalie\"){location.reload();window.name=\"bencalie\"}else{window.name=\"\"}</script>\n</head>\n<body>\n<strong>Demo 下载</strong>\n";
	$footer = "<address>%s</address>\n</body>\n</html>";
	$template_a = $header.'<p>没有文件</p>'.$footer;
	$template = $header.'<table><th><img src="data:image/gif;base64,R0lGODlhFAAWAKEAAP///8z//wAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAACE4yPqcvtD6OctNqLs968+w+GSQEAOw==" alt="[ICO]"></th><th><a href="?sort=name">名称</a></th><th><a href="?sort=mtime">最后更改</a></th><th><a href="?sort=size">大小</a></th></tr><tr><th colspan="6"><hr></th></tr>%s<tr><th colspan="6"><hr></th></tr></table>'.$footer;
	if(!$table) return sprintf($template_a, get_ver($data));
	return sprintf($template, $table, "{$GLOBALS['total_files']} files, total {$GLOBALS['total_size']}.</br>".get_ver($data));
}

$http_worker = new Worker("http://0.0.0.0:12101");
$http_worker->count = 4;
$http_worker->onMessage = function($connection, $data) {
	$GLOBALS['time_start'] = microtime(true);
	$GLOBALS['queries'] = 0;
	if(!isset($_GET['sort'])) $_GET['sort'] = false;
	if(!isset($_GET['dir'])) $_GET['dir'] = '';
	if(isset($_GET['download'])) {
		$file_path = $GLOBALS['path'].$_GET['download'];
		if(is_readable($file_path)) {
			Http::header("Content-type: text/plain");
			Http::header("Accept-Ranges: bytes");
			Http::header("Content-Disposition: attachment; filename=".$_GET['download']);
			$connection->send(file_get_contents($file_path));
		} else {
			Http::header("HTTP/1.1 404 Not Found");
		}
	} elseif(isset($_GET['delete'])) {
		unlink($GLOBALS['path'].$_GET['delete']);
		$connection->send('<script>history.go(-1)</script>');
	} else {
		$connection->send(get_full_html($GLOBALS['path'].$_GET['dir'], $_GET['sort'], $data));
	}
};

Worker::runAll();