FROM php:8.4-cli

# Install system dependencies required for MongoDB extension and FFmpeg
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    pkg-config \
    zip \
    unzip \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pcntl mbstring xml

# Install MongoDB extension (requires openssl and pkg-config)
RUN pecl install mongodb-1.19.4 && docker-php-ext-enable mongodb

# Verify MongoDB extension is loaded
RUN php -m | grep mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install dependencies (update to sync lock file, no dev dependencies)
RUN composer update --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Create temp directory for video processing
RUN mkdir -p /tmp/converter && chmod 777 /tmp/converter

# Set environment variables
ENV CONVERTER_TEMP_DIR=/tmp/converter
ENV FFMPEG_PATH=/usr/bin/ffmpeg
ENV FFPROBE_PATH=/usr/bin/ffprobe

# Run the converter consumer
CMD ["php", "artisan", "converter:consume"]
