<?php

header('Access-Control-Allow-Origin: *');
//header('Content-Type: application/vnd.api+json');
header("Access-Control-Allow-Headers: Authorization, Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control");
// @todo use only GET, POST instead?
header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');

// @todo extend Workerman

//if (explode('.', $_SERVER['HTTP_HOST'])[0] != 'api') {
//if (preg_match('/xmlhttprequest/i', ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $app = (new \Rapidest\App)->run();
//}

//require_once 'index.html';