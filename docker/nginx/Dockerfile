FROM nginx:1-alpine

WORKDIR /var/www

# Copy the whole repository (build context must be the repo root)
COPY . /var/www

# Copy global nginx configuration and virtual hosts
ADD docker/nginx/nginx.conf /etc/nginx/
COPY docker/nginx/conf.d /etc/nginx/conf.d

RUN apk add --no-cache bash gawk sed grep bc coreutils nano && \
    echo 'alias ll="ls -larth"' >> ~/.bashrc
