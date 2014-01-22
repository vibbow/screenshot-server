<?php
date_default_timezone_set('PRC');
set_error_handler('handle_error');
set_exception_handler('handle_error');

define('BASE_DIR', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);

// 简单的防止盗链
//if ($_SERVER['HTTP_REFERER'] != 'http://vps.vsean.net/screenshot/')
//	handle_error();

// 简单的防止盗链
// 如果浏览器的UserAgent标记他不支持Javascript，那么他将不可能到达此页面
//if (get_browser(NULL, TRUE)['javascript'] != '1')
//	handle_error();

// 禁止截取截图网站自身
//if (strpos($url, 'vps.vsean.net') !== FALSE)
//	handle_error();

$url = trim($_GET['url']);
if (empty($url))
	handle_error();

if (strlen($url) > 1024)
	handle_error();

if (substr($url, 0, 4) != 'http')
	$url = "http://{$url}";

$GLOBALS['url'] = rawurldecode($url);

$cache_file = BASE_DIR . DS . 'cache' . DS . md5($url) . '.png';
$GLOBALS['cache_file'] = $cache_file;

// 用于标记截图过程是否开始
// 如果截图过程尚未开始，那么发生错误时不将错误图像缓存为网站截图
// 如果截图过程已经开始，那么发生错误时则将错误图像缓存为网站截图
$GLOBALS['start'] = TRUE;

// 记录截图访问日志
$log = date('Y-m-d H:i') . "\t" . $_SERVER['REMOTE_ADDR'] . "\t" . $url . "\r\n";
file_put_contents('access.log', $log, FILE_APPEND);

if (!file_exists($cache_file))
	take();

if ((time() - filemtime($cache_file)) > 3600)
	take();

header("content-type:image/png");
header("Cache-control: max-age=3600");
header("Content-Length: " . filesize($cache_file));
readfile($cache_file);

function take() {
	$cache_dir = BASE_DIR . DS . 'cache' . DS;
	$file_list = scandir($cache_dir);

	// 清理存在时间超过1小时的截图
	foreach ($file_list as $file) {
		$file_path = $cache_dir . $file;

		if (in_array($file, array('.', '..')))
			continue;

		if (is_dir($file_path))
			continue;

		if ((time() - filemtime($file_path)) > 3600)
			unlink($file_path);
	}

	require_once 'PHPWebDriver' . DS . '__init__.php';

	$wd_host = 'http://localhost:4444/wd/hub';
	$WebDriver = new PHPWebDriver_WebDriver($wd_host);

	$session = $WebDriver->session('firefox');
	
	// 将Selenium session的URL放置到全局变量，方便在出错时由handle_error函数关闭Firefox窗口
	$GLOBALS['session_url'] = $session->getURL();

	$session->window()->postSize(array('width' => 1366, 'height' => 768));
	$session->open($GLOBALS['url']);
	$img = $session->screenshot();
	$session->close();

	$data = base64_decode($img);
	file_put_contents($GLOBALS['cache_file'], $data);
}

function handle_error() {
	$error_file_path = BASE_DIR . DS . 'error.png';

	// 如果前面标记截图过程已经开始
	// 那么在这里要关闭Firefox窗口
	// 同时将错误图像缓存为网站截图
	if ($GLOBALS['start']) {
		require_once 'PHPWebDriver' . DS . '__init__.php';

		$wd_host = 'http://localhost:4444/wd/hub';
		$WebDriver = new PHPWebDriver_WebDriver($wd_host);

		$result = $WebDriver->sessions();
		foreach ($result as $session) {
			if ($session->getURL() == $GLOBALS['session_url']) {
				$session->close();
			}
		}

		copy($error_file_path, $GLOBALS['cache_file']);
	}

	header("content-type:image/png");
	header("Cache-control: max-age=3600");
	header("Content-Length: " . filesize($error_file_path));
	readfile($error_file_path);

	exit(1);
}
?>
