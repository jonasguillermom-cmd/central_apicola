<?php
require_once 'config.php';
requiere_login();
$ya = q('SELECT 1 FROM empresa_usuarios WHERE id_usr=? LIMIT 1', [$_SESSION['id_usr']])->fetch();
if ($ya) { header('Location: panel_empresa.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? ''); $correo = trim($_POST['correo'] ?? '');
    $tel = preg_replace('/\D/', '', $_POST['tel'] ?? ''); $desc = trim($_POST['desc'] ?? '');
    if (!csrf_ok()) $err = 'Sesión expirada, intenta de nuevo.';
    elseif ($nombre === '' || mb_strlen($nombre) > 100) $err = 'Escribe el nombre de tu empresa (máx. 100 caracteres).';
    elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $err = 'Correo no válido.';
    elseif ($tel !== '' && strlen($tel) !== 10) $err = 'El teléfono debe tener 10 dígitos.';
    elseif (q('SELECT 1 FROM empresas WHERE LOWER(nom_emp)=LOWER(?)', [$nombre])->fetch()) $err = 'Ya existe una empresa con ese nombre.';
    else {
        $pdo->beginTransaction();
        try {
            q('INSERT INTO empresas (nom_emp,desc_emp,correo_emp,tel_emp) VALUES (?,?,?,?)',
              [$nombre, $desc ?: null, $correo ?: null, $tel ?: null]);   // est_emp = 'P'
            q("INSERT INTO empresa_usuarios (id_emp,id_usr,rol_emu) VALUES (?,?,'propietario')",
              [$pdo->lastInsertId(), $_SESSION['id_usr']]);
            $pdo->commit();
            header('Location: panel_empresa.php'); exit;
        } catch (Throwable $x) { $pdo->rollBack(); $err = 'No se pudo registrar la empresa.'; }
    }
}
$titulo = 'Registrar empresa'; require 'includes/header.php';
?>
<form class="caja" method="post"><h2>Quiero vender</h2>
<p>Tu solicitud será revisada antes de publicar tu tienda.</p>
<?= $err ? '<p class="error">'.h($err).'</p>' : '' ?><?= csrf() ?>
<label>Nombre de la empresa</label><input name="nombre" maxlength="100" required value="<?= h($_POST['nombre'] ?? '') ?>">
<label>Correo de contacto</label><input type="email" name="correo" maxlength="100">
<label>Teléfono (10 dígitos)</label><input name="tel" maxlength="10">
<label>Descripción</label><input name="desc" maxlength="500">
<button class="btn">Enviar solicitud</button></form>
<?php require 'includes/footer.php'; ?>
