tz: time zone to consider
title/text: text displayed at upper left, none if empty or missing
title/image: image displayed at upper left, none if empty or missing
title/url: meaningful only if text or image filled - link
bookmarks/enabled: "yes" to enable bookmarks, "no" or missing to disable
bookmarks/max: maximum number of bookmarks
log/file: all actions are logged in this file - no logging if empty or missing
auth: auth mechanism - possible values: "none", "ldap"
ldap: this section considered if auth set to ldap
ldap/server: ldap server
ldap/port: ldap server port
ldap/pattern: ldap query, metas are {login}
ldap/protocol_version: 2 or 3
display/columns: colums to display - array of names - possible values are : checkbox, Filename, Size, Perms, mtime, type, actions
display/pagesize: lignes per page
csv/enabled: if set to "yes", enable dir content download as CSV file, disabled of "no" or empty or missing
csv/columns: colums to export - possible values are the same as diplay columns except checkbox and actions (meaning less here)
volumes: array - items to select in left menu
volumes[i]/name: label to display in menu
volumes[i]/path: path to use to access files
volumes[i]/download: if set to "yes", enable file download from this volume, disabled if set to no or empty or missing
volumes[i]/delete: if set to "yes", enable file deletion from this volume, disabled if set to no or empty or missing
volumes[i]/upload: if set to "yes", enable file upload from this volume, disabled if set to no or empty or missing
volumes[i]/showhiddenfiles: if set to "yes", show hidden files (starting with a dot), disabled if set to no or empty or missing
volumes[i]/encoding: charset of the volume, as seen by filebrowser (depends on native encoding and mount options) - possible values: iso-8859-1, utf-8
default: unused
