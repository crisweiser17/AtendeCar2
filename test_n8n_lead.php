<?php
// Teste para simular POST do n8n
// Use: php test_n8n_lead.php ou acesse via browser

require_once 'config/database.php';

function testarN8NLead() {
    echo "🧪 Testando endpoint para n8n (leads frios)...\n\n";
    
    try {
        $pdo = getConnection();
        
        // Teste 1: Lead novo para cliente 1
        echo "Teste 1 - Lead novo para cliente 1:\n";
        $resultado1 = testarLead($pdo, 1, 'Maria Silva', '11999898999', false);
        echo "Resultado: " . json_encode($resultado1, JSON_PRETTY_PRINT) . "\n\n";
        
        // Teste 2: Mesmo número para cliente 2 (deve permitir - cliente diferente)
        echo "Teste 2 - Mesmo número para cliente 2:\n";
        $resultado2 = testarLead($pdo, 2, 'Maria Silva', '11999898999', false);
        echo "Resultado: " . json_encode($resultado2, JSON_PRETTY_PRINT) . "\n\n";
        
        // Teste 3: Tentativa duplicada para cliente 1 (deve ignorar)
        echo "Teste 3 - Tentativa duplicada para cliente 1:\n";
        $resultado3 = testarLead($pdo, 1, 'Maria Silva', '11999898999', false);
        echo "Resultado: " . json_encode($resultado3, JSON_PRETTY_PRINT) . "\n\n";
        
        // Teste 4: Atualizar para hot lead
        echo "Teste 4 - Atualizar para hot lead:\n";
        $resultado4 = testarLead($pdo, 1, 'Maria Silva', '11999898999', true);
        echo "Resultado: " . json_encode($resultado4, JSON_PRETTY_PRINT) . "\n\n";
        
        // Mostrar todos os leads
        echo "📊 Todos os leads por cliente:\n";
        $stmt = $pdo->query("SELECT client_id, lead_name, lead_number, is_hot_lead, created_at FROM leads ORDER BY client_id, id");
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($leads as $lead) {
            echo "Cliente {$lead['client_id']}: {$lead['lead_name']} - {$lead['lead_number']} - Hot: " . ($lead['is_hot_lead'] ? 'Sim' : 'Não') . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}

function testarLead($pdo, $client_id, $lead_name, $lead_number, $is_hot_lead) {
    // Simular a função salvarLead
    $lead_number_formatado = '55' . preg_replace('/[^0-9]/', '', $lead_number);
    
    // Verificar se o lead já existe para este cliente específico
    $stmt = $pdo->prepare("SELECT id, is_hot_lead FROM leads WHERE client_id = ? AND lead_number = ?");
    $stmt->execute([$client_id, $lead_number_formatado]);
    $lead_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lead_existente) {
        $lead_id = $lead_existente['id'];
        $hot_lead_atual = (bool)$lead_existente['is_hot_lead'];
        
        if ($is_hot_lead && !$hot_lead_atual) {
            $stmt = $pdo->prepare("UPDATE leads SET is_hot_lead = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$lead_id]);
            return [
                'success' => true,
                'lead_id' => $lead_id,
                'message' => 'Lead existente atualizado para hot lead',
                'action' => 'updated'
            ];
        } else {
            return [
                'success' => true,
                'lead_id' => $lead_id,
                'message' => 'Lead já existe para este cliente',
                'action' => 'skipped'
            ];
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO leads (client_id, lead_number, lead_name, is_hot_lead) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $lead_number_formatado, $lead_name, $is_hot_lead ? 1 : 0]);
        $lead_id = $pdo->lastInsertId();
        return [
            'success' => true,
            'lead_id' => $lead_id,
            'message' => 'Novo lead salvo com sucesso',
            'action' => 'inserted'
        ];
    }
}

// Executar teste
testarN8NLead();
echo "\n✅ Teste concluído!\n";
?>