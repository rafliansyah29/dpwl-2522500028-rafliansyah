<!-- Left side column. contains the logo and sidebar -->
<aside class="main-sidebar">
<!-- sidebar: style can be found in sidebar.less -->
<section class="sidebar">
<!-- Sidebar user panel (optional) -->
<div class="user-panel">
<div class="pull-left image">
<img src="<?= base_url('dist/img/avatar.png') ?>" class="user-image" alt="User Image">
</div>
<div class="pull-left info">
<p>
Login sebagai:
<strong><?= htmlspecialchars(
$username,
ENT_QUOTES,
'UTF-8'
) ?></strong>

</p>
<!-- Status -->
<a href="#"><i class="fa fa-circle text-success"></i> Online</a>
</div>
</div>
<!-- search form (Optional) -->
<form action="#" method="get" class="sidebar-form">
<div class="input-group">
<input type="text" name="q" class="form-control" placeholder="Search...">
<span class="input-group-btn">
<button type="submit" name="search" id="search-btn" class="btn btn-flat"><i class="fa

fa-search"></i>
</button>
</span>
</div>
</form>
<!-- /.search form -->
<!-- Sidebar Menu -->
<ul class="sidebar-menu" data-widget="tree">
<li class="header">HEADER</li>
<!-- Optionally, you can add icons to the links -->
<li class="active">
    <a href="<?php echo base_url('index.php/admin/madmin'); ?>">
        <i class="fa fa-link"></i> <span>Manajemen Admin</span>
    </a>
</li>

<!-- /.sidebar-menu -->
</section>
<!-- /.sidebar -->
</aside>