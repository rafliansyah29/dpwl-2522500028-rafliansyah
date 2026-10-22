<?php
require_once APPPATH . 'models/Mobil_model.php';
class Katalog_controller extends Controller
{
private Mobil_model $mobilModel;
public function __construct()
{
$this->mobilModel = new Mobil_model();
}
public function index(): void
{
$data = [
'daftarMobil' => $this->mobilModel->getKatalog()
];
$this->view('katalog/index', $data);
}
}