FROM php:alpine
RUN apk add nano
RUN apk add git
WORKDIR /app
VOLUME /app/data
RUN git clone https://github.com/johansatge/workflowy-php.git
COPY backup.php credentials.php crontab ./
RUN crontab crontab
RUN rm crontab
ENTRYPOINT ["crond", "-f"]
