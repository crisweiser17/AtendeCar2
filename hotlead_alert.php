<?php
// Configurar timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

// Headers para JSON e CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Se for requisição OPTIONS (preflight), retornar 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuração do banco de dados
require_once 'config/database.php';

// URL base fixa da Evolution API
const EVOLUTION_API_BASE_URL = 'https://evolution-evolution-api.zhtcom.easypanel.host/message/sendText/';

// Função para registrar logs
function registrarLog($mensagem, $tipo = 'INFO') {
    $logDir = 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $dataHora = date('Y-m-d H:i:s');
    $logMensagem = "[$dataHora] $tipo: $mensagem" . PHP_EOL;
    file_put_contents($logDir . '/hotlead_alert.log', $logMensagem, FILE_APPEND | LOCK_EX);
}

// Função para salvar payload - REMOVIDA (não salva mais arquivos .txt)
function salvarPayload($payload, $cliente_nome, $numero_destino, $instancia) {
    // Agora apenas loga o payload no arquivo de log principal
    registrarLog("Payload enviado - Cliente: $cliente_nome, Número: $numero_destino, Instância: $instancia, Payload: " . json_encode($payload));
    return null;
}

// Função para formatar número de WhatsApp
function formatarNumeroWhatsApp($numero) {
    // Remover todos os caracteres não numéricos
    $numeroLimpo = preg_replace('/[^0-9]/', '', $numero);
    
    // Adicionar código do país +55 se não estiver presente
    if (strlen($numeroLimpo) === 11 && substr($numeroLimpo, 0, 2) !== '55') {
        // Número brasileiro sem código do país (ex: 11999898999)
        $numeroLimpo = '55' . $numeroLimpo;
    } elseif (strlen($numeroLimpo) === 10 && substr($numeroLimpo, 0, 2) !== '55') {
        // Número brasileiro sem DDD e sem código do país (ex: 999898999)
        // Adicionar DDD 19 (SP) e código do país
        $numeroLimpo = '5519' . $numeroLimpo;
    } elseif (substr($numeroLimpo, 0, 2) === '55') {
        // Já tem código do país, manter como está
        // Remover o 55 se estiver duplicado
        if (substr($numeroLimpo, 0, 4) === '5555') {
            $numeroLimpo = substr($numeroLimpo, 2);
        }
    } else {
        // Adicionar código do país +55
        $numeroLimpo = '55' . $numeroLimpo;
    }
    
    return $numeroLimpo;
}

// Função para escapar caracteres especiais na mensagem
function escaparMensagem($mensagem) {
    // Limpar múltiplos escapes e manter apenas o necessário
    $mensagem = stripslashes($mensagem);
    
    // Substituir aspas duplas por aspas simples para evitar problemas
    $mensagem = str_replace('"', "'", $mensagem);
    
    // Remover quebras de linha
    $mensagem = str_replace(["\n", "\r"], ' ', $mensagem);
    
    // Limpar espaços múltiplos
    $mensagem = preg_replace('/\s+/', ' ', $mensagem);
    
    return trim($mensagem);
}

