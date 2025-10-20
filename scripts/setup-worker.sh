#!/bin/bash

# Obtener el directorio del proyecto
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Reemplazar las rutas en el archivo de configuración
sed -i "s|/path/to/your/project|$PROJECT_DIR|g" "$PROJECT_DIR/scripts/supervisor/unblock-worker.conf"

# Copiar el archivo de configuración a supervisor
sudo cp "$PROJECT_DIR/scripts/supervisor/unblock-worker.conf" /etc/supervisor/conf.d/

# Recargar supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Iniciar el worker
sudo supervisorctl start unblock-worker:*

echo "Worker configurado y ejecutándose"
