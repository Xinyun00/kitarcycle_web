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

# Set the correct permissions for Laravel's files.
# The web server (nginx) and PHP process run as the 'www-data' user.
# This command ensures they have ownership of all application files.
RUN chown -R www-data:www-data /var/www/html

# Grant write access to the storage and cache directories, which Laravel needs.
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80 to the outside world
EXPOSE 80

# --- KEY CHANGE ---
# The original CMD only started nginx.
# This new CMD starts supervisord, which is a process manager that will
# start and monitor BOTH nginx and php-fpm, ensuring they are running together.
# This is the correct way to start services in this base image.
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
