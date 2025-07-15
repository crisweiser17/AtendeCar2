# AtendeCar - Sistema de Gestão de Clientes

Sistema desenvolvido em PHP puro para gestão de clientes de lojas de veículos, com funcionalidades de cadastro, edição, listagem e controle de status.

## Características

- **PHP Puro**: Sem frameworks, sem MVC, sem sistema de rotas
- **Interface Moderna**: Utiliza Tailwind CSS e Preline.co
- **Responsivo**: Interface adaptável para desktop e mobile
- **Integração ViaCEP**: Autocomplete de endereços via API
- **Sistema de Autenticação**: Login simples e seguro
- **CRUD Completo**: Criar, listar, editar e excluir clientes

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Extensões PHP: PDO, PDO_MySQL, JSON

## Instalação

### 1. Clone ou baixe o projeto
```bash
git clone [url-do-repositorio]
cd atendecar
```

### 2. Configure o banco de dados
1. Crie um banco de dados MySQL chamado `atendecar`
2. Edite o arquivo `config/database.php` com suas credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'atendecar');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### 3. Execute o sistema
1. Coloque os arquivos no diretório do seu servidor web
2. Acesse o sistema pelo navegador
3. As tabelas serão criadas automaticamente no primeiro acesso

## Acesso Padrão

- **URL**: `http://localhost/atendecar/`
- **Email**: `admin@atendecar.net`
- **Senha**: `password`

## Estrutura do Projeto

```
atendecar/
├── config/
│   └── database.php          # Configurações do banco de dados
├── includes/
│   ├── header.php           # Cabeçalho HTML comum
│   └── footer.php           # Rodapé e scripts JavaScript
├── index.php                # Dashboard principal
├── login.php                # Página de login
├── logout.php               # Script de logout
├── clientes.php             # Listagem de clientes
├── cliente_form.php         # Formulário de cadastro/edição
└── README.md                # Este arquivo
```

## Funcionalidades

### Dashboard
- Visão geral do sistema
- Acesso rápido aos módulos
- Interface limpa e intuitiva

### Gestão de Clientes
- **Cadastro**: Formulário completo com todos os campos solicitados
- **Listagem**: Visualização em lista com filtros e busca
- **Edição**: Modificação de dados existentes
- **Exclusão**: Remoção com confirmação
- **Status**: Controle de status (Pendente, Ativo, Desabilitado)

### Campos do Cliente

#### Dados Básicos
- Nome da Pessoa Responsável
- Email
- Celular

#### Dados da Loja
- Nome da Loja de Veículos
- WhatsApp da Loja
- Telefone Fixo da Loja
- Website da Loja
- Endereço da Loja (com autocomplete via ViaCEP)
- Estoque Médio de Veículos
- Média de Vendas Mensais
- Tipo de Estoque (múltipla escolha)
- Segmento de Atuação (múltipla escolha)

#### Ativação do Cliente
- Nome da Instância do WhatsApp
- URL do Estoque
- Alertas de Hot Leads (múltiplos WhatsApp)
- Controle de Cobrança/Assinatura

#### Status
- Pendente (padrão)
- Ativo
- Desabilitado (com campo para motivo)

## Recursos Técnicos

### Integração ViaCEP
- Autocomplete automático de endereços
- Busca por CEP em tempo real
- Preenchimento automático dos campos de endereço

### Validações JavaScript
- Formatação automática de telefones
- Validação de campos obrigatórios
- Confirmação de exclusão
- Controle dinâmico de campos condicionais

### Segurança
- Proteção contra SQL Injection (PDO com prepared statements)
- Escape de dados de saída (htmlspecialchars)
- Controle de sessão
- Validação de dados de entrada

### Interface
- Design responsivo com Tailwind CSS
- Componentes Preline.co
- Ícones SVG integrados
- Feedback visual para ações do usuário

## Personalização

### Modificar Campos
Para adicionar ou remover campos do cliente:
1. Altere a estrutura da tabela em `config/database.php`
2. Modifique o formulário em `cliente_form.php`
3. Atualize a listagem em `clientes.php`

### Alterar Estilos
- Os estilos utilizam Tailwind CSS via CDN
- Personalizações podem ser feitas diretamente nas classes CSS
- Componentes Preline.co podem ser customizados

### Adicionar Módulos
- Crie novos arquivos PHP seguindo a estrutura existente
- Inclua os headers e footers padrão
- Adicione links no dashboard (`index.php`)

## Troubleshooting

### Erro de Conexão com Banco
- Verifique as credenciais em `config/database.php`
- Certifique-se que o MySQL está rodando
- Confirme que o banco `atendecar` existe

### Problemas com ViaCEP
- Verifique a conexão com internet
- A API é gratuita e pode ter limitações de uso
- Funciona apenas com CEPs válidos brasileiros

### Problemas de Permissão
- Certifique-se que o servidor web tem permissão de leitura nos arquivos
- Verifique as configurações do PHP (display_errors, etc.)

## Suporte

Para dúvidas ou problemas:
1. Verifique este README
2. Consulte os comentários no código
3. Teste em ambiente local primeiro

## Licença

Este projeto foi desenvolvido para uso interno e educacional.