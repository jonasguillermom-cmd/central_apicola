<?php
require_once 'config.php';
require_once 'includes/catalogo.php';
requiere_login();
$emp = empresa_del_usuario();
if (!$emp) { header('Location: registro_empresa.php'); exit; }
$idEmp = (int)$emp['id_emp']; $idProd = (int)($_GET['id_prod'] ?? $_POST['id_prod'] ?? 0);
$vende = fn() => (bool)q('SELECT 1 FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_emp=? AND v.id_prod=? LIMIT 1', [$idEmp, $idProd])->fetch();
if ($emp['est_emp'] !== 'A' || !$vende()) { header('Location: panel_empresa.php?emp=' . $idEmp); exit; }
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $a = $_POST['accion'] ?? '';
        $pdo->beginTransaction();
        if ($a === 'producto') {
            if (!es_unico_vendedor($idProd, $idEmp)) throw new RuntimeException('Este producto lo comparten otras tiendas: solo el administrador puede cambiar su nombre o categoría.');
            $nom = trim($_POST['nom'] ?? ''); $cat = (int)($_POST['id_cat'] ?? 0);
            if ($nom === '' || mb_strlen($nom) > 150 || !q("SELECT 1 FROM categorias WHERE id_cat=? AND est_cat='A'", [$cat])->fetch()) throw new RuntimeException('Revisa el nombre y la categoría.');
            q('UPDATE productos SET nom_prod=?, id_cat=?, desc_prod=? WHERE id_prod=?', [$nom, $cat, trim($_POST['desc'] ?? '') ?: null, $idProd]);
            $msg = 'Producto actualizado.';
        } elseif ($a === 'agregar') {
            $msg = 'Agregaste ' . publicar_combinaciones($idEmp, $idProd, $_POST, $_FILES) . ' variante(s).';
        } elseif ($a === 'actualizar') {
            $o = q('SELECT o.* FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_ofe=? AND o.id_emp=? AND v.id_prod=?', [(int)($_POST['id_ofe'] ?? 0), $idEmp, $idProd])->fetch();
            if (!$o) throw new RuntimeException('Oferta no encontrada.');
            $precio = (float)str_replace(',', '.', $_POST['precio'] ?? 0); $desc = (float)str_replace(',', '.', $_POST['precio_desc'] ?? 0) ?: null;
            $stock = max(0, min(999999, (int)($_POST['stock'] ?? 0)));
            if ($precio <= 0 || ($desc !== null && $desc >= $precio)) throw new RuntimeException('Revisa los precios.');
            q('UPDATE ofertas SET precio_ofe=?, precio_desc_ofe=?, stock_ofe=?, est_ofe=? WHERE id_ofe=?', [$precio, $desc, $stock, ($_POST['est'] ?? 'A') === 'I' ? 'I' : 'A', $o['id_ofe']]);
            if ($stock != $o['stock_ofe']) q("INSERT INTO inventario_movimientos (id_ofe,id_usr,tipo_mov,cant_mov,stock_res_mov,nota_mov) VALUES (?,?, 'ajuste',?,?, 'Ajuste manual')",
                                             [$o['id_ofe'], $_SESSION['id_usr'], $stock - $o['stock_ofe'], $stock]);
            $msg = 'Variante actualizada.';
        } elseif ($a === 'eliminar_oferta') {
            $ok = q('SELECT 1 FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_ofe=? AND v.id_prod=?', [(int)($_POST['id_ofe'] ?? 0), $idProd])->fetch();
            if (!$ok) throw new RuntimeException('Oferta no encontrada.');
            $msg = eliminar_oferta((int)$_POST['id_ofe'], $idEmp) === 'borrada' ? 'Variante eliminada.' : 'Esa variante ya tiene pedidos: se pausó para conservar el historial.';
        } elseif ($a === 'eliminar_producto') {
            [$b, $p] = eliminar_producto_empresa($idProd, $idEmp); $msg = msg_borrado($b, $p);
        }
        $pdo->commit();
        if (!$vende()) { $_SESSION['flash'] = $msg ?: 'Producto eliminado.'; header('Location: panel_empresa.php?emp=' . $idEmp); exit; }
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($x->getMessage()); $err = 'No se pudo guardar.'; }
}
$p = q('SELECT * FROM productos WHERE id_prod=?', [$idProd])->fetch();
$cats = q("SELECT id_cat, nom_cat AS etiqueta FROM categorias WHERE est_cat='A' ORDER BY nom_cat")->fetchAll();
$ofertas = q("SELECT o.*, (SELECT COUNT(*) FROM producto_imagenes i WHERE i.id_var=o.id_var) AS n_fotos,
      (SELECT GROUP_CONCAT(av.valor_atv ORDER BY av.id_atr SEPARATOR ' / ') FROM variante_valores vv JOIN atributo_valores av ON av.id_atv=vv.id_atv WHERE vv.id_var=o.id_var) AS variante
    FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_emp=? AND v.id_prod=? ORDER BY variante", [$idEmp, $idProd])->fetchAll();
$unico = es_unico_vendedor($idProd, $idEmp);
$titulo = 'Editar — ' . $p['nom_prod']; require 'includes/header.php';
?>
<section class="sec"><h2>✏️ <?= h(mb_strtoupper($p['nom_prod'])) ?></h2>
<p style="text-align:center"><a href="panel_empresa.php?emp=<?= $idEmp ?>">← Volver al panel</a> · <a href="producto.php?slug=<?= h($p['slug_prod']) ?>">👁 Ver en la tienda</a></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>

<h3>Datos del producto</h3>
<?php if ($unico): ?>
<form class="caja caja-ancha" method="post"><?= csrf() ?><input type="hidden" name="accion" value="producto"><input type="hidden" name="id_prod" value="<?= $idProd ?>">
  <label>Nombre</label><input name="nom" maxlength="150" value="<?= h($p['nom_prod']) ?>" required>
  <label>Categoría</label><select name="id_cat"><?php foreach ($cats as $c) echo '<option value="' . $c['id_cat'] . '"' . ($c['id_cat'] == $p['id_cat'] ? ' selected' : '') . '>' . h($c['etiqueta']) . '</option>'; ?></select>
  <label>Descripción</label><input name="desc" value="<?= h($p['desc_prod']) ?>">
  <button class="btn">Guardar datos</button></form>
<?php else: ?><p class="hint" style="margin:0 0 18px">Este producto lo venden también otras tiendas, así que su nombre, categoría y descripción solo los cambia el administrador. Tú controlas tus variantes, precios, stock y fotos.</p><?php endif; ?>

<h3>Mis variantes</h3>
<table><tr><th>Variante</th><th>Precio</th><th>Promo</th><th>Stock</th><th>Estado</th><th>Fotos</th><th></th></tr>
<?php foreach ($ofertas as $o): ?>
<form method="post"><tr><?= csrf() ?><input type="hidden" name="id_prod" value="<?= $idProd ?>"><input type="hidden" name="id_ofe" value="<?= $o['id_ofe'] ?>">
<td><b><?= h($o['variante'] ?: 'Única') ?></b></td>
<td><input name="precio" size="7" value="<?= h($o['precio_ofe']) ?>"></td><td><input name="precio_desc" size="7" value="<?= h($o['precio_desc_ofe']) ?>"></td>
<td><input name="stock" size="5" value="<?= h($o['stock_ofe']) ?>"></td>
<td><select name="est"><option value="A" <?= $o['est_ofe'] === 'A' ? 'selected' : '' ?>>Activa</option><option value="I" <?= $o['est_ofe'] === 'I' ? 'selected' : '' ?>>Pausada</option></select></td>
<td><a href="fotos_producto.php?emp=<?= $idEmp ?>&ofe=<?= $o['id_ofe'] ?>">📷 <?= (int)$o['n_fotos'] ?></a></td>
<td style="white-space:nowrap"><button class="btn" name="accion" value="actualizar">Guardar</button>
  <button class="btn" name="accion" value="eliminar_oferta" style="background:#a33" onclick="return confirm('¿Eliminar esta variante?')">🗑</button></td></tr></form>
<?php endforeach; ?></table>

<h3 style="margin-top:34px">Agregar más variantes</h3>
<form class="caja caja-ancha" method="post" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="accion" value="agregar"><input type="hidden" name="id_prod" value="<?= $idProd ?>">
  <?php form_variantes(claves_que_vende($idEmp), $idProd); ?>
  <button class="btn" style="margin-top:14px">Agregar variantes</button></form>

<h3 style="margin-top:34px">Zona de peligro</h3>
<form method="post" onsubmit="return confirm('¿Eliminar este producto de tu tienda con todas tus variantes?')"><?= csrf() ?><input type="hidden" name="accion" value="eliminar_producto"><input type="hidden" name="id_prod" value="<?= $idProd ?>">
  <button class="btn" style="background:#a33">🗑 Eliminar este producto de mi tienda</button>
  <p class="hint" style="margin-top:8px">Si una variante ya tiene pedidos, se pausa en lugar de borrarse para conservar el historial de ventas.</p></form>
</section>
<?php require 'includes/footer.php'; ?>
