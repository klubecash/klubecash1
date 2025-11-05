# 🚀 PROMPT: Como Adicionar Nova API no Servidor Ubuntu

## 📋 Contexto para a IA

**Use este prompt quando precisar adicionar uma nova API/serviço no servidor Ubuntu:**

---

## 💬 PROMPT COMPLETO

```
Olá! Preciso adicionar uma nova API/aplicação no meu servidor Ubuntu que já está rodando o WPPConnect.

## 📊 INFORMAÇÕES DO SERVIDOR ATUAL

### Hardware e Sistema Operacional
- **Servidor**: Notebook Acer Aspire A315-58
- **Sistema**: Ubuntu Linux
- **Usuário**: kaua-matheus-da-silva-lopes
- **IP Local**: 192.168.100.4
- **IP Público**: 191.7.9.179
- **Localização**: Rede doméstica

### Configuração de Rede
- **Roteador**: Huawei EG8145V5 (IP: 192.168.100.1)
- **DDNS**: No-IP (hostname: kaua-servidor.zapto.org)
- **Provedor**: Fibra ótica sem CGNAT
- **Firewall**: UFW ativo

### Portas Já em Uso
- **80/tcp**: HTTP (Nginx/CloudPanel)
- **443/tcp**: HTTPS (Nginx/CloudPanel)
- **8080/tcp**: CloudPanel/Nginx
- **8443/tcp**: CloudPanel Admin
- **21465/tcp**: WPPConnect API
- **22/tcp**: SSH

### Serviços Rodando
1. **WPPConnect** (WhatsApp API)
   - Porta: 21465
   - Serviço: systemd (wppconnect.service)
   - Localização: ~/wppconnect-server/
   - Documentação: Ver documentacao/WPPCONNECT_SETUP.md

2. **CloudPanel** (Gerenciador Web)
   - Porta: 8443
   - URL: https://serverdev.syncholding.com.br

3. **Nginx** (Web Server)
   - Portas: 80, 443, 8080

### Estrutura de Pastas Atual
```
/home/kaua-matheus-da-silva-lopes/
├── wppconnect-server/          # WPPConnect (porta 21465)
├── backup/                      # Backups
└── [nova-aplicacao]/           # Onde será instalada a nova API
```

## 🎯 NOVA APLICAÇÃO QUE QUERO INSTALAR

**Nome da Aplicação**: [PREENCHER]

**Tipo**:
- [ ] API REST (Node.js, Python, PHP, etc.)
- [ ] Aplicação Web
- [ ] Bot/Worker
- [ ] Banco de Dados
- [ ] Outro: _____________

**Tecnologia**:
- [ ] Node.js
- [ ] Python
- [ ] PHP
- [ ] Go
- [ ] Java
- [ ] Docker
- [ ] Outro: _____________

**Porta Desejada**: [PREENCHER] (ex: 3000, 5000, 8000)

**Precisa de acesso externo?**
- [ ] Sim - Preciso acessar pela internet
- [ ] Não - Apenas acesso local

**URL de Acesso Desejada** (se aplicável):
- [ ] http://191.7.9.179:[PORTA]
- [ ] http://[subdominio].syncholding.com.br
- [ ] Apenas localhost

**Repositório/Link** (se houver): [PREENCHER]

## ✅ O QUE PRECISO QUE VOCÊ FAÇA

Por favor, me guie passo a passo para:

1. **Instalar as dependências** necessárias (se precisar)
2. **Configurar a aplicação** no diretório adequado
3. **Criar um serviço systemd** para iniciar automaticamente
4. **Configurar o firewall** (liberar porta no UFW)
5. **Configurar port forwarding** no roteador (se necessário)
6. **Testar o acesso** local e externo
7. **Documentar** a configuração completa

## 📝 REQUISITOS IMPORTANTES

- A nova aplicação **NÃO pode conflitar** com as portas já em uso
- Precisa iniciar **automaticamente** quando o servidor reiniciar
- Deve ter **logs** configurados para troubleshooting
- Preciso de **comandos de manutenção** (start, stop, restart, logs)
- Quero uma **documentação** igual à do WPPConnect

## 🔒 SEGURANÇA

- Se a aplicação precisa de autenticação, me ajude a configurar
- Se usar banco de dados, me ajude a criar senhas seguras
- Configure apenas o mínimo necessário de permissões

## 📦 INFORMAÇÕES ADICIONAIS

[Adicione aqui qualquer informação extra: variáveis de ambiente, dependências específicas, configurações especiais, etc.]

---

**Após isso, documente tudo em**: `documentacao/[NOME_DA_APLICACAO]_SETUP.md`
```

---

## 📋 EXEMPLO DE USO DO PROMPT

### **Exemplo 1: API Node.js Express**

