MinePHPQuery
------------

This library is a utility to query Minecraft servers and get their status.
It is possible to fetch these status values:

* Current number of players playing
* Maximum number of players
* All names of currently playing players
* Plugins (if any)
* Port and current server IP
* Name of the server (MOTD)
* Name of the modification used (if any)
* Is the server online?

## Before starting
This library uses Minecraft's query protocol, which isn't activated by default.
You need to change the configuration if necessary.

```
enable-query=false
```

needs to be changed to

```
enable-query=true
query.port=25565
```

To get started, take a look at `example.php` for basic usage.
