<?php
/**
 * Script de mantenimiento de tokens ML.
 * Ejecutar cada 4 horas desde cron de Hostinger.
 *
 * Renueva proactivamente los tokens antes de que expiren.
 * Si el refresh falla, crea una notificación para el admin.
 *
 * Cron Hostinger (hPanel → Avanzado → Cron Jobs):
 *   Tipo: PHP
 *   Comando: php /home/USUARIO/domains/TUDOMINIO.com/public_html/app/cli/refresh_tokens.php
 *   Frecuencia: cada 4 horas
 */

require_once __DIR__ . '/bootstrap.php';

// ── Conexión a DB ──
$db = new Database();

// ── Obtener tiendas activas ──
$stmt = $db->query('SELECT id, user_id, store_name, is_active, token_expires_at FROM ml_connections WHERE is_active = 1');
$stmt->execute();
$stores = $stmt->fetchAll();

if (empty($stores)) {
    error_log("[TOKEN-CRON] No hay tiendas activas.");
    exit(0);
}

error_log("[TOKEN-CRON] Verificando tokens de " . count($stores) . " tienda(s)...");

$authService = new MercadoLibreAuthService($db);
$notificationService = new NotificationService($db);

$refreshed = 0;
$errors = 0;
$skipped = 0;

foreach ($stores as $store) {
    $connId = (int) $store->id;
    $userId = (int) $store->user_id;
    $storeName = $store->store_name ?? "Tienda #{$connId}";

    // Si expira en más de 30 min, saltar (no necesita refresh aún)
    if ($store->token_expires_at && strtotime($store->token_expires_at . ' UTC') > time() + 1800) {
        $skipped++;
        continue;
    }

    // getTokenForStore() internamente decide si refrescar o no
    $token = $authService->getTokenForStore($connId, $userId);

    if ($token) {
        $refreshed++;
        error_log("[TOKEN-CRON] {$storeName}: token OK");
    } else {
        $errors++;
        error_log("[TOKEN-CRON] {$storeName}: token INVÁLIDO — refresh falló");

        // Crear notificación para el admin
        try {
            $notificationService->createNotification(
                $userId,
                'token_expired',
                'Token expirado — ' . $storeName,
                'El token de MercadoLibre para "' . $storeName . '" expiró y no se pudo renovar. Reconectá la tienda desde Conexiones.'
            );
        } catch (\Exception $e) {
            error_log("[TOKEN-CRON] {$storeName}: error creando notificación: " . $e->getMessage());
        }
    }

    // Pausa entre tiendas para no saturar API de ML
    if (count($stores) > 1) {
        sleep(1);
    }
}

error_log("[TOKEN-CRON] Finalizado: {$refreshed} renovados, {$errors} errores, {$skipped} saltados (aún válidos).");
exit($errors > 0 ? 1 : 0);
