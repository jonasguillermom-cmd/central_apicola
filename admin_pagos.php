<?php
require_once 'config.php';
if (!es_admin()) { http_response_code(403); exit('Acceso restringido.'); }
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $idPag = (int)($_POST['id_pag'] ?? 0); $accion = $_POST['accion'] ?? '';
    try {
        $pdo->beginTransaction();
        $pag = q("SELECT * FROM pagos WHERE id_pag=? AND estado_pag='pendiente' FOR UPDATE", [$idPag])->fetch();
        if (!$pag) throw new RuntimeException('Ese pago ya fue procesado.');
        if ($accion === 'aprobar') {
            q("UPDATE pagos SET estado_pag='aprobado', ref_pag=? WHERE id_pag=?", [trim($_POST['ref'] ?? '') ?: null, $idPag]);
            q("UPDATE pedidos SET estado_ped='pagado' WHERE id_ped=? AND estado_ped='pendiente'", [$pag['id_ped']]);
            q("UPDATE pedidos_empresa SET estado_pem='pagado' WHERE id_ped=? AND estado_pem='pendiente'", [$pag['id_ped']]);
            $msg = 'Pago aprobado. Las empresas ya pueden preparar el pedido.';
        } elseif ($accion === 'rechazar') {
            foreach (q("SELECT id_pem FROM pedidos_empresa WHERE id_ped=? AND estado_pem IN ('pendiente','pagado','preparando')", [$pag['id_ped']])->fetchAll(PDO::FETCH_COLUMN) as $pem) cancelar_pem((int)$pem);
            q("UPDATE pagos SET estado_pag='rechazado' WHERE id_pag=?", [$idPag]);
            $msg = 'Pago rechazado y pedido cancelado (stock devuelto).';
        }
        $pdo->commit();
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'No se pudo procesar el pago.'; }
}
$pend = q("SELECT g.*, p.total_ped, p.fecha_reg_ped, u.nom_usr, u.correo_usr FROM pagos g JOIN pedidos p ON p.id_ped=g.id_ped JOIN usuarios u ON u.id_usr=p.id_usr
           WHERE g.estado_pag='pendiente' ORDER BY g.id_pag")->fetchAll();
$hist = q("SELECT g.*, u.nom_usr FROM pagos g JOIN pedidos p ON p.id_ped=g.id_ped JOIN usuarios u ON u.id_usr=p.id_usr
           WHERE g.estado_pag<>'pendiente' ORDER BY g.id_pag DESC LIMIT 15")->fetchAll();
$titulo = 'Admin - Pagos'; require 'includes/header.php';
?>
<section class="sec"><h2>💳 PAGOS</h2><p style="text-align:center"><a href="admin_empresas.php">← Empresas</a></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>
<h3>Pendientes de confirmar</h3>
<table><tr><th>Pedido</th><th>Cliente</th><th>Método</th><th>Monto</th><th>Fecha</th><th>Referencia / acción</th></tr>
<?php foreach ($pend as $g): ?><tr><td>#<?= (int)$g['id_ped'] ?></td><td><?= h($g['nom_usr']) ?><br><small><?= h($g['correo_usr']) ?></small></td>
  <td><?= h($g['metodo_pag']) ?></td><td><?= dinero($g['monto_pag']) ?></td><td><?= h(date('d/m/Y H:i', strtotime($g['fecha_reg_ped']))) ?></td>
  <td><form method="post"><?= csrf() ?><input type="hidden" name="id_pag" value="<?= $g['id_pag'] ?>">
    <input name="ref" placeholder="Folio / referencia" maxlength="80" size="16">
    <button class="btn" name="accion" value="aprobar">Aprobar</button>
    <button class="btn" name="accion" value="rechazar" style="background:#a33" onclick="return confirm('¿Rechazar y cancelar el pedido?')">Rechazar</button></form></td></tr>
<?php endforeach; if (!$pend) echo '<tr><td colspan="6">No hay pagos pendientes.</td></tr>'; ?></table>
<h3 style="margin-top:32px">Últimos procesados</h3>
<table><tr><th>Pedido</th><th>Cliente</th><th>Método</th><th>Monto</th><th>Estado</th><th>Ref.</th></tr>
<?php foreach ($hist as $g): ?><tr><td>#<?= (int)$g['id_ped'] ?></td><td><?= h($g['nom_usr']) ?></td><td><?= h($g['metodo_pag']) ?></td><td><?= dinero($g['monto_pag']) ?></td><td><?= etq_estado($g['estado_pag']) ?></td><td><?= h($g['ref_pag']) ?></td></tr><?php endforeach; ?></table>
</section>
<?php require 'includes/footer.php'; ?>
