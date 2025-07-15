<?php
// Página para administradores redefinirem senhas manualmente
// Acesso restrito - deve ser usado com cuidado

session_start();
require_once 'config/database.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

if ($_POST) {
    $user_id = $_POST['user_id'] ?? null;
    $new_password = $_POST['new_password'] ?? '';
    
    if ($user_id && $new_password) {
        if (strlen($new_password) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } else {
            $pdo = getConnection();
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt->execute([$hashed_password, $user_id]);
            
            $message = 'Senha redefinida com sucesso!';
        }
    } else {
        $error = 'Por favor, preencha todos os campos.';
    }
}

// Buscar usuários
$pdo = getConnection();
$stmt = $pdo->query("SELECT id, email FROM users ORDER BY email");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">Redefinir Senha - Admin</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-blue-600 hover:text-blue-500">← Voltar</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Redefinir Senha de Usuário</h2>
                
                <?php if ($message): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative mb-4">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700">
                            Usuário
                        </label>
                        <select id="user_id" name="user_id" required 
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Selecione um usuário</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">
                            Nova Senha
                        </label>
                        <input type="password" id="new_password" name="new_password" required 
                               class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                               placeholder="Mínimo 6 caracteres">
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Redefinir Senha
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                    <h3 class="text-sm font-medium text-yellow-800">Instruções para recuperação de senha:</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>• Entre em contato com o administrador do sistema</p>
                        <p>• O administrador pode usar esta página para redefinir sua senha</p>
                        <p>• Você receberá a nova senha por um canal seguro (WhatsApp, email ou pessoalmente)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>