## Despliegue (staging / producción)

Este proyecto puede desplegarse con Docker (recomendado) o directamente en un servidor PHP. La configuración está pensada para operar con:

- APP_DEBUG desactivado
- HTTPS terminado por un proxy/ingress (o por NGINX)
- Cache/queue/sessions en Redis (recomendado)
- Logs estructurados hacia stderr (ideal para contenedores)

### Variables de entorno

Usa [.env.production.example](file:///c:/xampp/htdocs/emprengrandez/.env.production.example) como base.

Valores mínimos:

- `APP_KEY` generado con `php artisan key:generate --show`
- `APP_URL` apuntando a tu dominio HTTPS
- `DB_*` apuntando a la base de datos de producción
- `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` (recomendado: `redis`)
- `LOG_CHANNEL=json_stderr`
- `HEALTHZ_TOKEN` para checks profundos (opcional)

### Docker (producción)

1) Copia `.env.production.example` a `.env` en el servidor y completa valores.
2) Levanta contenedores:

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec php php artisan migrate --force
docker compose -f docker-compose.prod.yml exec php php artisan optimize:clear
```

3) Verifica salud:

- `GET /up` (Laravel)
- `GET /api/healthz` (rápido)
- `GET /api/healthz` con `X-Healthz-Token: <HEALTHZ_TOKEN>` (DB/Redis)

### HTTPS

Recomendado:

- Terminar TLS fuera del contenedor (NGINX reverse proxy / Traefik / Caddy / Cloudflare).
- Configurar `APP_URL=https://...`.
- Mantener `TrustProxies` habilitado (ya está configurado en [bootstrap/app.php](file:///c:/xampp/htdocs/emprengrandez/bootstrap/app.php)).

### Hardening

- Headers de seguridad se agregan por middleware: [SecurityHeaders](file:///c:/xampp/htdocs/emprengrandez/app/Http/Middleware/SecurityHeaders.php)
- Ajustes vía `.env`:
  - `SECURE_HEADERS=true`
  - `HSTS=true`
  - `CSP=...` (si necesitas una política más estricta)

### Cache y performance

En contenedores de producción el build usa:

- OPCache (ver [opcache.ini](file:///c:/xampp/htdocs/emprengrandez/docker/php/conf.d/opcache.ini))
- `php artisan config:cache`, `route:cache`, `view:cache` en build
- Cache headers para `public/build` en NGINX (ver [prod.conf](file:///c:/xampp/htdocs/emprengrandez/docker/nginx/prod.conf))

### Logs estructurados

Configura:

- `LOG_CHANNEL=json_stderr`
- `LOG_LEVEL=info`

Canal: [config/logging.php](file:///c:/xampp/htdocs/emprengrandez/config/logging.php)

### Scheduler y queue

En Docker:

- `queue` corre `php artisan queue:work`
- `scheduler` corre `php artisan schedule:work`

Archivo: [docker-compose.prod.yml](file:///c:/xampp/htdocs/emprengrandez/docker-compose.prod.yml)

### Backups (documentado)

Recomendación:

- Backup diario de DB (mysqldump) + storage (si aplica) a un bucket (S3/Backblaze/Drive) o volumen externo.
- Prueba de restore mensual.

### Checklist de staging

Antes de producción:

- Migraciones en staging: `php artisan migrate --force`
- Vite build generado (`public/build/manifest.json`)
- Login / Filament panel carga sin errores
- Endpoints críticos: `/up`, `/api/healthz`
- Crear: compra, venta, crédito, pago, gasto (flujo completo)
- Reportes: flujo de caja, morosidad, estado de cartera

