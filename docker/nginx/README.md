# `docker/nginx/` — Servidor web / porteiro

O nginx recebe **toda** requisição de `http://localhost:8080` e decide:

- **arquivo estático** (`.css`, `.js`, imagem em `public/`) → serve direto do disco;
- **qualquer outra coisa** → repassa para o PHP-FPM (`php:9000`) via **FastCGI** e
  devolve a resposta.

O PHP nunca é acessado direto pelo navegador — sempre passa pelo nginx.

```
navegador ──:8080──▶ nginx ──FastCGI php:9000──▶ php-fpm ──▶ public/index.php
```

## `default.conf` — linha a linha

```nginx
server {
```
Um *virtual host*: um bloco de regras para um site. O arquivo é montado em
`/etc/nginx/conf.d/default.conf` pelo `docker-compose.yml`.

```nginx
    listen 80;
    server_name _;
```
- `listen 80` — porta **dentro** do container. O mapeamento para `8080` do host
  está no Compose, não aqui.
- `server_name _` — curinga: responde a qualquer Host. Suficiente para dev.

```nginx
    root /var/www/public;
    index index.php;
```
- `root` — pasta exposta à web. É **`public/`**, não a raiz do projeto → só o que
  está em `public/` é acessível. `src/`, `vendor/`, `.env`, `docs/` ficam fora do
  alcance. Isso é o *front controller pattern*: um único ponto de entrada.
- `index` — arquivo padrão quando a URL aponta para um diretório.

```nginx
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
```
O coração do front controller. Para cada URL, nesta ordem:
1. existe um arquivo com esse nome? (`$uri`) → serve o arquivo
2. existe um diretório? (`$uri/`) → serve
3. senão → entrega tudo para `/index.php`, repassando a query string

Assim `GET /health`, `GET /commands/123`, `POST /orders` caem todos no
`public/index.php`, que faz o roteamento interno (o kernel, no passo M0.4).

```nginx
    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
```
Casa com URLs terminadas em `.php`. É aqui que o PHP roda:
- `fastcgi_pass php:9000` — envia a requisição para o serviço `php` na porta
  9000. O nome `php` é resolvido pela rede do Compose.
- `fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name` — informa ao
  PHP-FPM **qual arquivo** executar (caminho absoluto real). Sem essa linha:
  `File not found`.
- `include fastcgi_params` — traz dezenas de parâmetros padrão (método, headers,
  IP do cliente, etc.) de um arquivo do próprio nginx.

```nginx
    location ~ /\.(?!well-known) {
        deny all;
    }
}
```
Bloqueia acesso a qualquer caminho que comece com `.` (`.env`, `.git`, `.htaccess`)
— exceto `.well-known` (usado por Let's Encrypt e afins). Defesa contra
vazamento de arquivos sensíveis.

## Notas

- **`nginx -t` isolado falha** com `host not found in upstream "php"` — é
  esperado fora do Compose (não há serviço `php` para resolver). Dentro de
  `docker compose up` funciona.
- **Encoding**: manter o arquivo em **UTF-8** (o `.editorconfig` do projeto
  fixa `charset = utf-8`).
- Config de dev. Para produção faltaria: `gzip`, cache de estáticos, headers de
  segurança, HTTPS, `client_max_body_size`, timeouts de FastCGI.
