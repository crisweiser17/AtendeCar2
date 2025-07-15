# Configuração SMTP com Mailtrap

## Dados de Configuração (já pre-configurados)
- **Host**: sandbox.smtp.mailtrap.io
- **Port**: 587
- **Username**: 7d5be16b754a10
- **Password**: ca82ecd42df904
- **Security**: TLS

## Como Testar

### 1. Configuração já aplicada
Os dados do Mailtrap já estão configurados no arquivo `config/smtp_config.json`.

### 2. Testar o envio de email
1. Acesse: `configuracoes.php`
2. Vá para a aba "Configurações SMTP"
3. Clique em "Testar SMTP"
4. Digite seu email de teste
5. Verifique a caixa de entrada no Mailtrap

### 3. Testar recuperação de senha
1. Vá para `login.php`
2. Clique em "Esqueci minha senha"
3. Digite seu email cadastrado
4. Verifique o email no Mailtrap

### 4. Verificar logs
- Logs de email: `logs/mailtrap.log`
- Logs de recuperação: `logs/recuperacao_senha.log`

## Configuração Manual (se necessário)
Se precisar alterar as credenciais, edite `config/smtp_config.json`:

```json
{
    "smtp_host": "sandbox.smtp.mailtrap.io",
    "smtp_port": "587",
    "smtp_username": "7d5be16b754a10",
    "smtp_password": "ca82ecd42df904",
    "smtp_from_email": "noreply@atendecar.net",
    "smtp_from_name": "AtendeCar",
    "smtp_security": "tls"
}
```

## Acesso ao Mailtrap
- URL: https://mailtrap.io
- Inbox: Verifique os emails enviados na caixa de entrada do Mailtrap

## Notas Importantes
- O sistema está configurado para usar o Mailtrap em modo de teste
- Em produção, substitua pelas credenciais do seu provedor de email real
- Os emails serão capturados pelo Mailtrap e não serão enviados para destinatários reais