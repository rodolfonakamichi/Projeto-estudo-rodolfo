-- Roda só na 1ª subida do volume (docker-entrypoint-initdb.d).
-- Banco de teste: o Doctrine usa "<DB_DATABASE>_test" em APP_ENV=test.
CREATE DATABASE IF NOT EXISTS comandas_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON comandas_test.* TO 'comandas'@'%';
