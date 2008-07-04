<?php

!defined('DIR_APPLICATION') && exit();

$routes['/page/(:num)'] = '/index/index/';
$routes['/archive'] = '/index/archive';
$routes['/users/(:alphanum)'] = '/users/index/$1';

// archives
$routes['/(:year)'] = '/archive/$1';
$routes['/(:year)/(:month)'] = '/archive/$1/$2';
$routes['/(:year)/(:month)/(:day)'] = '/archive/$1/$2/$3';

// viewing single post
$routes['/(:year)/(:month)/(:day)/([a-zA-Z0-9-]*)'] = '/posts/index/$4';

$routes['/view-source'] = '/index/source';