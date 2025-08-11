tz: time zone to consider
title/text: text displayed at upper left, none if empty or missing
title/image: image displayed at upper left, none if empty or missing
title/url: meaningful only if text or image filled - link
bookmarks/enabled: "yes" to enable bookmarks, "no" or missing to disable
bookmarks/max: maximum number of bookmarks
log/file: all actions are logged in this file - no logging if empty or missing - filename can contain %Y,%m,%d,%H,%i,%s - can also be "php://stdout"
log/format: "text" or "json" (default)
auth: auth mechanism - possible values: "none", "ldap"
ldap: this section considered if auth set to ldap
ldap/uri: ldap server uri - syntax is ldap[s]://<server>[:port]
ldap/pattern: ldap query, metas are {login}
ldap/protocol_version: 2 or 3
display/columns: colums to display - array of names - possible values are : checkbox, Filename, Size, Perms, mtime, type, actions
display/pagesize: lignes per page
csv/enabled: if set to "yes", enable dir content download as CSV file, disabled of "no" or empty or missing
csv/columns: colums to export - possible values are the same as display columns except checkbox and actions (meaning less here)
volumes: array - items to select in left menu
volumes[i]/name: label to display in menu
volumes[i]/path: path to use to access files
volumes[i]/download: if set to "yes", enable file download from this volume, disabled if set to no or empty or missing
volumes[i]/delete: if set to "yes", enable file deletion from this volume, disabled if set to no or empty or missing
volumes[i]/upload: if set to "yes", enable file upload from this volume, disabled if set to no or empty or missing
volumes[i]/showhiddenfiles: if set to "yes", show hidden files (starting with a dot), disabled if set to no or empty or missing
volumes[i]/showlinks: if set to "no", hide links, links are visible if set to yes or empty or missing
volumes[i]/encoding: charset of the volume, as seen by filebrowser (depends on native encoding and mount options) - possible values: "iso-8859-1", "utf-8"
volumes[i]/groups: array of groups - only if auth set to ldap - restrict this volume to users belonging to one the listed groups
default: unused
debug: for troubleshooting
debug/enabled: if set to "yes", display "Debug" link to provide internal infos
