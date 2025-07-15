# Configuração do Cron Job para Sincronização Automática

## Sobre a Sincronização Automática

O sistema AtendeCar2 inclui um mecanismo de sincronização automática que:

- **Executa diariamente** para manter o estoque sempre atualizado
- **Remove veículos** que não estão mais disponíveis no site do lojista
- **Adiciona novos veículos** que aparecem no estoque
- **Gera logs detalhados** de cada execução
- **Funciona para todos os clientes ativos** com URL de estoque configurada

## Configuração do Cron Job

### 1. Acesso ao Servidor

Acesse seu servidor via SSH:
```bash
ssh usuario@seu-servidor.com
```

### 2. Editar o Crontab

Execute o comando para editar as tarefas agendadas:
```bash
crontab -e
```

### 3. Adicionar a Tarefa de Sincronização

Adicione uma das seguintes linhas no final do arquivo crontab:

**Execução diária às 6:00 da manhã:**
```bash
0 6 * * * /usr/bin/php /caminho/completo/para/projeto/cron_sincronizar_estoque.php
```

**Execução diária às 2:00 da madrugada:**
```bash
0 2 * * * /usr/bin/php /caminho/completo/para/projeto/cron_sincronizar_estoque.php
```

**Execução a cada 6 horas:**
```bash
0 */6 * * * /usr/bin/php /caminho/completo/para/projeto/cron_sincronizar_estoque.php
```

### 4. Substituir o Caminho

**IMPORTANTE:** Substitua `/caminho/completo/para/projeto/` pelo caminho real onde o projeto está instalado.

Exemplo:
```bash
0 6 * * * /usr/bin/php /var/www/html/atendecar2/cron_sincronizar_estoque.php
```

### 5. Verificar o PHP CLI

Certifique-se de que o caminho do PHP está correto:
```bash
which php
```

Se o PHP estiver em um local diferente, ajuste o comando:
```bash
0 6 * * * /usr/local/bin/php /caminho/para/projeto/cron_sincronizar_estoque.php
```

## Teste Manual

### Via Linha de Comando

Para testar a sincronização manualmente:
```bash
cd /caminho/para/projeto
php cron_sincronizar_estoque.php
```

### Via Navegador (para testes)

Acesse no navegador:
```
https://seudominio.com/cron_sincronizar_estoque.php?exec=sync
```

## Logs de Execução

### Localização dos Logs

Os logs são salvos automaticamente em:
```
/caminho/para/projeto/logs/sincronizacao_YYYY-MM-DD.log
```

### Visualizar Logs Recentes

```bash
# Ver log de hoje
tail -f /caminho/para/projeto/logs/sincronizacao_$(date +%Y-%m-%d).log

# Ver últimas 50 linhas do log
tail -50 /caminho/para/projeto/logs/sincronizacao_$(date +%Y-%m-%d).log

# Ver todos os logs disponíveis
ls -la /caminho/para/projeto/logs/
```

### Exemplo de Log

```
[2025-01-14 06:00:01] === INICIANDO SINCRONIZAÇÃO DIÁRIA ===
[2025-01-14 06:00:01] Data/Hora: 14/01/2025 06:00:01
[2025-01-14 06:00:01] Encontrados 3 clientes para sincronizar.

[2025-01-14 06:00:02] --- Sincronizando cliente: EMJ Motors (ID: 1) ---
[2025-01-14 06:00:02] URL: https://carrosp.com.br/piracicaba-sp/emj-motors/
[2025-01-14 06:00:05] ✅ SUCESSO: Importação concluída: 27 veículos importados
[2025-01-14 06:00:05] Total de veículos removidos: 3

[2025-01-14 06:00:08] === RESUMO DA SINCRONIZAÇÃO ===
[2025-01-14 06:00:08] Total de clientes: 3
[2025-01-14 06:00:08] Sucessos: 3
[2025-01-14 06:00:08] Erros: 0
[2025-01-14 06:00:08] === FIM DA SINCRONIZAÇÃO ===
```

## Limpeza Automática de Logs

O sistema automaticamente:
- **Remove logs antigos** (mais de 30 dias)
- **Mantém apenas logs recentes** para economizar espaço
- **Executa a limpeza** a cada sincronização

## Monitoramento

### Verificar se o Cron está Funcionando

```bash
# Ver tarefas agendadas ativas
crontab -l

# Ver logs do sistema cron
tail -f /var/log/cron

# Ver se o processo está executando
ps aux | grep cron_sincronizar_estoque
```

### Notificações por Email (Opcional)

Para receber emails sobre erros, adicione seu email no crontab:
```bash
MAILTO=seu-email@dominio.com
0 6 * * * /usr/bin/php /caminho/para/projeto/cron_sincronizar_estoque.php
```

## Solução de Problemas

### Erro: "php: command not found"

Encontre o caminho correto do PHP:
```bash
whereis php
which php
```

### Erro: "Permission denied"

Dê permissão de execução:
```bash
chmod +x /caminho/para/projeto/cron_sincronizar_estoque.php
```

### Erro: "Database connection failed"

Verifique se:
- As credenciais do banco estão corretas em `config/database.php`
- O servidor MySQL está rodando
- O usuário do cron tem acesso ao banco

### Logs Não São Criados

Verifique permissões da pasta:
```bash
mkdir -p /caminho/para/projeto/logs
chmod 755 /caminho/para/projeto/logs
chown www-data:www-data /caminho/para/projeto/logs
```

## Formato do Crontab

```
# ┌───────────── minuto (0 - 59)
# │ ┌───────────── hora (0 - 23)
# │ │ ┌───────────── dia do mês (1 - 31)
# │ │ │ ┌───────────── mês (1 - 12)
# │ │ │ │ ┌───────────── dia da semana (0 - 6) (0=domingo)
# │ │ │ │ │
# * * * * * comando-a-executar
```

### Exemplos de Horários

```bash
# Todos os dias às 6:00
0 6 * * *

# Todos os dias às 2:00 e 14:00
0 2,14 * * *

# A cada 4 horas
0 */4 * * *

# Apenas nos dias úteis às 8:00
0 8 * * 1-5

# Apenas aos domingos às 3:00
0 3 * * 0
```

## Suporte

Se encontrar problemas na configuração:

1. Verifique os logs de erro
2. Teste a execução manual primeiro
3. Confirme as permissões de arquivo
4. Verifique a conectividade com o banco de dados