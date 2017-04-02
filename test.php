<?php
function check_pid($pid) {
	exec('ps -ax | awk \'{print $1}\'', $pids);
	return (in_array($pid, $pids)) ? true : false;
}

function get_log() {
	$arr = explode("[ ", file_get_contents('/tmp/csgoserver.log'));
	$key = count($arr) - 1;
	$arr = explode("] ", $arr[$key]);
	$key = count($arr) - 1;
	if(empty($arr[$key])) return false;
	return date('Y-m-d H:i:s', time()).' '.rtrim($arr[$key]);
}

exec('su csgoserver -l -c "./csgoserver update" > /tmp/csgoserver.log &');
exec('program_check su', $pid);
$pid = implode('', $pid);

$print = [];

while(check_pid($pid)) {
	$log = get_log();
	if(strstr($log, 'exiting with')) break;
	if($log)$print[] = $log;
	var_dump($print);
	sleep(1);
}

$json = str_replace("\u001b[32m", '', json_encode($print));
$json = str_replace("\u001b[0m", '', $json);