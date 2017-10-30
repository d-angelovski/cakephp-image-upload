# CakePHP Application with Images upload

CakePHP images upload in /images, resizes image to specified coordinates:
```
Top, Left, Width, Height
 ```
 and advanced searches them with a query like search:
 
```
filename CONTAINS text AND (imgw < 800 OR imgh < 800)
```

## Instalation

- Download from git, and run 
```
composer install
```

- Copy config/app.default.php to config/app.php and change the values to fit your MySQL database.

- Install database/database_install.sql

- Run the project, and visit /images
