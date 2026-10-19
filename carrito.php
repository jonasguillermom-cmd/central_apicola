<?php
require_once 'config.php';
requiere_login();
$u = (int)$_SESSION['id_usr'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $ofe = (int)($_POST['id_ofe'] ?? 0); $accion = $_POST['accion'] ?? '';
    if ($accion === 'cupon') { $_SESSION['cupon'] = strtoupper(trim($_POST['codigo'] ?? '')); }
    elseif ($accion === 'quitar_cupon') { unset($_SESSION['cupon']); }
    elseif ($accion === 'agregar') {
        $o = q("SELECT o.stock_ofe FROM ofertas o JOIN empresas e ON e.id_emp=o.id_emp AND e.est_emp='A'
                WHERE o.id_ofe=? AND o.est_ofe='A' AND o.stock_ofe>0", [$ofe])->fetch();
        if ($o) {
            $cap = min(99, (int)$o['stock_ofe']); $cant = max(1, min($cap, (int)($_POST['cant'] ?? 1)));
            q('INSERT INTO carrito (id_usr,id_ofe,cant_car) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cant_car=LEAST(cant_car+?,?)', [$u, $ofe, $cant, $cant, $cap]);
        }
    } elseif ($accion === 'quitar') {
        q('DELETE FROM carrito WHERE id_usr=? AND id_ofe=?', [$u, $ofe]);
    } elseif ($accion === 'cambiar') {
        $d = ((int)($_POST['delta'] ?? 0)) < 0 ? -1 : 1;
        q('UPDATE carrito c JOIN ofertas o ON o.id_ofe=c.id_ofe SET c.cant_car=GREATEST(1,LEAST(c.cant_car+?,o.stock_ofe,99)) WHERE c.id_usr=? AND c.id_ofe=?', [$d, $u, $ofe]);
    }
    header('Location: carrito.php'); exit;
}
$titulo = 'Mi Carrito — Central Apícola del Usumacinta';
require 'includes/header.php';
$items = q("SELECT c.id_ofe, c.cant_car, o.id_emp, COALESCE(o.precio_desc_ofe,o.precio_ofe) AS precio, p.nom_prod, p.slug_prod, e.nom_emp,
      (SELECT url_img FROM producto_imagenes i WHERE i.id_prod=p.id_prod ORDER BY (i.id_var <=> v.id_var) DESC, i.orden_img LIMIT 1) AS img,
      (SELECT GROUP_CONCAT(av.valor_atv SEPARATOR ' / ') FROM variante_valores vv JOIN atributo_valores av ON av.id_atv=vv.id_atv WHERE vv.id_var=v.id_var) AS variante
    FROM carrito c JOIN ofertas o ON o.id_ofe=c.id_ofe JOIN producto_variantes v ON v.id_var=o.id_var
    JOIN productos p ON p.id_prod=v.id_prod JOIN empresas e ON e.id_emp=o.id_emp WHERE c.id_usr=?", [$u])->fetchAll();
$total = 0; foreach ($items as $i) $total += $i['precio'] * $i['cant_car'];
$desc = 0; $cupon = null; $msgCupon = '';
if (!empty($_SESSION['cupon'])) { [$cupon, $desc, $msgCupon] = cupon_evaluar($_SESSION['cupon'], $u, $items); if (!$cupon) unset($_SESSION['cupon']); }
$totalFinal = max(0, $total - $desc);
?>
<div class="carrito-wrap"><h2 class="carrito-titulo">Mi Carrito</h2>
<?php if (!$items): ?>
  <div class="carrito-vacio"><div class="icon">🛒</div><p>Tu carrito está vacío</p>
    <a href="index.php" class="btn-link" style="background:var(--naranja);color:#fff;padding:12px 28px;border-radius:8px;font-weight:700;">Ver productos</a></div>
<?php else: ?>
  <table class="carrito-tabla"><thead><tr><th></th><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th><th></th></tr></thead><tbody>
  <?php foreach ($items as $i): $sub = $i['precio'] * $i['cant_car']; ?>
    <tr><td><img class="prod-thumb" src="<?= h($i['img'] ?: 'imagen/logo_centro.png') ?>" alt="" onerror="this.style.background='#f5f0e8'"></td>
      <td><div class="prod-nombre"><a href="producto.php?slug=<?= h($i['slug_prod']) ?>"><?= h($i['nom_prod']) ?></a></div>
        <small style="color:#7a7a6a"><?= h($i['variante']) ?> · <?= h($i['nom_emp']) ?></small></td>
      <td><?= dinero($i['precio']) ?></td>
      <td><div class="qty-ctrl">
        <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="accion" value="cambiar"><input type="hidden" name="id_ofe" value="<?= $i['id_ofe'] ?>"><input type="hidden" name="delta" value="-1"><button class="qty-btn">−</button></form>
        <span class="qty-num"><?= $i['cant_car'] ?></span>
        <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="accion" value="cambiar"><input type="hidden" name="id_ofe" value="<?= $i['id_ofe'] ?>"><input type="hidden" name="delta" value="1"><button class="qty-btn">+</button></form></div></td>
      <td><?= dinero($sub) ?></td>
      <td><form method="post"><?= csrf() ?><input type="hidden" name="accion" value="quitar"><input type="hidden" name="id_ofe" value="<?= $i['id_ofe'] ?>"><button class="btn-eliminar" title="Eliminar">🗑</button></form></td></tr>
  <?php endforeach; ?></tbody></table>
  <div class="carrito-resumen"><div class="resumen-card">
    <?php if ($cupon): ?><div class="msg-cupon ok">✓ Cupón "<?= h($cupon['cod_cup']) ?>" aplicado.
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="accion" value="quitar_cupon"><button style="background:none;border:0;color:#c0392b;cursor:pointer;font-weight:700">Quitar</button></form></div>
    <?php else: ?><form method="post" class="campo-cupon"><?= csrf() ?><input type="hidden" name="accion" value="cupon">
      <input type="text" name="codigo" placeholder="Código de cupón" style="text-transform:uppercase"><button>Aplicar</button></form><?php endif; ?>
    <?php if ($msgCupon): ?><div class="msg-cupon error"><?= h($msgCupon) ?></div><?php endif; ?>
    <p style="font-size:12px;margin:6px 0 12px"><a href="cupones.php">Ver mis cupones</a></p>
    <div class="resumen-row"><span>Subtotal</span><span><?= dinero($total) ?></span></div>
    <?php if ($cupon): ?><div class="resumen-row" style="color:#1e8449"><span>Descuento (<?= h($cupon['cod_cup']) ?>)</span><span>-<?= dinero($desc) ?></span></div><?php endif; ?>
    <div class="resumen-row"><span>Envío</span><span>Se calcula al pagar</span></div>
    <div class="resumen-row resumen-total"><span>Total</span><span><?= dinero($totalFinal) ?></span></div>
    <a href="checkout.php" class="btn-link" style="display:block;text-align:center;background:var(--naranja);color:#fff;padding:12px;border-radius:2px;font-weight:700;margin-top:14px">Continuar con el pago</a>
  </div></div>
<?php endif; ?></div>
<?php require 'includes/footer.php'; ?>
