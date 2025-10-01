<?php
// views/errors/permission-denied.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - Klube Cash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <div class="mb-4">
                <i class="fas fa-lock fa-5x text-warning"></i>
            </div>
            <h1 class="display-4 mb-3">Acesso Negado</h1>
            <p class="lead mb-4">
                Você não tem permissão para acessar esta funcionalidade. 
                <br>Entre em contato com o administrador da loja para solicitar acesso.
            </p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    Voltar
                </a>
                <a href="/views/store/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>
                    Ir para Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>