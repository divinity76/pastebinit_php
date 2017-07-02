# pastebinit_php
php script to upload stuff to pastebin websites - inspired by Stéphane Graber's pastebinit

supports pastebin.com and paste.fedoraproject.org and paste.ratma.net.

the default pastebin is paste.fedoraproject.org, and by default, everything is at decent "safe" defaults 
(by "safe" i mean it generates non-public cryptographically-secure-random-password-protected URLs, and an expire date of 1 year)

the (completely optional) custom configuration file resides in ~/.pastebinit.php.ini, and looks something like

```ini
[global]
default_pastebin=paste.ratma.net
generate_random_password=false
default_hidden_url=false
```
(for a complete list of configuration options, refer to the source code, look for `$config` , as of writing its on line 6)


example install

```bash
sudo rm -rfv /usr/bin/pastebinit.php /usr/bin/pastebinit
sudo wget -O /usr/bin/pastebinit.php https://raw.githubusercontent.com/divinity76/pastebinit_php/master/pastebinit.php
sudo chmod 0555 /usr/bin/pastebinit.php
sudo ln -s /usr/bin/pastebinit.php /usr/bin/pastebinit

```
