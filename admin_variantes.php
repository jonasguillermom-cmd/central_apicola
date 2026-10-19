<?php
require_once 'config.php';
require_once 'includes/catalogo.php';
if (!es_admin()) { http_response_code(403); exit('Acceso restringido.'); }
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $pdo->beginTransaction();
        $idVar = (int)($_POST['id_var'] ?? 0);
        if (!q('SELECT 1 FROM producto_variantes WHERE id_var=? FOR UPDATE', [$idVar])->fetch()) throw new RuntimeException('Variante no encontrada.');
        $ofertas = q('SELECT id_ofe, id_emp FROM ofertas WHERE id_var=?', [$idVar])->fetchAll();
        $b = $p = 0;
        foreach ($ofertas as $o) eliminar_oferta((int)$o['id_ofe'], (int)$o['id_emp']) === 'borrada' ? $b++ : $p++;
        if (!$ofertas) limpiar_variante($idVar);                    // variante sin ninguna tienda: se borra directo
        $pdo->commit();
        $msg = $p ? "$p oferta(s) ya tenían pedidos y se pausaron para conservar el historial; la variante se conserva." . ($b ? " Se eliminaron $b oferta(s) más." : '')
                  : 'Variante eliminada' . ($b ? " (con $b oferta(s) de tiendas)." : '.');
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($x->getMessage()); $err = 'No se pudo eliminar la variante.'; }
}
$busca = trim($_GET['q'] ?? '');
$rows = q("SELECT v.id_var, p.id_prod, p.nom_prod, p.est_prod,
    (SELECT GROUP_CONCAT(av.valor_atv ORDER BY av.id_atr SEPARATOR ' / ') FROM variante_valores vv JOIN atributo_valores av ON av.id_atv=vv.id_atv WHERE vv.id_var=v.id_var) AS variante,
    (SELECT COUNT(*) FROM ofertas o WHERE o.id_var=v.id_var) AS n_ofe,
    (SELECT COALESCE(SUM(o.stock_ofe),0) FROM ofertas o WHERE o.id_var=v.id_var) AS stock,
    (SELECT COUNT(*) FROM producto_imagenes i WHERE i.id_var=v.id_var) AS n_fotos,
    (SELECT COUNT(*) FROM pedido_items pi JOIN ofertas o ON o.id_ofe=pi.id_ofe WHERE o.id_var=v.id_var) AS n_ped
  FROM producto_variantes v JOIN productos p ON p.id_prod=v.id_prod
  WHERE (? = '' OR p.nom_prod LIKE ?) ORDER BY p.nom_prod, variante LIMIT 300", [$busca, '%' . $busca . '%'])->fetchAll();
$titulo = 'Admin - Variantes'; require 'includes/header.php';
?>
<section class="sec"><h2>🧩 VARIANTES DE PRODUCTOS</h2>
<p style="text-align:center"><a href="admin_catalogo.php">← Catálogo</a> · Al eliminar una variante se quitan también las ofertas de todas las tiendas que la venden.</p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>
<form method="get" style="text-align:center;margin-bottom:16px"><input name="q" value="<?= h($busca) ?>" placeholder="Buscar producto…" style="padding:8px;width:260px"> <button class="btn">Buscar</button></form>
<table><tr><th>Producto</th><th>Variante</th><th>Tiendas</th><th>Stock</th><th>Fotos</th><th>Pedidos</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr>
  <td><b><?= h($r['nom_prod']) ?></b><?= $r['est_prod'] === 'I' ? ' <small>(oculto)</small>' : '' ?></td><td><?= h($r['variante'] ?: 'Única') ?></td>
  <td><?= (int)$r['n_ofe'] ?></td><td><?= (int)$r['stock'] ?></td><td><?= (int)$r['n_fotos'] ?></td><td><?= (int)$r['n_ped'] ?></td>
  <td><form method="post" onsubmit="return confirm('<?= $r['n_ped'] > 0 ? 'Esta variante ya tiene pedidos: las ofertas se pausarán en lugar de borrarse. ¿Continuar?' : '¿Eliminar esta variante y sus ofertas?' ?>')"><?= csrf() ?>
    <input type="hidden" name="id_var" value="<?= $r['id_var'] ?>"><button class="btn" style="background:#a33;padding:7px 12px;font-size:10px"><?= $r['n_ped'] > 0 ? '⏸ Pausar' : '🗑 Eliminar' ?></button></form></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7">No hay variantes' . ($busca ? ' con ese nombre' : '') . '.</td></tr>'; ?></table>
<p class="hint" style="text-align:center;margin-top:12px">Las variantes con pedidos no se pueden borrar para conservar el historial de ventas: sus ofertas se pausan y dejan de mostrarse en la tienda.</p>
</section>
<?php require 'includes/footer.php'; ?>