// Função para enviar mensagem via webhook
function enviarMensagemWhatsApp($numero, $mensagem, $nome_instancia, $token_evo) {
    // Construir URL completa com a instância
    $webhookUrl = EVOLUTION_API_BASE_URL . $nome_instancia;
    
    // Usar mensagem direta sem escapamento adicional
    $payload = [
        'number' => $numero,
        'text' => $mensagem,
        'options' => [
            'delay' => 1200,
            'presence' => 'composing'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'apikey: ' . $token_evo
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log detalhado da resposta da API
    registrarLog("Resposta da API - URL: $webhookUrl, HTTP Code: $httpCode, Response: $response, Error: $error", 'DEBUG');
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'httpCode' => $httpCode,
        'response' => $response,
        'error' => $error,
        'url' => $webhookUrl,
        'payload' => $payload
    ];
}

// Função para validar parâmetros
function validarParametros($client_id, $lead_name, $lead_number) {
    $erros = [];
    
    if (empty($client_id) || !is_numeric($client_id) || $client_id <= 0) {
        $erros[] = 'client_id é obrigatório e deve ser um número positivo';
    }
    
    if (empty($lead_name) || !is_string($lead_name)) {
        $erros[] = 'lead_name é obrigatório e deve ser uma string';
    }
    
    if (empty($lead_number) || !is_string($lead_number)) {
        $erros[] = 'lead_number é obrigatório e deve ser uma string';
    }
    
    return $erros;
}

// Função para salvar lead no banco de dados com verificação de duplicados por cliente e veículo
function salvarLead($pdo, $client_id, $lead_name, $lead_number, $is_hot_lead = true, $veiculo = null) {
    try {
        $lead_number_formatado = formatarNumeroWhatsApp($lead_number);
        
        // Verificar se o lead já existe para este cliente específico
        $stmt = $pdo->prepare("SELECT id, is_hot_lead, veiculo FROM leads WHERE client_id = ? AND lead_number = ?");
        $stmt->execute([$client_id, $lead_number_formatado]);
        $lead_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lead_existente) {
            // Lead já existe para este cliente, verificar se precisa atualizar
            $lead_id = $lead_existente['id'];
            $hot_lead_atual = (bool)$lead_existente['is_hot_lead'];
            $veiculo_atual = $lead_existente['veiculo'] ?? null;
            
            $updates = [];
            $params = [];
            
            if ($is_hot_lead && !$hot_lead_atual) {
                $updates[] = "is_hot_lead = 1";
            }
            
            if ($veiculo && $veiculo !== $veiculo_atual) {
                $updates[] = "veiculo = ?";
                $params[] = $veiculo;
            }
            
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $sql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = ?";
                $params[] = $lead_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                registrarLog("Lead atualizado: ID=$lead_id, client_id=$client_id, updates=" . implode(', ', $updates));
                return $lead_id;
            } else {
                registrarLog("Lead já existe para este cliente, sem mudanças: ID=$lead_id, client_id=$client_id");
                return $lead_id;
            }
        } else {
            // Lead não existe para este cliente, inserir novo
            $stmt = $pdo->prepare("
                INSERT INTO leads (client_id, lead_number, lead_name, is_hot_lead, veiculo)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $client_id,
                $lead_number_formatado,
                $lead_name,
                $is_hot_lead ? 1 : 0,
                $veiculo
            ]);
            
            $lead_id = $pdo->lastInsertId();
            registrarLog("Novo lead salvo: ID=$lead_id, client_id=$client_id, lead_name=$lead_name, lead_number=$lead_number_formatado, hot_lead=" . ($is_hot_lead ? 'true' : 'false') . ", veiculo=" . ($veiculo ?? 'NULL'));
            return $lead_id;
        }
    } catch (Exception $e) {
        registrarLog("Erro ao salvar lead: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Início do processamento com debug
registrarLog('=== NOVA REQUISIÇÃO HOTLEAD ===');

try {
    // Obter parâmetros via GET
    $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
    $lead_name = isset($_GET['lead_name']) ? trim($_GET['lead_name']) : null;
    $lead_number = isset($_GET['lead_number']) ? trim($_GET['lead_number']) : null;
    
    registrarLog("Parâmetros recebidos - client_id: $client_id, lead_name: $lead_name, lead_number: $lead_number");
    
    // Validar parâmetros
    $erros = validarParametros($client_id, $lead_name, $lead_number);
    if (!empty($erros)) {
        registrarLog('Erro de validação: ' . implode(', ', $erros), 'ERROR');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Parâmetros inválidos',
            'details' => $erros
        ]);
        exit();
    }
    
    // Conectar ao banco de dados
    $pdo = getConnection();
    
    // Buscar cliente, números de WhatsApp, instância e token
    $stmt = $pdo->prepare("SELECT id, nome_loja, alertas_whatsapp, nome_instancia_whatsapp, token_evo_api FROM clientes WHERE id = ?");
    $stmt->execute([$client_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        registrarLog("Cliente não encontrado: ID $client_id", 'ERROR');
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cliente não encontrado',
            'details' => "Nenhum cliente encontrado com ID: $client_id"
        ]);
        exit();
    }
    
    registrarLog("Cliente encontrado: {$cliente['nome_loja']} (ID: {$cliente['id']})");
    
    // Validar instância e token
    $nome_instancia = $cliente['nome_instancia_whatsapp'] ?? '';
    $token_evo = $cliente['token_evo_api'] ?? '';
    
    if (empty($nome_instancia) || empty($token_evo)) {
        registrarLog("Cliente {$cliente['nome_loja']} não tem instância ou token configurados", 'WARNING');
        http_response_code(200);
        echo json_encode([
            'status' => 'warning',
            'message' => 'Cliente não possui instância WhatsApp ou token configurados',
            'details' => [
                'client_id' => $client_id,
                'cliente_nome' => $cliente['nome_loja'],
                'has_instancia' => !empty($nome_instancia),
                'has_token' => !empty($token_evo)
            ]
        ]);
        exit();
    }
    
    // Processar números de WhatsApp
    $alertas_whatsapp = json_decode($cliente['alertas_whatsapp'] ?? '[]', true);
    
    if (empty($alertas_whatsapp) || !is_array($alertas_whatsapp)) {
        registrarLog("Cliente {$cliente['nome_loja']} não tem números de WhatsApp configurados", 'WARNING');
        http_response_code(200);
        echo json_encode([
            'status' => 'warning',
            'message' => 'Cliente não possui números de WhatsApp configurados',
            'details' => [
                'client_id' => $client_id,
                'cliente_nome' => $cliente['nome_loja']
            ]
        ]);
        exit();
    }
    
    // Preparar mensagem limpa sem escapamento excessivo
    $mensagem = "AtendeCar identificou um lead qualificado. Nome = " . $lead_name . " e numero = " . $lead_number;
    
    // Receber veículo via GET (opcional)
    $veiculo = isset($_GET['veiculo']) ? trim($_GET['veiculo']) : null;
    
    // Salvar lead no banco de dados com veículo
    $lead_id = salvarLead($pdo, $client_id, $lead_name, $lead_number, true, $veiculo);
    
    // Enviar mensagens
    $envios = [];
    $envios_sucesso = 0;
    $envios_erro = 0;
    
    foreach ($alertas_whatsapp as $numero_original) {
        $numero_formatado = formatarNumeroWhatsApp($numero_original);
        
        if (!$numero_formatado) {
            registrarLog("Número inválido: $numero_original", 'WARNING');
            $envios[] = [
                'numero_original' => $numero_original,
                'status' => 'erro',
                'erro' => 'Formato de número inválido'
            ];
            $envios_erro++;
            continue;
        }
        
        $payload = [
            'number' => $numero_formatado,
            'text' => $mensagem
        ];
        
        // Log do payload (sem salvar arquivo .txt)
        salvarPayload($payload, $cliente['nome_loja'], $numero_formatado, $nome_instancia);
        
        $resultado = enviarMensagemWhatsApp($numero_formatado, $mensagem, $nome_instancia, $token_evo);
        
        if ($resultado['success']) {
            registrarLog("Mensagem enviada com sucesso para: $numero_formatado via instância: $nome_instancia");
            $envios[] = [
                'numero_original' => $numero_original,
                'numero_formatado' => $numero_formatado,
                'status' => 'sucesso',
                'http_code' => $resultado['httpCode']
            ];
            $envios_sucesso++;
        } else {
            registrarLog("Erro ao enviar mensagem para: $numero_formatado via instância: $nome_instancia - " . $resultado['error'], 'ERROR');
            $envios[] = [
                'numero_original' => $numero_original,
                'numero_formatado' => $numero_formatado,
                'status' => 'erro',
                'http_code' => $resultado['httpCode'],
                'erro' => $resultado['error']
            ];
            $envios_erro++;
        }
    }
    
    // Preparar resposta final
    $resposta = [
        'status' => $envios_erro > 0 ? 'partial' : 'success',
        'message' => sprintf('%d mensagens enviadas com sucesso, %d erros', $envios_sucesso, $envios_erro),
        'details' => [
            'client_id' => $client_id,
            'cliente_nome' => $cliente['nome_loja'],
            'lead_name' => $lead_name,
            'lead_number' => $lead_number,
            'lead_id' => $lead_id,
            'instancia' => $nome_instancia,
            'total_numeros' => count($alertas_whatsapp),
            'envios_sucesso' => $envios_sucesso,
            'envios_erro' => $envios_erro,
            'envios' => $envios
        ]
    ];
    
    registrarLog("Processamento concluído - Sucesso: $envios_sucesso, Erros: $envios_erro, Lead ID: $lead_id");
    
    http_response_code(200);
    echo json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    registrarLog('Erro fatal: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'details' => $e->getMessage()
    ]);
}
?>