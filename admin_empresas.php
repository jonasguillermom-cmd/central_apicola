<?php
require_once 'config.php';
if (!es_admin()) { http_response_code(403); exit('Acceso restringido.'); }
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)($_POST['id_emp'] ?? 0); $accion = $_POST['accion'] ?? '';
    $pdo->beginTransaction();
    try {
        if ($accion === 'aprobar') {
            q("UPDATE empresas SET est_emp='A' WHERE id_emp=?", [$id]);
            q("UPDATE usuarios u JOIN empresa_usuarios eu ON eu.id_usr=u.id_usr
               SET u.rol_usr='vendedor' WHERE eu.id_emp=? AND eu.rol_emu='propietario' AND u.rol_usr='cliente'", [$id]);
            $msg = 'Empresa aprobada.';
        } elseif ($accion === 'desactivar') { q("UPDATE empresas SET est_emp='I' WHERE id_emp=?", [$id]); $msg = 'Empresa desactivada.'; }
          elseif ($accion === 'reactivar')  { q("UPDATE empresas SET est_emp='A' WHERE id_emp=?", [$id]); $msg = 'Empresa reactivada.'; }
        $pdo->commit();
    } catch (Throwable $x) { $pdo->rollBack(); $msg = 'Error al guardar.'; }
}
$lista = q("SELECT e.*, u.nom_usr, u.correo_usr FROM empresas e
    LEFT JOIN empresa_usuarios eu ON eu.id_emp=e.id_emp AND eu.rol_emu='propietario'
    LEFT JOIN usuarios u ON u.id_usr=eu.id_usr
    ORDER BY FIELD(e.est_emp,'P','A','I'), e.fecha_reg_emp DESC")->fetchAll();
$nombres = ['P' => '⏳ Pendiente', 'A' => '✅ Activa', 'I' => '⛔ Inactiva'];
$titulo = 'Admin - Empresas'; require 'includes/header.php';
?>
<section class="sec"><h2>EMPRESAS</h2><p style="text-align:center"><a href="admin_catalogo.php">🗂️ Catálogo</a> · <a href="admin_pagos.php">💳 Confirmar pagos</a></p><?= $msg ? '<p class="ok">'.h($msg).'</p>' : '' ?>
<table><tr><th>Empresa</th><th>Propietario</th><th>Contacto</th><th>Estado</th><th>Acción</th></tr>
<?php foreach ($lista as $e): ?>
<tr><td><b><?= h($e['nom_emp']) ?></b><br><small><?= h($e['desc_emp']) ?></small></td>
<td><?= h($e['nom_usr']) ?><br><small><?= h($e['correo_usr']) ?></small></td>
<td><?= h($e['correo_emp']) ?><br><?= h($e['tel_emp']) ?></td><td><?= $nombres[$e['est_emp']] ?></td>
<form method="post"><?= csrf() ?><input type="hidden" name="id_emp" value="<?= $e['id_emp'] ?>">
<td>
<?php if ($e['est_emp'] === 'P'): ?><button class="btn" name="accion" value="aprobar">Aprobar</button>
<?php elseif ($e['est_emp'] === 'A'): ?><button class="btn" name="accion" value="desactivar" style="background:#a33">Desactivar</button>
<?php else: ?><button class="btn" name="accion" value="reactivar">Reactivar</button><?php endif; ?>
</td></form></tr>
<?php endforeach; if (!$lista) echo '<tr><td colspan="6">No hay empresas registradas.</td></tr>'; ?></table></section>
<?php require 'includes/footer.php'; ?>
