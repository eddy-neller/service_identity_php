# Varnish and HTTP cache

This document summarizes the Varnish setup and API Platform cache configuration for `en_shop_api`.
It includes the current behavior and the planned improvements.

## 1) Docker + Varnish container

Paths:
- `docker/varnish/Dockerfile`
- `docker/varnish/conf/default.vcl`
- `docker-compose.yaml`
- `docker-compose.override.yaml`

What it does:
- Builds a Varnish container from `varnish:stable`.
- Loads VCL from `docker/varnish/conf/default.vcl`.
- Exposes Varnish on port `20901` in dev.
- Uses the `nginx` service (port `80`) as backend; nginx forwards to the `app` container (php-fpm).

Notes:
- The whole stack runs in Docker: `varnish -> nginx -> app`. Nothing is served from the host anymore.
- API Platform sends its `BAN` requests to `VARNISH_URL` (`http://varnish`), resolved on the compose network.

## 2) VCL rules (default.vcl)

File: `docker/varnish/conf/default.vcl`

Key rules:
- `backend default`: points to `nginx:80` (the reverse proxy in front of php-fpm).
- `BAN` support: accepts BAN requests with `ApiPlatform-Ban-Regex` and invalidates by `Cache-Tags`.
- `pass` on auth/cookies:
  - `if (req.http.Authorization || req.http.Cookie) { return (pass); }`
  - Ensures private endpoints are not cached.
- `grace`: serves stale content temporarily if the backend is down.
- `healthz`: responds to `GET /healthz` without hitting the backend.

## 3) API Platform cache headers

Files:
- `presentation/src/Shop/ApiResource/Catalog/ProductResource.php`
- `presentation/src/Shop/ApiResource/Catalog/CategoryResource.php`

What it does:
- Adds `Cache-Control` to catalog GET endpoints with long TTLs:
  - `max-age=21600` (6h for clients)
  - `s-maxage=86400` (24h for shared cache: Varnish/CDN)

## 4) Cache invalidation (BAN by tags)

File: `config/packages/api_platform.yaml`

Config:
```yaml
api_platform:
    http_cache:
        invalidation:
            enabled: true
            urls: ["%env(VARNISH_URL)%"]
```

What it does:
- API Platform adds `Cache-Tags` to cacheable responses.
- On writes (POST/PATCH/DELETE), API Platform sends BAN requests to Varnish.

## 5) ETag and Last-Modified

Files:
- `config/packages/api_platform.yaml` (ETag default enabled)
- `infrastructure/src/EventListener/LastModifiedListener.php`

What it does:
- ETag is enabled by API Platform (hash of response content).
- `LastModifiedListener` sets `Last-Modified` based on `updatedAt` or `createdAt` in the response data.
- Enables 304 responses when the resource has not changed.

## 6) Suggested future improvements

1) Scoped invalidation:
   - Decorate the purge service to only BAN `/shop/*` resources.
2) Monitoring:
   - Track Varnish hit/miss ratio to validate cache strategy.
