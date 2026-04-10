# Build stage
FROM node:20-alpine AS build

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install all dependencies including devDependencies (needed for vite build)
RUN npm ci --include=dev

# Copy application files
COPY . .

# Build for production
RUN npm run build

# Ensure Vite emitted assets (fails if build was cached with no output)
RUN test -d /app/dist/assets && test -n "$(ls -A /app/dist/assets 2>/dev/null)" || (echo "Build did not produce dist/assets – rebuild with: docker compose build client --no-cache" && exit 1)

# Dev stage: Node + app for "npm run dev" in docker-compose
FROM node:20-alpine AS dev
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
EXPOSE 80
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "80"]

# Production stage (used by docker-compose; serves built SPA)
FROM nginx:alpine

COPY --from=build /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Expose port 80
EXPOSE 80

# Start nginx
CMD ["nginx", "-g", "daemon off;"]
