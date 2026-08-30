# `docker/php/` — Imagem do PHP

Receita da imagem que executa o código do projeto: **PHP 8.3 + PHP-FPM** sobre
Alpine Linux, com as extensões que o domínio precisa, o Composer embutido e um
usuário não-root.

## Conceitos

- **Imagem × container.** O `Dockerfile` é a receita de uma *imagem* (sistema de
  arquivos pronto). Dela o Docker cria *containers* (instâncias em execução).
- **PHP-FPM.** *FastCGI Process Manager*. O nginx não executa PHP; ele repassa a
  requisição para um processo PHP-FPM na porta `9000` via protocolo FastCGI. Por
  isso a base é `php:8.3-fpm-*` e não `cli` ou `apache`.
- **Alpine.** Distribuição Linux minúscula (~10 MB). Imagem final menor. Usa
  `apk` (não `apt`).
- **Camadas.** Cada instrução do Dockerfile vira uma camada em cache. Instruções
  que mudam pouco vêm antes das que mudam muito, para reaproveitar cache.
- **Instrução do Dockerfile × comando de shell.** `FROM`, `RUN`, `COPY`, `ARG`,
  `WORKDIR`, `USER` são instruções do Docker — **uma por linha**. `&&` e `\`
  (quebra de linha) só valem **dentro** de um `RUN`, que é shell. O `\` fica no
  fim de toda linha do `RUN` **menos a última**.

## Linha a linha do `Dockerfile`

```dockerfile
FROM php:8.3-fpm-alpine
```
Imagem base oficial: PHP 8.3 (versão exigida pela modelagem — `enum`, `readonly`
etc.) + PHP-FPM + Alpine.

```dockerfile
ARG UID=1000
ARG GID=1000
```
Variáveis **de build** (só existem enquanto a imagem é construída). O
`docker-compose.yml` passa os valores em `build.args`. Default `1000` = valor
mais comum do primeiro usuário Linux.

```dockerfile
RUN apk add --no-cache icu-dev \
    && docker-php-ext-install pdo_mysql intl opcache
```
Um único `RUN` (uma camada):
- `apk add --no-cache icu-dev` — biblioteca ICU (internacionalização), exigida
  para compilar a extensão `intl`. `--no-cache` não grava o índice de pacotes
  (imagem menor).
- `docker-php-ext-install ...` — compila e ativa extensões PHP:
  - **`pdo_mysql`** — driver PDO para MySQL. É a base da persistência (ADR-011:
    PDO puro, sem ORM). Sem ele, `new PDO("mysql:...")` falha.
  - **`intl`** — `\NumberFormatter` etc. Usado na formatação de `Money` e pelo
    `symfony/uid`.
  - **`opcache`** — cache de bytecode. Padrão de produção, barato em dev.

> A imagem oficial do PHP hoje instala o compilador (`gcc`, `make`…) só para
> compilar as extensões e o **remove no fim automaticamente** — no build aparece
> `Purging gcc, make, binutils...`. Em imagens antigas seria preciso adicionar e
> remover `$PHPIZE_DEPS` manualmente.

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```
*Multi-stage build*: usa a imagem oficial `composer:2` só como origem, copia
apenas o binário e descarta o resto. Composer com versão fixa (2.x), sem baixar
instalador por script.

```dockerfile
RUN addgroup -g ${GID} app \
    && adduser -u ${UID} -G app -s /bin/sh -D app
```
Cria grupo e usuário `app` com o **mesmo UID/GID do host**:
- `-u` / `-g` — ids iguais aos seus → arquivos que o container criar no bind
  mount (`vendor/`, caches) ficam seus, não do `root`; você edita/apaga sem
  `sudo`.
- `-s /bin/sh` — shell padrão. `-D` — sem senha (não faz login interativo).

```dockerfile
WORKDIR /var/www
```
Diretório de trabalho padrão. Todo comando (`composer install`, `phpunit`…) roda
a partir daqui. É onde o Compose monta o código (`.:/var/www`).

```dockerfile
USER app
```
Daqui em diante o container roda como `app` (não-root). Boa prática de segurança
e resolve a permissão do bind mount.

## Verificação

```bash
docker compose build php
docker compose run --rm php sh -c 'php -v && php -m | grep -E "pdo_mysql|intl" && id && composer --version'
```

Esperado: `PHP 8.3.x`, `pdo_mysql` e `intl` na lista, `uid=1000(app)`,
`Composer version 2.x`.
