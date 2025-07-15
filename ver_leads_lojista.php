<?php
// Página para visualizar leads de um lojista específico
// URL: ver_leads_lojista.php?client_id=1

// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar se client_id foi fornecido
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if ($client_id <= 0) {
    header('Location: clientes.php');
    exit();
}

$pdo = getConnection();

// Buscar informações do cliente
$stmt = $pdo->prepare("SELECT id, nome_loja, nome_responsavel FROM clientes WHERE id = ?");
$stmt->execute([$client_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: clientes.php');
    exit();
}

// Parâmetros de filtro
$search = $_GET['search'] ?? '';
$hot_lead_filter = $_GET['hot_lead'] ?? '';
$date_filter = $_GET['date_filter'] ?? 'all';
$per_page = (int)($_GET['per_page'] ?? 25);
$allowed_per_page = [10, 25, 50, 100];
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 25;
}

// Calcular datas para o filtro
$today = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('first day of previous month'));
$last_month_end = date('Y-m-t', strtotime('last day of previous month'));

// Contar total de leads
$countSql = "SELECT COUNT(*) FROM leads WHERE client_id = ?";
$countParams = [$client_id];

// Adicionar filtro de data
switch ($date_filter) {
    case 'today':
        $countSql .= " AND DATE(created_at) = ?";
        $countParams[] = $today;
        break;
    case 'this_week':
        $countSql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $countParams[] = $current_week_start;
        $countParams[] = $current_week_end;
        break;
    case 'this_month':
        $countSql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $countParams[] = $current_month_start;
        $countParams[] = $current_month_end;
        break;
    case 'last_month':
        $countSql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $countParams[] = $last_month_start;
        $countParams[] = $last_month_end;
        break;
    case 'all':
    default:
        // Não adicionar filtro de data
        break;
}

if ($search) {
    $countSql .= " AND (lead_name LIKE ? OR lead_number LIKE ? OR veiculo LIKE ?)";
    $searchTerm = "%$search%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

if ($hot_lead_filter !== '') {
    $countSql .= " AND is_hot_lead = ?";
    $countParams[] = (int)$hot_lead_filter;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total_records = $countStmt->fetchColumn();

// Paginação
$page = max(1, (int)($_GET['page'] ?? 1));
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Buscar leads
$sql = "SELECT id, lead_name, lead_number, is_hot_lead, veiculo, created_at, updated_at
        FROM leads
        WHERE client_id = ?";
$params = [$client_id];

// Adicionar filtro de data na query principal
switch ($date_filter) {
    case 'today':
        $sql .= " AND DATE(created_at) = ?";
        $params[] = $today;
        break;
    case 'this_week':
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $current_week_start;
        $params[] = $current_week_end;
        break;
    case 'this_month':
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $current_month_start;
        $params[] = $current_month_end;
        break;
    case 'last_month':
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $last_month_start;
        $params[] = $last_month_end;
        break;
    case 'all':
    default:
        // Não adicionar filtro de data
        break;
}

if ($search) {
    $sql .= " AND (lead_name LIKE ? OR lead_number LIKE ? OR veiculo LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($hot_lead_filter !== '') {
    $sql .= " AND is_hot_lead = ?";
    $params[] = (int)$hot_lead_filter;
}

$sql .= " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="clientes.php" class="text-blue-600 hover:text-blue-500 mr-4">← Clientes</a>
                    <h1 class="text-xl font-semibold text-gray-900">Leads - <?php echo htmlspecialchars($cliente['nome_loja']); ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Bem-vindo, <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <!-- Header -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($cliente['nome_loja']); ?></h2>
                        <p class="text-gray-600">Responsável: <?php echo htmlspecialchars($cliente['nome_responsavel']); ?></p>
                        <p class="text-sm text-gray-500">Total de leads: <?php echo $total_records; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">ID do Cliente: #<?php echo str_pad($cliente['id'], 3, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Buscar por nome, telefone ou veículo..."
                               class="w-full py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <select name="date_filter" class="py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>Todos os períodos</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Hoje</option>
                            <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>Esta semana</option>
                            <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>Este mês</option>
                            <option value="last_month" <?php echo $date_filter === 'last_month' ? 'selected' : ''; ?>>Mês passado</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="hot_lead" class="py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos os leads</option>
                            <option value="1" <?php echo $hot_lead_filter === '1' ? 'selected' : ''; ?>>Hot Leads</option>
                            <option value="0" <?php echo $hot_lead_filter === '0' ? 'selected' : ''; ?>>Leads Normais</option>
                        </select>
                    </div>
                    
                    <div>
                        <select name="per_page" class="py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10 por página</option>
                            <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25 por página</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50 por página</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100 por página</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Filtrar
                    </button>
                    
                    <?php if ($search || $hot_lead_filter !== '' || $date_filter !== 'all'): ?>
                        <a href="ver_leads_lojista.php?client_id=<?php echo $client_id; ?>" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                            Limpar
                        </a>
                    <?php endif; ?>
                </form>
                
                <?php if ($total_records > 0): ?>
                    <div class="mt-4 text-sm text-gray-600">
                        Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $per_page, $total_records); ?> de <?php echo $total_records; ?> leads
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tabela de Leads -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <?php if (empty($leads)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum lead encontrado</h3>
                        <p class="mt-1 text-sm text-gray-500">Este lojista ainda não possui leads cadastrados.</p>
                    </div>
                <?php else: ?>
                    <!-- Cabeçalho das Colunas -->
                    <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                        <div class="grid grid-cols-12 gap-4 items-center">
                            <div class="col-span-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID do lead
                            </div>
                            <div class="col-span-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nome
                            </div>
                            <div class="col-span-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Telefone
                            </div>
                            <div class="col-span-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Veículo de Interesse
                            </div>
                            <div class="col-span-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Data & Hora
                            </div>
                            <div class="col-span-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </div>
                        </div>
                    </div>
                    
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($leads as $lead): ?>
                            <li class="px-6 py-4">
                                <div class="grid grid-cols-12 gap-4 items-center">
                                    <!-- ID do lead -->
                                    <div class="col-span-1">
                                        <div class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full inline-block">
                                            #<?php echo str_pad($lead['id'], 3, '0', STR_PAD_LEFT); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Nome -->
                                    <div class="col-span-3">
                                        <h3 class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($lead['lead_name']); ?>
                                        </h3>
                                    </div>
                                    
                                    <!-- Telefone -->
                                    <div class="col-span-2">
                                        <p class="text-sm text-gray-600">
                                            <svg class="inline h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                            <?php echo htmlspecialchars($lead['lead_number']); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Veículo de Interesse -->
                                    <div class="col-span-3">
                                        <?php if ($lead['veiculo']): ?>
                                            <p class="text-sm text-gray-600">
                                                <svg class="inline h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <?php echo htmlspecialchars($lead['veiculo']); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-400">-</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Data & Hora -->
                                    <div class="col-span-2">
                                        <p class="text-sm text-gray-600">
                                            <svg class="inline h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <?php echo date('d/m/Y H:i', strtotime($lead['created_at'])); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Status -->
                                    <div class="col-span-1">
                                        <?php if ($lead['is_hot_lead']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Hot
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Normal
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6 rounded-lg shadow">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Anterior
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Próxima
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando <span class="font-medium"><?php echo $offset + 1; ?></span> a
                                <span class="font-medium"><?php echo min($offset + $per_page, $total_records); ?></span> de
                                <span class="font-medium"><?php echo $total_records; ?></span> resultados
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <!-- Botão Anterior -->
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Números das páginas -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                                <?php endif; ?>
                                
                                <!-- Botão Próxima -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>