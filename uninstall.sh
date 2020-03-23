#!/bin/sh

if grep -q /opt/farm/mgr/inspect-users/cron /etc/crontab; then
	sed -i -e "/\/opt\/farm\/mgr\/inspect-users\/cron/d" /etc/crontab
fi
