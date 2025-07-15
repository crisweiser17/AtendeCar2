<?php
// Endpoint para salvar leads/mensagens com hot_lead=false
// Para uso futuro quando receber mensagens normais do WhatsApp

require_once 'config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function registrarLog($mensagem, $tipo = 'INFO') {
    $logDir = 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $dataHora = date('Y-m-d H:i:s');
    $logMensagem = "[$dataHora] $tipo: $mensagem" . PHP_EOL;
    file_put_contents($logDir . '/save_lead.log', $logMensagem, FILE_APPEND | LOCK_EX);
}

function formatarNumeroWhatsApp($numero) {
    $numeroLimpo = preg_replace('/[^0-9]/', '', $numero);
    
    if (strlen($numeroLimpo) === 11 && substr($numeroLimpo, 0, 2) !== '55') {
        $numeroLimpo = '55' . $numeroLimpo;
    } elseif (strlen($numeroLimpo) === 10 && substr($numeroLimpo, 0, 2) !== '55') {
        $numeroLimpo = '5519' . $numeroLimpo;
    } elseif (substr($numeroLimpo, 0, 2) === '55') {
        if (substr($numeroLimpo, 0, 4) === '5555') {
            $numeroLimpo = substr($numeroLimpo, 2);
        }
    } else {
        $numeroLimpo = '55' . $numeroLimpo;
    }
    
    return $numeroLimpo;
}

function salvarLead($pdo, $client_id, $lead_name, $lead_number, $is_hot_lead = false) {
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
            $veiculo_novo = $GLOBALS['veiculo'];
            
            $updates = [];
            $params = [];
            
            if ($is_hot_lead && !$hot_lead_atual) {
                $updates[] = "is_hot_lead = 1";
            }
            
            if ($veiculo_novo && $veiculo_novo !== $veiculo_atual) {
                $updates[] = "veiculo = ?";
                $params[] = $veiculo_novo;
            }
            
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $sql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = ?";
                $params[] = $lead_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                registrarLog("Lead atualizado: ID=$lead_id, client_id=$client_id, updates=" . implode(', ', $updates));
                return [
                    'success' => true,
                    'lead_id' => $lead_id,
                    'message' => 'Lead atualizado com sucesso',
                    'action' => 'updated'
                ];
            } else {
                registrarLog("Lead já existe para este cliente, sem mudanças: ID=$lead_id, client_id=$client_id");
                return [
                    'success' => true,
                    'lead_id' => $lead_id,
                    'message' => 'Lead já existe para este cliente',
                    'action' => 'skipped'
                ];
            }
        } else {
            // Lead não existe para este cliente, inserir novo
            $veiculo = isset($GLOBALS['veiculo']) ? $GLOBALS['veiculo'] : null;
            
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
            registrarLog("Novo lead salvo: ID=$lead_id, client_id=$client_id, lead_name=$lead_name, lead_number=$lead_number_formatado, hot_lead=" . ($is_hot_lead ? 'true' : 'false'));
            return [
                'success' => true,
                'lead_id' => $lead_id,
                'message' => 'Novo lead salvo com sucesso',
                'action' => 'inserted'
            ];
        }
    } catch (Exception $e) {
        registrarLog("Erro ao salvar lead: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Erro ao salvar lead: ' . $e->getMessage()
        ];
    }
}

try {
    // Receber dados via POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $client_id = isset($data['client_id']) ? (int)$data['client_id'] : null;
    $lead_name = isset($data['lead_name']) ? trim($data['lead_name']) : null;
    $lead_number = isset($data['lead_number']) ? trim($data['lead_number']) : null;
    $is_hot_lead = isset($data['is_hot_lead']) ? (bool)$data['is_hot_lead'] : false;
    $veiculo = isset($data['veiculo']) ? trim($data['veiculo']) : null;
    
    // Tornar veículo disponível globalmente para a função salvarLead
    $GLOBALS['veiculo'] = $veiculo;
    
    // Validar parâmetros
    if (empty($client_id) || empty($lead_name) || empty($lead_number)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros obrigatórios: client_id, lead_name, lead_number'
        ]);
        exit();
    }
    
    // Conectar ao banco
    $pdo = getConnection();
    
    // Salvar lead com veículo
    $resultado = salvarLead($pdo, $client_id, $lead_name, $lead_number, $is_hot_lead);
    
    http_response_code($resultado['success'] ? 200 : 500);
    echo json_encode($resultado);
    
} catch (Exception $e) {
    registrarLog('Erro fatal: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>