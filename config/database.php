<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'uuzybhpjay');
define('DB_USER', 'uuzybhpjay');
define('DB_PASS', 'yVRuFD2nk3');

// Função para conectar ao banco de dados
function getConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Configurar timezone do MySQL para São Paulo
        $pdo->exec("SET time_zone = '-03:00'");
        
        return $pdo;
    } catch(PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
    }
}

// Criar tabelas se não existirem
function createTables() {
    $pdo = getConnection();
    
    // Tabela de usuários
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Inserir usuário admin padrão
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@atendecar.net']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->execute(['admin@atendecar.net', password_hash('password', PASSWORD_DEFAULT)]);
    }
    
    // Tabela de clientes
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_responsavel VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        celular VARCHAR(20) NOT NULL,
        nome_loja VARCHAR(255) NOT NULL,
        whatsapp_loja VARCHAR(20),
        telefone_loja VARCHAR(20),
        website_loja TEXT,
        cep VARCHAR(10),
        endereco_loja TEXT,
        numero_endereco VARCHAR(20),
        complemento_endereco VARCHAR(255),
        estoque_medio INT,
        vendas_mensais INT,
        tipo_estoque JSON,
        segmento_atuacao JSON,
        nome_instancia_whatsapp VARCHAR(255),
        token_evo_api VARCHAR(255),
        url_estoque TEXT,
        alertas_whatsapp JSON,
        cobranca_ativa BOOLEAN DEFAULT FALSE,
        status ENUM('pendente', 'ativo', 'desabilitado') DEFAULT 'pendente',
        motivo_desabilitacao TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Adicionar colunas se não existirem (para bancos existentes)
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN cep VARCHAR(10)");
    } catch (Exception $e) {
        // Coluna já existe
    }
    
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN numero_endereco VARCHAR(20)");
    } catch (Exception $e) {
        // Coluna já existe
    }
    
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN complemento_endereco VARCHAR(255)");
    } catch (Exception $e) {
        // Coluna já existe
    }
    
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN token_evo_api VARCHAR(255)");
    } catch (Exception $e) {
        // Coluna já existe
    }
    
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN ultima_sincronizacao TIMESTAMP NULL");
    } catch (Exception $e) {
        // Coluna já existe
    }
    
    // Tabela de filas de sincronização
    $pdo->exec("CREATE TABLE IF NOT EXISTS sync_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        priority INT DEFAULT 0,
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 3,
        error_message TEXT,
        scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        INDEX idx_status_priority (status, priority, scheduled_at),
        INDEX idx_cliente_status (cliente_id, status)
    )");
    
    // Tabela de veículos
    $pdo->exec("CREATE TABLE IF NOT EXISTS veiculos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        nome VARCHAR(500) NOT NULL,
        preco DECIMAL(10,2),
        ano INT,
        km INT,
        cambio VARCHAR(50),
        cor VARCHAR(100),
        combustivel VARCHAR(50),
        link TEXT,
        foto TEXT,
        ativo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        INDEX idx_cliente_id (cliente_id),
        INDEX idx_ativo (ativo)
    )");
}

// Executar criação das tabelas
createTables();
?>