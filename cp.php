<?php
// Consulta de código postal para el checkout: cp.php?cp=86901  ->  {"ok":true,"estado":"Tabasco","ciudad":"...","municipio":"...","colonias":[...]}
require __DIR__ . '/includes/mexico.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
$r = cp_buscar(preg_replace('/\D/', '', $_GET['cp'] ?? ''));
echo json_encode($r ? ['ok' => true] + $r : ['ok' => false], JSON_UNESCAPED_UNICODE);
