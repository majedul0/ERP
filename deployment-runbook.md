# Deployment Runbook

**Target:** Hostinger VPS behind CyberPanel/OpenLiteSpeed
**Registry:** `ghcr.io/majedul0/erp`

> This repository is public. The server's address, and every credential, live in
> GitHub Actions secrets and in `.env` on the box — never here.

---

## 1. How it works

```
push to main
  └─ .github/workflows/deploy.yml
       ├─ test    composer ci:check  (pint, phpstan, eslint, prettier, tsc, phpunit)
       ├─ build   docker build → ghcr.io/majedul0/erp:{latest,<sha>}
       └─ deploy  ssh → pull, migrate, up -d, restart workers, health check
```

On the box:

```
internet :443
  └─ OpenLiteSpeed (CyberPanel — owns 80/443, terminates TLS)
       └─ proxy → 127.0.0.1:8080
            └─ docker compose (project "erp")
                 ├─ app        nginx + php-fpm      → publishes 127.0.0.1:8080
                 ├─ horizon    php artisan horizon
                 ├─ scheduler  php artisan schedule:work
                 ├─ postgres   (private network only)
                 └─ redis      (private network only)
```

Only `app` publishes a port, and only to loopback. Postgres and Redis are
reachable solely from inside the compose network — never from the internet.

---

## 2. Files

| File | Lives | Purpose |
|---|---|---|
| `Dockerfile` | repo | Two stages: build assets, then the runtime image |
| `.dockerignore` | repo | Keeps `.env`, `node_modules`, `vendor`, `.git` out of the image |
| `docker/nginx.conf` | repo → image | Server block, static caching, upload limit |
| `docker/supervisord.conf` | repo → image | Runs nginx and php-fpm, restarts either if it dies |
| `docker/php.ini` | repo → image | opcache, upload limits, errors to stderr |
| `docker/php-fpm.conf` | repo → image | Pool sizing, worker recycling, log passthrough |
| `docker/entrypoint.sh` | repo → image | Rebuilds storage dirs, links storage, caches config |
| `deploy/docker-compose.yml` | **copy to VPS** | The five services |
| `deploy/.env.example` | **copy to VPS as `.env`** | Production configuration and secrets |
| `deploy/provision.sh` | run once on VPS | Docker, deploy user, firewall |

`deploy/*` is committed for review but **CI never writes it to the server** —
the `.env` beside it holds every production secret and must not be overwritten
by a deploy.

---

## 3. One-time: the VPS

```bash
# as root
bash provision.sh "ssh-ed25519 AAAA... github-actions-deploy@erp"
```

Installs Docker, creates `deploy` (in the `docker` group, key-only), creates
`/home/deploy/app`, and closes everything except 22, 80, 443 and 8090.

Then, as `deploy`:

```bash
cd /home/deploy/app
# place docker-compose.yml and .env here
chmod 600 .env
docker compose up -d
curl -I http://127.0.0.1:8080/up      # expect 200
```

`APP_KEY` is generated **once** (`php artisan key:generate --show`) and never
changed: it decrypts existing sessions, cookies and any encrypted column.

---

## 4. One-time: CyberPanel

1. **Websites → Create Website** for the app's domain.
2. **Manage → vHost Conf**, add:

   ```
   extprocessor erpapp {
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
     handler                 erpapp
     addDefaultCharset       off
   }
   ```

   One `extprocessor` name and one local port per Dockerised site on this box.
3. **Server Status → OpenLiteSpeed → Graceful Restart** (not a hard restart —
   other sites stay up).
