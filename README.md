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
