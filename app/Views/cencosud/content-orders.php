<?php
$connections = $data['connections'] ?? [];
$activeStoreId = $data['active_store_id'] ?? null;
$activeStore = $data['active_store'] ?? null;
$csrfToken = $data['csrf_token'] ?? '';
?>

<!-- Header -->
<div class="flex flex-wrap items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Pedidos Cencosud</h1>
        <p class="text-xs text-muted mt-0.5">Monitorea las ventas, subórdenes y despachos de tus canales Cencosud.</p>
    </div>
    
    <div class="ml-auto flex items-center gap-3">
        <?php if (!empty($connections)): ?>
            <div class="flex items-center gap-2">
                <span class="text-xs text-muted">Tienda activa:</span>
                <form action="<?php echo URLROOT; ?>/cencosud/selectstore/<?php echo $activeStoreId; ?>" method="POST" id="storeSelectForm" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <select onchange="changeActiveStore(this.value)" class="text-xs rounded-lg border border-border bg-surface text-primary px-3 py-2 focus:outline-none focus:border-accent font-semibold cursor-pointer">
                        <?php foreach ($connections as $c): ?>
                            <option value="<?php echo $c->id; ?>" <?php echo (int)$c->id === (int)$activeStoreId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c->store_name); ?> (<?php echo $c->environment === 'production' ? 'Prod' : 'Mock'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <button onclick="syncOrders()" id="syncBtn" class="btn btn-secondary flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg">
                <?php echo icon('refresh-cw', ['size' => 14, 'id' => 'syncIcon']); ?> <span id="syncBtnText">Sincronizar Ventas</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($connections)): ?>
    <div class="card p-12 text-center text-muted">
        <?php echo icon('store', ['size' => 48, 'class' => 'mx-auto mb-4 opacity-50']); ?>
        <p class="text-sm font-semibold">No se encontraron tiendas activas de Cencosud.</p>
        <p class="text-xs mt-1">Primero debes configurar una tienda en <a href="<?php echo URLROOT; ?>/cencosud/connections" class="text-accent underline">Conexiones</a>.</p>
    </div>
<?php else: ?>
    <!-- Orders Filter & Grid -->
    <div class="card p-0 overflow-hidden mb-8">
        <!-- Filters Header -->
        <div class="p-4 border-b border-border/50 flex flex-wrap items-center gap-4 bg-surface/30">
            <div class="relative w-full sm:max-w-xs">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-muted">
                    <?php echo icon('search', ['size' => 14]); ?>
                </span>
                <input type="text" id="searchInput" oninput="debounceSearch()" placeholder="Buscar por ID u Operador..." class="text-xs rounded-lg border border-border bg-surface pl-9 pr-3 py-2 w-full text-primary focus:outline-none focus:border-accent">
            </div>
        </div>

        <!-- Orders Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="ordersTable">
                <thead>
                    <tr class="text-muted text-xs uppercase tracking-wide border-b border-border/50 bg-surface/10">
                        <th class="py-3 px-4 text-left font-medium w-36">ID Pedido</th>
                        <th class="py-3 px-4 text-left font-medium">Cliente</th>
                        <th class="py-3 px-4 text-left font-medium w-32">Total</th>
                        <th class="py-3 px-4 text-left font-medium w-32">Pago</th>
                        <th class="py-3 px-4 text-left font-medium w-32">Despacho</th>
                        <th class="py-3 px-4 text-left font-medium w-40">Fecha Compra</th>
                        <th class="py-3 px-4 text-right font-medium w-24">Acción</th>
                    </tr>
                </thead>
                <tbody id="ordersBody">
                    <tr>
                        <td colspan="7" class="py-8 text-center text-muted text-xs">
                            Cargando pedidos...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-border/50 flex items-center justify-between bg-surface/30" id="paginationRow">
            <span class="text-xs text-muted" id="totalRecordsText">Mostrando 0 de 0 pedidos</span>
            <div class="flex items-center gap-1.5" id="pageBtnContainer">
                <!-- buttons injected dynamically -->
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Order Detail Modal -->
<div id="orderDetailModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-surface border border-border w-full max-w-2xl rounded-xl shadow-2xl p-6 relative overflow-hidden transition-all transform scale-95 opacity-0 duration-200" id="modalCard">
        <h2 class="text-lg font-bold text-primary mb-2 flex items-center gap-2">
            <?php echo icon('shopping-bag', ['size' => 18, 'class' => 'text-accent']); ?> Detalle del Pedido Cencosud
        </h2>
        <p class="text-xs text-muted mb-4" id="detailOrderIdText">ID: -</p>

        <!-- General Info -->
        <div class="grid grid-cols-2 gap-4 text-xs bg-surface/20 border border-border/50 rounded-lg p-3.5 mb-5">
            <div>
                <span class="text-muted block">Cliente</span>
                <span class="font-semibold text-primary" id="detailCustomer">Cargando...</span>
            </div>
            <div>
                <span class="text-muted block">Fecha Compra</span>
                <span class="font-semibold text-primary" id="detailDate">Cargando...</span>
            </div>
            <div>
                <span class="text-muted block">Estado Pago</span>
                <span class="font-semibold text-primary uppercase" id="detailStatus">Cargando...</span>
            </div>
            <div>
                <span class="text-muted block">Estado Despacho</span>
                <span class="font-semibold text-primary uppercase" id="detailShipping">Cargando...</span>
            </div>
        </div>

        <!-- Suborders / Items -->
        <h3 class="text-xs font-bold text-primary uppercase tracking-wider mb-2.5">Subórdenes y Productos</h3>
        <div class="border border-border/40 rounded-lg overflow-hidden bg-surface mb-5 max-h-60 overflow-y-auto">
            <table class="w-full text-xs text-left">
                <thead>
                    <tr class="bg-surface/30 border-b border-border/40 text-muted">
                        <th class="p-2.5 font-medium">SKU / Producto</th>
                        <th class="p-2.5 font-medium w-16 text-center">Cant.</th>
                        <th class="p-2.5 font-medium w-24 text-right">Precio Unit.</th>
                        <th class="p-2.5 font-medium w-24 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="detailItemsBody">
                    <!-- items injected dynamically -->
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between border-t border-border/50 pt-4">
            <div class="text-xs text-primary font-semibold">
                Total Pedido: <span class="text-base font-bold text-accent" id="detailTotalAmount">$0</span>
            </div>
            <button type="button" onclick="closeDetailModal()" class="btn btn-secondary text-xs px-4 py-2 rounded-lg">Cerrar</button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let searchTimeout = null;
