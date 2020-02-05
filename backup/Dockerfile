FROM alpine AS get_workflowy-php
RUN apk add git
RUN git clone https://github.com/johansatge/workflowy-php.git

FROM php:alpine
RUN apk add nano
WORKDIR /app
VOLUME /app/data
COPY backup.php credentials.php crontab ./
COPY --from=get_workflowy-php /workflowy-php ./workflowy-php
RUN crontab crontab
RUN rm crontab
ENTRYPOINT ["crond", "-f"]
