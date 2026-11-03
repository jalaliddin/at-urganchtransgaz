# syntax=docker/dockerfile:1.7
# AT.urtg.uz — frontend (React SPA) image
# Stages: dev (Vite dev server, bind-mounted code) | build → prod (static files, unprivileged nginx)

FROM node:22-bookworm-slim AS dev
WORKDIR /app
ENV CI=true
EXPOSE 5173
HEALTHCHECK --interval=15s --timeout=5s --start-period=120s --retries=5 \
    CMD node -e "fetch('http://127.0.0.1:5173/').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["sh", "-c", "[ -d node_modules/.bin ] || npm ci; exec npm run dev"]

FROM node:22-bookworm-slim AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM nginxinc/nginx-unprivileged:1.27-alpine AS prod
COPY docker/frontend/spa.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=5 \
    CMD wget -qO- http://127.0.0.1:8080/index.html >/dev/null || exit 1
