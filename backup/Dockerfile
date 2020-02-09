FROM php:alpine
RUN apk add git
RUN apk add nano
WORKDIR /app
VOLUME /data/data
RUN git clone https://github.com/johansatge/workflowy-php.git
COPY backup.php crontab ./
RUN crontab crontab
RUN rm crontab
CMD ["crond", "-f"]
