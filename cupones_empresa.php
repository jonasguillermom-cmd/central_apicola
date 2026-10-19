<?php
require_once 'config.php';
requiere_login();
$emps = q("SELECT e.* FROM empresa_usuarios eu JOIN empresas e ON e.id_emp=eu.id_emp
           WHERE eu.id_usr=? AND eu.rol_emu IN ('propietario','admin')", [$_SESSION['id_usr']])->fetchAll();
if (!$emps) { header('Location: registro_empresa.php'); exit; }
$emp = $emps[0];
foreach ($emps as $x) if (isset($_GET['emp']) && $x['id_emp'] == (int)$_GET['emp']) $emp = $x;
$idEmp = (int)$emp['id_emp']; $msg = $err = '';

if ($emp['est_emp'] === 'A' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        if (($_POST['accion'] ?? '') === 'crear') {
            $cod = strtoupper(trim($_POST['cod'] ?? ''));
            if (!preg_match('/^[A-Z0-9_-]{3,20}$/', $cod)) throw new RuntimeException('El código debe tener 3 a 20 letras, números, - o _.');
            $tit = trim($_POST['titulo'] ?? '');
            if ($tit === '' || mb_strlen($tit) > 80) throw new RuntimeException('Escribe un título (máx. 80 caracteres).');
            $tipo = ($_POST['tipo'] ?? '') === 'monto_fijo' ? 'monto_fijo' : 'porcentaje';
            $valor = (float)str_replace(',', '.', $_POST['valor'] ?? 0);
            if ($valor <= 0 || $valor > 999999 || ($tipo === 'porcentaje' && $valor > 100)) throw new RuntimeException('Valor de descuento no válido.');
            $min = max(0, min(999999, (float)str_replace(',', '.', $_POST['min'] ?? 0)));
            $ini = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['ini'] ?? ''); $fin = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['fin'] ?? '');
            if (!$ini || !$fin || $fin <= $ini) throw new RuntimeException('Revisa las fechas: el fin debe ser posterior al inicio.');
            $usos = ($_POST['usos'] ?? '') !== '' ? max(1, (int)$_POST['usos']) : null;
            $dest = trim($_POST['dest'] ?? '');
            if ($dest !== '' && !filter_var($dest, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo del destinatario no válido.');
            if (q('SELECT 1 FROM cupones WHERE cod_cup=?', [$cod])->fetch()) throw new RuntimeException('Ya existe un cupón con ese código.');
            q('INSERT INTO cupones (id_emp,cod_cup,titulo_cup,desc_cup,tipo_desc_cup,valor_cup,monto_min_cup,fecha_ini_cup,fecha_fin_cup,usos_max_cup,vis_cup,correo_dest_cup)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
              [$idEmp, $cod, $tit, trim($_POST['desc'] ?? '') ?: null, $tipo, $valor, $min, $ini->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'),
               $usos, ($_POST['vis'] ?? 'S') === 'N' ? 'N' : 'S', $dest ?: null]);
            $msg = 'Cupón creado.';
        } elseif (($_POST['accion'] ?? '') === 'estado') {
            q("UPDATE cupones SET est_cup=IF(est_cup='A','I','A') WHERE id_cup=? AND id_emp=?", [(int)($_POST['id_cup'] ?? 0), $idEmp]);
            $msg = 'Estado actualizado.';
        }
    } catch (RuntimeException $x) { $err = $x->getMessage(); }
      catch (Throwable $x)        { $err = 'No se pudo guardar el cupón.'; }
}
$titulo = 'Cupones — ' . $emp['nom_emp']; require 'includes/header.php';
if ($emp['est_emp'] !== 'A') { echo '<section class="sec"><h2>Tu empresa aún no está activa</h2></section>'; require 'includes/footer.php'; exit; }
$cupones = q('SELECT * FROM cupones WHERE id_emp=? ORDER BY fecha_reg_cup DESC', [$idEmp])->fetchAll();
?>
<section class="sec"><h2>🎟️ CUPONES DE <?= h(mb_strtoupper($emp['nom_emp'])) ?></h2>
<p style="text-align:center"><a href="panel_empresa.php?emp=<?= $idEmp ?>">← Volver al panel</a></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>

<table><tr><th>Código</th><th>Título</th><th>Descuento</th><th>Mínimo</th><th>Vigencia</th><th>Usos</th><th>Para</th><th>Estado</th><th></th></tr>
<?php foreach ($cupones as $c): ?><tr>
  <td><b><?= h($c['cod_cup']) ?></b></td><td><?= h($c['titulo_cup']) ?></td>
  <td><?= $c['tipo_desc_cup'] === 'porcentaje' ? h(rtrim(rtrim($c['valor_cup'], '0'), '.')) . '%' : dinero($c['valor_cup']) ?></td>
  <td><?= dinero($c['monto_min_cup']) ?></td>
  <td><?= h(date('d/m/Y', strtotime($c['fecha_ini_cup']))) ?> – <?= h(date('d/m/Y', strtotime($c['fecha_fin_cup']))) ?></td>
  <td><?= (int)$c['usos_act_cup'] ?> / <?= $c['usos_max_cup'] ?? '∞' ?></td>
  <td><?= $c['correo_dest_cup'] ? h($c['correo_dest_cup']) : ($c['vis_cup'] === 'S' ? 'Todos (visible)' : 'Solo con código') ?></td>
  <td><?= $c['est_cup'] === 'A' ? '✅ Activo' : '⛔ Inactivo' ?></td>
  <td><form method="post"><?= csrf() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_cup" value="<?= $c['id_cup'] ?>">
    <button class="btn"><?= $c['est_cup'] === 'A' ? 'Desactivar' : 'Activar' ?></button></form></td></tr>
<?php endforeach; if (!$cupones) echo '<tr><td colspan="9">Aún no has creado cupones.</td></tr>'; ?></table>

<h3 style="margin-top:36px">Nuevo cupón</h3>
<form class="caja" style="margin:10px 0;max-width:520px" method="post"><?= csrf() ?><input type="hidden" name="accion" value="crear">
  <label>Código (lo escribe el cliente)</label><input name="cod" maxlength="20" placeholder="MIEL10" style="text-transform:uppercase" required>
  <label>Título</label><input name="titulo" maxlength="80" placeholder="10% en miel" required>
  <label>Descripción (opcional)</label><input name="desc" maxlength="200">
  <label>Tipo de descuento</label><select name="tipo"><option value="porcentaje">Porcentaje (%)</option><option value="monto_fijo">Monto fijo ($)</option></select>
  <label>Valor</label><input name="valor" required>
  <label>Compra mínima (opcional)</label><input name="min" value="0">
  <label>Inicia</label><input type="datetime-local" name="ini" value="<?= date('Y-m-d\TH:i') ?>" required>
  <label>Termina</label><input type="datetime-local" name="fin" value="<?= date('Y-m-d\TH:i', strtotime('+30 days')) ?>" required>
  <label>Usos máximos (vacío = ilimitado)</label><input type="number" name="usos" min="1">
  <label>Visibilidad</label><select name="vis"><option value="S">Visible para todos en "Mis cupones"</option><option value="N">Oculto: solo con el código</option></select>
  <label>Solo para este correo (opcional)</label><input type="email" name="dest" maxlength="100" placeholder="cliente@correo.com">
  <button class="btn">Crear cupón</button></form>
<p style="font-size:13px;color:#666">Cada cliente puede usar un cupón una sola vez. Los cupones de tu empresa descuentan únicamente sobre tus productos.</p>
</section>
<?php require 'includes/footer.php'; ?>
