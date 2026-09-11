<?php
// CONFIGURACIÓN DEL SISTEMA - MÓDULO DE CONDOMINIO
// Ubicación: raíz del sistema

// Roles de usuario
define('ROLES', [
    'admin'       => 'Administrador',
    'junta'       => 'Junta de Condominio',
    'propietario' => 'Propietario',
    'inquilino'   => 'Inquilino'
]);

// Cargos de la junta de condominio
define('CARGOS_JUNTA', [
    'presidente'    => 'Presidente',
    'vicepresidente'=> 'Vicepresidente',
    'tesorero'      => 'Tesorero',
    'secretario'    => 'Secretario',
    'administrador' => 'Administrador',
    'vocal'         => 'Vocal'
]);

// Roles de ocupación de una unidad
define('ROLES_TENENCIA', [
    'propietario' => 'Propietario',
    'inquilino'   => 'Inquilino',
    'residente'   => 'Residente'
]);

// Tipos de cédula de ciudadano (prefijo nacional/extranjero)
define('TIPOS_CEDULA', [
    'V' => 'Venezolano',
    'E' => 'Extranjero',
    'J' => 'Jurídico',
    'G' => 'Gobierno'
]);

// Tipos de cédula cuyo nombre (razón social) puede contener números
define('TIPOS_NOMBRE_CON_NUMEROS', ['J', 'G']);

// Tipos de cédula permitidos para el representante legal de una persona J/G
define('TIPOS_CEDULA_REP_LEGAL', [
    'V' => 'Venezolano',
    'E' => 'Extranjero'
]);

// Tipos de unidad
define('TIPOS_UNIDAD', [
    'apartamento'     => 'Apartamento',
    'penthouse'       => 'Penthouse',
    'estacionamiento' => 'Estacionamiento',
    'local'           => 'Local',
    'deposito'        => 'Depósito'
]);

// Estados de una cuota emitida (aviso de cobro)
define('ESTADOS_CUOTA', [
    'pendiente' => '🟡 Pendiente',
    'vencida'   => '🔴 Vencida',
    'pagada'    => '🟢 Pagada',
    'parcial'   => '🔵 Parcial',
    'condonada' => '⚪ Condonada'
]);

// Métodos de pago
define('METODOS_PAGO', [
    'efectivo'      => 'Efectivo',
    'transferencia' => 'Transferencia',
    'tarjeta'       => 'Tarjeta',
    'cheque'        => 'Cheque',
    'punto_venta'   => 'Punto de Venta',
    'deposito'      => 'Depósito'
]);

// Estados de los comprobantes de pago (declaraciones de propietarios/inquilinos)
define('ESTADOS_COMPROBANTE', [
    'pendiente' => 'Pendiente',
    'aprobado'  => 'Aprobado',
    'rechazado' => 'Rechazado'
]);

// Límites de archivos de comprobante
define('COMPROBANTE_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
define('COMPROBANTE_EXT', ['jpg', 'jpeg', 'png', 'pdf']);

// Día de vencimiento estándar de las cuotas
define('DIA_VENCIMIENTO', 15);

function getFechaVencimiento($periodo_mes, $periodo_anio) {
    return sprintf('%04d-%02d-%02d', $periodo_anio, $periodo_mes, DIA_VENCIMIENTO);
}

function getNombreMes($mes) {
    $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $meses[(int)$mes] ?? $mes;
}

/**
 * Obtiene la tasa BCV del dólar oficial del día.
 * Usa la API pública de bcvapi.cc (endpoint sin API key).
 * Cachea en sesión para no repetir la petición.
 * Retorna float con la tasa o null si falla.
 */
function obtenerTasaBCV($force_refresh = false) {
    $hoy = date('Y-m-d');
    if (!$force_refresh && isset($_SESSION['tasa_bcv']) && $_SESSION['tasa_bcv']['fecha'] === $hoy) {
        return $_SESSION['tasa_bcv']['tasa'];
    }

    $url = 'https://bcvapi.cc/api/v1/dolar/public';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data['tasa']) && is_numeric($data['tasa'])) {
            $tasa = (float)$data['tasa'];
            $_SESSION['tasa_bcv'] = ['tasa' => $tasa, 'fecha' => $hoy];
            return $tasa;
        }
    }
    return null;
}

/**
 * Calcula el equivalente en Bs de un monto en USD.
 */
function convertirABolivares($monto_usd, $tasa = null) {
    if ($tasa === null) $tasa = obtenerTasaBCV();
    if ($tasa === null || $monto_usd <= 0) return null;
    return round($monto_usd * $tasa, 2);
}
?>