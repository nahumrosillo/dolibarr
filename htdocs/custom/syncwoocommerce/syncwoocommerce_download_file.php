<?php


$file = htmlspecialchars($_REQUEST['file']);

if (!empty($file)) {
	$dir = "/var/www/html/documents";
	$filename = $dir . '/' . $file;

	if (file_exists($filename)) {
		$ext = strtolower(substr($file, strrpos($file, '.') + 1));
		$ext = preg_replace('/[^a-z0-9]/', '', $ext);


		// return a PNG image response
		if (file_exists($filename) && is_readable($filename)) {
			header('Content-Type: image/png');
			header('Content-Length: ' . filesize($filename));
			header('Content-Disposition: attachment; filename="' . basename($file) . '"');
			header('Cache-Control: private, max-age=10800, pre-check=10800');
			header('Pragma: private');
			header('Expires: ' . date(DATE_RFC822, strtotime(' 1 hour')));

			readfile($filename);
			exit;
		} else {
			die("File not found or not readable.");
		}
	}
}
