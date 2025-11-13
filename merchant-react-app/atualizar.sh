#!/bin/bash

# Script para atualizar o projeto React do Git
# Autor: Claude AI
# Uso: ./atualizar.sh

echo "🔄 Atualizando projeto React..."
echo ""

# Voltar para pasta raiz
cd ..

# Verificar se há mudanças locais
if [[ -n $(git status -s) ]]; then
  echo "⚠️  Você tem mudanças locais não commitadas."
  echo "Deseja fazer stash delas? (s/n)"
  read -r resposta
  if [[ $resposta == "s" ]]; then
    echo "📦 Salvando mudanças locais..."
    git stash
  fi
fi

# Puxar as últimas mudanças
echo "⬇️  Puxando mudanças do Git..."
git pull origin claude/analyze-merchant-user-screens-011CV551prV2r2QUtaYW3zqX

# Voltar para pasta do React
cd merchant-react-app

# Verificar se há novas dependências
echo ""
echo "📦 Verificando dependências..."

if [[ ! -d "node_modules" ]]; then
  echo "❌ node_modules não encontrado!"
  echo "🔧 Instalando dependências..."
  npm install
else
  echo "✅ node_modules já existe"
  echo ""
  echo "💡 Dica: Se houver erros, delete a pasta node_modules e rode 'npm install'"
fi

echo ""
echo "✅ Projeto atualizado!"
echo ""
echo "🚀 Para rodar o projeto:"
echo "   cd merchant-react-app"
echo "   npm start"
echo ""
