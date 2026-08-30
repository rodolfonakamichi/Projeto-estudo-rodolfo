FROM php:8.3-fpm-alpine

# Imagem base: PHP 8.3 + PHP-FPM, sobre Alpine Linux (enxuto).
# FPM = processo que o nginx aciona via FastCGI para executar o PHP.
ARG UID=1000
ARG GID=1000

# Primeiro RUN: instala dependências de sistema e extensões PHP.
# apk add --no-cache icu-dev  -> lib ICU (internacionalização), exigida pela extensão intl;
#  --no-cache não guarda índice do apk (imagem menor).
# docker-php-ext-install ...   -> compila e ativa as extensões PHP:
# pdo_mysql -> driver PDO para MySQL (a persistência do projeto é PDO puro)
# intl      -> \NumberFormatter, usado na formatação de Money e pelo symfony/uid
# opcache   -> cache de bytecode (padrão de produção; barato em dev)
# O && encadeia os dois comandos numa única camada de imagem.
RUN apk add --no-cache icu-dev \
      && docker-php-ext-install pdo_mysql intl opcache

# Copia SÓ o binário do Composer da imagem oficial composer:2.
# --from=composer:2 -> usa essa imagem como origem; pega um arquivo e descarta o resto.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Segundo RUN: cria grupo e usuário 'app' com o MESMO UID/GID do seu host.
#   addgroup -g ${GID} app                 -> grupo 'app' com GID 1000
#   adduser  -u ${UID} -G app -s /bin/sh -D app
#     -u UID   -> id do usuário = 1000 (igual ao seu no host)
#     -G app   -> grupo primário
#     -s /bin/sh -> shell padrão
#     -D       -> sem senha (não faz login interativo)
# Motivo: arquivos que o container criar no bind mount (vendor/, caches)
# ficam seus, não do root — você edita/apaga sem sudo.
RUN addgroup -g ${GID} app \
      && adduser -u ${UID} -G app -s /bin/sh -D app

# Diretório de trabalho padrão dentro do container.
# Todo comando (composer install, phpunit...) roda a partir daqui.
# É onde o compose vai montar o código do projeto.
WORKDIR /var/www

# A partir daqui o container roda como 'app', não como root.
USER app