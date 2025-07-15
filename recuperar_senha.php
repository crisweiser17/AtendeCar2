<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - AtendeCar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Recuperar Senha
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Informe seu email para receber instruções de recuperação
                </p>
            </div>
            
            <div id="mensagemSucesso" class="hidden">
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
                    <h3 class="text-sm font-medium text-green-800">Sucesso!</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>Verifique sua caixa de entrada e siga as instruções para redefinir sua senha.</p>
                        <p class="mt-2">O link de redefinição expira em 1 hora.</p>
                    </div>
                    <div class="mt-4">
                        <a href="login.php" class="text-blue-600 hover:text-blue-500">
                            Ir para login →
                        </a>
                    </div>
                </div>
            </div>
            
            <div id="mensagemErro" class="hidden">
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                    <h3 class="text-sm font-medium text-red-800">Erro</h3>
                    <div class="mt-2 text-sm text-red-700" id="erroTexto"></div>
                </div>
            </div>
            
            <form class="mt-8 space-y-6" id="recuperacaoForm">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email
                    </label>
                    <input type="email" id="email" name="email" required 
                           class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="seu@email.com">
                </div>
                
                <div>
                    <button type="submit" id="enviarBtn"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span id="btnText">Enviar Instruções</span>
                        <span id="btnLoading" class="hidden">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Enviando...
                        </span>
                    </button>
                </div>
                
                <div class="text-center">
                    <a href="login.php" class="text-sm text-blue-600 hover:text-blue-500">
                        Voltar para login
                    </a>
                </div>
            </form>

            <script>
                document.getElementById('recuperacaoForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const email = document.getElementById('email').value;
                    const btnText = document.getElementById('btnText');
                    const btnLoading = document.getElementById('btnLoading');
                    const mensagemSucesso = document.getElementById('mensagemSucesso');
                    const mensagemErro = document.getElementById('mensagemErro');
                    const erroTexto = document.getElementById('erroTexto');
                    
                    // Mostrar loading
                    btnText.classList.add('hidden');
                    btnLoading.classList.remove('hidden');
                    mensagemSucesso.classList.add('hidden');
                    mensagemErro.classList.add('hidden');
                    
                    try {
                        const response = await fetch('enviar_recuperacao.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'email=' + encodeURIComponent(email)
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            mensagemSucesso.classList.remove('hidden');
                            document.getElementById('recuperacaoForm').style.display = 'none';
                        } else {
                            erroTexto.textContent = data.message;
                            mensagemErro.classList.remove('hidden');
                        }
                    } catch (error) {
                        erroTexto.textContent = 'Erro ao enviar solicitação. Tente novamente.';
                        mensagemErro.classList.remove('hidden');
                    } finally {
                        btnText.classList.remove('hidden');
                        btnLoading.classList.add('hidden');
                    }
                });
            </script>
        </div>
    </div>
</body>
</html>