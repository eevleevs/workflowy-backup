FROM php:alpine
RUN apk add git
WORKDIR /app
RUN git clone https://github.com/johansatge/workflowy-php.git
COPY backup.php crontab ./
RUN crontab crontab
RUN rm crontab
CMD ["crond", "-f"]
