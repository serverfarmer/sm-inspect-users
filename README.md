## Overview

`sm-inspect-users` management extension analyzes key authentication-related files on either local, or remote server:

- `/etc/group`
- `/etc/passwd`
- `/etc/shadow`
- `~/.ssh/authorized_keys` (for all users)

Next, for each server it creates a script that can recreate existing:

 - groups
 - users
 - passwords (in encrypted form)
 - home directories (commands that rsync them fron another server)
 - ssh keys
 - Samba passwords

on a fresh system.

## Advantages over generic backup/export tools?

Traditional, generic tools that export user credentials, generate complicated commands, that duplicate various default options, eg.

`useradd -m -d /home/steve steve`

This example may look ok at the first sight, but if you manage X servers, each with different set of users and their specific options, it becomes a problem.

Therefore, what this extension really does, is:

- analyze the above files
- compute, which user options follow defaults
- generate optimized scripts, with minimal set of options
