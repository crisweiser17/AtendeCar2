<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getConnection();
$message = '';
$messageType = '';

// Processar ações
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        if ($stmt->execute([$_POST['id']])) {
            $message = 'Cliente excluído com sucesso!';
            $messageType = 'success';
        } else {
            $message = 'Erro ao excluir cliente.';
            $messageType = 'error';
        }
    }
}

// Parâmetros de paginação
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = (int)($_GET['per_page'] ?? 25);
$allowed_per_page = [10, 25, 50];
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 25;
}

// Buscar clientes
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Contar total de registros
$countSql = "SELECT COUNT(*) FROM clientes c WHERE 1=1";
$countParams = [];

if ($search) {
    $countSql .= " AND (c.nome_responsavel LIKE ? OR c.nome_loja LIKE ? OR c.email LIKE ?)";
    $searchTerm = "%$search%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

if ($status_filter) {
    $countSql .= " AND c.status = ?";
    $countParams[] = $status_filter;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total_records = $countStmt->fetchColumn();

// Calcular paginação
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Buscar clientes com paginação incluindo última sincronização
$sql = "SELECT c.*,
               (SELECT MAX(v.updated_at)
                FROM veiculos v
                WHERE v.cliente_id = c.id) as ultima_sincronizacao_estoque
        FROM clientes c
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (c.nome_responsavel LIKE ? OR c.nome_loja LIKE ? OR c.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($status_filter) {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY c.created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-500 mr-4">← Dashboard</a>
                    <h1 class="text-xl font-semibold text-gray-900">Gerenciar Clientes</h1>
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
            
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Header com botão de adicionar -->
            <div class="sm:flex sm:items-center sm:justify-between mb-6">
                <div>
                    <h2 class="text-lg font-medium text-gray-900">Lista de Clientes</h2>
                    <p class="mt-1 text-sm text-gray-500">Gerencie todos os clientes do sistema</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <a href="cliente_form.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Adicionar Cliente
                    </a>
                </div>
            </div>

            <!-- Filtros e Controles -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Buscar por nome, loja ou email..."
                               class="w-full py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <select name="status" class="py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos os status</option>
                            <option value="pendente" <?php echo $status_filter === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="ativo" <?php echo $status_filter === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="desabilitado" <?php echo $status_filter === 'desabilitado' ? 'selected' : ''; ?>>Desabilitado</option>
                        </select>
                    </div>
                    <div>
                        <select name="per_page" class="py-2 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10 por página</option>
                            <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25 por página</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50 por página</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Filtrar
                    </button>
                    <?php if ($search || $status_filter): ?>
                        <a href="clientes.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                            Limpar
                        </a>
                    <?php endif; ?>
                </form>
                
                <!-- Informações de paginação -->
                <?php if ($total_records > 0): ?>
                    <div class="mt-4 text-sm text-gray-600">
                        Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $per_page, $total_records); ?> de <?php echo $total_records; ?> clientes
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tabela de clientes -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <?php if (empty($clientes)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum cliente encontrado</h3>
                        <p class="mt-1 text-sm text-gray-500">Comece adicionando um novo cliente.</p>
                        <div class="mt-6">
                            <a href="cliente_form.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Adicionar Cliente
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($clientes as $cliente): ?>
                            <li class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 mr-4">
                                                <div class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                                    #<?php echo str_pad($cliente['id'], 2, '0', STR_PAD_LEFT); ?>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h3 class="text-lg font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($cliente['nome_responsavel']); ?>
                                                </h3>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($cliente['nome_loja']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($cliente['email']); ?> •
                                                    <?php echo htmlspecialchars($cliente['celular']); ?>
                                                </p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    <svg class="inline h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    Última sincronização:
                                                    <?php
                                                    if ($cliente['ultima_sincronizacao_estoque']) {
                                                        echo date('d/m/Y H:i', strtotime($cliente['ultima_sincronizacao_estoque']));
                                                    } else {
                                                        echo 'Nunca sincronizado';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="ml-4">
                                                <?php
                                                $statusColors = [
                                                    'pendente' => 'bg-yellow-100 text-yellow-800',
                                                    'ativo' => 'bg-green-100 text-green-800',
                                                    'desabilitado' => 'bg-red-100 text-red-800'
                                                ];
                                                $statusLabels = [
                                                    'pendente' => 'Pendente',
                                                    'ativo' => 'Ativo',
                                                    'desabilitado' => 'Desabilitado'
                                                ];
                                                ?>
                                                <?php if ($cliente['status'] === 'desabilitado' && !empty($cliente['motivo_desabilitacao'])): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusColors[$cliente['status']]; ?> cursor-help"
                                                          title="Motivo: <?php echo htmlspecialchars($cliente['motivo_desabilitacao']); ?>">
                                                        <?php echo $statusLabels[$cliente['status']]; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusColors[$cliente['status']]; ?>">
                                                        <?php echo $statusLabels[$cliente['status']]; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <a href="estoque.php?id=<?php echo $cliente['id']; ?>"
                                           class="text-green-600 hover:text-green-500 text-sm font-medium">
                                           Estoque
                                       </a>
                                       <a href="ver_leads_lojista.php?client_id=<?php echo $cliente['id']; ?>"
                                           class="text-purple-600 hover:text-purple-500 text-sm font-medium">
                                           Leads
                                       </a>
                                       <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>"
                                           class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                                           Editar
                                       </a>
                                        <form method="POST" class="inline" onsubmit="return confirmarExclusao('<?php echo htmlspecialchars($cliente['nome_responsavel']); ?>')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-500 text-sm font-medium">
                                                Excluir
                                            </button>
                                        </form>
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

<script>
function confirmarExclusao(nomeCliente) {
    return confirm('Tem certeza que deseja excluir o cliente "' + nomeCliente + '"? Esta ação não pode ser desfeita.');
}
</script>

<?php include 'includes/footer.php'; ?>