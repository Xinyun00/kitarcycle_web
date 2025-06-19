# Use a standard, trusted image for Nginx and PHP
FROM richarvey/nginx-php-fpm:latest

# Set the working directory inside the container
WORKDIR /var/www/html

# Copy all your application files from the repo into the container
COPY . .

# Install your Laravel project's dependencies
# --no-dev: Don't install development packages
# --optimize-autoloader: Build a faster class autoloader for production
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Set the correct permissions for Laravel's storage and cache directories
# This allows the web server to write logs and cache files
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Expose port 80 to the outside world
EXPOSE 80

# The command to start the Nginx server when the container runs
CMD ["/usr/sbin/nginx", "-g", "daemon off;"]
