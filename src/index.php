<?php

require_once __DIR__ . '/classes/Config.php';
require_once __DIR__ . '/classes/SessionManager.php';
require_once __DIR__ . '/classes/Utility.php';
require_once __DIR__ . '/classes/Query.php';
require_once __DIR__ . '/classes/WebHandler.php';

function main()
{
    // Initialize configuration
    \MultiDbSqlTool\Config::initialize(__DIR__ . '/config.php');

    // Handle web requests
    $webHandler = new \MultiDbSqlTool\WebHandler();
    $webHandler->execute();
}

main();
