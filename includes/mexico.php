<?php
// Estados de México y búsqueda de código postal (datos/cp/NN.json: estado, ciudad, municipio y colonias)
const ESTADOS_MX = [1 => 'Aguascalientes', 2 => 'Baja California', 3 => 'Baja California Sur', 4 => 'Campeche', 5 => 'Coahuila', 6 => 'Colima', 7 => 'Chiapas',
    8 => 'Chihuahua', 9 => 'Ciudad de México', 10 => 'Durango', 11 => 'Guanajuato', 12 => 'Guerrero', 13 => 'Hidalgo', 14 => 'Jalisco', 15 => 'México',
    16 => 'Michoacán', 17 => 'Morelos', 18 => 'Nayarit', 19 => 'Nuevo León', 20 => 'Oaxaca', 21 => 'Puebla', 22 => 'Querétaro', 23 => 'Quintana Roo',
    24 => 'San Luis Potosí', 25 => 'Sinaloa', 26 => 'Sonora', 27 => 'Tabasco', 28 => 'Tamaulipas', 29 => 'Tlaxcala', 30 => 'Veracruz', 31 => 'Yucatán', 32 => 'Zacatecas'];

function cp_buscar(string $cp): ?array {
    if (!preg_match('/^\d{5}$/', $cp)) return null;
    static $cache = [];
    $sh = substr($cp, 0, 2);
    if (!isset($cache[$sh])) {
        $f = __DIR__ . '/../datos/cp/' . $sh . '.json';
        $cache[$sh] = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    }
    $r = $cache[$sh][$cp] ?? null;
    return $r ? ['cp' => $cp, 'estado' => ESTADOS_MX[$r['e']] ?? '', 'ciudad' => $r['c'], 'municipio' => $r['m'], 'colonias' => $r['col']] : null;
}
