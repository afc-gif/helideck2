FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
ARG VITE_API_BASE_URL
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm run build

FROM nginx:stable-alpine
RUN apk add --no-cache gettext
COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY nginx/entrypoint.sh /usr/local/bin/artsci-nginx-entrypoint
COPY --from=build /app/dist /usr/share/nginx/html
RUN chmod +x /usr/local/bin/artsci-nginx-entrypoint \
    && printf 'ok\n' > /usr/share/nginx/html/healthz
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/artsci-nginx-entrypoint"]
