FROM alpine AS git
RUN apk add git
RUN git clone https://github.com/johansatge/workflowy-php.git

FROM php:alpine
WORKDIR /app
COPY backup.php crontab ./
COPY --from=git /workflowy-php ./workflowy-php
RUN crontab crontab
RUN rm crontab
ENTRYPOINT ["sh"]
