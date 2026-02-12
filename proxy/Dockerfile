# Nginx + certbot for automatic first-time Let's Encrypt cert
FROM nginx:alpine
RUN apk add --no-cache certbot
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
