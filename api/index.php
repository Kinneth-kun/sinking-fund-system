<?php

// One serverless entrypoint serves the existing PHP pages. Static assets are
// served directly by Vercel before the fallback rewrite reaches this file.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

$routes = [
    '/' => 'index.php',
    '/index.php' => 'index.php',
    '/setup.php' => 'setup.php',
    '/verify.php' => 'verify.php',
    '/logout.php' => 'logout.php',
    '/pages/dashboard.php' => 'pages/dashboard.php',
    '/pages/calendar.php' => 'pages/calendar.php',
    '/pages/members.php' => 'pages/members.php',
    '/pages/payments.php' => 'pages/payments.php',
    '/pages/reports.php' => 'pages/reports.php',
];

if (!isset($routes[$path])) {
    http_response_code(404);
    exit('Page not found.');
}

$projectRoot = dirname(__DIR__);
$_SERVER['PHP_SELF'] = $path === '/' ? '/index.php' : $path;
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
require $projectRoot . '/' . $routes[$path];
