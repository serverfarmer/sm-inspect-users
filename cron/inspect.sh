#!/bin/bash
. /opt/farm/scripts/init
. /opt/farm/scripts/functions.custom

out=/var/cache/farm
admin=`primary_admin_account`

for server in `/opt/farm/ext/inspect-users/utils/get-hosts.sh`; do

	if [[ $server =~ ^[a-z0-9.-]+$ ]]; then
		server="$server::"
	elif [[ $server =~ ^[a-z0-9.-]+[:][0-9]+$ ]]; then
		server="$server:"
	fi

	host=$(echo $server |cut -d: -f1)
	port=$(echo $server |cut -d: -f2)

	if [ "$port" = "" ]; then
		port=22
	fi

	sshkey=`/opt/farm/ext/keys/get-ssh-management-key.sh $host`

	/opt/farm/ext/inspect-users/internal/users.php $host root@$host $port root $sshkey $admin \
		|/opt/farm/ext/versioning/save.sh daily 20 $out users-$host.script

done

/opt/farm/ext/inspect-users/internal/users.php "" root@$HOST "" "" "" $admin \
	|/opt/farm/ext/versioning/save.sh daily 20 $out users-$HOST.script
