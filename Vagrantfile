# Only tested with Vagrant 2 and libvirt provider

Vagrant.configure("2") do |config|
    config.vm.provider :libvirt do |libvirt|
        libvirt.cpus = 1
        libvirt.memory = 1024
    end

    config.vm.box = "generic/ubuntu1804"
    config.vm.hostname = "neucore"

    config.vm.synced_folder "./", "/var/www/neucore", type: "rsync", rsync__exclude: [
        "backend/.env", "backend/vendor/", "frontend/node_modules/", "frontend/neucore-js-client/",
        ".settings/", ".buildpath", ".project", ".idea", ".jshintrc"]

    config.ssh.username = 'vagrant'
    config.ssh.password = 'vagrant'

    # run server setup as root
    config.vm.provision "shell", inline: <<-SHELL
        export DEBIAN_FRONTEND=noninteractive
        echo "LC_ALL=en_US.UTF-8" > /etc/environment

        apt-get update
        apt-get upgrade -y -o Dpkg::Options::="--force-confold"
        apt-get autoremove -y

        # install php + composer
        apt-get install -y php php7.2 php7.2-fpm
        apt-get install -y php-cli php-curl php-xml php-json php-mbstring php-mysql php7.2-opcache
        apt-get install -y php-apcu php-xdebug
        apt-get install -y composer

        # install node + npm (npm version in Ubuntu does not yet support package-lock.json)
        apt-get install -y nodejs npm
        npm install -y npm@6.4.1 -g
        apt-get remove -y npm
        apt-get autoremove -y

        # install mariadb server
        apt-get install -y mariadb-server
        mysql -e 'CREATE DATABASE IF NOT EXISTS core'
        mysql -e 'CREATE DATABASE IF NOT EXISTS core_test'
        mysql -e "GRANT ALL PRIVILEGES ON core.* TO core@localhost IDENTIFIED BY 'brave'"
        mysql -e "GRANT ALL PRIVILEGES ON core_test.* TO core@localhost IDENTIFIED BY 'brave'"

        # install apache
        apt-get install apache2 -y
        a2enmod rewrite proxy_fcgi setenvif
        a2enconf php7.2-fpm
        cat > /etc/apache2/sites-available/010-neucore.conf <<EOL
<VirtualHost *:80>
    ServerName neucore
    DocumentRoot /var/www/neucore/web
    <Directory /var/www/neucore/web/>
        AllowOverride All
    </Directory>
</VirtualHost>
EOL
        a2ensite 010-neucore
        a2dissite 000-default.conf

        # put cli and web user into each other's group for write permissions
        usermod -a -G www-data vagrant
        usermod -a -G vagrant www-data

        # restart apache and php
        systemctl restart apache2
        systemctl restart php7.2-fpm

        # install git, Java
        apt-get install -y git openjdk-8-jre-headless

        # install Heroku
        sudo snap install heroku --classic
    SHELL

    # run app setup as an unprivileged user
    config.vm.provision "up", type: "shell", run: "always", privileged: false, inline: <<-SHELL
        cd /var/www/neucore

        chmod 0775 backend/var/logs
        chmod 0775 backend/var/cache

        if [ ! -f backend/.env ]; then
            cp backend/.env.dist backend/.env
            echo "backend/.env created"
        fi

        ./install.sh

        echo " "
        echo "--------------------------------------------------------------------------------"
        echo "-- Neucore http://192.168.121.111 (change IP as needed)                       --"
        echo "-- SSH user: vagrant/vagrant                                                  --"
        echo "-- IP of vm:                                                                  --"
        /sbin/ifconfig eth0 | grep "inet "
        echo "--------------------------------------------------------------------------------"
    SHELL
end
