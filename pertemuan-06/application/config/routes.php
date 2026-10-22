<?php
// DIPERBARUI - application/config/routes.php
$route = [];
// Route P2/P3 - dipertahankan.
$route['default_controller'] = 'home';
$route['info/(:any)'] = 'home/info/$1';
$route['login'] = 'auth/login';
$route['auth/login'] = 'auth/login';
$route['auth/logout'] = 'auth/logout';
$route['admin'] = 'admin/index';
// P4: BARU - semua proses CRUD admin dipindahkan di bawah /admin/madmin.
$route['admin/madmin'] = 'madmin_controller/index';
$route['admin/madmin/tambah'] = 'madmin_controller/tambah';
$route['admin/madmin/ubah/(:any)'] = 'madmin_controller/ubah/$1';
$route['admin/madmin/hapus/(:any)'] = 'madmin_controller/hapus/$1';
// P5: BARU - semua proses CRUD mobil dipindahkan di bawah /admin/mobil.
$route['admin/mobil'] = 'Mobil_controller/index'; 
$route['admin/mobil/tambah'] = 'Mobil_controller/tambah'; 
$route['admin/mobil/ubah/(:any)'] = 'Mobil_controller/ubah/$1'; 
$route['admin/mobil/hapus/(:any)'] = 'Mobil_controller/hapus/$1'; 
// Route P6
$route['katalog'] = 'Katalog_controller/index';