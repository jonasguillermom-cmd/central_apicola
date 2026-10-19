<?php
require_once 'config.php';
requiere_login();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && ($_POST['accion'] ?? '') === 'cancelar') {
    try {
        $pdo->beginTransaction();
        $ped = q("SELECT id_ped FROM pedidos WHERE id_ped=? AND id_usr=? AND estado_ped='pendiente' FOR UPDATE", [(int)($_POST['id_ped'] ?? 0), $_SESSION['id_usr']])->fetch();
        if (!$ped) throw new RuntimeException('Este pedido ya no se puede cancelar.');
        $pems = q('SELECT id_pem, estado_pem FROM pedidos_empresa WHERE id_ped=?', [$ped['id_ped']])->fetchAll();
        foreach ($pems as $pm) if ($pm['estado_pem'] !== 'pendiente') throw new RuntimeException('Una de las tiendas ya empezó a preparar tu pedido. Contáctanos para cancelarlo.');
        foreach ($pems as $pm) cancelar_pem((int)$pm['id_pem']);
        $pdo->commit();
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'No se pudo cancelar el pedido.'; }
}
$estados = ['pendiente' => '⏳ Pendiente', 'pagado' => '💳 Pagado', 'completado' => '✅ Completado', 'cancelado' => '⛔ Cancelado',
            'preparando' => '📦 Preparando', 'enviado' => '🚚 Enviado', 'entregado' => '✅ Entregado'];
$peds = q('SELECT * FROM pedidos WHERE id_usr=? ORDER BY id_ped DESC', [$_SESSION['id_usr']])->fetchAll();
$titulo = 'Mis pedidos — Central Apícola del Usumacinta'; require 'includes/header.php';
?>
<div class="carrito-wrap"><h2 class="carrito-titulo">Mis pedidos</h2>
<?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>
<?php if (!$peds): ?><div class="carrito-vacio"><div class="icon">📦</div><p>Aún no has hecho pedidos</p>
  <a href="index.php" class="btn-link" style="background:var(--naranja);color:#fff;padding:12px 28px;border-radius:8px;font-weight:700">Ver productos</a></div><?php endif; ?>
<?php foreach ($peds as $p): ?>
  <div class="resumen-card" style="margin-bottom:22px">
    <div class="resumen-row"><b>Pedido #<?= (int)$p['id_ped'] ?></b><span><?= h(date('d/m/Y H:i', strtotime($p['fecha_reg_ped']))) ?> · <?= $estados[$p['estado_ped']] ?></span></div>
    <?php foreach (q('SELECT pe.*, e.nom_emp FROM pedidos_empresa pe JOIN empresas e ON e.id_emp=pe.id_emp WHERE pe.id_ped=?', [$p['id_ped']])->fetchAll() as $pe): ?>
      <p style="margin-top:12px;font-weight:700">🏪 <?= h($pe['nom_emp']) ?> — <?= $estados[$pe['estado_pem']] ?>
        <?php if ($pe['guia_pem']): ?><small style="font-weight:400"> · <?= h($pe['paqueteria_pem']) ?> guía <?= h($pe['guia_pem']) ?></small><?php endif; ?></p>
      <?php foreach (q('SELECT * FROM pedido_items WHERE id_pem=?', [$pe['id_pem']])->fetchAll() as $it): ?>
        <div class="resumen-row"><span><?= (int)$it['cant_pei'] ?> × <?= h($it['nom_pei']) ?></span><span><?= dinero($it['precio_pei'] * $it['cant_pei']) ?></span></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($p['desc_ped'] > 0): ?><div class="resumen-row" style="color:#1e8449"><span>Descuento</span><span>-<?= dinero($p['desc_ped']) ?></span></div><?php endif; ?>
    <div class="resumen-row"><span>Envío</span><span><?= $p['envio_ped'] > 0 ? dinero($p['envio_ped']) : 'Gratis' ?></span></div>
    <div class="resumen-row resumen-total"><span>Total</span><span><?= dinero($p['total_ped']) ?></span></div>
    <?php if ($p['estado_ped'] === 'pendiente'): ?><form method="post" onsubmit="return confirm('¿Cancelar este pedido?')" style="margin-top:10px"><?= csrf() ?><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="id_ped" value="<?= $p['id_ped'] ?>"><button class="btn" style="background:#a33">Cancelar pedido</button></form><?php endif; ?>
    <p style="font-size:12px;color:#666;margin-top:8px">Envío a: <?= h($p['dir_envio_ped']) ?></p>
  </div>
<?php endforeach; ?></div>
<?php require 'includes/footer.php'; ?>
