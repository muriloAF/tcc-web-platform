-- Patch: Convert database and core tables to utf8mb4
-- Purpose: Fix accent issues and enable full emoji support
-- Safe to run multiple times; ensure you have a backup before applying in production.

-- Use correct database
USE `db_avena`;

-- Set database default charset/collation
ALTER DATABASE `db_avena` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convert tables to utf8mb4 (standard conversion)
ALTER TABLE `agenda`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `avaliacoes`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `chat`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `cliente`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `curso`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mensagem`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `notificacoes`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `prestadora`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `solicitacoes`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Optional: If you already have mojibake (e.g., 'Ã§' instead of 'ç'),
-- you may need to re-interpret specific columns by converting via BLOB first.
-- Uncomment and run carefully per affected column.
-- Example for `agenda.anotacao` and `mensagem.conteudo`:
-- ALTER TABLE `agenda`   MODIFY `anotacao` BLOB;
-- ALTER TABLE `agenda`   MODIFY `anotacao` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ALTER TABLE `mensagem` MODIFY `conteudo` BLOB;
-- ALTER TABLE `mensagem` MODIFY `conteudo` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ensure connection-level charset is utf8mb4 in application (already in php/conexao.php)
-- mysqli_set_charset($conexao, 'utf8mb4');
