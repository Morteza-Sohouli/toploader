# Nginx + certbot for automatic first-time Let's Encrypt cert
FROM nginx:alpine
# Update CA certs first (helps with "TLS: server certificate not trusted" behind proxies/regional filters)
RUN apk add --no-cache ca-certificates && update-ca-certificates
# Optional: use a different mirror if dl-cdn is blocked in your region (uncomment and adjust if needed):
# RUN sed -i 's|https://dl-cdn.alpinelinux.org|https://dl-3.alpinelinux.org|g' /etc/apk/repositories
RUN apk add --no-cache certbot
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
