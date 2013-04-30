#!/package/host/localhost/php-5/bin/php
<?php
exit();
$files = array('fsgparse-update.php', 'fsgplan-update.php');
foreach($files as $file) {
	echo 'loading file '.$file.PHP_EOL;
	$time_start = microtime(true);
	$errors = 0;

	require_once($file);

	$time_end = microtime(true);
	$time = $time_end - $time_start;

	echo $file.' complete, took '.(round($time * 10000) / 10000).'s, '.$errors.' error(s)'.PHP_EOL;
}
?>
