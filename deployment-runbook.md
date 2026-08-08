# Deployment Runbook — Laravel + Inertia/React + Postgres + Redis + Horizon
### Target: Hostinger VPS (CyberPanel / OpenLiteSpeed) via Docker + GitHub Actions

---

## 0. Overview

```
Push to main (GitHub)
   -> CI: run tests
   -> CI: build Docker image, push to GHCR
   -> CD: SSH into VPS, pull image, run migrations, restart containers
   -> CyberPanel/OpenLiteSpeed proxies the public domain to the container over localhost
```

Docker never touches ports 80/443 — CyberPanel owns those. Docker's internal nginx
listens on `127.0.0.1:8080` only, and CyberPanel reverse-proxies to it.

---

## 1. One-time: containerize the app

**`Dockerfile`** (repo root):

```dockerfile
# --- Stage 1: build frontend assets ---
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# --- Stage 2: install PHP deps ---
FROM composer:2 AS vendor
WORKDIR /app
COPY database/ database/
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# --- Stage 3: final runtime image ---
FROM php:8.3-fpm-alpine
RUN apk add --no-cache postgresql-dev libzip-dev zip icu-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip intl opcache pcntl

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

`pcntl` is required for Horizon's signal handling — don't drop it.

**`nginx.conf`** (repo root, used only inside Docker — HTTP only, no SSL here):

```nginx
server {
    listen 80;
    server_name _;

    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|woff2?)$ {
        expires 30d;
        access_log off;
    }
}
```

Add both files to git. Add `.env`, `vendor/`, `node_modules/`, `public/build/` to `.gitignore`
if not already there (build artifacts are produced in CI, not committed).

---

## 2. One-time: prep the Hostinger VPS

1. SSH in as root (or sudo user).
2. Install Docker + Compose plugin:
   ```bash
   curl -fsSL https://get.docker.com | sh
   ```
3. Create a dedicated deploy user, add to `docker` group:
   ```bash
   adduser deploy
   usermod -aG docker deploy
   ```
4. Generate a CI-only SSH keypair (on your local machine, not the VPS):
   ```bash
   ssh-keygen -t ed25519 -f deploy_key -C "github-actions-deploy"
   ```
   - Put `deploy_key.pub` into `/home/deploy/.ssh/authorized_keys` on the VPS.
   - Keep `deploy_key` (private) for GitHub Secrets — never commit it.
5. Create the deploy directory:
   ```bash
   mkdir -p /home/deploy/app && cd /home/deploy/app
   ```
6. Put `docker-compose.yml` (below) and a real `.env` here. This directory is the
   persistent home for everything that isn't rebuilt by CI.
7. Generate `APP_KEY` **once**, locally:
   ```bash
   php artisan key:generate --show
   ```
   Paste the value into the VPS `.env`. Never regenerate on later deploys — it
   invalidates sessions/cookies and encrypted DB fields.

---

## 3. `docker-compose.yml` (lives permanently on the VPS)

```yaml
services:
  app:
    image: ghcr.io/yourname/yourapp:latest
    restart: unless-stopped
    env_file: .env
    depends_on: [postgres, redis]
    volumes:
      - storage:/var/www/html/storage

  horizon:
    image: ghcr.io/yourname/yourapp:latest
    command: php artisan horizon
    restart: unless-stopped
    stop_grace_period: 30s
    env_file: .env
    depends_on: [postgres, redis]
    volumes:
      - storage:/var/www/html/storage

  nginx:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:80"   # localhost only — CyberPanel proxies to this
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on: [app]

  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    env_file: .env
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - redisdata:/data

volumes:
  pgdata:
  redisdata:
  storage:
