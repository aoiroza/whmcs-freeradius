#!/bin/bash

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y git-core pkg-config build-essential gcc g++ checkinstall software-properties-common \
                   apache2 mysql-server \
                   php5 php5-mysql php5-mcrypt php5-curl php5-gd libapache2-mod-php5

mysqladmin -u root create whmcs

cd /tmp && \
wget http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz && \
tar zxf ioncube_loaders_lin_x86-64.tar.gz && \
mv ioncube /usr/local/ && \
rm -f ioncube_loaders_lin_x86-64.tar.gz

sed -i '/;   extension=modulename.extension/a zend_extension = /usr/local/ioncube/ioncube_loader_lin_5.5.so' /etc/php5/apache2/php.ini

rm -rf /var/www/html
ln -fs /vagrant/whmcs_install /var/www/html

/etc/init.d/apache2 restart
