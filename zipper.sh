wget https://github.com/laravel/laravel/archive/master.zip
unzip master.zip -d working
cd working/laravel-master
composer install
awk '!/composer.lock/' .gitignore > temp && mv temp .gitignore
zip -r ../../laravel-craft.zip .
cd ../..
mv laravel-craft.zip public/laravel-craft.zip
rm -rf working
rm master.zip
