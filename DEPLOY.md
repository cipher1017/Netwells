# Deployment — hotspot.netwells.co.ke

Dockerized Laravel app (FrankenPHP) behind the host nginx + certbot, matching the
other apps on this server (e.g. postpilot.netwells.co.ke).

## Layout
- App container: `hotspot-app` (FrankenPHP serving `public/` on :80), published to
  `127.0.0.1:8160`.
- `hotspot-scheduler`: runs `php artisan schedule:work` (subscription expiry).
- SQLite DB on the `hotspot-data` named volume at `/data/database.sqlite`.
- Host nginx vhost proxies `hotspot.netwells.co.ke` -> `127.0.0.1:8160`, TLS via certbot.

## First deploy
```sh
git clone https://github.com/cipher1017/Netwells.git /srv/hotspot
cd /srv/hotspot
cp /path/to/.env .env         # production env (not in git)
#  set APP_ENV=production, APP_DEBUG=false,
#      APP_URL=https://hotspot.netwells.co.ke, DB_DATABASE=/data/database.sqlite
docker compose up -d --build
docker compose exec app sh -lc 'touch /data/database.sqlite && php artisan migrate --seed --force'

ln -s /srv/hotspot/deploy/hotspot.netwells.co.ke.conf /etc/nginx/sites-available/hotspot.netwells.co.ke
ln -s /etc/nginx/sites-available/hotspot.netwells.co.ke /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d hotspot.netwells.co.ke --non-interactive --agree-tos -m admin@netwells.co.ke
```

## Update
```sh
cd /srv/hotspot && git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

## Note
The RouterOS provisioning (grant/revoke) needs network reach to the MikroTik API
(192.168.88.1:8728), which a public VPS does not have. Expose the router via a
public endpoint / VPN before the paid flow can provision from here.
