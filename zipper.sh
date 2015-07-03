#!/usr/bin/env bash
wget https://github.com/laravel/laravel/archive/master.zip
unzip master.zip -d working
cd working/laravel-master
composer install
tar cvfz ../../laravel-craft.tar.gz .
cd ../..
mv laravel-craft.tar.gz public/laravel-craft.tar.gz
rm -rf working
rm master.zip
