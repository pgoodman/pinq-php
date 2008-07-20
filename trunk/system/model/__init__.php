<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

$dir = dirname(__FILE__);

require_once $dir .'/validation.php';
require_once $dir .'/model-gateway.php';
require_once $dir .'/model-definition.php';
require_once $dir .'/model-dictionary.php';
require_once $dir .'/model-relations.php';
require_once $dir .'/model-exception.php';
require_once $dir .'/type-handlers.php';
