#!/bin/sh
# Auto-generate a self-signed SSL certificate if none is present.
# Runs automatically via /docker-entrypoint.d/ before nginx starts.
#
# The names the certificate must cover come from SSL_CERT_DOMAINS (comma
# separated, first one becomes the CN). It defaults to "localhost" so the
# original behaviour is preserved wherever the variable is not set. Local dev
# reaches the site over a custom DNS name (meyers.local), and a cert issued
# only for CN=localhost makes every browser refuse that hostname outright
# rather than offering the usual self-signed warning — so the name has to be a
# real SAN entry, not just the CN.
#
# The cert files are a host bind mount, so a stale certificate survives a
# container rebuild: to re-issue after changing SSL_CERT_DOMAINS, delete
# deployments/docker/proxy/ssl/localhost.{crt,key} and restart the proxy.
#
# nginx:alpine ships NO openssl binary (only the library), so this script was
# previously a no-op in this image and the certificate had to come from the
# repository. Install it on demand so a fresh clone self-heals instead of
# crash-looping on "cannot load certificate".

set -e

SSL_CERT_DOMAINS="${SSL_CERT_DOMAINS:-localhost}"

mkdir -p /etc/nginx/ssl

if [ ! -f /etc/nginx/ssl/localhost.crt ]; then
    # First entry is the CN; every entry (plus the loopback IPs) becomes a SAN.
    CN="$(echo "$SSL_CERT_DOMAINS" | cut -d, -f1 | tr -d ' ')"
    SAN=""
    for d in $(echo "$SSL_CERT_DOMAINS" | tr ',' ' '); do
        [ -n "$d" ] || continue
        SAN="${SAN}DNS:${d},"
    done
    SAN="${SAN}IP:127.0.0.1"

    if ! command -v openssl >/dev/null 2>&1; then
        echo "[proxy] openssl missing from this image, installing..."
        apk add --no-cache openssl >/dev/null 2>&1 || {
            echo "[proxy] ERROR: could not install openssl; cannot generate certificate." >&2
            exit 1
        }
    fi

    echo "[proxy] Generating self-signed SSL certificate for ${SAN}..."
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/localhost.key \
        -out    /etc/nginx/ssl/localhost.crt \
        -subj   "/CN=${CN}" \
        -addext "subjectAltName=${SAN}"
    echo "[proxy] Certificate generated."
fi

# Ensure nginx (running as a non-root user) can read the certs
chmod 644 /etc/nginx/ssl/localhost.crt /etc/nginx/ssl/localhost.key
