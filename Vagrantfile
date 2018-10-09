# Only tested with Vagrant 2 and libvirt provider

Vagrant.configure("2") do |config|
    config.vm.provider :libvirt do |libvirt|
        libvirt.cpus = 1
        libvirt.memory = 1024
    end

    config.vm.box = "generic/ubuntu1804"
    config.vm.hostname = "brvneucore"

    config.vm.synced_folder "./", "/var/www/brvneucore", type: "rsync", rsync__exclude: [
        "backend/.env", "backend/vendor/", "frontend/node_modules/", "frontend/brvneucore-js-client/",
        ".settings/", ".buildpath", ".project", ".idea", ".jshintrc"]

    config.ssh.username = 'vagrant'
    config.ssh.password = 'vagrant'

    # run server setup as root
    config.vm.provision "shell", inline: <<-SHELL
        export DEBIAN_FRONTEND=noninteractive
        echo "LC_ALL=en_US.UTF-8" > /etc/environment

        apt-get update
        apt-get upgrade -y
        apt-get autoremove -y

        # install php + composer
        apt-get install -y php php7.2 php7.2-fpm
        apt-get install -y php-cli php-curl php-xml php-json php-mbstring php-mysql php7.2-opcache
        apt-get install -y php-apcu php-xdebug
        apt-get install -y composer

        # install node + npm (npm version in Ubuntu does not yet support package-lock.json)
        apt-get install -y nodejs npm
        npm install -y npm@5.6 -g
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
        cp /var/www/brvneucore/config/brvneucore.vagrant.conf /etc/apache2/sites-available/010-brvneucore.conf
        a2enmod rewrite ssl proxy_fcgi setenvif
        a2ensite default-ssl 010-brvneucore
        a2dissite 000-default
        a2enconf php7.2-fpm

        # also install php-pgsql, php-sqlite3
        apt-get install -y adminer
        echo "Alias /adminer /usr/share/adminer/" > /etc/apache2/sites-available/020-adminer.conf
        a2ensite 020-adminer

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
        cd /var/www/brvneucore

        chmod 0775 backend/var/logs
        chmod 0775 backend/var/cache

        if [ ! -f backend/.env ]; then
            cp backend/.env.dist backend/.env
            echo "backend/.env created"
        fi

        ./install.sh

        echo " "
        echo "--------------------------------------------------------------------------------"
        echo "-- URLs (change IP as needed):                                                --"
        echo "-- Brave Core  https://192.168.121.111                                        --"
        echo "-- Adminer: https://192.168.121.111/adminer/adminer/designs.php (core/brave)  --"
        echo "-- SSH user: vagrant/vagrant                                                  --"
        echo "-- mount: sshfs vagrant@192.168.121.111:/ /mnt/brvneucore                     --"
        echo "-- unmount: fusermount -u /mnt/brvneucore                                     --"
        echo "-- $ ifconfig eth0 | grep inet:                                               --"
        /sbin/ifconfig eth0 | grep "inet "
        echo "--------------------------------------------------------------------------------"
    SHELL
end
