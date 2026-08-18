tz: time zone to consider (example: Europe/Paris)

title/text: text displayed at upper left, none if empty or missing
title/image: image displayed at upper left, none if empty or missing
title/url: meaningful only if text or image filled - link

bookmarks/enabled: "yes" to enable bookmarks, "no" to disable (default: no)
bookmarks/max: maximum number of bookmarks

log: actions log
log/file: file to store log - no logging if empty or missing - filename can contain %Y,%m,%d,%H,%i,%s - can also be "php://stdout"
log/format: "text" or "json" (default)

auth: auth mechanism - possible values: "none", "ldap"

ldap: LDAP configuration - this section considered only if auth is set to "ldap"
ldap/uri: ldap server uri - syntax is ldap[s]://<server>[:port]
ldap/pattern: ldap query, metas are {login}
ldap/protocol_version: 2 or 3 (default)
ldap/baseDN: where to search
ldap/debug_level: 0-7 - debug is sent to stdout
ldap/start_tls: "yes" if TLS has to be manually started (usually used when switching to TLS on port 389), or "no" (default)
note: to setup ldap parameters, you can either provide /etc/ldap/ldap.conf or use ldap/options[]
more options are available with ldap.conf and this not PHP dependent, so this is the preferred way
ldap/options[].name: name of the option (see https://www.php.net/manual/en/function.ldap-set-option.php)
ldap/options[].name: value of the option

display: configure how directory content is displayed
display/columns[]: colums to display - array of: checkbox, Filename, Size, Perms, mtime, type, actions
display/pagesize: lines per page

csv: download directory content as a CSV file
csv/enabled: enabled if set to "yes", disabled if "no" or empty or missing
csv/columns[]: colums to export - array of: Filename, Size, Perms, mtime, type

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
volumes[i]/volumegroup: if set, this volume is a member of this group and will be displayed ion this group
volumes with no volumegroup belong to _default volumegroup

debug: for troubleshooting
debug/enabled: if set to "yes", display "Debug" link to provide internal infos

includes[]: list of conf files to include, can include patterns - included files can contain any entry - if duplicate entries are found among conf files, the last entry overwrites previous ones (so order of includes matters), except for volumes where entries are merged

fastmode: in fastmode, less info is delivered, reducing time to process entries (decreases user wait and can avoid timeouts)
fastmode/threshold: number of entries in directory to enter fastmode - default: 5000

upload: restrictions to apply on names of uploaded files - whitelist is preferred, if missing, blacklist will be used - if none, no restriction on file extensions
upload.blacklist: forbidden extensions (if present, all extensions allowed except these, not case sensitive)
upload.whitelist: allowed extensions (if present, only these extensions allowed, not case sensitive)

volumegroups: list of groups used to gather volumes on display
volumegroups[i].name: as used in volumes
volumegroups[i].label: for display
at least one group exist, the _default one

default: unused
