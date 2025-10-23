#!/usr/bin/env bash
set -e

# Set timezone
if [ -n "${TZ}" ]; then
  ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime && echo "${TZ}" > /etc/timezone
fi

# Configure PHP settings from environment variables
cat > /usr/local/etc/php/conf.d/custom.ini <<EOF
date.timezone=${TZ:-UTC}
memory_limit=${PHP_MEMORY_LIMIT:-512M}
upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE:-64M}
post_max_size=${PHP_POST_MAX_SIZE:-64M}
max_execution_time=${PHP_MAX_EXECUTION_TIME:-120}
max_input_vars=${PHP_MAX_INPUT_VARS:-3000}
EOF

# Opcache recommended settings
cat > /usr/local/etc/php/conf.d/opcache-recommended.ini <<EOF
opcache.enable=${PHP_OPCACHE_ENABLE:-1}
opcache.enable_cli=0
opcache.memory_consumption=${PHP_OPCACHE_MEMORY_CONSUMPTION:-128}
opcache.interned_strings_buffer=${PHP_OPCACHE_INTERNED_STRINGS_BUFFER:-16}
opcache.max_accelerated_files=${PHP_OPCACHE_MAX_ACCELERATED_FILES:-10000}
opcache.validate_timestamps=${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-1}
opcache.revalidate_freq=${PHP_OPCACHE_REVALIDATE_FREQ:-2}
EOF

# Respect custom document root if provided
if [ -n "${APACHE_DOCUMENT_ROOT}" ] && [ "${APACHE_DOCUMENT_ROOT}" != "/var/www/html" ]; then
  sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf
fi

# Ensure .htaccess works
if ! grep -q "<Directory \"/var/www/html\">" /etc/apache2/apache2.conf; then
  cat >> /etc/apache2/apache2.conf <<'EOT'
<Directory "/var/www/html">
    AllowOverride All
</Directory>
EOT
fi

exec "$@"


