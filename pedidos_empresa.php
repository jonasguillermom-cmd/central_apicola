<?php
require_once 'config.php';
requiere_login();
$emps = q('SELECT e.* FROM empresa_usuarios eu JOIN empresas e ON e.id_emp=eu.id_emp WHERE eu.id_usr=?', [$_SESSION['id_usr']])->fetchAll();
if (!$emps) { header('Location: registro_empresa.php'); exit; }
$emp = $emps[0];
foreach ($emps as $x) if (isset($_GET['emp']) && $x['id_emp'] == (int)$_GET['emp']) $emp = $x;
$idEmp = (int)$emp['id_emp']; $msg = $err = '';

if ($emp['est_emp'] === 'A' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $pdo->beginTransaction();
        $pem = q('SELECT pe.*, (SELECT metodo_pag FROM pagos WHERE id_ped=pe.id_ped ORDER BY id_pag DESC LIMIT 1) AS metodo
                  FROM pedidos_empresa pe WHERE pe.id_pem=? AND pe.id_emp=? FOR UPDATE', [(int)($_POST['id_pem'] ?? 0), $idEmp])->fetch();
        if (!$pem) throw new RuntimeException('Pedido no encontrado.');
        if (($_POST['accion'] ?? '') === 'cancelar') { cancelar_pem((int)$pem['id_pem']); $msg = 'Pedido cancelado y stock devuelto.'; }
        else {
            $sig = ['pagado' => 'preparando', 'preparando' => 'enviado', 'enviado' => 'entregado'][$pem['estado_pem']] ?? null;
            if ($pem['estado_pem'] === 'pendiente' && $pem['metodo'] === 'efectivo') $sig = 'preparando';   // pago contra entrega
            if (!$sig) throw new RuntimeException('Este pedido no puede avanzar todavía.');
            if ($sig === 'enviado') {
                $paq = trim($_POST['paq'] ?? ''); $guia = trim($_POST['guia'] ?? '');
                if ($paq === '' || mb_strlen($paq) > 40 || !preg_match('/^[A-Za-z0-9-]{4,40}$/', $guia)) throw new RuntimeException('Escribe la paquetería y una guía válida (letras, números o guiones).');
                q('UPDATE pedidos_empresa SET estado_pem=?, paqueteria_pem=?, guia_pem=?, fecha_env_pem=NOW() WHERE id_pem=?', [$sig, $paq, $guia, $pem['id_pem']]);
            } elseif ($sig === 'entregado') q('UPDATE pedidos_empresa SET estado_pem=?, fecha_ent_pem=NOW() WHERE id_pem=?', [$sig, $pem['id_pem']]);
            else q('UPDATE pedidos_empresa SET estado_pem=? WHERE id_pem=?', [$sig, $pem['id_pem']]);
            sincronizar_pedido((int)$pem['id_ped']);
            $msg = 'Pedido actualizado: ' . etq_estado($sig);
        }
        $pdo->commit();
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'No se pudo actualizar el pedido.'; }
}
$titulo = 'Pedidos — ' . $emp['nom_emp']; require 'includes/header.php';
if ($emp['est_emp'] !== 'A') { echo '<section class="sec"><h2>Tu empresa aún no está activa</h2></section>'; require 'includes/footer.php'; exit; }
$f = $_GET['estado'] ?? '';
$lista = q('SELECT pe.*, p.fecha_reg_ped, p.dir_envio_ped, u.nom_usr, u.tel_usr,
              (SELECT metodo_pag FROM pagos WHERE id_ped=pe.id_ped ORDER BY id_pag DESC LIMIT 1) AS metodo
            FROM pedidos_empresa pe JOIN pedidos p ON p.id_ped=pe.id_ped JOIN usuarios u ON u.id_usr=p.id_usr
            WHERE pe.id_emp=?' . ($f !== '' ? ' AND pe.estado_pem=?' : '') . ' ORDER BY pe.id_pem DESC LIMIT 100', $f !== '' ? [$idEmp, $f] : [$idEmp])->fetchAll();
?>
<section class="sec"><h2>📦 PEDIDOS DE <?= h(mb_strtoupper($emp['nom_emp'])) ?></h2>
<p style="text-align:center"><a href="panel_empresa.php?emp=<?= $idEmp ?>">← Volver al panel</a> ·
  <?php foreach (['' => 'Todos', 'pagado' => 'Por preparar', 'preparando' => 'Preparando', 'enviado' => 'Enviados', 'entregado' => 'Entregados', 'cancelado' => 'Cancelados'] as $k => $v)
    echo '<a href="?emp=' . $idEmp . '&estado=' . $k . '" style="' . ($f === $k ? 'font-weight:700' : '') . '">' . $v . '</a> '; ?></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>
<?php foreach ($lista as $o): ?>
  <div class="resumen-card" style="margin-bottom:20px;max-width:none">
    <div class="resumen-row"><b>Pedido #<?= (int)$o['id_ped'] ?>-<?= (int)$o['id_pem'] ?></b><span><?= h(date('d/m/Y H:i', strtotime($o['fecha_reg_ped']))) ?> · <?= etq_estado($o['estado_pem']) ?> · <?= h($o['metodo'] ?? 'sin pago') ?></span></div>
    <?php foreach (q('SELECT * FROM pedido_items WHERE id_pem=?', [$o['id_pem']])->fetchAll() as $it): ?>
      <div class="resumen-row"><span><?= (int)$it['cant_pei'] ?> × <?= h($it['nom_pei']) ?></span><span><?= dinero($it['precio_pei'] * $it['cant_pei']) ?></span></div><?php endforeach; ?>
    <div class="resumen-row"><span>Envío</span><span><?= dinero($o['envio_pem']) ?></span></div>
    <div class="resumen-row resumen-total"><span>Total</span><span><?= dinero($o['total_pem']) ?></span></div>
    <p style="font-size:13px;margin-top:8px">👤 <?= h($o['nom_usr']) ?><?= $o['tel_usr'] ? ' · ' . h($o['tel_usr']) : '' ?><br>📍 <?= h($o['dir_envio_ped']) ?>
      <?php if ($o['guia_pem']): ?><br>🚚 <?= h($o['paqueteria_pem']) ?> · guía <?= h($o['guia_pem']) ?><?php endif; ?></p>
    <?php $e = $o['estado_pem']; $puede = in_array($e, ['pagado', 'preparando', 'enviado'], true) || ($e === 'pendiente' && $o['metodo'] === 'efectivo'); ?>
    <?php if ($e === 'pendiente' && !$puede): ?><p style="font-size:13px;color:#a66">⏳ Esperando confirmación del pago.</p><?php endif; ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
    <?php if ($puede): ?><form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><?= csrf() ?><input type="hidden" name="id_pem" value="<?= $o['id_pem'] ?>"><input type="hidden" name="accion" value="avanzar">
      <?php if ($e === 'preparando'): ?><input name="paq" placeholder="Paquetería" maxlength="40" required><input name="guia" placeholder="Número de guía" maxlength="40" required><?php endif; ?>
      <button class="btn"><?= ['pendiente' => 'Empezar a preparar', 'pagado' => 'Empezar a preparar', 'preparando' => 'Marcar enviado', 'enviado' => 'Marcar entregado'][$e] ?></button></form><?php endif; ?>
    <?php if (in_array($e, ['pendiente', 'pagado', 'preparando'], true)): ?><form method="post" onsubmit="return confirm('¿Cancelar este pedido? El stock se devolverá.')"><?= csrf() ?>
      <input type="hidden" name="id_pem" value="<?= $o['id_pem'] ?>"><input type="hidden" name="accion" value="cancelar"><button class="btn" style="background:#a33">Cancelar</button></form><?php endif; ?>
    </div></div>
<?php endforeach; if (!$lista) echo '<p style="text-align:center">No hay pedidos en esta vista.</p>'; ?>
</section>
<?php require 'includes/footer.php'; ?>
