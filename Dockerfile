# Official WordPress + PHP 8.2 (Oct 2026 security line), plus calendar for Church Admin PDF (cal_days_in_month()).
FROM wordpress:7.1.4-php8.2-apache
RUN docker-php-ext-install calendar
