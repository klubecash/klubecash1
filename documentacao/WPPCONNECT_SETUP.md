# 📱 Documentação - WPPConnect API WhatsApp

## 📋 Índice
1. [Visão Geral](#visão-geral)
2. [Arquitetura do Sistema](#arquitetura-do-sistema)
3. [Configuração Completa](#configuração-completa)
4. [Dependências do Sistema](#dependências-do-sistema)
5. [Manutenção e Troubleshooting](#manutenção-e-troubleshooting)
6. [Comandos Úteis](#comandos-úteis)
7. [Backup e Recuperação](#backup-e-recuperação)

---

## 🎯 Visão Geral

### **O que é?**
API REST do WhatsApp rodando em servidor Ubuntu local (notebook), acessível pela internet através de port forwarding e DDNS.

### **Dados de Acesso**

| Item | Valor |
|------|-------|
| **URL Interna** | `http://localhost:21465` |
| **URL Externa** | `http://191.7.9.179:21465` |
| **Sessão** | `NERDWHATS_AMERICA` |
| **Token** | `$2b$10$shgeryglQ2U_18jhOI6Q0e5yQZ8H3pVi.dKxkLBrCgEjaoG0XpXMO` |
| **Servidor** | Ubuntu - Notebook Acer Aspire A315-58 |
| **IP Local** | `192.168.100.4` |
| **IP Público** | `191.7.9.179` |
| **Porta** | `21465` |

---

## 🏗️ Arquitetura do Sistema

```
Internet (191.7.9.179:21465)
    ↓
[Roteador Huawei EG8145V5]
    ↓ Port Forwarding
[Firewall UFW - Porta 21465]
    ↓
[Ubuntu - Notebook (192.168.100.4:21465)]
    ↓
[WPPConnect Service (systemd)]
    ↓
[Node.js + Puppeteer + Chrome/Chromium]
    ↓
[WhatsApp Web]
```

---

## 🔧 Configuração Completa

### **1. Localização dos Arquivos**

```bash
# Diretório principal do WPPConnect
/home/kaua-matheus-da-silva-lopes/wppconnect-server/

# Arquivos importantes:
├── config.json                    # Configuração principal
├── userDataDir/                   # Dados das sessões do WhatsApp
│   └── NERDWHATS_AMERICA/        # Sessão ativa
├── tokens/                        # Tokens de autenticação
├── logs/                          # Logs do sistema
└── node_modules/                  # Dependências Node.js
```

### **2. Arquivo de Configuração (config.json)**

```json
{
  "secretKey": "CHANGE-ME",
  "host": "0.0.0.0",
  "port": 21465,
  "deviceName": "WPPConnect",
  "poweredBy": "Klube Cash",
  "startAllSession": true,
  "tokenStoreType": "file"
}
```

### **3. Serviço Systemd**

**Arquivo**: `/etc/systemd/system/wppconnect.service`

```ini
[Unit]
Description=WPPConnect WhatsApp API Server
After=network.target

[Service]
Type=simple
User=kaua-matheus-da-silva-lopes
WorkingDirectory=/home/kaua-matheus-da-silva-lopes/wppconnect-server
ExecStart=/usr/bin/npm start
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
Environment=NODE_ENV=production
Environment=PATH=/usr/bin:/usr/local/bin

[Install]
WantedBy=multi-user.target
```

### **4. Configuração PHP (Hostinger)**

**Arquivo**: `config/whatsapp.php`

```php
<?php
define('WHATSAPP_ENABLED', true);
define('WHATSAPP_BASE_URL', 'http://191.7.9.179:21465');
define('WHATSAPP_SESSION_NAME', 'NERDWHATS_AMERICA');
define('WHATSAPP_API_TOKEN', '$2b$10$shgeryglQ2U_18jhOI6Q0e5yQZ8H3pVi.dKxkLBrCgEjaoG0XpXMO');
define('WHATSAPP_HTTP_TIMEOUT', 20);
?>
```

### **5. Firewall UFW**

```bash
# Regras configuradas:
Port 21465/tcp - ALLOW - WPPConnect API
Port 80/tcp    - ALLOW - HTTP
Port 443/tcp   - ALLOW - HTTPS
Port 22/tcp    - ALLOW - SSH
Port 8443/tcp  - ALLOW - CloudPanel
```

### **6. Port Forwarding (Roteador)**

```
Nome: WPPConnect
WAN: 1_INTERNET_R_VID_851
Porta Externa: 21465
IP Interno: 192.168.100.4
Porta Interna: 21465
Protocolo: TCP
Status: Habilitado
```

---

## 🌐 Dependências do Sistema

### **1. Notebook Ubuntu (Servidor Principal)**
- **Função**: Executar o WPPConnect e manter WhatsApp conectado
- **Requisito**: Ligado 24/7 e conectado à internet
- **Se desligar**: API para de funcionar
- **Status**:
  ```bash
  sudo systemctl status wppconnect.service
  ```

### **2. No-IP (DDNS)**
- **Função**: Manter domínio apontando para IP público dinâmico
- **Hostname**: `kaua-servidor.zapto.org` (se configurado)
- **Cliente**: `noip2`
- **Status**:
  ```bash
  sudo systemctl status noip2
  ```

### **3. Roteador Huawei EG8145V5**
- **Função**: Port forwarding da porta 21465
- **IP Roteador**: `192.168.100.1`
- **Status**: Deve estar ligado com configurações salvas

### **4. Firewall UFW**
- **Função**: Permitir acesso externo à porta 21465
- **Status**:
  ```bash
  sudo ufw status | grep 21465
  ```

### **5. Provedor de Internet**
- **Função**: Fornecer IP público
- **IP Atual**: `191.7.9.179`
- **Tipo**: IP dinâmico (pode mudar)
- **Verificar**:
  ```bash
  curl ifconfig.me
  ```

---

## 🔍 Manutenção e Troubleshooting

### **Verificação Completa do Sistema**

```bash
# 1. Status do WPPConnect
sudo systemctl status wppconnect.service

# 2. Status do firewall
sudo ufw status

# 3. Verificar se a porta está escutando
sudo netstat -tlnp | grep 21465

# 4. Ver IP público atual
curl ifconfig.me

# 5. Testar acesso local
curl http://localhost:21465

# 6. Testar acesso externo (de outro computador/celular)
curl http://191.7.9.179:21465

# 7. Testar API com token
curl -X GET 'http://localhost:21465/api/NERDWHATS_AMERICA/check-connection-session' \
  -H 'Authorization: Bearer $2b$10$shgeryglQ2U_18jhOI6Q0e5yQZ8H3pVi.dKxkLBrCgEjaoG0XpXMO'
```

### **Problemas Comuns e Soluções**

#### ❌ **Problema: API não responde externamente**

```bash
# 1. Verificar se o serviço está rodando
sudo systemctl status wppconnect.service

# 2. Verificar firewall
sudo ufw status | grep 21465

# Se não aparecer, liberar:
sudo ufw allow 21465/tcp

# 3. Verificar IP público
curl ifconfig.me

# 4. Testar localmente primeiro
curl http://localhost:21465
```

#### ❌ **Problema: WhatsApp desconectou**

```bash
# 1. Parar o serviço
sudo systemctl stop wppconnect.service

# 2. Limpar sessão
pkill -9 chrome
pkill -9 chromium
rm -rf ~/wppconnect-server/userDataDir/NERDWHATS_AMERICA

# 3. Reiniciar serviço
sudo systemctl start wppconnect.service

# 4. Gerar novo QR Code
curl -X POST 'http://localhost:21465/api/NERDWHATS_AMERICA/start-session' \
  -H 'Authorization: Bearer $2b$10$shgeryglQ2U_18jhOI6Q0e5yQZ8H3pVi.dKxkLBrCgEjaoG0XpXMO' \
  -H 'Content-Type: application/json' \
  -d '{}'

# 5. Aguardar e obter QR Code
sleep 10
curl -X GET 'http://localhost:21465/api/NERDWHATS_AMERICA/qrcode-session' \
  -H 'Authorization: Bearer $2b$10$shgeryglQ2U_18jhOI6Q0e5yQZ8H3pVi.dKxkLBrCgEjaoG0XpXMO' \
  --output ~/qrcode.png

# 6. Abrir e escanear
xdg-open ~/qrcode.png
```

#### ❌ **Problema: Serviço não inicia após reboot**

```bash
# Verificar se está habilitado
sudo systemctl is-enabled wppconnect.service

# Se retornar "disabled", habilitar:
sudo systemctl enable wppconnect.service

# Testar
sudo reboot
```

#### ❌ **Problema: IP público mudou**

```bash
# 1. Verificar novo IP
curl ifconfig.me

# 2. Se usar No-IP, verificar status
sudo systemctl status noip2

# 3. Reiniciar No-IP (se necessário)
sudo systemctl restart noip2

# 4. Atualizar config/whatsapp.php na Hostinger com novo IP
```

#### ❌ **Problema: Erro "Browser already running"**

```bash
# Matar processos do Chrome
pkill -9 chrome
pkill -9 chromium

# Reiniciar serviço
sudo systemctl restart wppconnect.service
```

---

## 📝 Comandos Úteis

### **Gerenciamento do Serviço**

```bash
# Iniciar
sudo systemctl start wppconnect.service

# Parar
sudo systemctl stop wppconnect.service

# Reiniciar
sudo systemctl restart wppconnect.service

# Ver status
sudo systemctl status wppconnect.service

# Ver logs em tempo real
sudo journalctl -u wppconnect.service -f

# Ver últimas 100 linhas de log
sudo journalctl -u wppconnect.service -n 100

# Habilitar início automático
sudo systemctl enable wppconnect.service

# Desabilitar início automático
sudo systemctl disable wppconnect.service
```

### **API - Exemplos de Uso**

```bash
# Token para facilitar
TOKEN="$2b$10$shgeryglQ2U_18jhOI6Q0e5yQZ8H3pVi.dKxkLBrCgEjaoG0XpXMO"

# Verificar conexão
curl -X GET "http://localhost:21465/api/NERDWHATS_AMERICA/check-connection-session" \
  -H "Authorization: Bearer $TOKEN"

# Iniciar sessão
curl -X POST "http://localhost:21465/api/NERDWHATS_AMERICA/start-session" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'

# Obter QR Code
curl -X GET "http://localhost:21465/api/NERDWHATS_AMERICA/qrcode-session" \
  -H "Authorization: Bearer $TOKEN" \
  --output ~/qrcode.png

# Enviar mensagem
curl -X POST "http://localhost:21465/api/NERDWHATS_AMERICA/send-message" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "5534999999999",
    "message": "Olá, teste do WPPConnect!"
  }'

# Status da sessão
curl -X GET "http://localhost:21465/api/NERDWHATS_AMERICA/status-session" \
  -H "Authorization: Bearer $TOKEN"

# Desconectar sessão
curl -X POST "http://localhost:21465/api/NERDWHATS_AMERICA/close-session" \
  -H "Authorization: Bearer $TOKEN"
```

### **Monitoramento**

```bash
# Ver uso de recursos
htop

# Ver processos do Node
ps aux | grep node

# Ver uso de porta 21465
sudo lsof -i :21465

# Ver conexões ativas
sudo netstat -an | grep 21465

# Teste de latência
ping -c 4 google.com

# Ver IP público
curl ifconfig.me

# Teste de acesso externo (de outro terminal/máquina)
curl -I http://191.7.9.179:21465
```

---

## 💾 Backup e Recuperação

### **O que fazer backup**

```bash
# 1. Configurações
cp ~/wppconnect-server/config.json ~/backup/

# 2. Sessão do WhatsApp (IMPORTANTE!)
tar -czf ~/backup/wppconnect-session-$(date +%Y%m%d).tar.gz \
  ~/wppconnect-server/userDataDir/

# 3. Tokens
tar -czf ~/backup/wppconnect-tokens-$(date +%Y%m%d).tar.gz \
  ~/wppconnect-server/tokens/ \
  ~/wppconnect-server/wppconnect_tokens/

# 4. Arquivo de serviço
sudo cp /etc/systemd/system/wppconnect.service ~/backup/
```

### **Restaurar Backup**

```bash
# 1. Parar serviço
sudo systemctl stop wppconnect.service

# 2. Restaurar sessão
tar -xzf ~/backup/wppconnect-session-YYYYMMDD.tar.gz -C ~/

# 3. Restaurar tokens
tar -xzf ~/backup/wppconnect-tokens-YYYYMMDD.tar.gz -C ~/

# 4. Restaurar config
cp ~/backup/config.json ~/wppconnect-server/

# 5. Reiniciar
sudo systemctl start wppconnect.service
```

### **Script de Backup Automático**

Criar arquivo `~/backup-wppconnect.sh`:

```bash
#!/bin/bash
BACKUP_DIR=~/backup/wppconnect
mkdir -p $BACKUP_DIR
DATE=$(date +%Y%m%d_%H%M%S)

echo "Iniciando backup WPPConnect - $DATE"

# Backup da sessão
tar -czf $BACKUP_DIR/session-$DATE.tar.gz \
  ~/wppconnect-server/userDataDir/

# Backup de tokens
tar -czf $BACKUP_DIR/tokens-$DATE.tar.gz \
  ~/wppconnect-server/tokens/ \
  ~/wppconnect-server/wppconnect_tokens/

# Backup do config
cp ~/wppconnect-server/config.json $BACKUP_DIR/config-$DATE.json

# Limpar backups antigos (manter últimos 7 dias)
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup concluído: $BACKUP_DIR"
```

Tornar executável e agendar:

```bash
chmod +x ~/backup-wppconnect.sh

# Agendar com cron (diário às 3h da manhã)
crontab -e

# Adicionar linha:
0 3 * * * /home/kaua-matheus-da-silva-lopes/backup-wppconnect.sh
```

---

## 🚨 Alertas e Monitoramento

### **O que pode derrubar o sistema**

| Problema | Impacto | Solução |
|----------|---------|---------|
| Notebook desligou | API offline | Ligar notebook |
| Queda de energia | API offline | Usar nobreak/UPS |
| Wi-Fi desconectou | API offline | Reconectar Wi-Fi |
| IP público mudou | Site não acessa API | Verificar No-IP ou atualizar IP manualmente |
| WhatsApp desconectou | Mensagens não enviam | Gerar novo QR Code |
| Serviço travou | API não responde | Reiniciar serviço |
| Disco cheio | Serviço pode parar | Limpar logs e arquivos temporários |
| Memória RAM cheia | Sistema lento | Reiniciar ou adicionar mais RAM |

---

## 📊 Informações Técnicas

### **Requisitos de Sistema**

- **SO**: Ubuntu 20.04+ (ou similar)
- **Node.js**: v18.x ou superior
- **RAM**: Mínimo 2GB (recomendado 4GB+)
- **Disco**: Mínimo 5GB livres
- **Internet**: Conexão estável (upload mínimo 2Mbps)
- **Dependências**: Chromium/Chrome, npm, git

### **Portas Utilizadas**

| Porta | Serviço | Protocolo |
|-------|---------|-----------|
| 21465 | WPPConnect API | TCP |
| 80 | HTTP | TCP |
| 443 | HTTPS | TCP |
| 8443 | CloudPanel | TCP |

### **Processos em Execução**

```bash
# WPPConnect
npm start → node ./dist/server.js

# Chrome/Chromium
chromium-browser --headless ...
```

---

## 📞 Suporte e Links Úteis

- **WPPConnect Docs**: https://wppconnect.io/docs/
- **GitHub**: https://github.com/wppconnect-team/wppconnect-server
- **API Swagger**: `http://localhost:21465/api-docs` (quando rodando)

---

## 📅 Histórico de Alterações

| Data | Versão | Alteração |
|------|--------|-----------|
| 2025-10-31 | 1.0 | Configuração inicial completa |

---

**Última atualização**: 31 de Outubro de 2025
**Responsável**: Kaua Matheus da Silva Lopes
**Sistema**: WPPConnect v2.8.6
