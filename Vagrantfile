# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.box = "ubuntu/trusty64"

  config.vm.network "private_network", ip: "192.168.50.18"
  config.vm.network :forwarded_port, host: 80, guest: 8080, auto_correct: true

  config.vm.provider "virtualbox" do |v|
    v.memory = 2048
    v.cpus = 2
  end

  if Vagrant.has_plugin?("vagrant-cachier")
      config.cache.scope = :box
  end

  config.vm.provision "docker" do |d|
    d.run "tutum/mysql", name: "mysql", args: "-p 3306:3306 -e MYSQL_PASS=flora"
    d.run "tutum/postgresql", name: "postgresql", args: "-p 5432:5432 -e POSTGRES_PASS=flora"
    d.run "cncflora/couchdb", name: "couchdb", args: "-p 5489:5489"
    d.run "dockerfile/elasticsearch", name: "elasticsearch", args: "-p 9200:9200"
  end

  config.vm.provision :shell, :path => "vagrant.sh"
end

