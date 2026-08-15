-- Comprovantes duraveis para ambientes serverless (Vercel).
-- MEDIUMBLOB armazena o limite funcional de 4 MB sem depender do filesystem.

CREATE TABLE IF NOT EXISTS pagamentos_comprovantes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pagamento_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    tamanho_bytes INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    conteudo MEDIUMBLOB NOT NULL,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pagamentos_comprovantes_pagamento (pagamento_id),
    CONSTRAINT fk_pagamentos_comprovantes_pagamento
        FOREIGN KEY (pagamento_id) REFERENCES pagamentos_comissao (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
