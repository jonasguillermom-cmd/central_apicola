<?php
require_once 'config.php';
require_once 'includes/catalogo.php';
requiere_login();
$emp = empresa_del_usuario();
if (!$emp) { header('Location: registro_empresa.php'); exit; }
$idEmp = (int)$emp['id_emp'];
$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); $err = '';

if ($emp['est_emp'] === 'A' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $pdo->beginTransaction();
        if (($_POST['accion'] ?? '') === 'eliminar_producto') {
            [$b, $p] = eliminar_producto_empresa((int)($_POST['id_prod'] ?? 0), $idEmp);
            $msg = msg_borrado($b, $p) ?: 'Nada que eliminar.';
        } else {                                                  // crear
            $idProd = (int)($_POST['id_prod'] ?? 0);
            if ($idProd === 0) $idProd = crear_producto(trim($_POST['nom_prod'] ?? ''), (int)($_POST['id_cat'] ?? 0), trim($_POST['desc_prod'] ?? ''));
            elseif (!q("SELECT 1 FROM productos WHERE id_prod=? AND est_prod='A'", [$idProd])->fetch()) throw new RuntimeException('Producto no válido.');
            $n = publicar_combinaciones($idEmp, $idProd, $_POST, $_FILES);
            $msg = "Publicaste $n variante(s). Si no subiste fotos, agrégalas con el botón ✏️ Editar.";
        }
        $pdo->commit();
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($x->getMessage()); $err = 'No se pudo guardar.'; }
}

$titulo = 'Panel de ' . $emp['nom_emp']; require 'includes/header.php';
if ($emp['est_emp'] !== 'A') {
    echo '<section class="sec"><h2>' . h($emp['nom_emp']) . '</h2><p style="text-align:center">' .
         ($emp['est_emp'] === 'P' ? '⏳ Tu empresa está en revisión. Te avisaremos al aprobarla.' : 'Tu empresa está desactivada.') . '</p></section>';
    require 'includes/footer.php'; exit;
}
$cats = q("SELECT id_cat, nom_cat AS etiqueta FROM categorias WHERE est_cat='A' ORDER BY nom_cat")->fetchAll();
$prods = q("SELECT id_prod, nom_prod FROM productos WHERE est_prod='A' ORDER BY nom_prod")->fetchAll();
$mios = q("SELECT p.id_prod, p.nom_prod, p.slug_prod, c.nom_cat, COUNT(*) AS n_var, SUM(o.stock_ofe) AS stock, SUM(o.est_ofe='A') AS activas,
      MIN(COALESCE(o.precio_desc_ofe,o.precio_ofe)) AS desde,
      (SELECT url_img FROM producto_imagenes i WHERE i.id_prod=p.id_prod ORDER BY (i.id_var IN (SELECT o2.id_var FROM ofertas o2 WHERE o2.id_emp=?)) DESC, i.orden_img LIMIT 1) AS img
    FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var JOIN productos p ON p.id_prod=v.id_prod JOIN categorias c ON c.id_cat=p.id_cat
    WHERE o.id_emp=? GROUP BY p.id_prod, p.nom_prod, p.slug_prod, c.nom_cat ORDER BY p.nom_prod", [$idEmp, $idEmp])->fetchAll();
?>
<section class="sec"><h2><?= h(mb_strtoupper($emp['nom_emp'])) ?> · PANEL</h2>
<p style="text-align:center"><a class="btn" href="cupones_empresa.php?emp=<?= $idEmp ?>">🎟️ Cupones de mi tienda</a> <a class="btn" href="pedidos_empresa.php?emp=<?= $idEmp ?>">📦 Pedidos recibidos</a></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>

<h3>Mis productos</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;margin-bottom:34px">
<?php foreach ($mios as $m): ?>
  <div class="resumen-card" style="padding:12px;max-width:none">
    <img src="<?= h($m['img'] ?: 'imagen/logo_centro.png') ?>" alt="" style="width:100%;height:150px;object-fit:cover;border-radius:4px;background:#f5f0e8">
    <p style="font-weight:700;margin:8px 0 2px"><?= h($m['nom_prod']) ?></p>
    <p style="font-size:12px;color:#666;margin:0 0 8px"><?= h($m['nom_cat']) ?> · <?= (int)$m['n_var'] ?> variante(s) · desde <?= dinero($m['desde']) ?> · stock <?= (int)$m['stock'] ?><?= $m['activas'] < $m['n_var'] ? ' · ⏸ ' . ($m['n_var'] - $m['activas']) . ' pausada(s)' : '' ?></p>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a class="btn" style="padding:7px 12px;font-size:10px" href="editar_producto.php?emp=<?= $idEmp ?>&id_prod=<?= $m['id_prod'] ?>">✏️ Editar</a>
      <a class="btn" style="padding:7px 12px;font-size:10px;background:#7a7a6a" href="producto.php?slug=<?= h($m['slug_prod']) ?>">👁 Ver</a>
      <form method="post" onsubmit="return confirm('¿Eliminar «<?= h($m['nom_prod']) ?>» de tu tienda con todas tus variantes?')"><?= csrf() ?>
        <input type="hidden" name="accion" value="eliminar_producto"><input type="hidden" name="id_prod" value="<?= $m['id_prod'] ?>">
        <button class="btn" style="padding:7px 12px;font-size:10px;background:#a33">🗑 Eliminar</button></form></div>
  </div>
<?php endforeach; if (!$mios) echo '<p>Aún no tienes productos. Publica el primero abajo.</p>'; ?></div>

<h3>Publicar producto</h3>
<form class="caja caja-ancha" method="post" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="accion" value="crear">
  <label>Producto</label>
  <select name="id_prod" id="selProd"><option value="0">— Producto nuevo —</option>
    <?php foreach ($prods as $p) echo '<option value="' . $p['id_prod'] . '">' . h($p['nom_prod']) . '</option>'; ?></select>
  <div id="nuevo">
    <label>Nombre del producto nuevo</label><input name="nom_prod" maxlength="150">
    <label>Categoría</label><select name="id_cat"><?php foreach ($cats as $c) echo '<option value="' . $c['id_cat'] . '">' . h($c['etiqueta']) . '</option>'; ?></select>
    <p class="hint">¿No encuentras tu categoría? <?= pedir_admin('una nueva categoría') ?></p>
    <label>Descripción</label><input name="desc_prod">
  </div>
  <?php form_variantes(claves_que_vende($idEmp)); ?>
  <button class="btn" style="margin-top:14px">Publicar</button>
</form>
<script>const sp = document.getElementById('selProd'), nv = document.getElementById('nuevo');
sp.addEventListener('change', () => nv.style.display = sp.value === '0' ? 'block' : 'none');</script>
</section>
<?php require 'includes/footer.php'; ?>