```

Boot it once manually to confirm everything comes up before wiring CI:
```bash
docker compose up -d
docker compose ps
curl -I http://127.0.0.1:8080
```

**`.env` essentials on the VPS:**
```
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
REDIS_HOST=redis
APP_URL=https://yourdomain.com
```

---

## 4. CyberPanel: point the domain at the container

1. **Websites → Create Website** — create the domain/subdomain this app owns.
2. **Websites → List Websites → Manage → [domain] → vHost Conf** — add:
   ```
   extprocessor dockerapp {
     type                    proxy
     address                 127.0.0.1:8080
     maxConns                100
     pcKeepAliveTimeout      60
     connTimeout             10
     retryTimeout            0
     respBuffer              0
   }

   context / {
     type                    proxy
     handler                 dockerapp
     addDefaultCharset       off
   }
   ```
   If other Dockerized sites exist on this box, rename `dockerapp` uniquely per
   site (e.g. `dockerapp_appname`) and give each its own local port (8080, 8081...).
3. **Server Status → OpenLiteSpeed → Graceful Restart** (not a hard restart —
   other sites on the box stay up).
4. **Websites → List Websites → Manage → [domain] → SSL → Issue SSL** (AutoSSL/Let's Encrypt).
5. Verify:
   ```bash
   curl -I http://127.0.0.1:8080          # from inside the VPS — should hit Laravel
   curl -I https://yourdomain.com         # from outside — should hit the same, over TLS
   ```
6. Confirm port 8080 isn't reachable from the public internet directly:
   ```bash
   curl -I http://<vps-public-ip>:8080    # should fail/timeout
   ```

DNS: A records for `@` and `www` → VPS IP, set in whichever panel manages this
domain's DNS zone. Allow up to ~24h to propagate.

---

## 5. GitHub: secrets

Repo → Settings → Secrets and variables → Actions → add:

| Secret | Value |
|---|---|
| `VPS_HOST` | VPS IP or hostname |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | contents of the private `deploy_key` from step 2 |

`GITHUB_TOKEN` for GHCR auth is provided automatically — no setup needed.

---

## 6. `.github/workflows/deploy.yml`

```yaml
name: Build and Deploy

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_PASSWORD: secret
          POSTGRES_DB: testing
        ports: ["5432:5432"]
        options: >-
          --health-cmd pg_isready --health-interval 10s --health-timeout 5s --health-retries 5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --prefer-dist --no-progress
      - uses: actions/setup-node@v4
        with:
          node-version: 20
      - run: npm ci && npm run build
      - run: cp .env.testing .env
      - run: php artisan key:generate
      - run: php artisan test

  build-and-push:
    needs: test
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    steps:
      - uses: actions/checkout@v4
      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      - uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ghcr.io/${{ github.repository }}:latest

  deploy:
    needs: build-and-push
    runs-on: ubuntu-latest
    steps:
      - uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: |
            cd /home/deploy/app
            docker compose pull
            docker compose run --rm app php artisan migrate --force
            docker compose up -d
            docker compose exec app php artisan config:cache
            docker compose exec app php artisan route:cache
            docker compose exec app php artisan view:cache
            docker compose restart horizon
            docker image prune -f
```

Why each piece is ordered this way:
- `migrate --force` runs as a one-off container **before** `up -d` swaps the
  running containers.
- Config/route/view caching runs **after** `up -d`, against the live container,
  since `.env` values need to already be present.
- `horizon` is explicitly restarted so queue workers pick up new job class code
  (PHP workers cache class definitions per process — they won't reload otherwise).

---

## 7. First real deploy checklist

- [ ] `Dockerfile`, `nginx.conf` committed to repo
- [ ] `.env.testing` committed (for CI test job), real `.env` only on the VPS
- [ ] VPS: Docker installed, deploy user created, SSH key authorized
- [ ] VPS: `/home/deploy/app/docker-compose.yml` + `.env` in place, `docker compose up -d` run manually once and confirmed healthy
- [ ] CyberPanel: website created, vHost proxy config added, graceful restart done, SSL issued
- [ ] DNS A records point at VPS IP
- [ ] GitHub secrets set: `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`
- [ ] Push to `main`, watch the Actions run, confirm `https://yourdomain.com` loads
- [ ] Check `docker compose logs horizon` on the VPS to confirm queues are processing

## 8. Known gotchas

- **Vite/`APP_URL`**: build-time asset URLs get baked in by Vite — make sure
  `.env` used during the CI build step matches production's actual domain, or
  assets will 404 after deploy.
- **Horizon dashboard**: open to everyone by default outside `local` env unless
  gated in `HorizonServiceProvider::gate()`. Lock this down before going live.
- **Port collisions**: if more Dockerized sites get added to this VPS later,
  each needs its own local port and its own `extprocessor` name in CyberPanel.
- **WebSockets** (Reverb/Echo, if added later): needs an additional `context`
  block in the vHost conf with upgrade headers — not covered by the config above.
- **Zero-downtime**: this setup has a few seconds of downtime during
  `docker compose up -d`. Fine for scheduled deploys; revisit with a blue-green
  pattern if that ever matters.
