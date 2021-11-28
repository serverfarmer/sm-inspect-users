#!/bin/sh

/opt/farm/scripts/setup/extension.sh sf-versioning
/opt/farm/scripts/setup/extension.sh sm-farm-manager
/opt/farm/scripts/setup/extension.sh sf-php

echo "setting up base directories and files"
mkdir -p ~/.serverfarmer/inspection
chmod 0710 ~/.serverfarmer ~/.serverfarmer/inspection
chown root:www-data ~/.serverfarmer ~/.serverfarmer/inspection

if ! grep -q /opt/farm/mgr/inspect-users/cron /etc/crontab; then
	echo "48 6 * * * root /opt/farm/mgr/inspect-users/cron/inspect.sh" >>/etc/crontab
fi
