#!/usr/bin/env bash
#
# One-time VPS provisioning. Safe to re-run: every step checks before acting.
#
#   sudo bash provision.sh <deploy-public-key>
#
# Leaves behind:
#   - Docker + compose plugin
#   - a `deploy` user in the docker group, with the CI key authorised
#   - /home/deploy/app/{docker-compose.yml,.env}
#   - a firewall that allows only SSH, HTTP, HTTPS and the CyberPanel port
#
# It does NOT touch CyberPanel, OpenLiteSpeed, or ports 80/443 — those belong to
# CyberPanel, and the app is reached through it over 127.0.0.1:8080.

set -euo pipefail

DEPLOY_USER="deploy"
APP_DIR="/home/${DEPLOY_USER}/app"
DEPLOY_KEY="${1:-}"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m    ! %s\033[0m\n' "$1"; }

if [[ $EUID -ne 0 ]]; then
    echo "Run this as root." >&2
    exit 1
fi

log "Docker"
if command -v docker >/dev/null 2>&1; then
    echo "    already installed: $(docker --version)"
else
    curl -fsSL https://get.docker.com | sh
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "    installing the compose plugin"
    apt-get update -qq && apt-get install -y -qq docker-compose-plugin
fi

systemctl enable --now docker

log "Deploy user"
if id -u "${DEPLOY_USER}" >/dev/null 2>&1; then
    echo "    ${DEPLOY_USER} already exists"
else
    adduser --disabled-password --gecos "" "${DEPLOY_USER}"
fi

# Membership in `docker` is effectively root on this box. That is the trade for
# letting CI deploy without sudo; the account has no password and only accepts
# the one key below.
usermod -aG docker "${DEPLOY_USER}"

if [[ -n "${DEPLOY_KEY}" ]]; then
    install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
    touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"

    if grep -qF "${DEPLOY_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"; then
        echo "    deploy key already authorised"
    else
        echo "${DEPLOY_KEY}" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
        echo "    deploy key added"
    fi

    chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
    chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
else
    warn "No deploy key given — GitHub Actions will not be able to connect yet."
fi

log "Application directory"
install -d -m 755 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${APP_DIR}"
echo "    ${APP_DIR}"

log "Firewall"
if command -v ufw >/dev/null 2>&1; then
    ufw allow 22/tcp    >/dev/null
    ufw allow 80/tcp    >/dev/null
    ufw allow 443/tcp   >/dev/null
    # CyberPanel's own admin interface.
    ufw allow 8090/tcp  >/dev/null
    # 8080 is deliberately absent: the app must only be reachable through
    # CyberPanel, never directly from the internet.
    ufw --force enable  >/dev/null
    echo "    22, 80, 443, 8090 open; everything else closed"
else
    warn "ufw not installed — skipping. Check the provider firewall instead."
fi

log "Done"
cat <<EOF

    Next, as the deploy user:

      cd ${APP_DIR}
      # place docker-compose.yml and .env here, then
      docker compose up -d
      curl -I http://127.0.0.1:8080/up

    Still to do by hand:
      - CyberPanel: proxy app.galaxyconsumer.com to 127.0.0.1:8080, issue SSL
      - Change the root password (it was shared in plain text)
      - Consider: PermitRootLogin prohibit-password, PasswordAuthentication no

EOF
