<?php
require_once 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nombre'] ?? ''); $cor = trim($_POST['correo'] ?? '');
    $tel = preg_replace('/\D/', '', $_POST['tel'] ?? ''); $cla = $_POST['clave'] ?? '';
    if ($nom === '' || !filter_var($cor, FILTER_VALIDATE_EMAIL)) $err = 'Revisa tu nombre y correo.';
    elseif ($tel !== '' && strlen($tel) !== 10) $err = 'El teléfono debe tener 10 dígitos.';
    elseif (strlen($cla) < 8) $err = 'La contraseña debe tener al menos 8 caracteres.';
    elseif (q('SELECT 1 FROM usuarios WHERE correo_usr=?', [$cor])->fetch()) $err = 'Ese correo ya está registrado.';
    else {
        q('INSERT INTO usuarios (correo_usr,clave_usr,nom_usr,tel_usr) VALUES (?,?,?,?)', [$cor, password_hash($cla, PASSWORD_DEFAULT), $nom, $tel ?: null]);
        header('Location: login.php'); exit;
    }
}
$titulo = 'Crear cuenta — Central Apícola del Usumacinta'; require 'includes/header.php';
?>
<div class="auth-wrap"><form class="auth-card" method="post">
  <h2>Crear cuenta</h2><p class="sub">Regístrate gratis para comprar en nuestra tienda.</p>
  <div class="auth-field"><label>Nombre completo</label><input name="nombre" value="<?= h($_POST['nombre'] ?? '') ?>" required></div>
  <div class="auth-field"><label>Correo electrónico</label><input type="email" name="correo" value="<?= h($_POST['correo'] ?? '') ?>" required></div>
  <div class="auth-field"><label>Teléfono (10 dígitos)</label><input name="tel" maxlength="10" inputmode="numeric"></div>
  <div class="auth-field"><label>Contraseña</label><input type="password" name="clave" placeholder="Mínimo 8 caracteres" required minlength="8"></div>
  <button class="btn-auth">Registrarme</button>
  <div class="auth-msg" style="color:#c0392b"><?= h($err) ?></div>
  <div class="auth-switch">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></div>
</form></div>
<?php require 'includes/footer.php'; ?>
