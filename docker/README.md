# `docker/` — Ambiente de desenvolvimento

Ambiente **isolado e específico deste projeto** (ver ADR-002 e a conversa de
setup do M0.1). Não reaproveita nem interfere em nada que já exista na máquina.

## Componentes

| Serviço | Imagem | Papel | Porta no host |
|---------|--------|-------|---------------|
| `php`   | build de [`php/Dockerfile`](php/README.md) | PHP 8.3 + PHP-FPM: executa o código | — (só interna, `:9000`) |
| `nginx` | `nginx:1.27-alpine` | servidor web; repassa `.php` para o `php` via FastCGI | `8080` → `80` |
| `db`    | `mysql:8.4` | banco de dados | `33061` → `3306` |

## Isolamento (como não colide com o resto da máquina)

Tudo é prefixado pelo nome do projeto Compose (`name: comandas-universal` no
`docker-compose.yml`):

| Recurso | Nome real | Observação |
|---------|-----------|------------|
| Containers | `comandas-universal-php-1`, `-nginx-1`, `-db-1` | não colidem com os containers de trabalho |
| Rede | `comandas-universal_default` | criada só para este projeto |
| Volume | `comandas-universal_db-data` | dados do MySQL deste projeto, separados |
| Portas host | `8080` (web), `33061` (mysql) | escolhidas por estarem livres — as de trabalho usam 80, 443, 3306, 3307 |
| PHP / Composer | só dentro do container `php` | nada instalado no sistema; o `php8.0` local fica intacto |

## Rede interna do Compose

Dentro da rede, cada serviço é alcançável **pelo nome do serviço**:

```
navegador do host  ──:8080──▶  nginx  ──php:9000 (FastCGI)──▶  php  ──db:3306──▶  db
cliente MySQL do host  ──:33061──────────────────────────────────────────────▶  db
```

- `DB_HOST=db` e `DB_PORT=3306` no `.env` são o endereço **de dentro** da rede.
- Do **host** (ex.: DBeaver, PhpStorm), o banco é `127.0.0.1:33061`.

## Comandos do dia a dia

```bash
cp .env.example .env                       # 1ª vez: cria o .env local
docker compose build php                   # constrói a imagem do PHP
docker compose run --rm php composer install
docker compose up -d                       # sobe tudo em background
docker compose ps                          # o que está rodando
docker compose logs -f nginx               # acompanhar logs de um serviço

docker compose exec php sh                 # abrir shell no container PHP
docker compose exec php php -v
docker compose exec php composer <cmd>
docker compose exec php vendor/bin/phpunit

docker compose down                        # derruba os containers (mantém o volume)
docker compose down -v                     # derruba e APAGA o volume do banco
```

> `run --rm` cria um container descartável para um comando pontual.
> `exec` roda um comando num container **já em execução**.

## `docker-compose.yml` — bloco a bloco

Fica na **raiz** do projeto (não em `docker/`), porque é o ponto de entrada do
ambiente. Descreve o *conjunto* de containers; o Dockerfile descreve *uma*
imagem.

### Conceitos que aparecem no arquivo

- **`name:`** — nome do projeto Compose. É o **prefixo de isolamento**:
  containers viram `comandas-universal-php-1`, a rede
  `comandas-universal_default`, o volume `comandas-universal_db-data`. É isso que
  separa do resto da máquina.
- **`services:`** — cada serviço é um container. Aqui são 3: `php`, `nginx`, `db`.
- **Rede** — o Compose cria uma rede automática onde cada serviço é alcançável
  **pelo nome**. Dentro dela, o nginx acha o PHP em `php:9000` e o PHP acha o
  banco em `db:3306`. Nada disso é exposto ao host, exceto o que você declarar em
  `ports`.
- **`ports: "8080:80"`** — mapeia porta do **host** : porta do **container**. Só
  o que está aqui é acessível de fora. Portas livres escolhidas: nginx `8080`,
  MySQL `33061`.
- **`volumes`** — dois tipos:
  - *bind mount* (`.:/var/www`): a pasta do projeto no host aparece dentro do
    container. Você edita no PhpStorm, o container vê na hora.
  - *volume nomeado* (`db-data:/var/lib/mysql`): área gerenciada pelo Docker para
    os dados do MySQL persistirem entre `up`/`down`. Não fica na sua pasta.
