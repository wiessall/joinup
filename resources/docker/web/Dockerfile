FROM fpfis/httpd-php-dev:8.1

# USER_ID and GROUP_ID should not be used at the same time with DAEMON_USER and DAEMON_GROUP respectively.
ARG USER_ID
ARG GROUP_ID

RUN if [ ! -z "${USER_ID}" ] && [ -z "$(grep -E \"[^:]*:x:${USER_ID}:\" /etc/passwd)" ] ; then \
      usermod -u ${USER_ID} www-data; \
    elif [ ! -z "${USER_ID}" ] ; then \
      echo "User with ID ${USER_ID} already exists. Assign '$(grep -E "[^:]*:x:${USER_ID}:" /etc/passwd | awk -F: '{ print $1 }')' to the environment variable DAEMON_USER instead."; \
      exit 2; \
  fi;

RUN if [ ! -z "${GROUP_ID}" ] && [ -z "$(grep -E \"[^:]*:x:${GROUP_ID}:\" /etc/group)" ] ; then \
      groupmod -g ${GROUP_ID} www-data; \
    elif [ ! -z "${GROUP_ID}" ] ; then \
      echo "Group with ID ${GROUP_ID} already exists. Assign '$(grep -E "[^:]*:x:${GROUP_ID}:" /etc/group | awk -F: '{ print $1 }')' to the environment variable DAEMON_GROUP instead."; \
      exit 2; \
  fi;
