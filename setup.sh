#!/bin/sh

/opt/farm/scripts/setup/extension.sh sf-versioning
/opt/farm/scripts/setup/extension.sh sf-farm-manager
/opt/farm/scripts/setup/extension.sh sf-php

echo "setting up base directories and files"
mkdir -p   /var/cache/farm
chmod 0710 /var/cache/farm
chown root:www-data /var/cache/farm

if ! grep -q /opt/farm/ext/inspect-users/cron /etc/crontab; then
	echo "48 6 * * * root /opt/farm/ext/inspect-users/cron/inspect.sh" >>/etc/crontab
fi
