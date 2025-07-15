# Especificação Técnica - hotlead_alert.php

## Objetivo
Criar um endpoint PHP que recebe leads qualificados do n8n e dispara alertas via WhatsApp para lojistas.

## Requisitos Funcionais

### 1. Recepção de Dados
- **Método**: GET
- **Parâmetros obrigatórios**:
  - `client_id` (int): ID do cliente no banco de dados
  - `lead_name` (string): Nome do lead qualificado
  - `lead_number` (string): Número de telefone do lead

### 2. Processamento
- Validar parâmetros recebidos
- Consultar banco de dados para obter números de WhatsApp configurados
- Formatar números para envio
- Disparar mensagens via webhook

### 3. Resposta
- Formato: JSON
- Status HTTP apropriado (200, 400, 404, 500)

## Estrutura do Banco de Dados

### Tabela: `clientes`
- **Coluna**: `alertas_whatsapp` (JSON)
- **Formato armazenado**: `["(11) 5555-4444","(11) 44445-5555"]`

## Formatação de Números

### Entrada (banco)
- Formato: `(11) 99999-9999`

### Saída (webhook)
- Formato: `5511999999999`
- Processo:
  1. Remover parênteses, espaços e hífens
  2. Adicionar código do país `55`
  3. Resultado: número completo com DDD

## Configuração do Webhook

### URL
```
https://webhook.site/9fc17a5b-9798-4d34-925f-510ed883ed20
```

### Payload
```json
{
  "number": "5511999999999",
  "mensagem": "AtendeCar identificou um lead qualificado. Nome = João Silva e numero = 11988887777"
}
```

## Estrutura do Código

### 1. Configurações Iniciais
```php
<?php
// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Headers para JSON
header('Content-Type: application/json');

// Incluir configuração do banco
require_once 'config/database.php';
```

### 2. Validação de Parâmetros
- Verificar se todos os parâmetros estão presentes
- Sanitizar entrada
- Validar tipos de dados

### 3. Funções Auxiliares
- `formatarNumeroWhatsApp($numero)`: Remove formatação e adiciona código do país
- `enviarMensagemWhatsApp($numero, $mensagem)`: Envia requisição para webhook
- `registrarLog($mensagem)`: Registra eventos para debug

### 4. Fluxo Principal
1. Receber e validar parâmetros
2. Buscar cliente no banco
3. Processar números de WhatsApp
4. Enviar mensagens
5. Retornar resposta JSON

## Tratamento de Erros

### Códigos de Status HTTP
- **200**: Sucesso
- **400**: Parâmetros inválidos
- **404**: Cliente não encontrado
- **500**: Erro interno do servidor

### Mensagens de Erro
```json
{
  "status": "error",
  "message": "Descrição do erro",
  "details": "Informações adicionais"
}
```

## Exemplos de Uso

### Requisição
```
GET /hotlead_alert.php?client_id=1&lead_name=João%20Silva&lead_number=11988887777
```

### Resposta de Sucesso
```json
{
  "status": "success",
  "message": "2 mensagens enviadas com sucesso",
  "details": {
    "client_id": 1,
    "lead_name": "João Silva",
    "lead_number": "11988887777",
    "numeros_enviados": ["5511999999999", "5511888888888"],
    "total_envios": 2
  }
}
```

### Resposta de Erro
```json
{
  "status": "error",
  "message": "Cliente não encontrado",
  "details": "Nenhum cliente encontrado com ID: 999"
}
```

## Logs e Debug

### Registro de Eventos
- Data/hora da requisição
- Parâmetros recebidos
- Números processados
- Sucesso/falha de cada envio
- Erros ocorridos

### Localização dos Logs
- Arquivo: `logs/hotlead_alert.log`
- Formato: `[YYYY-MM-DD HH:MM:SS] TIPO: Mensagem`

## Segurança

### Validações
- Sanitização de entrada com `filter_input()`
- Validação de tipos de dados
- Prevenção de SQL injection com prepared statements

### Rate Limiting
- Considerar implementação futura para evitar spam

## Testes

### Casos de Teste
1. **Sucesso completo**: Cliente com números configurados
2. **Cliente sem números**: Cliente existe mas sem alertas configurados
3. **Cliente não encontrado**: ID inválido
4. **Parâmetros faltando**: Requisição incompleta
5. **Formato de número inválido**: Números mal formatados no banco

### Ferramentas de Teste
- Postman ou cURL para requisições
- Verificação direta no banco de dados
- Monitoramento dos logs

## Implementação

O arquivo deve ser criado na raiz do projeto (`/Users/cristianweiser/Projects 2025/AtendeCar2/hotlead_alert.php`) e ter permissões adequadas para execução.