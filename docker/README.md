# Docker Setup for PHP Craps

## Architecture

The Docker setup uses two containers:

1. **PHP Container** (`phpcraps-php`)
   - Based on `php:8.2-fpm-alpine`
   - Runs PHP-FPM on port 9000
   - Runs WebSocket server on port 8080
   - Uses Supervisor to manage both processes
   - Includes SQLite with pre-initialized database

2. **Caddy Container** (`phpcraps-caddy`)
   - Based on `caddy:2-alpine`
   - Listens on port 8000
   - Serves static files
   - Proxies PHP requests to PHP-FPM
   - Proxies WebSocket connections to port 8080

## Quick Commands

### Start everything
```bash
docker-compose up
```

### Start in background
```bash
docker-compose up -d
```

### View logs
```bash
docker-compose logs -f
```

### Restart services
```bash
docker-compose restart
```

### Stop everything
```bash
docker-compose down
```

### Rebuild after code changes
```bash
docker-compose up --build
```

### Reset the game (clear database)
```bash
docker-compose down -v
docker-compose up --build
```

### Access PHP container shell
```bash
docker-compose exec php sh
```

### Check WebSocket server status
```bash
docker-compose exec php ps aux | grep websocket
```

## Development Workflow

### Making Code Changes

1. Edit files locally (hot-reload for static files)
2. For PHP changes, restart:
   ```bash
   docker-compose restart php
   ```

### Database Access

The database is stored in `./database/craps_game.db` and persists between restarts.

To reset the database:
```bash
docker-compose down
rm database/craps_game.db
docker-compose up
```

### Debugging

View logs for a specific service:
```bash
docker-compose logs -f php
docker-compose logs -f caddy
```

View WebSocket server output:
```bash
docker-compose logs -f php | grep websocket
```

## Ports

- **8000**: Caddy (HTTP + WebSocket proxy)
- **9000**: PHP-FPM (internal, not exposed)
- **8080**: WebSocket server (internal, not exposed)

All traffic goes through Caddy on port 8000.

## Environment Variables

Currently no environment variables are needed. All configuration is in:
- `config/config.php`
- `Caddyfile`
- `docker-compose.yml`

## Troubleshooting

### Port 8000 already in use
```bash
# Find what's using it
lsof -i :8000

# Change port in docker-compose.yml
ports:
  - "8001:8000"  # Use 8001 instead
```

### Database permission errors
```bash
docker-compose down
sudo chown -R $USER database/
docker-compose up
```

### WebSocket not connecting
Check if the WebSocket server is running:
```bash
docker-compose exec php netstat -tlnp | grep 8080
```

### View all running processes
```bash
docker-compose exec php ps aux
```
