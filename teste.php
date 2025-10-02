<?php
$data = [
    'numero' => '5534993357697',
    'mensagem' => 'Olá, fazendo o teste da API!'
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/json",
            'method' => 'POST',
            'content' => json_encode($data)
            ]
        ];

    $context = stream_context_create($options);
    $result = file_get_contents('https://whatsapp-bot-open-wa.fb4z0o.easypanel.host/send-message', false, $context);
    
    var_dump($result);
