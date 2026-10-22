# ggsysinfo

System information for Exponential administrators and developers.

ggsysinfo adds a **System Information** tab to the Exponential administration interface. From one place you can
see how PHP is configured, inspect and clear the opcode cache, look through cache, storage and log files, count the
content in the database, run status checks (also as JSON for monitoring tools), run quality checks on INI, PHP and
template files, and browse every module, view, fetch function, policy function, operation, template operator and
workflow event type the installation knows about.

It is meant for:

- **site administrators** who want to know whether an installation is healthy without logging in to the server;
- **developers** who need a quick reference of the modules, views, fetch functions and operators available;
- **operations teams** who want a status URL to poll from Nagios, Zabbix, OpenNMS or a similar tool;
- **consultants and support staff** who audit an installation (see the command line report below).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Permissions](#permissions)
- [Command line scripts](#command-line-scripts)
- [Upgrading](#upgrading)
- [Known limits](#known-limits)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)
- [Credits and history](#credits-and-history)

## Requirements

- Exponential 6.x (the legacy kernel)
- PHP 8.0 or later
- Optional: the PHP GD extension and the Zeta Components Graph library (`zetacomponents/graph`) for the churn
  graphs. Without them the graph pages show a message instead of a chart.
- Optional: the `ezgeshi` extension (1.3 or later) for links to the source code of views and fetch functions.

## Installation

### With Composer

The package is `se7enxweb/ggsysinfo`. This maintained copy lives at
https://github.com/se7enxweb/ggsysinfo; add it as a repository in your project's `composer.json` so Composer takes
the maintained releases:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/se7enxweb/ggsysinfo" }
    ]
}
```

Then, in the root of your installation:

```bash
composer require se7enxweb/ggsysinfo:^0.10.3
```

The legacy extension installer puts the files in `extension/ggsysinfo`.

### Package name

Before 0.10.3 this extension was published as `gggeek/ggsysinfo`, the name of Gaetano Giunta's original package.
Since 0.10.3 the maintained releases are published as `se7enxweb/ggsysinfo`, which replaces `gggeek/ggsysinfo`, so
the two are never installed side by side. To switch, run
`composer remove gggeek/ggsysinfo && composer require se7enxweb/ggsysinfo`, or change the line in the `require`
section of your `composer.json` to `"se7enxweb/ggsysinfo": "^0.10.3"` and run `composer update se7enxweb/ggsysinfo`.

### By hand

1. Download a release from https://github.com/se7enxweb/ggsysinfo/releases (or `git clone` the repository) and
   copy it to `extension/ggsysinfo` in your installation. The directory must be called `ggsysinfo`.
2. Activate the extension in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=ggsysinfo
   ```

3. Regenerate the autoload array and clear the caches, from the root of the installation:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-all
   ```

4. Make sure the web server can serve the image files written to `var/<site>/cache/sysinfo`: the storage and log
   churn graphs are stored there.

Log in to the administration interface: the **System Information** tab is now in the top menu. In the admin4 design
it shows the `info-circle` icon.

## Configuration

All settings live in `settings/sysinfo.ini` of the extension. Override them in
`settings/override/sysinfo.ini.append.php` (or in a siteaccess) rather than editing the extension's file.

### [GeneralSettings]

Links to documentation and source code, used by the developer pages (fetch functions, operations, modules).

```ini
[GeneralSettings]
# Base URLs (or local directories) of the reference manual
DocRoot[objects]=https://doc.ez.no/eZ-Publish/Technical-manual/4.x/Reference/Objects
DocRoot[fetches]=https://doc.ez.no/eZ-Publish/Technical-manual/4.x/Reference/Modules
DocRoot[operators]=https://doc.ez.no/eZ-Publish/Technical-manual/4.x/Reference/Template-operators
# Leave empty for the online manual, '.html' for a local copy
PageSuffix=
# Browsable source code of the kernel
DocRoot[sourcecode]=https://github.com/ezsystems/ezpublish-legacy/tree/
# Kernel versions without a tag in that repository
MissingSourceVersions[]
MissingSourceVersions[]=4.5.0
```

### [SystemStatus]

The status page can probe the web and the mail server. The mail test is off until you set an address, and you can
empty `WebBeacons[]` to switch the web test off, since both contact external resources.

```ini
[SystemStatus]
# Pages fetched to test that the server can reach the web
WebBeacons[]
WebBeacons[]=https://www.example.com
# Address a test message is mailed to (empty: no mail test)
MailReceiver=webmaster@example.com
```

### [ModuleSettings]

The groups of pages in the left menu, each mapped to a class that returns its views, and the status tests. Add your
own group or test class here.

```ini
[ModuleSettings]
GroupsList[Index]=ezSysinfoIndexViewGroup
GroupsList[PHP]=ezSysinfoPHPViewGroup
GroupsList[eZPublish]=ezSysinfoeZPublishViewGroup
GroupsList[Development]=ezSysinfoDevelopmentViewGroup
GroupsList[QA]=ezSysinfoQAViewGroup
GroupsList[Reports]=ezSysinfoReportsViewGroup
# Classes implementing ezSysinfoTest, run by the system status page
TestsList[]=sysinfoTests
```

### [GraphSettings]

```ini
[GraphSettings]
Width=600
Height=400
# Longest time span shown, in seconds (0: no limit)
MaxTimespan=0
```

### [ReportsSettings]

The classes the command line report can run (see [Command line scripts](#command-line-scripts)).

```ini
[ReportsSettings]
ReportGenerators[]=sysInfoTools
ReportGenerators[]=systemChecker
ReportGenerators[]=contentStatsGatherer
ReportGenerators[]=ezPoliciesReport
ReportGenerators[]=ezContentClassesReport
ReportGenerators[]=ezWorkflowsReport
ReportGenerators[]=ezIniReport
```

### [ClusterSettings]

For installations with several web servers. With `ClusterNodes[]` set, the PHP pages (phpinfo, opcode caches,
MySQL, realpath cache), the status page and the cache and storage pages show the data of every node.

```ini
[ClusterSettings]
# Name => URL of the vhost + siteaccess root of each node
ClusterNodes[]
ClusterNodes[web1]=https://web1.example.com/admin
ClusterNodes[web2]=https://web2.example.com/admin
# 'iframe': the browser loads each node; 'server2server': this node asks the others
DataRetrieval=server2server
# Shared secret for the requests between nodes: change it
TokenSecret=ChangeMePlease
# Lifetime of a token, in seconds
TokenTTL=300
# Optional extra HTTP headers sent with server2server requests (header name => value)
#CustomHeaders[Host]=admin.example.com
```

Set `TokenSecret` to a long random value of your own, the same on every node, when you use this.

### [SCMSettings]

When the installation is a git checkout (or has `revision.txt`, `status.txt` and `tag.txt` files), an **SCM Info**
page shows the current revision.

```ini
[SCMSettings]
RepoLink=
RepoName=
# 'git' or 'file'
RepoType=git
# Directory (or list of directories) to check; empty: the installation root
RepoDir=
```

## Usage

Open the **System Information** tab. The left menu lists the pages by group; a page that does not apply to your
setup (for example an opcode cache that is not installed) is shown grayed out.

| Group | Pages (view name) |
|---|---|
| PHP | phpinfo() (`php`), OPcache (`acceleratorplus`), APC (`apc`), eAccelerator (`eaccelerator`), WinCache (`wincache`), XCache (`xcache`), MySQL connection stats (`mysqli`), Realpath cache (`realpath`) |
| eZPublish | System status (`systemstatus`), System check (`systemcheck`), Cache stats (`cachestats`, `clustercachestats`), Cache search (`cachesearch`), Storage stats (`storagestats`, `clusterstoragestats`), Storage churn (`storagechurn`), Content stats (`contentstats`), Log stats (`logstats`), Log churn (`logchurn`), Log view (`logview`), Custom log view (`customlogview`), SCM Info (`sourcerevision`) |
| Development | Module list (`modulelist`), View list (`viewlist`), Policy functions (`policylist`), Operations (`operationlist`), Fetch functions (`fetchlist`), Template operators (`operatorlist`), Workflow event types (`eventtypelist`) |
| QA | Security checks (`secinfo`), Database problems (`databaseqa`), INI files problems (`inifilesqa`), INI values problems (`inisettingsqa`), PHP files problems (`phpfilesqa`), Template files problems (`tplfilesqa`) |
| Reports | Content classes (`classesreport`), INI settings (`inireport`), Roles and policies (`policiesreport`), Workflows and triggers (`workflowsreport`) |

Every page is reachable at `sysinfo/<view>` in the admin siteaccess. For example `sysinfo/logview/error` shows
the kernel's `error.log`, and `sysinfo/logview/error/(view)/raw` sends it as plain text.

### Monitoring with the status page

`sysinfo/systemstatus` tests the database, the cluster database, the LDAP server, web access, the search server and
mail. Append `(view)/json` for a machine-readable answer:

```bash
curl -s -u monitor:secret 'https://admin.example.com/sysinfo/systemstatus/(view)/json'
```

JSON output is also available for `cachestats`, `clustercachestats`, `clusterstoragestats`, `logstats`, `logchurn`
and `tplfilesqa`.

A monitoring tool that cannot log in can be given anonymous access to this one page (and no other):

```ini
# settings/override/site.ini.append.php
[RoleSettings]
PolicyOmitList[]=sysinfo/systemstatus
```

### Template operators

The extension registers two template operators, used by its own templates and available to yours:

```
{* name of the opcode cache in use: apc, eaccelerator, xcache, wincache, ... or empty *}
{installedphpcache()}

{* the page groups and their views, as shown in the left menu *}
{def $groups = sysinfomoduleviews()}
{foreach $groups as $group_name => $views}
    {$group_name|wash}: {$views|count} pages
{/foreach}
{undef $groups}
```

### Cache item

The churn graphs are a cache item of their own, **sysinfo**, so the normal cache tools empty them:

```bash
php bin/php/ezcache.php --clear-id=sysinfo
```

## Permissions

ggsysinfo defines no policy functions of its own. Every page checks the kernel's **setup / system_info** policy,
the same one that guards the kernel's System information page. Give a role that policy to let its users in. The
tab is shown only to users who have it (`PolicyList[]=setup/system_info` in the extension's
`settings/menu.ini.append.php`).

The extension lists the `sysinfo` module in `PolicyOmitList[]` so that the kernel lets the requests through to the
extension's own check; this does not open the pages to anyone. To open one page to anonymous users, add
`sysinfo/<view>` to `PolicyOmitList[]` as shown above.

The `sysinfo/clusterslave` view is used between the nodes of a cluster and is protected by a token signed with
`TokenSecret`.

With ezgeshi installed, viewing source code also needs the `geshi/view_ezpublish_source` policy.

## Command line scripts

### System report

`bin/php/report.php` writes a CSV report of the installation. It works even when the extension is not activated.

```bash
# list the available reports
php extension/ggsysinfo/bin/php/report.php list

# generate some of them
php extension/ggsysinfo/bin/php/report.php generate --reports=systemstatus,contentstats > report.csv
```

Reports: `setupwizardchecks`, `contentstats`, `rolesandpolicies`, `systemstatus`, `workflows`, `contentclasses`,
`inisettings`.

### Server report

`bin/shell/osreport.sh` collects information about the operating system and the Apache, MySQL, PHP and Varnish
setup, and packs their configuration files into archives in the current directory. Run `sh osreport.sh -h` for its
options.

There are no cron jobs.

## Upgrading

Replace the `extension/ggsysinfo` directory (or run `composer update se7enxweb/ggsysinfo`), regenerate the autoloads
and clear the caches, as in step 3 of the installation. Keep your settings in
`settings/override/sysinfo.ini.append.php` so that an upgrade never overwrites them. The changes of each release are
in [doc/changelogs](doc/changelogs).

## Known limits

- The cache and storage pages need the file system or DFS cluster file handler; with others they are disabled.
- The APC, eAccelerator, XCache and WinCache panels only apply to old PHP versions; on PHP 8 the OPcache panel is
  the one that matters.
- The database checks are written for MySQL/MariaDB.
- The links to the reference manual point to the historical online manual of eZ Publish 4.x.
- Ideas for future work are kept in [doc/todo](doc/todo).

## Troubleshooting

- **The tab is missing.** The user lacks the `setup/system_info` policy, or the extension is not in
  `ActiveExtensions[]`. Clear the INI cache after activating it (`php bin/php/ezcache.php --clear-tag=ini`).
- **"Module not found" for sysinfo.** Regenerate the autoloads and clear all caches.
- **A page is grayed out.** It does not apply to this setup (see Known limits).
- **The churn graphs are broken images.** The web server does not serve `var/<site>/cache/sysinfo`, or GD or
  `zetacomponents/graph` is missing.

## Contributing

Bug reports and pull requests are welcome at https://github.com/se7enxweb/ggsysinfo. Please keep the code PHP 8
compatible and run `php -l` on every file you change.

## License

GNU General Public License v2.0 (or any later version). See [LICENSE](LICENSE).

## Credits and history

ggsysinfo was written by Gaetano Giunta from 2008 to 2022 for eZ Publish, the content management system the
Exponential legacy kernel grew out of, and was published as `gggeek/ggsysinfo`. Its contributors added the French
translation and many fixes. 7x maintains it for Exponential 6.x.
