FROM php:8.4-fpm-alpine
LABEL org.opencontainers.image.source="https://github.com/klubecash/klubecash1"

RUN apk add --no-cache ca-certificates icu-libs libcurl libstdc++ \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev curl-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli mbstring intl curl opcache \
    && apk del .build-deps \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=20M\npost_max_size=21M\nlog_errors=1\nerror_log=/proc/self/fd/2\n' > "$PHP_INI_DIR/conf.d/klubecash.ini"

WORKDIR /app
COPY . .
RUN mkdir -p /app/logs /app/uploads \
    && chown -R www-data:www-data /app/logs /app/uploads \
    && chmod 0755 /app/logs /app/uploads

EXPOSE 9000
CMD ["php-fpm", "-F"]
