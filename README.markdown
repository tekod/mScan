
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Issues](https://img.shields.io/github/issues/tekod/mScan.svg)](https://github.com/tekod/mScan/issues)

# What is this?

mScan is program that will periodically scan all files on hosting and generate report if any differences found.  
Report will be sent as email message to configured address or simply echoed if triggered by HTTP request.

It will alert you if:
* **your developer upload new version of code** - so you can track which part of site he touched
* **hacker inject malicious code** - for disaster recovery, to easily find which files are compromised 
* **uploads/changes of media files or documents** - to track activities of your employees

Also this program may help in "damage control" if automatic checking is set quite often (on every hour or less) by giving administrator chance to notice hacking event before other users and react as early as possible.

# How it works?

Program scan directories and collect information about last modification (as date and time) and content (as hash) and writes that collection in storage file.  
Storage file is compressed to reduce filesize and somewhat obfuscate it.  
On next run program will scan directories again and compare time and hash of each file with stored information and generate report if differences are found.  
Report can be send via email to predefined address or echoed at terminal.

There are two common usage cases: 
* **as monitor** - setup cron to periodically run scanning, setup and forget about it
* **as tool** - setup and run it manually when needed, both via HTTP or CLI protocol

Here is example how email message may looks like
(obviously this report was generated after one of wordpress updates):


><sup>mScan ver. 0.3.1</sup>  
><sup>\-----------------------------------------------------------------</sup>  
>
><sup>Differences found:</sup>  
>
><sup>Modified file: /home/pspen/public_html/wp-admin/includes/update-core.php {2018-01-17 00:49:08}</sup>  
><sup>Modified file: /home/pspen/public_html/wp-admin/about.php {2018-01-17 00:49:09}</sup>  
><sup>Modified file: /home/pspen/public_html/readme.html {2018-01-17 00:49:09}</sup>  
><sup>Modified file: /home/pspen/public_html/wp-includes/version.php {2018-01-17 00:49:09}</sup>  
>
>
><sup>\-----------------------------------------------------------------</sup>  
><sup>Found 8840 files</sup>  
><sup>Timestamp: 2018-01-17 02:00:10</sup>  
><sup>Last scanning occured on 2018-01-17 00:00:09 (2 hours ago)</sup>


## Security concern

Remember that storage file contains list of all your files.  
In order to hide that sensitive information storage file (or whole package) has to be stored in location that are inaccessible from web.  
Best solution is to locate it out of DOCUMENT_ROOT directory but if that is not possible protect it with .htaccess.

Note about calling index.php via HTTP request:  
remember that program will takes a lot of time and consume server resources to run so if you let it public accessible and
malicious visitor figure out its URL he can produce DOS attack on your server by dispatching bunch of calls to it.  
Do not left program accessible from web for long time.

# Installation

mScan consists of these files:
* **mScan.php** - program itself
* **mScan.dat** - storage for information about files from last scan, using this information program can detect changes
* **index.php** - launcher, user should not modify mScan main class in order to be able to easily update it with newer version, all configuration goes here
* this file
* license file

Perform following steps to install it:
1. Copy all files to your hosting in its own directory and protect it, see "Security concern"
1. Grant write permition to storage file (default: mScan.dat) to PHP
1. Edit index.php to configure script, see "Configuration options" section.
1. Manually run index.php to check how it works. 
1. If necessary reconfigure and run it again until you are satisfied with configuration.
1. Set up CRON system to trigger index.php at desired interval, typically once per day or per hour.

# Configuration options
* **PathsToScan** - target directories that need to be scanned (as array)
* **Extensions** - file extensions that need to be checked (as array, left empty for all extensions)
* **IgnoreDirs** - full path to directories that need to be skipped (as array)
* **StorageLocation** - path to data file, file that contains hashes from last scan
* **EmailReport** - configuration for dispatching email:
  * **Enabled** - permition to send email
  * **ToAddress** - email address of recipient, typically your address
  * **FromAddress** - email address of sender, without proper sender address email servers may recognize mail as spam
  * **Subject** - subject of email message
* **HashFileLimit** - speedup scanning by skipping hash calculation of huge files, set maximum filesize as integer
* **Messages** - redefine messages in output report, see source of mScan.php for more details
* **ReportTemplate** - redefine report template, see source of mScan.php for more details

# Translation / localization

Output report can be fully localized by modifying configuration options "Messages" and "ReportTemplate".  
All strings contained in output report are exposed there.

---

# Security issues

If you have found a security issue, please contact the author directly at office@tekod.com.

