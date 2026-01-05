-- Patch: adiciona suporte a anexos na tabela mensagem
-- Execute uma vez no banco `db_avena`
-- Verifica antes se as colunas não existem
-- Forma simples (sem AFTER para evitar erro 1054 se coluna de referência ausente)
ALTER TABLE mensagem ADD COLUMN tipo VARCHAR(16) NOT NULL DEFAULT 'text';
ALTER TABLE mensagem ADD COLUMN arquivo VARCHAR(255) NULL;

-- Opcional ajustar posição depois:
-- ALTER TABLE mensagem MODIFY COLUMN tipo VARCHAR(16) NOT NULL DEFAULT 'text' AFTER lido;
-- ALTER TABLE mensagem MODIFY COLUMN arquivo VARCHAR(255) NULL AFTER tipo;

-- Caso já existam, ignore erros ou rode:
-- SHOW COLUMNS FROM mensagem LIKE 'tipo';
-- SHOW COLUMNS FROM mensagem LIKE 'arquivo';