- **`build:` vs `image:`** — `php` usa `build:` (nosso Dockerfile); `nginx` e
  `db` usam `image:` (imagens prontas do Docker Hub).
- **`depends_on`** — ordem de subida. `nginx` depende de `php`, `php` depende de
  `db`.
- **`environment`** — variáveis dentro do container. O MySQL lê
  `MYSQL_DATABASE`, `MYSQL_USER`, etc. **na primeira subida** para criar o banco
  e o usuário.

```yaml
name: comandas-universal
```
Nome do projeto Compose. É o **prefixo de isolamento**: containers
(`comandas-universal-php-1`…), rede (`comandas-universal_default`) e volume
(`comandas-universal_db-data`) nascem com esse nome. Sem `name`, o Compose usa o
nome da pasta.

```yaml
services:
```
Cada item abaixo é um container.

### Serviço `php`
```yaml
  php:
    build:
      context: ./docker/php     # diretório com o Dockerfile
      args:
        UID: 1000               # vira o `ARG UID` do Dockerfile
        GID: 1000               # vira o `ARG GID`
    volumes:
      - .:/var/www              # bind mount: a pasta do projeto aparece no container
    depends_on:
      - db                      # ordem de subida: db antes de php
```
- `build` (em vez de `image`) → esta imagem é construída do nosso Dockerfile.
- `args` alimenta os `ARG` do Dockerfile → usuário `app` com o UID/GID certo.
- `.:/var/www` → você edita no host, o container vê na hora (sem rebuild).
- Não tem `ports`: o PHP-FPM (`:9000`) só é acessado pelo nginx, dentro da rede.

### Serviço `nginx`
```yaml
  nginx:
    image: nginx:1.27-alpine    # imagem pronta do Docker Hub, versão fixada
    ports:
      - "8080:80"               # porta do HOST : porta do CONTAINER
    volumes:
      - .:/var/www              # nginx precisa ver public/ para servir estáticos
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php
```
- `8080:80` → no navegador é `http://localhost:8080`; dentro do container é `:80`.
  `8080` foi escolhida por estar livre (as portas 80/443 são de containers de
  trabalho).
- O segundo volume injeta nossa config no lugar onde o nginx a procura
  (`/etc/nginx/conf.d/`). `:ro` = *read-only*, o container não altera o arquivo.

### Serviço `db`
```yaml
  db:
    image: mysql:8.4
    ports:
      - "33061:3306"            # host 33061 → container 3306
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}        # criado na 1ª subida
      MYSQL_USER: ${DB_USERNAME}            # usuário de app criado na 1ª subida
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db-data:/var/lib/mysql             # volume nomeado: dados sobrevivem a `down`
```
- `33061` no host evita os `3306`/`3307` já ocupados. De dentro da rede, os
  serviços falam com o banco em `db:3306`.
- As variáveis `MYSQL_*` **só têm efeito na primeira subida** (quando o volume
  está vazio). Mudou usuário/senha depois? Precisa `docker compose down -v` para
  recriar o volume.
- `${DB_*}` são lidas do arquivo **`.env`** na raiz (ver `.env.example`).

### Volumes nomeados
```yaml
volumes:
  db-data:
```
Declara o volume `db-data` (vira `comandas-universal_db-data`). Gerenciado pelo
Docker, fora da pasta do projeto. Diferente do *bind mount* `.:/var/www`, que é
a pasta real do host.

> **bind mount** = pasta do host montada no container (código-fonte).
> **volume nomeado** = área gerenciada pelo Docker (dados do banco).

## Estrutura

```
docker/
├── README.md          (este arquivo)
├── php/
│   ├── Dockerfile
│   └── README.md       explicação linha a linha da imagem PHP
└── nginx/
    ├── default.conf
    └── README.md       explicação do virtual host / FastCGI

docker-compose.yml      (raiz) — orquestra php + nginx + db
.env / .env.example     (raiz) — variáveis lidas pelo compose
```
