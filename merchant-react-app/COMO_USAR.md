# 🚀 COMO USAR O PROJETO REACT - GUIA RÁPIDO

## 📥 PRIMEIRA VEZ - SETUP INICIAL

### No seu computador:

```bash
# 1. Clone o repositório (se ainda não tem)
git clone <url-do-repositorio>
cd klubecash1

# 2. Checkout na branch correta
git checkout claude/analyze-merchant-user-screens-011CV551prV2r2QUtaYW3zqX

# 3. Entre na pasta do React
cd merchant-react-app

# 4. Instale as dependências (APENAS UMA VEZ)
npm install

# 5. Rode o projeto
npm start
```

O projeto abrirá em `http://localhost:3000` 🎉

---

## 🔄 ATUALIZAÇÕES DO GIT (Depois da primeira vez)

### Opção 1: Script Automático (RECOMENDADO)

```bash
cd klubecash1/merchant-react-app
./atualizar.sh
```

### Opção 2: Manual

```bash
# 1. Volte para a pasta raiz
cd klubecash1

# 2. Puxe as mudanças
git pull origin claude/analyze-merchant-user-screens-011CV551prV2r2QUtaYW3zqX

# 3. Entre na pasta do React
cd merchant-react-app

# 4. Rode o projeto (node_modules já existe!)
npm start
```

**IMPORTANTE**: Você **NÃO precisa** fazer `npm install` toda vez! Só faça se:
- Houver erro de dependência
- Eu adicionar novas bibliotecas (eu te aviso)
- Você deletar a pasta `node_modules`

---

## ⚙️ MODO DESENVOLVIMENTO (Sem Login)

O projeto está configurado para **rodar sem autenticação** durante o desenvolvimento!

Para verificar/alterar, edite o arquivo `.env`:

```env
# true = Trabalha SEM login (dados fake)
# false = Precisa de login real
REACT_APP_DEV_MODE=true
```

**Com modo dev ativado**, você verá no console:
```
🔧 MODO DESENVOLVIMENTO: Usando usuário fake
🔧 MODO DESENVOLVIMENTO: Usando dados fake da loja
```

---

## 📁 ESTRUTURA SIMPLIFICADA

```
merchant-react-app/
├── node_modules/         ← NÃO mexa aqui! (gerado pelo npm install)
├── src/                  ← Código fonte (ONDE VOCÊ TRABALHA)
│   ├── components/       ← Componentes React
│   ├── pages/           ← Páginas
│   ├── services/        ← APIs
│   └── utils/           ← Funções úteis
├── public/              ← Arquivos estáticos
├── .env                 ← Configurações (MODO DEV aqui!)
├── package.json         ← Lista de dependências
└── README.md            ← Documentação completa
```

---

## 🛠️ COMANDOS ÚTEIS

```bash
# Rodar projeto
npm start

# Build para produção
npm run build

# Rodar testes
npm test

# Ver erros de lint
npm run lint
```

---

## 🔧 SOBRE node_modules

### O que é?
Pasta com **todas as bibliotecas** que o projeto usa (React, Axios, etc.)

### Por que é grande?
Pode ter **200-300MB** e **milhares de arquivos**.

### Preciso commitar no Git?
**NÃO!** Está no `.gitignore`. Cada pessoa gera sua própria pasta.

### Quando preciso fazer npm install?
Apenas:
1. **Primeira vez** que roda o projeto
2. **Se eu adicionar** novas dependências
3. **Se tiver erro** de módulo não encontrado
4. **Se você deletar** a pasta node_modules

### Como funciona?
O `package.json` tem a **lista** de dependências:
```json
{
  "dependencies": {
    "react": "^18.2.0",
    "axios": "^1.4.0"
  }
}
```

Quando você roda `npm install`, ele:
1. Lê o `package.json`
2. Baixa todas as bibliotecas
3. Cria a pasta `node_modules`

---

## 💡 DICAS IMPORTANTES

### ✅ Sempre que puxar do Git:
```bash
git pull origin claude/analyze-merchant-user-screens-011CV551prV2r2QUtaYW3zqX
```

### ✅ Se houver erro de módulo:
```bash
rm -rf node_modules
npm install
```

### ✅ Se o servidor não iniciar:
```bash
# Mate processos na porta 3000
lsof -ti:3000 | xargs kill -9

# Rode novamente
npm start
```

### ✅ Se o navegador não abrir:
Abra manualmente: `http://localhost:3000`

---

## 🎯 FLUXO DE TRABALHO IDEAL

1. **Início do dia:**
   ```bash
   cd klubecash1
   git pull origin claude/analyze-merchant-user-screens-011CV551prV2r2QUtaYW3zqX
   cd merchant-react-app
   npm start
   ```

2. **Durante o desenvolvimento:**
   - O servidor fica rodando
   - Mudanças aparecem automaticamente (Hot Reload)
   - Não precisa reiniciar

3. **Fim do dia:**
   - `Ctrl + C` para parar o servidor
   - Pronto! node_modules continua lá

4. **Próximo dia:**
   ```bash
   git pull  # Pega atualizações
   npm start # Roda (sem npm install!)
   ```

---

## 🚨 RESOLUÇÃO DE PROBLEMAS

### Erro: "Module not found"
```bash
npm install
```

### Erro: "Port 3000 already in use"
```bash
lsof -ti:3000 | xargs kill -9
npm start
```

### Erro: "Cannot find package.json"
```bash
# Você está na pasta errada!
cd klubecash1/merchant-react-app
```

### Projeto não carrega / Tela branca
1. Abra o Console do navegador (F12)
2. Veja os erros
3. Pode ser problema de CORS ou API

### Modo dev não funciona
Verifique o arquivo `.env`:
```env
REACT_APP_DEV_MODE=true
```

---

## 📞 PRECISA DE AJUDA?

1. Leia o `README.md` completo
2. Veja o `PLANEJAMENTO_REACT_LOJISTA.md`
3. Pergunte para mim (Claude)!

---

## 🎉 RESUMO RÁPIDO

```bash
# PRIMEIRA VEZ (com npm install)
git clone <repo>
cd klubecash1/merchant-react-app
npm install
npm start

# PRÓXIMAS VEZES (SEM npm install)
cd klubecash1
git pull
cd merchant-react-app
npm start
```

**É isso! Simples assim! 🚀**
