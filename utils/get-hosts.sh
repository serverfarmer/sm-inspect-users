#!/bin/sh

path=/etc/local/.farm
cat $path/virtual.hosts $path/physical.hosts $path/cloud.hosts $path/container.hosts $path/lxc.hosts $path/workstation.hosts $path/problematic.hosts |grep -v "^#"
