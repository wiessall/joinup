FROM fpfis/httpd-php-dev:7.4

# USER_ID and GROUP_ID should not be used at the same time with DAEMON_USER and DAEMON_GROUP respectively.
ARG USER_ID
ARG GROUP_ID

RUN if [ ! -z ${USER_ID} ] ; then usermod -u ${USER_ID} www-data; fi;

RUN if [ -z $(grep -E "[^:]*:x:${GROUP_ID}:" /etc/group | awk -F: '{ print $1 }') ] ; then \
      groupmod -g ${GROUP_ID} www-data; \
    else \
      usermod -a -G $(grep -E "[^:]*:x:${GROUP_ID}:" /etc/group | awk -F: '{ print $1 }') www-data; \
  fi;
