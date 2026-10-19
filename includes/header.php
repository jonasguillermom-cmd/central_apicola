<?php
require_once __DIR__ . '/../config.php';
$logged = !empty($_SESSION['id_usr']);
$tieneEmp = $logged && (bool)q('SELECT 1 FROM empresa_usuarios WHERE id_usr=? LIMIT 1', [$_SESSION['id_usr']])->fetch();
$nCar = $logged ? (int)q('SELECT COALESCE(SUM(cant_car),0) FROM carrito WHERE id_usr=?', [$_SESSION['id_usr']])->fetchColumn() : 0;
$catsMenu = q("SELECT id_cat, nom_cat FROM categorias WHERE est_cat='A' ORDER BY nom_cat")->fetchAll();
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titulo ?? 'CENTRAL APÍCOLA DEL USUMACINTA') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilos.css"><link rel="stylesheet" href="css/paginas.css"><link rel="stylesheet" href="css/extra.css">
</head><body>
<div class="announce"></div>
<header><div class="header-top">
  <div class="header-left">
    <div class="logos-extra">
      <div class="logo-extra-slot"><img class="logo-extra-img" src="imagen/CONANP LOGO.png" alt="Logo 1"></div>
      <div class="logo-extra-slot"><img class="logo-extra-img" src="imagen/Cañon del Usumacinta_logo.png" alt="Logo 2"></div>
    </div>
    <a href="index.php" class="logo-wrap"><img class="logo-img" src="imagen/logo_centro.png" alt="Miel Oro">
      <div class="logo-name">CENTRAL APÍCOLA <span>DEL USUMACINTA</span></div></a>
  </div>
  <div class="header-icons">
    <a href="#" onclick="toggleSearch();return false"><svg class="icon-svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg> Buscar</a>
    <?php if ($logged): ?>
      <div class="user-menu" id="user-menu">
        <a href="#" class="user-menu-btn" onclick="toggleUserMenu(event)" aria-haspopup="true" aria-expanded="false">
          <svg class="icon-svg" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span><?= h(explode(' ', $_SESSION['nom_usr'])[0]) ?> ▾</span></a>
        <div class="user-dropdown">
          <div class="user-dropdown-head"><b><?= h($_SESSION['nom_usr']) ?></b><small><?= es_admin() ? 'Administrador' : ($tieneEmp ? 'Vendedor' : 'Cliente') ?></small></div>
          <a href="mis_pedidos.php">📦 Mis pedidos</a>
          <a href="cupones.php">🎟️ Mis cupones</a>
          <?php if ($tieneEmp): ?><a href="panel_empresa.php">🏪 Mi empresa</a>
          <?php else: ?><a href="registro_empresa.php">🏪 Vender en el marketplace</a><?php endif; ?>
          <?php if (es_admin()): ?><a href="admin_empresas.php">⚙️ Administración</a><a href="admin_catalogo.php">🗂️ Catálogo</a><a href="admin_variantes.php">🧩 Variantes</a><a href="admin_pagos.php">💳 Confirmar pagos</a><?php endif; ?>
          <a href="logout.php" class="user-dropdown-salir">🚪 Cerrar sesión</a>
        </div>
      </div>
    <?php else: ?>
      <a href="login.php"><svg class="icon-svg" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Ingresar</a>
    <?php endif; ?>
    <a href="carrito.php"><svg class="icon-svg" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Carrito <span class="cart-count-badge"><?= $nCar ?></span></a>
  </div>
</div></header>
<nav><div class="nav-inner">
  <div class="nav-item"><a href="index.php">Inicio</a></div>
  <div class="nav-item"><a href="nosotros.php">Nosotros</a></div>
  <div class="nav-item"><span>Productos <span class="chevron">▾</span></span>
    <div class="dropdown">
      <div class="dropdown-group"><div class="dropdown-title">Categorías</div>
        <?php foreach ($catsMenu as $c) echo '<a href="categoria.php?id=' . (int)$c['id_cat'] . '">' . h($c['nom_cat']) . '</a>'; ?>
        <a href="categoria.php?q=" style="font-weight:700">Ver todo</a></div>
    </div></div>
  <div class="nav-item"><a href="abejas.php">Abejas</a></div>
  <div class="nav-item"><a href="centro_rescate.php">Centro de Rescate</a></div>
  <div class="nav-item"><a href="blog.php">Blog y Eventos</a></div>
  <div class="nav-item"><a href="contacto.php">Contacto</a></div>
</div></nav>
<div class="barra-menu-movil"><span class="menu-label">☰ Menú</span>
  <button class="hamburger-btn" id="hamburger-btn" aria-label="Menú"><span></span><span></span><span></span></button></div>
<div class="menu-desplegable" id="menu-desplegable">
  <a href="index.php">Inicio</a><a href="nosotros.php">Nosotros</a><div class="menu-seccion">Productos</div>
  <div class="submenu"><?php foreach ($catsMenu as $c) echo '<a href="categoria.php?id=' . (int)$c['id_cat'] . '">' . h($c['nom_cat']) . '</a>'; ?></div>
  <a href="abejas.php">Abejas</a><a href="centro_rescate.php">Centro de Rescate</a><a href="blog.php">Blog y Eventos</a><a href="contacto.php">Contacto</a>
</div>
