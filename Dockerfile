# Same image as before, plus PHP's calendar extension (Church Admin's calendar PDF needs cal_days_in_month()).
FROM wordpress:6.8-php8.2-apache
RUN docker-php-ext-install calendar
