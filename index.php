<?php

require_once 'app/lib/CmsHook.php';

$app = new CmsHook(include 'config.php');

$app->run();