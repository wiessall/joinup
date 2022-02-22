FROM fpfis/httpd-php-dev:7.4

ARG USER_ID=1000
ARG GROUP_ID=1000
RUN usermod -u ${USER_ID} www-data && \
    groupmod -g ${GROUP_ID} www-data
