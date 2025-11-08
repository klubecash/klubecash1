# 📱 Baileys WhatsApp API - Documentação Completa

## 📋 Índice
- [Informações Gerais](#informações-gerais)
- [Acesso à API](#acesso-à-api)
- [Comandos de Gerenciamento](#comandos-de-gerenciamento)
- [Endpoints Disponíveis](#endpoints-disponíveis)
- [Integração com o Sistema](#integração-com-o-sistema)
- [Resolução de Problemas](#resolução-de-problemas)

---

## 🔧 Informações Gerais

### Servidor
- **Equipamento:** Acer Aspire A315-58 (Notebook Ubuntu)
- **Sistema Operacional:** Ubuntu
- **Usuário:** kaua-matheus-da-silva-lopes
- **IP:** 191.7.9.179
- **Porta:** 21465

### API WhatsApp
- **Biblioteca:** Baileys (@whiskeysockets/baileys)
- **URL Base:** http://191.7.9.179:21465
- **Sessão:** NERDWHATS_AMERICA
- **Token de Autenticação:** $2b$10$Bw104fXekPux3m86cHu7BOqkAtp_5IUlT7mpRPKKWTLZzAAzOIVzu
- **Diretório de Instalação:** ~/baileys-whatsapp

### Arquivos Importantes
```
~/baileys-whatsapp/
├── server.js              # Servidor principal da API
├── package.json           # Dependências Node.js
├── node_modules/          # Bibliotecas instaladas
└── auth_info_baileys/     # Credenciais do WhatsApp (NÃO APAGAR!)
```

---

## 🌐 Acesso à API

### URLs de Acesso

**Interface QR Code (Browser):**
```
http://191.7.9.179:21465/qr
```

**API Base:**
```
http://191.7.9.179:21465/api/NERDWHATS_AMERICA/
```

### Autenticação
A API **NÃO requer autenticação** por Bearer Token (configurado para uso interno).

Se precisar adicionar autenticação, edite `server.js` e adicione middleware.

---

## 🎮 Comandos de Gerenciamento

### Verificar Status do Serviço
```bash
sudo systemctl status baileys-whatsapp.service
```

**Saída esperada:**
```
● baileys-whatsapp.service - Baileys WhatsApp API Server
   Active: active (running)
```

---

### Iniciar o Serviço
```bash
sudo systemctl start baileys-whatsapp.service
```

---

### Parar o Serviço
```bash
sudo systemctl stop baileys-whatsapp.service
```

---

### Reiniciar o Serviço
```bash
sudo systemctl restart baileys-whatsapp.service
```

---

### Habilitar Inicialização Automática
```bash
sudo systemctl enable baileys-whatsapp.service
```

---

### Desabilitar Inicialização Automática
```bash
sudo systemctl disable baileys-whatsapp.service
```

---

### Ver Logs em Tempo Real
```bash
sudo journalctl -u baileys-whatsapp.service -f
```

Pressione `Ctrl+C` para sair.

---

### Ver Últimas 50 Linhas do Log
```bash
sudo journalctl -u baileys-whatsapp.service -n 50 --no-pager
```

---

## 📡 Endpoints Disponíveis

### 1. Página QR Code (Web)
```
GET http://191.7.9.179:21465/qr
```
Abre uma página HTML mostrando o QR Code para conectar o WhatsApp.

---

### 2. Verificar Status da Sessão
```http
GET http://191.7.9.179:21465/api/NERDWHATS_AMERICA/status-session
```

**Resposta:**
```json
{
  "status": "CONNECTED",
  "session": "NERDWHATS_AMERICA",
  "connected": true
}
```

---

### 3. Verificar Conexão
```http
GET http://191.7.9.179:21465/api/NERDWHATS_AMERICA/check-connection-session
```

**Resposta:**
```json
{
  "status": true,
  "connected": true,
  "state": "CONNECTED"
}
```

---

### 4. Obter QR Code (JSON)
```http
GET http://191.7.9.179:21465/api/NERDWHATS_AMERICA/qrcode-session
```

**Resposta (quando disponível):**
```json
{
  "qrcode": "data:image/png;base64,iVBOR...",
  "status": "QR_CODE_READY"
}
```

**Resposta (quando não disponível):**
```json
{
  "status": "CONNECTED",
  "message": "QR Code não disponível. Status: CONNECTED"
}
```

---

### 5. Iniciar Sessão
```http
POST http://191.7.9.179:21465/api/NERDWHATS_AMERICA/start-session
```

**Resposta:**
```json
{
  "success": true,
  "message": "Sessão iniciada. Aguarde o QR Code em /qr",
  "status": "STARTING"
}
```

---

### 6. Enviar Mensagem de Texto
```http
POST http://191.7.9.179:21465/api/NERDWHATS_AMERICA/send-message
Content-Type: application/json

{
  "phone": "5538991045205",
  "message": "Olá! Sua mensagem aqui."
}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Mensagem enviada com sucesso",
  "to": "553891045205@s.whatsapp.net"
}
```

**Formato do Número:**
- ✅ Correto: `5538991045205` (55 + DDD + 8 dígitos)
- ❌ Errado: `55389**9**1045205` (não duplicar o 9)

---

### 7. Informações do Dispositivo Conectado
```http
GET http://191.7.9.179:21465/api/NERDWHATS_AMERICA/host-device
```

**Resposta:**
```json
{
  "user": {
    "id": "553430301344:32@s.whatsapp.net",
    "name": "Klube Cash"
  },
  "status": "CONNECTED"
}
```

---

### 8. Listar Sessões
```http
GET http://191.7.9.179:21465/api/sessions
```

**Resposta:**
```json
[
  {
    "name": "NERDWHATS_AMERICA",
    "status": "CONNECTED",
    "connected": true
  }
]
```

---

## 🔗 Integração com o Sistema

### Arquivo Principal
O arquivo `utils/WhatsAppBot.php` gerencia toda comunicação com a API Baileys.

### Configuração
Edite `config/whatsapp.php`:

```php
define('WHATSAPP_ENABLED', true);
define('WHATSAPP_BASE_URL', 'http://191.7.9.179:21465');
define('WHATSAPP_SESSION_NAME', 'NERDWHATS_AMERICA');
define('WHATSAPP_API_TOKEN', '$2b$10$Bw104fXekPux3m86cHu7BOqkAtp_5IUlT7mpRPKKWTLZzAAzOIVzu');
```

### Enviar Mensagens no Código PHP

**Exemplo 1: Mensagem Simples**
```php
require_once __DIR__ . '/utils/WhatsAppBot.php';

$result = WhatsAppBot::sendTextMessage(
    '38991045205',  // Número (sem código do país)
    'Olá! Esta é uma mensagem de teste.'
);

if ($result['success']) {
    echo "✅ Mensagem enviada!";
} else {
    echo "❌ Erro: " . $result['message'];
}
```

**Exemplo 2: Notificação de Transação**
```php
$transactionData = [
    'cliente_nome' => 'João Silva',
    'nome_loja' => 'Loja Exemplo',
    'valor_cashback' => 15.50
];

$result = WhatsAppBot::sendNewTransactionNotification(
    '38991045205',
    $transactionData
);
```

**Exemplo 3: Notificação de Cashback Liberado**
```php
$result = WhatsAppBot::sendCashbackReleasedNotification(
    '38991045205',
    $transactionData
);
```

---

## 🔐 Formato de Números de Telefone

### Normalização Automática

O sistema **automaticamente** converte números para o formato correto do Baileys:

**Entrada → Saída**
```
38991045205        → 553891045205@s.whatsapp.net
5538991045205      → 553891045205@s.whatsapp.net
(38) 99104-5205    → 553891045205@s.whatsapp.net
```

**Regras de Conversão:**
1. Remove caracteres não numéricos
2. Adiciona código do país (55) se necessário
3. **Remove o 9 duplicado** de celulares (5538**9**91045205 → 553891045205)
4. Adiciona sufixo `@s.whatsapp.net`

---

## 🛠️ Resolução de Problemas

### ❌ Problema: WhatsApp Desconectado

**Verificar:**
```bash
curl http://191.7.9.179:21465/api/NERDWHATS_AMERICA/check-connection-session
```

**Se retornar `"connected": false`:**

1. Acesse o QR Code:
   ```
   http://191.7.9.179:21465/qr
   ```

2. Escaneie o QR Code com seu WhatsApp

3. Aguarde a conexão (5-10 segundos)

---

### ❌ Problema: Serviço Não Inicia

**Verificar logs:**
```bash
sudo journalctl -u baileys-whatsapp.service -n 50
```

**Possíveis causas:**
- Porta 21465 já está em uso
- Falta de dependências Node.js
- Erro no arquivo `server.js`

**Solução:**
```bash
# Verificar se a porta está em uso
sudo netstat -tlnp | grep 21465

# Matar processos na porta
sudo pkill -9 node

# Reiniciar serviço
sudo systemctl restart baileys-whatsapp.service
```

---

### ❌ Problema: Mensagens Não São Enviadas

**1. Verificar se WhatsApp está conectado:**
```bash
curl http://191.7.9.179:21465/api/NERDWHATS_AMERICA/check-connection-session
```

**2. Verificar formato do número:**
```php
// ✅ Correto
$phone = '38991045205';
$phone = '5538991045205';

// ❌ Errado
$phone = '38991045205@s.whatsapp.net';  // Sistema já adiciona sufixo
$phone = '55389910452059';              // Número com 9 duplicado
```

**3. Verificar logs da API:**
```bash
sudo journalctl -u baileys-whatsapp.service -f
```

---

### ❌ Problema: Arquivo `auth_info_baileys/` Foi Apagado

**Se apagar essa pasta, perde a conexão do WhatsApp!**

**Solução:**
1. Reiniciar serviço para gerar nova pasta
2. Escanear QR Code novamente

```bash
sudo systemctl restart baileys-whatsapp.service
```

Depois acesse: http://191.7.9.179:21465/qr

---

### ❌ Problema: QR Code Expira Muito Rápido

QR Code expira em **60 segundos**.

**Solução:**
1. Tenha o celular com WhatsApp aberto e pronto
2. Acesse http://191.7.9.179:21465/qr
3. Escaneie imediatamente

**Dica:** A página atualiza automaticamente a cada 2 segundos.

---

## 📞 Teste Rápido

Crie um arquivo `teste_whatsapp.php`:

```php
<?php
require_once __DIR__ . '/config/whatsapp.php';
require_once __DIR__ . '/utils/WhatsAppBot.php';

$result = WhatsAppBot::sendTextMessage(
    '38991045205',  // SEU NÚMERO AQUI
    "🧪 Teste Baileys - " . date('H:i:s')
);

echo $result['success'] ? "✅ OK" : "❌ ERRO: {$result['message']}";
?>
```

Execute:
```bash
php teste_whatsapp.php
```

---

## 🔒 Segurança

### Credenciais Sensíveis

**NÃO COMPARTILHE:**
- Token de autenticação: `$2b$10$Bw104fXekPux3m86cHu7BOqkAtp_5IUlT7mpRPKKWTLZzAAzOIVzu`
- Pasta `auth_info_baileys/` (contém sessão do WhatsApp)

### Acesso Restrito

A API está acessível apenas na rede local (191.7.9.179).

Para acesso externo, configure firewall:
```bash
sudo ufw allow 21465/tcp
```

---

## 📝 Changelog

### Versão 1.0 (08/11/2024)
- ✅ Migração de WPPConnect para Baileys
- ✅ Correção do formato de números (remove 9 duplicado)
- ✅ Serviço systemd configurado
- ✅ Auto-restart em caso de falha
- ✅ Documentação completa

---

## 📞 Suporte

Em caso de problemas:

1. Verificar logs: `sudo journalctl -u baileys-whatsapp.service -f`
2. Reiniciar serviço: `sudo systemctl restart baileys-whatsapp.service`
3. Verificar conexão: Acessar http://191.7.9.179:21465/qr

---

**Documentação gerada em:** 08/11/2024
**Última atualização:** 08/11/2024
