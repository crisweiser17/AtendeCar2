<?php
// Página de redefinição de senha com token
session_start();
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
$error = '';
$message = '';

// Verificar se o token é válido
if (!$token) {
    $error = 'Token inválido ou expirado.';
} else {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = FALSE");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $error = 'Token inválido, expirado ou já utilizado.';
    }
}

if ($_POST && $token && !$error) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($new_password && $confirm_password) {
        if (strlen($new_password) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'As senhas não coincidem.';
        } else {
            // Atualizar senha do usuário
            $email = $reset['email'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
            $stmt->execute([$token]);
            
            $message = 'Senha redefinida com sucesso! Você pode fazer login com sua nova senha.';
        }
    } else {
        $error = 'Por favor, preencha todos os campos.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - AtendeCar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Redefinir Senha
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Defina sua nova senha
                </p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
                    <?php echo htmlspecialchars($message); ?>
                    <div class="mt-4">
                        <a href="login.php" class="text-blue-600 hover:text-blue-500">
                            Ir para login →
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form class="mt-8 space-y-6" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">
                                Nova Senha
                            </label>
                            <input type="password" id="new_password" name="new_password" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                                Confirmar Nova Senha
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Repita a nova senha">
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Redefinir Senha
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="login.php" class="text-sm text-blue-600 hover:text-blue-500">
                            Voltar para login
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>