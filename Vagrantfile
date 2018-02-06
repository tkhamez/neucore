# -*- mode: ruby -*-
# vi: set ft=ruby :


# Only tested with Vagrant + libvirtd

Vagrant.configure("2") do |config|
	config.vm.hostname = "brvneucore"
	config.vm.box = "generic/ubuntu1710"

	config.vm.network "forwarded_port", guest: 443, host: 443

	config.vm.synced_folder "./", "/var/www/bravecore"
	config.vm.network :private_network, ip: "192.168.121.4"
	
	# run setup script as root
	config.vm.provision "shell", inline: <<-SHELL
		export DEBIAN_FRONTEND=noninteractive

		usermod -a -G www-data vagrant

		apt update
		apt install curl git -y
		# setup php + composer
		apt install -y php php-fpm php-mysql php-zip php-mbstring php-intl php-libsodium php-dom php-sqlite3 php-apcu

		php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"

		php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

		# setup node
		apt install -y nodejs npm

		# install apache
		apt install apache2 -y

		# setup mysql
		apt install mariadb-server -y

		service mysql start

		mysql -e 'CREATE DATABASE IF NOT EXISTS core'
		# TODO should pass password in via env
		mysql -e "GRANT ALL PRIVILEGES ON core.* TO core@localhost IDENTIFIED BY 'braveineve'"

		cp /var/www/bravecore/apache2/010-bravecore.vagrant.conf /etc/apache2/sites-available/010-bravecore.conf

		a2enmod rewrite
		a2enmod ssl
		a2ensite default-ssl
		a2ensite 010-bravecore
		a2enmod proxy_fcgi setenvif
		a2enconf php7.1-fpm

		chmod 0777 /var/www/bravecore/var/logs
		chmod 0777 /var/www/bravecore/var/cache

		systemctl reload apache2

	SHELL

	# run the server as an unprivileged user
	config.vm.provision "up", type: "shell", run: "always", privileged: false, inline: <<-SHELL
		echo "starting server"

		cd /var/www/bravecore

		if [ ! -f .env ]; then
			echo '.env not setup'
			exit
		fi
		composer install
		vendor/bin/doctrine-migrations migrations:migrate
		composer compile
		
		echo
		echo ---------------------------------------
		echo -- server up at https://localhost
		echo ---------------------------------------
		

	SHELL
end
