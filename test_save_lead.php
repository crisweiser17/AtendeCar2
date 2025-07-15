<?php
// Teste para salvar lead com hot_lead=false
// Use: php test_save_lead.php ou acesse via browser

require_once 'config/database.php';

function testarSalvarLead() {
    try {
        $pdo = getConnection();
        
        // Teste 1: Salvar lead normal (hot_lead=false)
        $stmt = $pdo->prepare("
            INSERT INTO leads (client_id, lead_number, lead_name, is_hot_lead) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            1, // client_id de teste
            '5519991234567', // número formatado
            'Cliente Teste Normal', 
            0 // hot_lead = false
        ]);
        
        $lead_id_1 = $pdo->lastInsertId();
        echo "✅ Lead normal salvo com ID: $lead_id_1\n";
        
        // Teste 2: Salvar hot lead (hot_lead=true)
        $stmt->execute([
            1, // client_id de teste
            '5519987654321', // número formatado
            'Cliente Hot Lead Teste', 
            1 // hot_lead = true
        ]);
        
        $lead_id_2 = $pdo->lastInsertId();
        echo "✅ Hot lead salvo com ID: $lead_id_2\n";
        
        // Teste 3: Verificar dados salvos
        $stmt = $pdo->query("SELECT * FROM leads ORDER BY id DESC LIMIT 5");
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n📊 Últimos leads salvos:\n";
        foreach ($leads as $lead) {
            echo "ID: {$lead['id']} | Cliente: {$lead['client_id']} | Nome: {$lead['lead_name']} | Número: {$lead['lead_number']} | Hot: " . ($lead['is_hot_lead'] ? 'Sim' : 'Não') . " | Data: {$lead['created_at']}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}

// Executar teste
echo "🧪 Testando salvamento de leads...\n";
testarSalvarLead();
echo "✅ Teste concluído!\n";
?>