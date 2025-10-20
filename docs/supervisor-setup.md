# Configuración de Supervisor para Laravel Queue Workers

## 📋 Configuración Actual

### Variables de Entorno Necesarias
```bash
# En .env de producción
REDIS_SCHEME=unix
REDIS_PASSWORD=null
REDIS_PORT=null
REDIS_PATH=/home/laravel/.redis/redis.sock
REDIS_SERVERS=/home/laravel/.redis/redis.sock
```

### Configuración de Colas (config/queue.php)
✅ **Ya configurado correctamente**
- Driver Redis configurado
- Retry after: 90 segundos
- Failed jobs: database-uuids

### Configuración de Redis (config/database.php)
✅ **Ya configurado correctamente**
- Conexión default y cache separadas
- Prefix automático basado en APP_NAME

## 🔧 Setup de Supervisor

### 1. Instalar Supervisor
```bash
sudo apt update
sudo apt install supervisor
```

### 2. Crear archivo de configuración
```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

### 3. Contenido del archivo
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/unblock/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=laravel
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/unblock/storage/logs/worker.log
stopwaitsecs=3600
directory=/path/to/your/unblock
environment=LARAVEL_ENV="production"
```

### 4. Activar configuración
```bash
# Recargar configuración
sudo supervisorctl reread

# Actualizar programas
sudo supervisorctl update

# Iniciar workers
sudo supervisorctl start laravel-worker:*

# Verificar estado
sudo supervisorctl status
```

## 📊 Comandos de Gestión

### Estado y Control
```bash
# Ver estado de todos los workers
sudo supervisorctl status laravel-worker:*

# Parar workers
sudo supervisorctl stop laravel-worker:*

# Reiniciar workers
sudo supervisorctl restart laravel-worker:*

# Iniciar workers
sudo supervisorctl start laravel-worker:*
```

### Logs y Monitoreo
```bash
# Ver logs en tiempo real
sudo supervisorctl tail -f laravel-worker:laravel-worker_00 stdout

# Ver logs del worker específico
tail -f /path/to/your/unblock/storage/logs/worker.log

# Ver logs de Laravel
tail -f /path/to/your/unblock/storage/logs/laravel.log
```

## 🧪 Testing del Sistema

### 1. Verificar configuración actual
```bash
cd /path/to/your/unblock
php artisan develop:queue-monitor
```

### 2. Enviar job de prueba
```bash
php artisan develop:test-email-job
```

### 3. Monitorear en tiempo real
```bash
php artisan develop:queue-monitor --watch
```

### 4. Verificar que Supervisor procesa el job
```bash
# Debería mostrar workers activos
sudo supervisorctl status laravel-worker:*
```

## ⚙️ Explicación de Parámetros

### Parámetros del Worker
- `--sleep=3`: Esperar 3 segundos entre jobs si no hay trabajo
- `--tries=3`: Reintentar jobs fallidos hasta 3 veces  
- `--max-time=3600`: Reiniciar worker cada hora (previene memory leaks)

### Parámetros de Supervisor
- `numprocs=2`: 2 workers en paralelo (ajustar según CPU)
- `user=laravel`: Ejecutar como usuario laravel
- `autostart=true`: Iniciar automáticamente al arrancar el sistema
- `autorestart=true`: Reiniciar automáticamente si el proceso falla
- `stopwaitsecs=3600`: Esperar hasta 1 hora para que el proceso termine gracefully

## 🚨 Troubleshooting

### Worker no inicia
```bash
# Verificar permisos
ls -la /path/to/your/unblock/artisan

# Verificar que Redis está corriendo
redis-cli ping

# Verificar logs de Supervisor
sudo tail -f /var/log/supervisor/supervisord.log
```

### Jobs no se procesan
```bash
# Verificar estado de workers
sudo supervisorctl status

# Verificar logs del worker
sudo supervisorctl tail laravel-worker:laravel-worker_00 stdout

# Verificar colas en Redis
php artisan develop:queue-monitor
```

### Reiniciar todo el sistema
```bash
# Parar workers
sudo supervisorctl stop laravel-worker:*

# Reiniciar Supervisor
sudo systemctl restart supervisor

# Verificar que todo está funcionando
sudo supervisorctl status
php artisan develop:queue-monitor
```

## 📈 Monitoreo en Producción

### Comandos útiles para administración
```bash
# Estado general del sistema
sudo supervisorctl status

# Logs de la aplicación
tail -f /path/to/your/unblock/storage/logs/laravel.log

# Estado de colas
cd /path/to/your/unblock && php artisan develop:queue-monitor

# Probar envío de email
cd /path/to/your/unblock && php artisan develop:test-email-job
```

### Alertas recomendadas
- Monitorear que los workers estén activos
- Alertar si hay muchos jobs fallidos
- Monitorear uso de memoria de los workers
- Verificar que Redis está disponible 
