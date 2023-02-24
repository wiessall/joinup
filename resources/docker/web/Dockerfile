FROM fpfis/httpd-php-dev:8.1 as web

# USER_ID and GROUP_ID should not be used at the same time with DAEMON_USER and DAEMON_GROUP respectively.
ARG USER_ID
ARG GROUP_ID

# Check if there is a user with the given ID. If not, change the UID of the
# www-data user to the given ID. If there is a user with the given ID, show an
# error that prints this user's name and that the DAEMON_USER environment
# variable should be used instead.
RUN if [ ! -z "${USER_ID}" ] && [ -z $(grep -E "[^:]*:x:${USER_ID}:" /etc/passwd) ] ; then \
      usermod -u ${USER_ID} ${DAEMON_USER}; \
    elif [ ! -z "${USER_ID}" ] ; then \
      echo "User with ID ${USER_ID} already exists. Assign '$(grep -E "[^:]*:x:${USER_ID}:" /etc/passwd | awk -F: '{ print $1 }')' to the environment variable DAEMON_USER instead."; \
      exit 2; \
  fi;


# Check if there is a group with the ID ${GROUP_ID}. If there is no group,
# run groupmod to the group www-data to the given ID. If there is a group with
# the given ID, show an error that prints this group's name and that the
# DAEMON_GROUP environment variable should be used instead.
RUN if [ ! -z "${GROUP_ID}" ] && [ -z $(grep -E "[^\:]*\:${GROUP_ID}\:$" /etc/group) ] ; then \
      groupmod -g ${GROUP_ID} ${DAEMON_GROUP}; \
    elif [ ! -z "${GROUP_ID}" ] ; then \
      echo "Group with ID ${GROUP_ID} already exists. Assign '$(grep -E "[^\:]*\:x\:${GROUP_ID}\:$" /etc/group | awk -F: '{ print $1 }')' to the environment variable DAEMON_GROUP instead."; \
      exit 2; \
  fi;

# Create temporary directories used by tests so that they are not created on the
# fly by the tests and end up owned by root.
RUN mkdir -p /tmp/behat_gherkin_cache /tmp/behat_rerun_cache /tmp/drupal && \
    chown -R www-data:www-data /tmp/behat_gherkin_cache /tmp/behat_rerun_cache \
    /tmp/drupal
