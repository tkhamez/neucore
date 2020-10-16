#
# NOTE: This is outdated since Neucore needs at least PHP 7.3 now.
#       But it should be relatively easy to adjust it for "ubuntu2004" with PHP 7.4.
#

# Only tested on Linux with Vagrant 2 + libvirt.
#
# Copy `backend/.env.dist` to `backend/.env` and adjust values, the database password and user are both `neucore`
# the database host is `localhost`.
#
# "vagrant up" creates and configures the virtual machine.
# If the Vagrant file changes, run "vagrant provision" to update the VM.
# "vagrant destroy" will completely remove the VM.
#
# Please note that the `rsync` synchronization method used is a one-way synchronization from host to virtual
# machine that is performed each time `vagrant up` or `vagrant reload` is executed.
# See https://www.vagrantup.com/docs/synced-folders for other methods.

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
        apt-get install -y php-fpm php-cli php-curl php-gmp php-json php-mbstring php-xml php-mysql php7.2-opcache \
            php-apcu php-xdebug composer

        # install node + npm (versions in Ubuntu are too old)
        curl -sL https://deb.nodesource.com/setup_10.x | bash -
        apt-get install -y nodejs

        # install mariadb server (10.1)
        apt-get install -y mariadb-server
        mysql -e 'CREATE DATABASE IF NOT EXISTS neucore'
        mysql -e 'CREATE DATABASE IF NOT EXISTS neucore_test'
        mysql -e "GRANT ALL PRIVILEGES ON neucore.* TO neucore@localhost IDENTIFIED BY 'neucore'"
        mysql -e "GRANT ALL PRIVILEGES ON neucore_test.* TO neucore@localhost IDENTIFIED BY 'neucore'"

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
        echo "-- IP of vm:                                                                  --"
        /sbin/ifconfig eth0 | grep "inet "
        echo "-- SSH user: vagrant/vagrant                                                  --"
        echo "-- Neucore http://ip.of.vm                                                    --"
        echo "--------------------------------------------------------------------------------"
    SHELL
end