```
[... Copiar todo o prompt acima e preencher:]

## 🎯 NOVA APLICAÇÃO QUE QUERO INSTALAR

**Nome da Aplicação**: API de Produtos - E-commerce

**Tipo**:
- [x] API REST (Node.js, Python, PHP, etc.)

**Tecnologia**:
- [x] Node.js

**Porta Desejada**: 3000

**Precisa de acesso externo?**
- [x] Sim - Preciso acessar pela internet

**URL de Acesso Desejada**:
- [x] http://191.7.9.179:3000
- [x] http://api-produtos.syncholding.com.br

**Repositório/Link**: https://github.com/meuuser/api-produtos

## 📦 INFORMAÇÕES ADICIONAIS

- Usa banco de dados MySQL (já tenho instalado via CloudPanel)
- Precisa de variáveis de ambiente (.env)
- Usa autenticação JWT
```

### **Exemplo 2: Bot Python**

```
[... Copiar todo o prompt acima e preencher:]

## 🎯 NOVA APLICAÇÃO QUE QUERO INSTALAR

**Nome da Aplicação**: Bot de Automação de Tarefas

**Tipo**:
- [x] Bot/Worker

**Tecnologia**:
- [x] Python

**Porta Desejada**: Não usa porta (apenas processa jobs)

**Precisa de acesso externo?**
- [ ] Não - Apenas acesso local

**Repositório/Link**: Projeto local

## 📦 INFORMAÇÕES ADICIONAIS

- Precisa rodar em segundo plano
- Processa fila de jobs a cada 5 minutos
- Conecta com a API do WPPConnect (localhost:21465)
```

---

## 🎯 O QUE A IA VAI ENTREGAR

Após usar este prompt, você receberá:

1. ✅ **Guia passo a passo** de instalação
2. ✅ **Arquivo de serviço systemd** pronto
3. ✅ **Configuração de firewall**
4. ✅ **Configuração de port forwarding** (se necessário)
5. ✅ **Comandos de teste** local e externo
6. ✅ **Comandos de manutenção** (start, stop, restart, logs)
7. ✅ **Documentação completa** em Markdown
8. ✅ **Script de backup** (se aplicável)
9. ✅ **Troubleshooting** de problemas comuns

---

## 📁 ESTRUTURA DE DOCUMENTAÇÃO

Após adicionar cada nova API, você terá:

```
documentacao/
├── WPPCONNECT_SETUP.md          # API WhatsApp (já existe)
├── [NOVA_API]_SETUP.md          # Nova API 1
├── [OUTRA_API]_SETUP.md         # Nova API 2
├── SERVIDOR_COMPLETO.md         # Visão geral de todas as APIs
└── PROMPT_NOVA_API.md           # Este arquivo (template)
```

---

## 💡 DICAS IMPORTANTES

### **Escolha de Portas**

Portas recomendadas para novas aplicações:

| Faixa | Uso Comum | Disponível? |
|-------|-----------|-------------|
| 3000-3999 | Node.js Apps | ✅ Sim |
| 5000-5999 | Python Apps | ✅ Sim |
| 8000-8999 | Várias Apps | ⚠️ 8080, 8443 em uso |
| 9000-9999 | Várias Apps | ✅ Sim |

### **Padrão de Nomes de Serviços**

```bash
# Padrão: [nome-da-aplicacao].service
wppconnect.service       # WhatsApp API
api-produtos.service     # API de Produtos
bot-automacao.service    # Bot de Automação
```

### **Padrão de Diretórios**

```bash
/home/kaua-matheus-da-silva-lopes/
├── wppconnect-server/     # API WhatsApp
├── api-produtos/          # Nova API 1
├── bot-automacao/         # Nova API 2
└── backup/
    ├── wppconnect/
    ├── api-produtos/
    └── bot-automacao/
```

---

## 🚀 COMANDO RÁPIDO PARA INICIAR

Quando for adicionar uma nova API, apenas diga:

```
"Use o prompt em documentacao/PROMPT_NOVA_API.md para me ajudar a instalar uma nova API no servidor Ubuntu. A aplicação é [NOME/TIPO], usa [TECNOLOGIA], e precisa rodar na porta [PORTA]."
```

**Exemplo**:
```
"Use o prompt em documentacao/PROMPT_NOVA_API.md para me ajudar a instalar uma nova API no servidor Ubuntu. A aplicação é uma API REST de Gerenciamento de Clientes, usa Node.js + Express, e precisa rodar na porta 3001 com acesso externo."
```

---

## 📞 CHECKLIST PRÉ-INSTALAÇÃO

Antes de adicionar uma nova API, verifique:

- [ ] Porta escolhida não está em uso: `sudo lsof -i :[PORTA]`
- [ ] Disco tem espaço suficiente: `df -h`
- [ ] Memória RAM disponível: `free -h`
- [ ] IP público atual: `curl ifconfig.me`
- [ ] Serviços atuais rodando: `sudo systemctl list-units --type=service --state=running | grep -v @`

---

**Criado em**: 31 de Outubro de 2025
**Versão**: 1.0
**Compatível com**: Ubuntu 20.04+, Debian 11+
