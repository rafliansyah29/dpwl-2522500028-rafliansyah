<?php

$route = [];

$route['default_controller'] = 'Home';
$route['info/(:any)'] = 'home/info/$1';