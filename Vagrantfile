
Vagrant.configure("2") do |config|
	config.vm.hostname = "brvneucore"
	config.vm.box = "generic/ubuntu1804"

	config.vm.synced_folder "./", "/var/www/brvneucore"

	config.vm.network "forwarded_port", guest: 443, host: 8443
	config.vm.network :private_network, type: "dhcp"

	# run setup script as root
	config.vm.provision "shell", inline: <<-SHELL
		echo "Installing software ..."

		export DEBIAN_FRONTEND=noninteractive

		apt update

		# setup php + composer
		apt install -y php7.2-fpm php-mysql php-zip php-mbstring php-intl php-dom php-apcu php-curl php-xdebug
		apt install -y composer

		# setup node + npm (npm version in Ubuntu does not yet support package-lock.json)
		apt install -y nodejs npm
		npm install -y npm@5.6.0 -g
		apt remove -y npm
		apt autoremove -y

		# setup mysql
		apt install -y mariadb-server
		service mysql start
		mysql -e 'CREATE DATABASE IF NOT EXISTS core'
		mysql -e 'CREATE DATABASE IF NOT EXISTS core_test'
		mysql -e "GRANT ALL PRIVILEGES ON core.* TO core@localhost IDENTIFIED BY 'braveineve'"
		mysql -e "GRANT ALL PRIVILEGES ON core_test.* TO core@localhost IDENTIFIED BY 'braveineve'"

		# install apache
		apt install apache2 -y
		cp /var/www/brvneucore/config/brvneucore.vagrant.conf /etc/apache2/sites-available/010-brvneucore.conf
		a2enmod rewrite ssl proxy_fcgi setenvif
		a2ensite default-ssl 010-brvneucore
		a2dissite 000-default
		a2enconf php7.2-fpm

		# for app write permissions
		usermod -a -G www-data vagrant
		usermod -a -G vagrant www-data

		# now restart apache/php
		systemctl restart apache2
		systemctl restart php7.2-fpm

		# install Java
        apt install openjdk-8-jre-headless -y

	SHELL

	# run the server as an unprivileged user
	config.vm.provision "up", type: "shell", run: "always", privileged: false, inline: <<-SHELL
		echo "installing neucore ..."

		cd /var/www/brvneucore

		chmod 0775 backend/var/logs
		chmod 0775 backend/var/cache

		if [ ! -f backend/.env ]; then
			cp backend/.env.dist backend/.env
			echo "backend/.env created"
		fi

		./install.sh

		echo
		echo -------------------------------------------------
		echo -- Application ready at https://localhost:8443 --
		echo -------------------------------------------------

	SHELL
end
