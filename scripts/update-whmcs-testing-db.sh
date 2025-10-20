#!/bin/bash

# Directorio donde se almacena la base de datos de pruebas
DB_DIR="database/database"
DB_NAME="whmcs_testing.sqlite"
DB_PATH="${DB_DIR}/${DB_NAME}"

# Asegurarse de que el directorio existe
mkdir -p "${DB_DIR}"

# Hacer copia de seguridad de la base de datos actual si existe
if [ -f "${DB_PATH}" ]; then
    cp "${DB_PATH}" "${DB_PATH}.backup-$(date +%Y%m%d)"
fi

# Copiar la base de datos WHMCS actualizada
echo "Copiando base de datos WHMCS para pruebas..."
cp /path/to/your/whmcs.sqlite "${DB_PATH}"

# Asegurar permisos correctos
chmod 644 "${DB_PATH}"

echo "Base de datos de pruebas WHMCS actualizada correctamente"
