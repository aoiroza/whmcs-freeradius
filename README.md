# WHMCS FreeRADIUS

[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/eksoverzero/whmcs-freeradius?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)


### Installing

##### WHMCS

Copy the `whmcs` folder structure exactly as it is to your WHMCS install folder

##### FreeRADIUS servers

- Download and unzip anywhere on your FreeRADIUS server. For this example: `/home/ubuntu/whmcs-freeradius`
- Rename `freeradius/config.php.example` to `freeradius/config.php`
- Edit `freeradius/config.php` for your configuration
- Create a Cron task for the `freeradius/cron.php` file. For example, to run every 5 minutes:

  ```
  */5 * * * * PATH_TO_PHP/php -q /home/ubuntu/whmcs-freeradius/freeradius/cron.php
  ```

- On Linux, you can find the `PATH_TO_PHP` by running `which php`


### Developing

- Download and install Vagrant
- Clone or download a release and unzip of this repository
- Download a copy of WHMCS from the website
- Unzip and rename the `whmcs` folder to `whmcs_install`
- Move the `whmcs_install` folder into this repository's folder (that you previously clone or downloaded and unziped)
- Run `vagrant up` from this repository's root folder

If you don't want to use Vagrant, use a clean Ubuntu Server 14.04 LTS install and, as root, run the commands in the `vagrant.sh` file

From here on, it doesn't matter if you're using vagrant or not, run these as root

- Link the module directory:
  ```
  ln -s /vagrant/whmcs/modules/servers/freeradius /vagrant/whmcs_install/modules/servers/freeradius
  ```

- Link the API file:
  ```
  ln -s /vagrant/whmcs/includes/api/freeradiusapi.php /vagrant/whmcs_install/includes/api/freeradiusapi.php
  ```
  
- Set a MySQL password:
  ```
  mysqladmin -u root password <PASSWORD>
  ```

  Replace `<PASSWORD>` with the password you want
