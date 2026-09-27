<?php
$connections = $data['connections'] ?? [];
$csrfToken = $data['csrf_token'] ?? '';
$isAdmin = !empty($data['is_admin']);
?>

<div class="flex items-center gap-2 mb-6">
    <h1 class="text-2xl font-bold text-primary">Conexiones Cencosud</h1>
    <button onclick="openModal()" class="ml-auto btn btn-accent flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg">
        <?php echo icon('plus', ['size' => 14]); ?> Nueva Conexión
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="p-4 mb-6 rounded-lg bg-success/10 text-success text-sm flex items-center gap-2">
        <?php echo icon('check-circle', ['size' => 16]); ?>
        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="p-4 mb-6 rounded-lg bg-danger/10 text-danger text-sm flex items-center gap-2">
        <?php echo icon('triangle-alert', ['size' => 16]); ?>
        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <?php if (empty($connections)): ?>
        <div class="col-span-full card p-8 text-center text-muted">
            <?php echo icon('store', ['size' => 48, 'class' => 'mx-auto mb-4 opacity-50']); ?>
            <p class="text-sm">No tienes conexiones a Cencosud configuradas aún.</p>
            <p class="text-xs mt-1">Haz clic en "Nueva Conexión" para agregar una tienda de París, Jumbo o Easy.</p>
        </div>
    <?php else: ?>
        <?php foreach ($connections as $c): ?>
            <div class="card flex flex-col relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 <?php echo $c->environment === 'production' ? 'bg-success/5' : 'bg-warning/5'; ?> rounded-bl-full flex items-center justify-end pr-4 pt-4">
                    <span class="text-[9px] uppercase font-bold tracking-wider <?php echo $c->environment === 'production' ? 'text-success' : 'text-warning'; ?>">
                        <?php echo $c->environment === 'production' ? 'Producción' : 'Sandbox (Mock)'; ?>
                    </span>
                </div>

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-accent/10 text-accent flex items-center justify-center font-bold">
                        <?php echo icon('store', ['size' => 20]); ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-primary leading-tight"><?php echo htmlspecialchars($c->store_name); ?></h3>
                        <span class="text-xs text-muted">Seller ID: <?php echo htmlspecialchars($c->seller_id ?? 'No especificado'); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs border-t border-b border-border/50 py-3 my-3">
                    <div>
                        <span class="text-muted block">Productos</span>
                        <span class="font-semibold text-primary text-sm"><?php echo $c->product_count; ?></span>
                    </div>
                    <div>
                        <span class="text-muted block">Pedidos</span>
                        <span class="font-semibold text-primary text-sm"><?php echo $c->order_count; ?></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-muted block">Sincronización</span>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="inline-block w-2 h-2 rounded-full <?php echo $c->sync_status === 'syncing' ? 'bg-accent animate-pulse' : ($c->sync_status === 'error' ? 'bg-danger' : 'bg-success'); ?>"></span>
                            <span class="font-semibold text-primary capitalize"><?php echo $c->sync_status; ?></span>
                            <?php if ($c->last_sync_at): ?>
                                <span class="text-muted text-[10px]"> (<?php echo date('d/m H:i', strtotime($c->last_sync_at . ' UTC')); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-auto pt-3">
                    <button onclick='editConnection(<?php echo json_encode($c); ?>)' class="btn btn-secondary text-xs px-3 py-1.5 rounded-lg flex items-center gap-1">
                        <?php echo icon('edit', ['size' => 12]); ?> Editar
                    </button>
                    <form action="<?php echo URLROOT; ?>/cencosud/deleteConnection" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar esta conexión? Se borrará todo el catálogo y pedidos locales en caché de esta tienda.');" class="inline ml-auto">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="id" value="<?php echo $c->id; ?>">
                        <button type="submit" class="btn btn-danger text-xs px-3 py-1.5 rounded-lg flex items-center gap-1">
                            <?php echo icon('trash', ['size' => 12]); ?> Eliminar
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Formulario -->
<div id="connectionModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-surface border border-border w-full max-w-md rounded-xl shadow-2xl p-6 relative overflow-hidden transition-all transform scale-95 opacity-0 duration-200" id="modalCard">
        <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2" id="modalTitle">
            <?php echo icon('store', ['size' => 18, 'class' => 'text-accent']); ?> Crear Conexión Cencosud
        </h2>

        <form action="<?php echo URLROOT; ?>/cencosud/saveConnection" method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="id" id="conn_id" value="">

            <div class="flex flex-col gap-1">
                <label for="store_name" class="text-xs font-semibold text-primary">Nombre de la Tienda *</label>
                <input type="text" name="store_name" id="store_name" required placeholder="Ej: Jumbo Costanera, París Online" class="text-sm rounded-lg border border-border bg-surface px-3 py-2 text-primary focus:outline-none focus:border-accent">
            </div>

            <div class="flex flex-col gap-1">
                <label for="seller_id" class="text-xs font-semibold text-primary">Seller ID *</label>
                <input type="text" name="seller_id" id="seller_id" required placeholder="ID de vendedor provisto por Cencosud" class="text-sm rounded-lg border border-border bg-surface px-3 py-2 text-primary focus:outline-none focus:border-accent">
            </div>

            <div class="flex flex-col gap-1">
                <label for="api_key" class="text-xs font-semibold text-primary" id="apiKeyLabel">API Key *</label>
                <input type="password" name="api_key" id="api_key" placeholder="API Key maestra de integración" class="text-sm rounded-lg border border-border bg-surface px-3 py-2 text-primary focus:outline-none focus:border-accent">
                <span class="text-[10px] text-muted leading-tight mt-0.5" id="apiKeyHelp">La llave de API se encripta de forma segura antes de ser guardada en la base de datos.</span>
            </div>

            <div class="flex flex-col gap-1">
                <label for="environment" class="text-xs font-semibold text-primary">Entorno de Conexión</label>
                <select name="environment" id="environment" class="text-sm rounded-lg border border-border bg-surface px-3 py-2 text-primary focus:outline-none focus:border-accent cursor-pointer">
                    <option value="sandbox_mock">Sandbox (Simulación / Mock)</option>
                    <option value="production">Producción (API Oficial Real)</option>
                </select>
            </div>

            <div class="flex items-center gap-3 mt-4 pt-3 border-t border-border/50">
                <button type="button" onclick="closeModal()" class="btn btn-secondary text-xs px-4 py-2 rounded-lg ml-auto">Cancelar</button>
                <button type="submit" class="btn btn-accent text-xs px-4 py-2 rounded-lg font-semibold">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('conn_id').value = '';
    document.getElementById('store_name').value = '';
    document.getElementById('seller_id').value = '';
    document.getElementById('api_key').value = '';
    document.getElementById('api_key').required = true;
    document.getElementById('environment').value = 'sandbox_mock';
    document.getElementById('modalTitle').innerHTML = '<?php echo icon("store", ["size" => 18, "class" => "text-accent"]); ?> Crear Conexión Cencosud';
    document.getElementById('apiKeyLabel').innerText = 'API Key *';

    const modal = document.getElementById('connectionModal');
    const card = document.getElementById('modalCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
        card.classList.remove('scale-95', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function editConnection(conn) {
    document.getElementById('conn_id').value = conn.id;
    document.getElementById('store_name').value = conn.store_name;
    document.getElementById('seller_id').value = conn.seller_id;
    document.getElementById('api_key').value = '';
    document.getElementById('api_key').required = false;
    document.getElementById('environment').value = conn.environment;
    document.getElementById('modalTitle').innerHTML = '<?php echo icon("edit", ["size" => 18, "class" => "text-accent"]); ?> Editar Conexión';
    document.getElementById('apiKeyLabel').innerText = 'Nueva API Key (dejar en blanco para conservar)';

    const modal = document.getElementById('connectionModal');
    const card = document.getElementById('modalCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
        card.classList.remove('scale-95', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('connectionModal');
    const card = document.getElementById('modalCard');
    card.classList.remove('scale-100', 'opacity-100');
    card.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}
</script>
