<?php
require_once 'config.php';
$paso = ($_GET['paso'] ?? '1') === '2' && !empty($_SESSION['rec_correo']) ? 2 : 1;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    if (($_POST['paso'] ?? '') === '1') {
        $correo = trim($_POST['correo'] ?? '');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $err = 'Escribe un correo válido.';
        else {
            $usr = q("SELECT id_usr FROM usuarios WHERE correo_usr=? AND est_usr='A'", [$correo])->fetch();
            if ($usr) {
                $recientes = (int)q("SELECT COUNT(*) FROM password_reset_codes WHERE id_usr=? AND fecha_reg_prc > NOW() - INTERVAL 15 MINUTE", [$usr['id_usr']])->fetchColumn();
                if ($recientes < 3) {                           // máx. 3 códigos cada 15 min
                    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    q("UPDATE password_reset_codes SET usado_prc='S' WHERE id_usr=? AND usado_prc='N'", [$usr['id_usr']]);
                    q('INSERT INTO password_reset_codes (id_usr,codigo_prc,fecha_exp_prc) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 15 MINUTE))',
                      [$usr['id_usr'], hash('sha256', $codigo . '|' . $usr['id_usr'])]);   // solo se guarda el hash
                    enviar_correo($correo, 'Tu código para recuperar la contraseña',
                        "<p>Tu código de verificación es:</p><h2 style='letter-spacing:4px'>$codigo</h2><p>Vence en 15 minutos. Si no lo pediste, ignora este correo.</p>");
                    if (MODO_DEV) $_SESSION['rec_dev'] = $codigo;
                }
            }
            $_SESSION['rec_correo'] = $correo; $_SESSION['rec_intentos'] = 0;   // misma respuesta exista o no el correo
            header('Location: recuperar.php?paso=2'); exit;
        }
    } elseif (($_POST['paso'] ?? '') === '2' && !empty($_SESSION['rec_correo'])) {
        $paso = 2; $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');
        $n1 = $_POST['nueva'] ?? ''; $n2 = $_POST['confirmar'] ?? '';
        if (($_SESSION['rec_intentos'] ?? 0) >= 5) $err = 'Demasiados intentos. Solicita un código nuevo.';
        elseif (strlen($codigo) !== 6) $err = 'El código tiene 6 dígitos.';
        elseif (strlen($n1) < 8) $err = 'La contraseña debe tener al menos 8 caracteres.';
        elseif ($n1 !== $n2) $err = 'Las contraseñas no coinciden.';
        else {
            $usr = q("SELECT id_usr FROM usuarios WHERE correo_usr=? AND est_usr='A'", [$_SESSION['rec_correo']])->fetch();
            $fila = $usr ? q("SELECT id_prc FROM password_reset_codes WHERE id_usr=? AND codigo_prc=? AND usado_prc='N' AND fecha_exp_prc > NOW()",
                             [$usr['id_usr'], hash('sha256', $codigo . '|' . $usr['id_usr'])])->fetch() : false;
            if ($fila) {
                q('UPDATE usuarios SET clave_usr=? WHERE id_usr=?', [password_hash($n1, PASSWORD_DEFAULT), $usr['id_usr']]);
                q("UPDATE password_reset_codes SET usado_prc='S' WHERE id_usr=?", [$usr['id_usr']]);
                unset($_SESSION['rec_correo'], $_SESSION['rec_intentos'], $_SESSION['rec_dev']);
                header('Location: login.php?ok=1'); exit;
            }
            $_SESSION['rec_intentos'] = ($_SESSION['rec_intentos'] ?? 0) + 1; usleep(500000);
            $err = 'Código incorrecto o vencido.';
        }
    }
}
$titulo = 'Recuperar contraseña — Central Apícola del Usumacinta'; require 'includes/header.php';
?>
<div class="auth-wrap">
<?php if ($paso === 1): ?>
<form class="auth-card" method="post"><?= csrf() ?><input type="hidden" name="paso" value="1">
  <h2>Recuperar contraseña</h2>
  <p class="rec-hint">Te enviaremos un código de 6 dígitos al correo con el que registraste tu cuenta.</p>
  <div class="auth-field"><label>Correo electrónico</label><input type="email" name="correo" placeholder="tu@correo.com" required></div>
  <button class="btn-auth">Enviar código</button>
  <div class="auth-msg" style="color:#c0392b"><?= h($err) ?></div>
  <div class="rec-back"><a href="login.php">← Volver a iniciar sesión</a></div>
</form>
<?php else: ?>
<form class="auth-card" method="post"><?= csrf() ?><input type="hidden" name="paso" value="2">
  <h2>Escribe tu código</h2>
  <p class="rec-hint">Si el correo <strong><?= h($_SESSION['rec_correo']) ?></strong> está registrado, enviamos un código de 6 dígitos. Revisa también tu carpeta de spam. Vence en 15 minutos.</p>
  <?php if (MODO_DEV && !empty($_SESSION['rec_dev'])): ?><p class="rec-hint" style="background:#fff6e0;padding:8px;border-radius:4px">🛠️ Modo desarrollo: tu código es <b><?= h($_SESSION['rec_dev']) ?></b></p><?php endif; ?>
  <div class="auth-field"><label>Código de verificación</label><input type="text" name="codigo" class="rec-code-input" maxlength="6" inputmode="numeric" placeholder="000000" required></div>
  <div class="auth-field"><label>Nueva contraseña</label><input type="password" name="nueva" placeholder="Mínimo 8 caracteres" minlength="8" required></div>
  <div class="auth-field"><label>Confirmar nueva contraseña</label><input type="password" name="confirmar" placeholder="Repite la contraseña" required></div>
  <button class="btn-auth">Cambiar contraseña</button>
  <div class="auth-msg" style="color:#c0392b"><?= h($err) ?></div>
  <div class="rec-reenviar">¿No te llegó? <a href="recuperar.php">Pedir otro código</a></div>
  <div class="rec-back"><a href="login.php">← Volver a iniciar sesión</a></div>
</form>
<?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
