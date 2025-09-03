<?php

require_once __DIR__ . '/classes/Config.php';
require_once __DIR__ . '/classes/Utility.php';
require_once __DIR__ . '/classes/Query.php';
require_once __DIR__ . '/classes/WebHandler.php';

(new \MultiDbSqlTool\WebHandler())->execute();