4. **Manage → SSL → Issue SSL** (Let's Encrypt).
5. DNS: the app's A record → the VPS IP.

Verify:

```bash
curl -I http://127.0.0.1:8080/up        # on the box  → 200
curl -I https://<your-domain>           # outside     → 200 over TLS
curl -I http://<vps-ip>:8080            # outside     → must FAIL
```

That last one failing is the point: the app is only reachable through
CyberPanel, so TLS can never be bypassed.

---

## 5. One-time: GitHub secrets

Repo → Settings → Secrets and variables → Actions:

| Secret | Value |
|---|---|
| `VPS_HOST` | the VPS IP or hostname |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | the **private** half of the deploy key, whole file including header/footer |
| `VPS_PORT` | only if SSH is not on 22 |

`GITHUB_TOKEN` for GHCR is provided automatically.

---

## 6. Deploying

Push to `main`. To re-run without a commit: Actions → deploy → Run workflow.

The deploy step, in order, and why:

1. `docker compose pull app` — fetch the new image.
2. `docker compose run --rm --no-deps app php artisan migrate --force` — a
   one-off container on the **new** image, before anything is swapped. If a
   migration fails the deploy stops and the old release keeps serving.
3. `docker compose up -d --remove-orphans` — swap the containers.
4. `docker compose restart horizon scheduler` — PHP workers cache class
   definitions per process, so new job code needs a fresh worker.
5. Poll `/up` for 15s — a deploy that does not serve is a failed deploy, and
   the job prints the app logs before exiting non-zero.

`concurrency: cancel-in-progress: false` — a run cancelled between steps 2 and
3 would leave new schema against old code.

### Rolling back

```bash
cd /home/deploy/app
APP_IMAGE_TAG=<older-sha> docker compose up -d
```

Every build is tagged with its commit SHA. **Migrations are not rolled back** —
if the bad release migrated, restore the database too.

---

## 7. Operations

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f horizon
docker compose exec app php artisan about

# Database backup — do this before any risky deploy, and nightly off-box.
docker compose exec -T postgres pg_dump -U erp erp | gzip > backup-$(date +%F).sql.gz

# Uploaded logos and product photos live in the `storage` volume, not Postgres.
docker run --rm -v erp_storage:/s -v "$PWD":/b alpine tar czf /b/storage-$(date +%F).tar.gz -C /s .
```

Horizon dashboard: `https://<your-domain>/horizon`, restricted to the
addresses in `HORIZON_ALLOWED_EMAILS`. Empty means nobody.

---

## 8. Gotchas that have already bitten

- **PHP 8.4, not 8.3.** `composer.lock` pins `symfony/http-foundation v8.1.4`,
  which requires PHP ≥ 8.4.1. CI on 8.3 fails at `composer install` before a
  single test runs. `composer.json` still says `^8.3`, which is looser than the
  lock actually allows.
- **The asset build needs PHP.** Vite's Wayfinder plugin shells out to
  `php artisan wayfinder:generate --with-form`, and `resources/js/{actions,routes}`
  are gitignored. A Node-only build stage cannot produce them.
- **`ext-pcntl` is needed to *install*, not just to run.** Horizon declares it
  as a platform requirement, so `composer install` refuses without it — in the
  builder stage too, which never runs a worker.
- **`ext-redis` is not optional.** `REDIS_CLIENT=phpredis` means sessions,
  cache, queues and the invoice-number locks all go through the C extension.
  The Dockerfile asserts `pcntl`, `posix` and `redis` are present and fails the
  build if any is missing.
- **nginx cannot live in its own container here.** With php-fpm separate, nginx
  has no access to `public/`, so every asset 404s. They share the app container
  under supervisor.
- **`config:cache` belongs in the entrypoint, not the Dockerfile.** Baked at
  build time it freezes the CI runner's environment into the image.
- **The scheduler needs its own container.** Without it `horizon:snapshot`
  never runs and the Horizon metrics dashboard stays blank.
- **`storage` is a volume and mounts over the image's copy.** The entrypoint
  recreates `framework/{cache,sessions,views}` on every start, or the first
  boot on a new server cannot write a view cache.
- **Zero-downtime is not covered.** `up -d` has a few seconds of downtime.
  Fine for scheduled deploys; revisit with blue-green if that changes.
