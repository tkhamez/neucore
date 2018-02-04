# -*- mode: ruby -*-
# vi: set ft=ruby :


# Only tested with Vagrant + libvirtd

Vagrant.configure("2") do |config|
  config.vm.define "core" do |webapp|
		webapp.vm.box = "generic/ubuntu1710"

		webapp.vm.network "forwarded_port", guest: 80, host: 8080

		webapp.vm.network "private_network", ip: "192.168.121.4"

		# TODO this probably doesn't work on windows?
		config.vm.synced_folder "./", "/home/vagrant/brvneucore", type: "nfs", nfs_version: 4, "nfs_udp": false, mount_options: ["rw", "vers=4", "tcp"]
		
		# run setup script as root
		webapp.vm.provision "shell", inline: <<-SHELL
			export DEBIAN_FRONTEND=noninteractive
			apt update
			apt isntall curl git -y
			# setup php + composer
			apt install -y php php-fpm php-mysql php-zip php-mbstring php-intl php-libsodium php-dom php-sqlite3 php-apcu

			php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"

			php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

			# setup node
			if [ ! -x "$(command -v node)" ]; then
				curl -sL https://deb.nodesource.com/setup_9.x | bash -
				apt-get install -y nodejs
			fi

			# prepare env for webapp
			# chmod 0777 /var/cache
			# chmod 0777 /var/log

			# install apache
			apt install apache2 -y

		SHELL

		# run the server as an unprivileged user
		webapp.vm.provision "up", type: "shell", run: "always", privileged: false, inline: <<-SHELL
			echo "starting server"

			cd ./brvneucore

			if [ ! -f .env ]; then
				echo '.env not setup'
				exit
			fi
			composer install
			vendor/bin/doctrine-migrations migrations:migrate
			
		SHELL
	end


  config.vm.define "mariadb" do |db|
    db.vm.hostname = "postgres"
    db.vm.box = "debian/stretch64"

    db.vm.network "private_network", ip: "192.168.121.5"

		db.vm.provision "shell", inline: <<-SHELL
			export DEBIAN_FRONTEND=noninteractive
      apt update
			apt install mariadb-server -y

			mysql -e 'CREATE DATABASE IF NOT EXISTS core'
			# TODO should pass password in via env
			mysql -e "GRANT ALL PRIVILEGES ON core.* TO core@192.168.121.4 IDENTIFIED BY 'braveineve'"

      service mysql start
    SHELL
  end
end