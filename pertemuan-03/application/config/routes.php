<?php

$route = [];

$route['default_controller'] = 'home';
$route['info/(:any)'] = 'home/info/$1';

$route['default_controller'] = 'home';
$route['info/(:any)'] = 'home/info/$1';

// Route autentikasi P3
$route['login']        = 'auth/login';
$route['auth/login']   = 'auth/login';
$route['auth/logout']  = 'auth/logout';

// Route halaman administrasi
$route['admin']        = 'admin/index';