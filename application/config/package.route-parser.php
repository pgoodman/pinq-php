<?php

!defined('DIR_APPLICATION') && exit();

$routes['/about'] = '/index/about';
$routes['/view-source'] = '/index/source';

//$routes['/users/(:alphanum)'] = '/users/index/$1';

// archives
$routes['/(:year)'] = '/archive/$1';
$routes['/(:year)/(:month)'] = '/archive/$1/$2';
$routes['/(:year)/(:month)/(:day)'] = '/archive/$1/$2/$3';

// tags
$routes['/tags/(:alphanum)'] = '/tags/index/$1';

// viewing single post
$routes['/(:year)/(:month)/(:day)/([a-zA-Z0-9-]*)'] = '/posts/$4';