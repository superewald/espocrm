FROM webdevops/php-apache:8.0

ARG NODE_VERSION=16.16.0

# install nodejs, npm, grunt
RUN export NODE_VER=$(curl -s https://nodejs.org/en/ | grep -Po '\d*\.\d*\.\d* LTS' | head -n1 | cut -f1 -d' ') && \
    curl https://nodejs.org/dist/v$NODE_VER/node-v$NODE_VER-linux-x64.tar.xz    | tar --file=- --extract --xz --directory /usr/local/ --strip-components=1 && \
    npm install -g grunt-cli

# install wkhtmltopdf
RUN apt update && apt install -y wkhtmltopdf

# configure crontab
COPY ./espo-cron /etc/cron.d/espo-cron
RUN chmod 0644 /etc/cron.d/espo-cron && \
    crontab /etc/cron.d/espo-cron