let currentOrders = [];

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById('ordersTable')) {
        loadOrders(1);
    }
});

function changeActiveStore(id) {
    document.getElementById('storeSelectForm').action = "<?php echo URLROOT; ?>/cencosud/selectstore/" + id;
    document.getElementById('storeSelectForm').submit();
}

function loadOrders(page) {
    currentPage = page;
    const store = "<?php echo $activeStoreId; ?>";
    const q = document.getElementById('searchInput').value;
    const tbody = document.getElementById('ordersBody');

    tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-muted text-xs">Cargando pedidos...</td></tr>`;

    fetch(`<?php echo URLROOT; ?>/cencosud/ordersData?store=${store}&page=${page}&q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(res => {
            if (res.error) {
                tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-danger text-xs">${res.error}</td></tr>`;
                return;
            }
            currentOrders = res.items;
            renderTable(res.items);
            renderPagination(res.paging);
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-danger text-xs">Error de conexión.</td></tr>`;
        });
}

function renderTable(items) {
    const tbody = document.getElementById('ordersBody');
    tbody.innerHTML = '';
    
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-muted text-xs">No se encontraron pedidos.</td></tr>`;
        return;
    }

    items.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.className = "border-b border-border/40 hover:bg-surface/30 transition-colors";
        
        const priceFormatted = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(item.total_amount);
        
        let statusColor = "bg-success/10 text-success";
        if (item.status === 'pending' || item.status === 'paid') statusColor = "bg-accent/10 text-accent";
        if (item.status === 'cancelled') statusColor = "bg-danger/10 text-danger";

        let shippingColor = "bg-success/10 text-success";
        if (item.shipping_status === 'pendiente') shippingColor = "bg-warning/10 text-warning";
        if (item.shipping_status === 'cancelado') shippingColor = "bg-danger/10 text-danger";

        tr.innerHTML = `
            <td class="py-3 px-4 font-mono font-medium text-primary">
                ${item.order_id}
            </td>
            <td class="py-3 px-4 font-semibold text-primary">
                ${item.customer_name}
            </td>
            <td class="py-3 px-4 font-bold text-primary tabular-nums">
                ${priceFormatted}
            </td>
            <td class="py-3 px-4">
                <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded ${statusColor}">${item.status}</span>
            </td>
            <td class="py-3 px-4">
                <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded ${shippingColor}">${item.shipping_status}</span>
            </td>
            <td class="py-3 px-4 text-muted text-xs">
                ${item.created_at_cenco}
            </td>
            <td class="py-3 px-4 text-right">
                <button onclick="viewOrderDetail('${item.order_id}')" class="btn btn-secondary text-xs px-2.5 py-1.5 rounded-lg flex items-center gap-1">
                    <?php echo icon("eye", ["size" => 12]); ?> Detalle
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderPagination(paging) {
    const text = document.getElementById('totalRecordsText');
    const container = document.getElementById('pageBtnContainer');
    
    text.innerText = `Mostrando ${paging.offset + 1} a ${Math.min(paging.offset + paging.limit, paging.total)} de ${paging.total} pedidos`;
    container.innerHTML = '';

    if (paging.total_pages <= 1) return;

    // Previous Button
    const prevBtn = document.createElement('button');
    prevBtn.className = `btn btn-secondary text-[10px] px-2.5 py-1.5 rounded-lg ${paging.page === 1 ? 'opacity-50 cursor-not-allowed' : ''}`;
    prevBtn.innerHTML = `<?php echo icon('chevron-left', ['size' => 12]); ?>`;
    prevBtn.disabled = paging.page === 1;
    prevBtn.onclick = () => loadOrders(paging.page - 1);
    container.appendChild(prevBtn);

    // Current page indicator
    const indicator = document.createElement('span');
    indicator.className = "text-xs font-semibold text-primary px-3";
    indicator.innerText = `${paging.page} / ${paging.total_pages}`;
    container.appendChild(indicator);

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.className = `btn btn-secondary text-[10px] px-2.5 py-1.5 rounded-lg ${paging.page === paging.total_pages ? 'opacity-50 cursor-not-allowed' : ''}`;
    nextBtn.innerHTML = `<?php echo icon('chevron-right', ['size' => 12]); ?>`;
    nextBtn.disabled = paging.page === paging.total_pages;
    nextBtn.onclick = () => loadOrders(paging.page + 1);
    container.appendChild(nextBtn);
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadOrders(1);
    }, 400);
}

function viewOrderDetail(orderId) {
    const order = currentOrders.find(o => o.order_id === orderId);
    if (!order) return;

    document.getElementById('detailOrderIdText').innerText = `ID: ${order.order_id}`;
    document.getElementById('detailCustomer').innerText = order.customer_name;
    document.getElementById('detailDate').innerText = order.created_at_cenco;
    document.getElementById('detailStatus').innerText = order.status;
    document.getElementById('detailShipping').innerText = order.shipping_status;
    
    const formattedTotal = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(order.total_amount);
    document.getElementById('detailTotalAmount').innerText = formattedTotal;

    const tbody = document.getElementById('detailItemsBody');
    tbody.innerHTML = '';

    // Handle mock items or real structured suborder items
    const rawData = order.raw_data;
    let items = [];
    
    if (rawData && Array.isArray(rawData.items)) {
        // Mock structure
        items = rawData.items;
    } else if (rawData && rawData.sub_orders) {
        // Real Cencosud API structure
        const subOrders = Array.isArray(rawData.sub_orders) ? rawData.sub_orders : [rawData.sub_orders];
        subOrders.forEach(so => {
            if (so && Array.isArray(so.items)) {
                items = items.concat(so.items);
            }
        });
    }

    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-muted">No hay desglose de productos disponible para este pedido.</td></tr>`;
    } else {
        items.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = "border-b border-border/30 last:border-none";
            
            const price = parseFloat(item.price || item.unitPrice || 0);
            const qty = parseInt(item.qty || item.quantity || 1);
            const subtotal = price * qty;
            
            const formatPrice = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(price);
            const formatSubtotal = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(subtotal);

            tr.innerHTML = `
                <td class="p-2.5">
                    <span class="font-semibold text-primary block">${item.title || item.productName || 'Producto sin nombre'}</span>
                    <span class="text-[9px] text-muted font-mono">${item.sku || 'Sin SKU'}</span>
                </td>
                <td class="p-2.5 text-center font-medium text-primary">${qty}</td>
                <td class="p-2.5 text-right font-medium text-primary">${formatPrice}</td>
                <td class="p-2.5 text-right font-bold text-accent">${formatSubtotal}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    const modal = document.getElementById('orderDetailModal');
    const card = document.getElementById('modalCard');
    modal.classList.remove('hidden');
    setTimeout(() => {
        card.classList.remove('scale-95', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeDetailModal() {
    const modal = document.getElementById('orderDetailModal');
    const card = document.getElementById('modalCard');
    card.classList.remove('scale-100', 'opacity-100');
    card.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function syncOrders() {
    const store = "<?php echo $activeStoreId; ?>";
    const btn = document.getElementById('syncBtn');
    const icon = document.getElementById('syncIcon');
    const text = document.getElementById('syncBtnText');

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.innerText = "Sincronizando...";

    const formData = new FormData();
    formData.append('store_id', store);
    formData.append('csrf_token', "<?php echo $csrfToken; ?>");

    fetch(`<?php echo URLROOT; ?>/cencosud/syncOrders`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.error) {
            alert("Error al sincronizar: " + res.error);
        } else {
            loadOrders(1);
        }
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.innerText = "Sincronizar Ventas";
    })
    .catch(err => {
        alert("Error de red.");
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.innerText = "Sincronizar Ventas";
    });
}
</script>
