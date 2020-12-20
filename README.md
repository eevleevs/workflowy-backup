Daily backup of workflowy data. It keeps:
- all copies for a month
- three copies per month between one and six months
- one copy per month older than six months 

`docker run -d -v BACKUP_PATH:/app/data/ -e USERNAME=WF_USERNAME -e PASSWORD=WF_PASSWORD --restart unless-stopped ivlivs/workflowy-backup`