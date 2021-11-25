#!/usr/bin/php
<?php
// Analyze /etc/passwd and /etc/group files, and print out set of commands
// creating all found users and groups, along with their directories and
// synchronizing data.
// Tomasz Klim, Aug 2014, Feb 2016, Oct 2018


$mingid = 200;
$minuid = 200;
$maxuid = 65500;

$required_groups = array();
$ignored_users = array("newrelic", "ubuntu", "libvirt-qemu");
$ignored_passwords = array("root");
$ignored_supplemental_system_groups = array("audio", "bluetooth", "cdrom", "dialout", "dip", "floppy", "games", "lpadmin", "netdev", "video");

if ($argc < 6)
	die("usage: php users.php <hostname> <target> <port> <user> <key> [ignore-user(s)]\n");

$hostname = $argv[1];  // local mode if empty
$target = $argv[2];
$port = ( is_numeric($argv[3]) ? intval($argv[3]) : 22 );
$user = $argv[4];
$sshkey = $argv[5];

if (!empty($argv[6])) {
	$list = explode(",", $argv[6]);
	foreach ($list as $entry) {
		$ignored_users[] = $entry;
		$ignored_passwords[] = $entry;
	}
}

// end of configuration, now functions

function execute($command, $hostname, $port, $user, $sshkey) {
	if (!empty($hostname))
		$command = "ssh -i $sshkey -p $port -o StrictHostKeyChecking=no $user@$hostname \"$command\"";
	return shell_exec($command);
}

// end of functions, now gathering data

$uname = trim(execute("uname -a", $hostname, $port, $user, $sshkey));

$data = execute("cat /etc/group", $hostname, $port, $user, $sshkey);
$lines = explode("\n", $data);
$groups = array();

foreach ($lines as $line) {
	if (empty($line)) continue;
	$fields = explode(":", $line);
	$groups[$fields[2]] = array(
		"group"        => $fields[0],
		"gid"          => $fields[2],
		"supplemental" => empty($fields[3]) ? array() : explode(",", $fields[3]),
	);
}

$data = execute("cat /etc/passwd", $hostname, $port, $user, $sshkey);
$lines = explode("\n", $data);
$users = array();
$keys = array();

foreach ($lines as $line) {
	if (empty($line)) continue;
	$fields = explode(":", $line);
	$login = $fields[0];
	$uid = $fields[2];
	$gid = $fields[3];
	if ($uid >= $minuid && $uid <= $maxuid && in_array($login, $ignored_users, true) === false) {
		$users[$login] = array(
			"login" => $login,
			"group" => $groups[$gid]["group"],
			"uid"   => $uid,
			"gid"   => $gid,
			"gecos" => $fields[4],
			"home"  => $fields[5],
			"shell" => $fields[6],
		);
		if ($uid == $gid && $fields[0] == $groups[$gid]["group"])
			$users[$login]["usergroup"] = true;
		else if ($gid >= $mingid)
			$required_groups[$gid] = $groups[$gid]["group"];
	}
}

foreach ($users as $login => $entry) {
	$home = $entry["home"];
	$data = execute("cat $home/.ssh/authorized_keys 2>/dev/null", $hostname, $port, $user, $sshkey);
	$lines = explode("\n", $data);

	foreach ($lines as $line) {
		$line = trim($line);
		if (!empty($line) && substr($line, 0, 3) == "ssh")
			$keys[$login][] = $line;
	}
}

$data = execute("cat /etc/shadow", $hostname, $port, $user, $sshkey);
$lines = explode("\n", $data);
$shadow = array();

foreach ($lines as $line) {
	if (empty($line)) continue;
	$fields = explode(":", $line);
	$login = $fields[0];
	$password = $fields[1];
	$changed = !is_numeric($fields[2]) ? $fields[2] : date("Y-m-d", $fields[2] * 86400);
	if ($password != "" && $password != "*" && $password != "!" && in_array($login, $ignored_passwords, true) === false)
		$shadow[$login] = array($password, $changed);
}

// end of gathering data, now print out the commands

$date = date("Y-m-d");
echo "# $uname\n";
echo "# generated at $date\n#\n\n";

foreach ($required_groups as $gid => $group)
	echo "groupadd -g $gid $group\n";

if (!empty($required_groups))
	echo "\n";

foreach ($users as $login => $data) {
	$uidgid = $data["uid"];

	if (empty($data["shell"]))
		$data["shell"] = "/bin/false";

	$cmd = "useradd -s " . $data["shell"];

	if (empty($data["usergroup"]))
		$cmd .= " -g " . $data["group"];
	else {
		echo "groupadd -g $uidgid $login\n";
		$cmd .= " -g $login";
	}

	$cmd .= " -u $uidgid";

	if (!empty($data["gecos"]))
		$cmd .= " -c \"" . $data["gecos"] . "\"";

	if (strpos($data["home"], "/srv/") === 0 || strpos($data["home"], "/data/") === 0 || strpos($data["home"], "/var/") === 0 || strpos($data["home"], "/opt/") === 0)
		$cmd .= " -m -d " . $data["home"];
	else if (strpos($data["home"], "/home/") === 0)
		$cmd .= " -m";
	else
		$cmd .= " -M";

	$cmd .= " $login";
	echo "$cmd\n";
}

echo "\n";
foreach ($groups as $gid => $data) {
	if (!empty($data["supplemental"])) {
		$group = $data["group"];
		foreach ($data["supplemental"] as $login)
			if (isset($users[$login]) && in_array($group, $ignored_supplemental_system_groups, true) == false)
				echo "usermod -G $group -a $login\n";
	}
}

echo "\n";
foreach ($users as $login => $data)
	if (strpos($login, "smb-") === 0)
		echo "smbpasswd -a $login\n";

echo "\n";
foreach ($users as $login => $data)
	if (strpos($login, "smb-") === false && strpos($login, "rsync-") === false && strpos($login, "sshfs-") === false && !isset($shadow[$login]) && $login != "motion")
		echo "passwd $login\n";

echo "\n";
foreach ($shadow as $login => $password)
	echo "shadow $login $password[0] (changed $password[1])\n";

echo "\n";
foreach ($keys as $login => $lines)
	foreach ($lines as $line)
		echo "key $login $line\n";

echo "\n";
foreach ($users as $login => $data) {
	if (strpos($data["home"], "/srv/") === 0 || strpos($data["home"], "/data/") === 0 || strpos($data["home"], "/var/") === 0 || strpos($data["home"], "/opt/") === 0) {
		$home = $data["home"];
		$parent = dirname($home);
		echo "rsync -e \"ssh -i ~/.serverfarmer/ssh/key-$target\" -av $target:$home $parent\n";
	}
}
