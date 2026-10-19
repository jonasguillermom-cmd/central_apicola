<?php
require_once 'config.php';
$qtxt = trim($_GET['q'] ?? ''); $idCat = (int)($_GET['id'] ?? 0); $cat = null;
if ($qtxt === '' && $idCat > 0) {
    $cat = q("SELECT * FROM categorias WHERE id_cat=? AND est_cat='A'", [$idCat])->fetch();
    if (!$cat) { http_response_code(404); $titulo = 'No encontrada'; require 'includes/header.php';
        echo '<div class="container section"><h2 class="section-title">Categoría no encontrada</h2></div>'; require 'includes/footer.php'; exit; }
}
$todasCats = q("SELECT c.id_cat, c.nom_cat, (SELECT COUNT(*) FROM v_producto_desde d JOIN productos p ON p.id_prod=d.id_prod WHERE p.id_cat=c.id_cat) AS n
                FROM categorias c WHERE c.est_cat='A' ORDER BY c.nom_cat")->fetchAll();
$w = ['1=1']; $p = [];
if ($qtxt !== '') { $w[] = 'd.nom_prod LIKE ?'; $p[] = '%' . $qtxt . '%'; }
elseif ($cat)     { $w[] = 'p.id_cat = ?'; $p[] = $cat['id_cat']; }
$min = $_GET['min'] ?? ''; $max = $_GET['max'] ?? '';
if (is_numeric($min)) { $w[] = 'd.precio_desde >= ?'; $p[] = $min; }
if (is_numeric($max)) { $w[] = 'd.precio_desde <= ?'; $p[] = $max; }
$orden = ['price-asc' => 'd.precio_desde ASC', 'price-desc' => 'd.precio_desde DESC', 'name-asc' => 'd.nom_prod ASC'][$_GET['orden'] ?? ''] ?? 'd.id_prod DESC';
$rows = q(SQL_TARJETAS . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . $orden, $p)->fetchAll();

$nombre = $cat ? $cat['nom_cat'] : ($qtxt !== '' ? 'Resultados para "' . $qtxt . '"' : 'Todos los productos');
$banner = $cat && is_file('imagen/banner_' . slugify($cat['nom_cat']) . '.png') ? 'imagen/banner_' . slugify($cat['nom_cat']) . '.png' : '';
$titulo = $nombre . ' — Central Apícola del Usumacinta';
require 'includes/header.php';
?>
<div class="coll-banner">
  <?php if ($banner): ?><img class="coll-banner-img" src="<?= h($banner) ?>" alt="<?= h($nombre) ?>" onerror="this.style.opacity='0'"><?php endif; ?>
  <div class="coll-banner-content">
    <div class="breadcrumb"><a href="index.php">Inicio</a> &nbsp;/&nbsp; Productos &nbsp;/&nbsp; <?= h($nombre) ?></div>
    <h1><?= $cat ? 'Nuestros productos: ' . h($cat['nom_cat']) : h($nombre) ?></h1>
    <?php if ($cat && $cat['desc_cat']): ?><p><?= h($cat['desc_cat']) ?></p><?php endif; ?>
  </div>
</div>
<div class="coll-toolbar"><div class="coll-toolbar-inner">
  <span class="coll-count"><strong><?= count($rows) ?></strong> productos</span>
  <div class="filter-tags">
    <a class="filter-tag<?= !$cat ? ' active' : '' ?>" href="categoria.php?q=">Todos</a>
    <?php foreach ($todasCats as $t) echo '<a class="filter-tag' . ($cat && $cat['id_cat'] == $t['id_cat'] ? ' active' : '') . '" href="categoria.php?id=' . (int)$t['id_cat'] . '">' . h($t['nom_cat']) . '</a>'; ?>
  </div>
  <form class="coll-sort" method="get">
    <input type="hidden" name="id" value="<?= (int)$idCat ?>"><input type="hidden" name="q" value="<?= h($qtxt) ?>">
    <input type="hidden" name="min" value="<?= h($min) ?>"><input type="hidden" name="max" value="<?= h($max) ?>">
    <label for="sort-select">Ordenar:</label>
    <select id="sort-select" name="orden" onchange="this.form.submit()">
      <?php foreach (['' => 'Destacados', 'price-asc' => 'Precio: menor a mayor', 'price-desc' => 'Precio: mayor a menor', 'name-asc' => 'Nombre A–Z'] as $k => $v)
        echo '<option value="' . $k . '"' . (($_GET['orden'] ?? '') === $k ? ' selected' : '') . '>' . $v . '</option>'; ?>
    </select>
  </form>
</div></div>
<div class="coll-layout">
  <aside class="coll-sidebar">
    <div class="filter-section"><div class="filter-heading">Categorías <span class="toggle-icon">−</span></div>
      <ul class="filter-list"><?php foreach ($todasCats as $t) echo '<li><label><a href="categoria.php?id=' . (int)$t['id_cat'] . '">' . h($t['nom_cat']) . '</a> <span class="f-count">' . (int)$t['n'] . '</span></label></li>'; ?></ul></div>
    <div class="filter-section"><div class="filter-heading">Precio <span class="toggle-icon">−</span></div>
      <form method="get"><input type="hidden" name="id" value="<?= (int)$idCat ?>"><input type="hidden" name="q" value="<?= h($qtxt) ?>">
        <div class="price-range"><input type="number" name="min" placeholder="$ Min" min="0" value="<?= h($min) ?>"><span>—</span><input type="number" name="max" placeholder="$ Máx" min="0" value="<?= h($max) ?>"></div>
        <button class="btn-apply-price">Aplicar</button></form></div>
  </aside>
  <div class="coll-products"><div class="coll-grid-products">
    <?php foreach ($rows as $r) echo tarjeta($r); ?>
    <?php if (!$rows) echo '<p style="grid-column:1/-1;text-align:center;color:#7a7a6a;padding:40px">No encontramos productos con esos filtros.</p>'; ?>
  </div></div>
</div>
<?php require 'includes/footer.php'; ?>
