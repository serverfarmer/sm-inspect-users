#!/bin/sh

if grep -q /opt/farm/ext/inspect-users/cron /etc/crontab; then
	sed -i -e "/\/opt\/farm\/ext\/inspect-users\/cron/d" /etc/crontab
fi
