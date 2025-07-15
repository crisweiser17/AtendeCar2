    <style>
        /* Estilo customizado para inputs */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="url"],
        input[type="number"],
        textarea,
        select {
            background-color: #deedfc !important;
        }
        
        /* Manter foco com a mesma cor */
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="url"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            background-color: #deedfc !important;
        }
    </style>
    
    <script>
        // Inicializar componentes Preline
        window.HSStaticMethods.autoInit();
        
        // Função para normalizar URLs
        function normalizarURL(input) {
            let url = input.value.trim();
            if (url && !url.startsWith('http://') && !url.startsWith('https://')) {
                // Remove www. se existir para evitar duplicação
                url = url.replace(/^www\./, '');
                // Adiciona https://
                url = 'https://' + url;
                input.value = url;
            }
        }
        
        // Função para buscar CEP
        function buscarCEP(cep) {
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('endereco_loja').value = 
                                `${data.logradouro}, ${data.bairro}, ${data.localidade} - ${data.uf}`;
                        }
                    })
                    .catch(error => console.error('Erro ao buscar CEP:', error));
            }
        }
        
        // Função para formatar telefone
        function formatarTelefone(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
            } else {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            input.value = value;
        }
        
        // Função para confirmar exclusão
        function confirmarExclusao(nome) {
            return confirm(`Tem certeza que deseja excluir o cliente "${nome}"?`);
        }
        
        // Função para mostrar/ocultar campo de motivo de desabilitação
        function toggleMotivoDesabilitacao() {
            const status = document.getElementById('status').value;
            const motivoDiv = document.getElementById('motivo_desabilitacao_div');
            if (status === 'desabilitado') {
                motivoDiv.style.display = 'block';
            } else {
                motivoDiv.style.display = 'none';
            }
        }
        
        // Função para adicionar campo de WhatsApp para alertas
        function adicionarWhatsApp() {
            const container = document.getElementById('alertas_whatsapp_container');
            const div = document.createElement('div');
            div.className = 'flex gap-2 mb-2';
            div.innerHTML = `
                <input type="text" name="alertas_whatsapp[]"
                       class="flex-1 py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                       placeholder="(11) 99999-9999" onkeyup="formatarTelefone(this)">
                <button type="button" onclick="this.parentElement.remove()"
                        class="py-3 px-4 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Remover
                </button>
            `;
            container.appendChild(div);
        }
        
        // Função para importar estoque
        function importarEstoque() {
            const urlEstoque = document.getElementById('url_estoque').value;
            const clienteId = getClienteId(); // Função para pegar o ID do cliente atual
            
            if (!urlEstoque || urlEstoque === 'https://') {
                alert('Por favor, insira uma URL válida para o estoque.');
                return;
            }
            
            if (!clienteId) {
                alert('Erro: ID do cliente não encontrado.');
                return;
            }
            
            // Mostrar loading
            const botao = event.target;
            const textoOriginal = botao.textContent;
            botao.textContent = 'Importando...';
            botao.disabled = true;
            
            // Fazer requisição AJAX
            fetch('importador_estoque.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=importar&cliente_id=${clienteId}&url=${encodeURIComponent(urlEstoque)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    alert(`✅ ${data.mensagem}`);
                } else {
                    alert(`❌ Erro: ${data.erro}`);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao importar estoque. Verifique a URL e tente novamente.');
            })
            .finally(() => {
                // Restaurar botão
                botao.textContent = textoOriginal;
                botao.disabled = false;
            });
        }
        
        // Função para obter o ID do cliente atual (para páginas de edição)
        function getClienteId() {
            // Tentar pegar da URL (cliente_form.php?id=123)
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('id');
            
            if (id) {
                return id;
            }
            
            // Se não encontrou na URL, tentar pegar de um campo hidden ou data attribute
            const hiddenField = document.querySelector('input[name="cliente_id"]');
            if (hiddenField) {
                return hiddenField.value;
            }
            
            return null;
        }
    </script>
</body>
</html>